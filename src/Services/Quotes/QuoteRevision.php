<?php

namespace Dashed\DashedEcommerceCore\Services\Quotes;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Quote;

/**
 * Revisies, intrekken, en een offerte beginnen vanuit een bestelling.
 *
 * Een revisie is een gewone rij met een eigen hash en versienummer, die het
 * nummer van de eerste versie houdt. De vorige versie gaat op "vervangen",
 * zodat er altijd hooguit een versie openstaat.
 */
class QuoteRevision
{
    /** De velden die een revisie overneemt. Stempels en antwoorden bewust niet. */
    private const COPIED_FIELDS = [
        'site_id', 'locale', 'user_id', 'created_by_user_id',
        'company_name', 'btw_id', 'first_name', 'last_name', 'email', 'phone_number',
        'street', 'house_nr', 'zip_code', 'city', 'country',
        'invoice_street', 'invoice_house_nr', 'invoice_zip_code', 'invoice_city', 'invoice_country',
        'title', 'reference', 'prices_ex_vat', 'payment_route',
        'intro', 'terms', 'acceptance_text', 'notes',
    ];

    public static function create(Quote $quote): Quote
    {
        return DB::transaction(function () use ($quote) {
            $root = $quote->rootQuote();

            // Slot op de wortelrij voordat we het hoogste versienummer lezen: zonder dat
            // berekenen twee gelijktijdige revisies hetzelfde nummer en krijg je twee
            // rijen met dezelfde versie. Zelfde reden als het slot in QuoteNumber::assign().
            Quote::query()->whereKey($root->id)->lockForUpdate()->first();

            $highest = Quote::query()
                ->where(fn ($q) => $q->whereKey($root->id)->orWhere('parent_quote_id', $root->id))
                ->max('version');

            $revision = new Quote();
            foreach (self::COPIED_FIELDS as $field) {
                $revision->{$field} = $quote->{$field};
            }
            $revision->hash = Str::random(32);
            $revision->parent_quote_id = $root->id;
            $revision->version = ((int) $highest) + 1;
            $revision->quote_number = $root->quote_number;
            $revision->status = Quote::STATUS_CONCEPT;
            $revision->valid_until = now()->addDays(QuoteDefaults::validityDays($quote->site_id));
            $revision->total = 0;
            $revision->save();

            foreach ($quote->lines as $line) {
                $revision->lines()->create([
                    'product_id' => $line->product_id,
                    'name' => $line->name,
                    'description' => $line->description,
                    'sku' => $line->sku,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'vat_rate' => $line->vat_rate,
                    'sort_order' => $line->sort_order,
                    'is_optional' => $line->is_optional,
                    'is_selected' => $line->is_selected,
                    'choice_group' => $line->choice_group,
                ]);
            }

            $revision->load('lines');
            $revision->recalculateTotal();

            if ($quote->status === Quote::STATUS_SENT) {
                $quote->status = Quote::STATUS_SUPERSEDED;
                $quote->save();
            }

            return $revision;
        });
    }

    public static function withdraw(Quote $quote): Quote
    {
        if ($quote->status !== Quote::STATUS_SENT) {
            return $quote;
        }

        $quote->status = Quote::STATUS_WITHDRAWN;
        $quote->save();

        return $quote;
    }

    public static function fromOrder(Order $order): Quote
    {
        $quote = new Quote();
        $quote->user_id = $order->user_id;
        $quote->locale = $order->locale ?: app()->getLocale();
        $quote->prices_ex_vat = (bool) $order->prices_ex_vat;
        $quote->title = __('Offerte op basis van bestelling :nummer', ['nummer' => $order->invoice_id ?: $order->id]);
        $quote->valid_until = now()->addDays(QuoteDefaults::validityDays($order->site_id));
        $quote->payment_route = Quote::ROUTE_PREPAY;
        $quote->created_by_user_id = auth()->id();

        foreach ([
            'company_name', 'btw_id', 'first_name', 'last_name', 'email', 'phone_number',
            'street', 'house_nr', 'zip_code', 'city', 'country',
            'invoice_street', 'invoice_house_nr', 'invoice_zip_code', 'invoice_city', 'invoice_country',
        ] as $field) {
            $quote->{$field} = $order->{$field};
        }

        foreach (['intro', 'terms', 'acceptance'] as $key) {
            $target = $key === 'acceptance' ? 'acceptance_text' : $key;
            $quote->{$target} = QuoteDefaults::text($key, $quote->locale, $order->site_id);
        }

        $quote->save();

        $sort = 0;
        foreach ($order->orderProducts as $orderProduct) {
            $quantity = max(1, (int) $orderProduct->quantity);

            $quote->lines()->create([
                'product_id' => $orderProduct->product_id,
                'name' => $orderProduct->name,
                'sku' => $orderProduct->sku,
                'quantity' => $quantity,
                // price op een orderregel is het regeltotaal inclusief btw;
                // een offerteregel houdt de prijs per stuk.
                'unit_price' => round((float) $orderProduct->price / $quantity, 2),
                'vat_rate' => (float) ($orderProduct->vat_rate ?: 21),
                'sort_order' => $sort++,
            ]);
        }

        $quote->load('lines');
        $quote->recalculateTotal();

        return $quote->fresh();
    }
}
