<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\PersonalData;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;
use Dashed\DashedCore\PersonalData\Anonymized;
use Dashed\DashedCore\PersonalData\PersonalDataExport;
use Dashed\DashedCore\PersonalData\PersonalDataFinding;
use Dashed\DashedCore\PersonalData\PersonalDataSubject;
use Dashed\DashedCore\PersonalData\PersonalDataProvider;

/**
 * Persoonsgegevens in de webshop. Bestellingen, en wat er fiscaal aan
 * vastzit (retouren, betaalherinneringen, geaccepteerde offertes), blijven
 * staan: de bewaarplicht is zeven jaar en een factuur moet kloppen met wie
 * hij gericht was. Die rijen worden alleen van het account losgekoppeld.
 * Al het andere (winkelwagens, verlanglijst, wachtrijmeldingen, notities,
 * klantprijzen) wordt verwijderd of geanonimiseerd.
 *
 * Alles via de query builder: geen model-events en geen activiteitenlog, die
 * zouden de oude waarden anders juist bewaren.
 */
class EcommercePersonalDataProvider extends PersonalDataProvider
{
    public function key(): string
    {
        return 'dashed-ecommerce-core';
    }

    public function label(): string
    {
        return __('Webshop');
    }

    public function priority(): int
    {
        return 50;
    }

    public function find(PersonalDataSubject $subject): array
    {
        $findings = [];

        if ($q = $this->orders($subject)) {
            $count = (clone $q)->count();
            if ($count > 0) {
                $findings[] = PersonalDataFinding::keep('orders', __('Bestellingen en facturen'), $count, $this->fiscalReason()
                    . ($subject->hasUsers() ? ' ' . __('Ze worden wel losgekoppeld van het account.') : ''));
            }
        }

        if ($q = $this->returns($subject)) {
            $this->push($findings, PersonalDataFinding::keep('order_returns', __('Retouren'), (clone $q)->count(), __('Horen bij een bestelling en een creditfactuur; fiscale bewaarplicht.')));
        }

        if ($q = $this->paymentReminders($subject)) {
            $this->push($findings, PersonalDataFinding::keep('order_payment_reminders', __('Betalingsherinneringen'), (clone $q)->count(), __('Horen bij een openstaande factuur; nodig bij een geschil over de betaling.')));
        }

        if ($q = $this->quotes($subject)) {
            $kept = (clone $q)->where(fn ($w) => $w->whereNotNull('order_id')->orWhereNotNull('accepted_at'))->count();
            $other = (clone $q)->whereNull('order_id')->whereNull('accepted_at')->count();
            $this->push($findings, PersonalDataFinding::keep('quotes_accepted', __('Geaccepteerde offertes'), $kept, __('Een geaccepteerde offerte is de basis onder een overeenkomst en de akkoord-PDF het bewijsstuk.')));
            $this->push($findings, PersonalDataFinding::anonymize('quotes', __('Offertes zonder akkoord'), $other));
        }

        foreach ($this->deletable($subject) as $key => $source) {
            $this->push($findings, PersonalDataFinding::delete($key, $source['label'], (clone $source['query'])->count()));
        }

        foreach ($this->unlinkable($subject) as $key => $source) {
            $this->push($findings, PersonalDataFinding::unlink($key, $source['label'], (clone $source['query'])->count(), $source['reason'] ?? null));
        }

        return $findings;
    }

    public function export(PersonalDataSubject $subject, PersonalDataExport $export): void
    {
        if ($q = $this->orders($subject)) {
            $orders = $this->rows($q, ['hash', 'psp_id']);
            $ids = array_column($orders, 'id');
            $products = $ids !== [] && $this->hasTable('dashed__order_products')
                ? DB::table('dashed__order_products')->whereIn('order_id', $ids)->get()->groupBy('order_id')
                : collect();
            $payments = $ids !== [] && $this->hasTable('dashed__order_payments')
                ? DB::table('dashed__order_payments')->whereIn('order_id', $ids)->get()->groupBy('order_id')
                : collect();

            $export->add('orders', array_map(fn (array $order) => $order + [
                'products' => ($products[$order['id']] ?? collect())->map(fn ($r) => (array) $r)->values()->all(),
                'payments' => ($payments[$order['id']] ?? collect())->map(fn ($r) => array_diff_key((array) $r, array_flip(['hash', 'psp_request', 'psp_response'])))->values()->all(),
            ], $orders));
        }

        if ($q = $this->returns($subject)) {
            $returns = $this->rows($q, ['hash']);
            $ids = array_column($returns, 'id');
            $lines = $ids !== [] && $this->hasTable('dashed__order_return_lines')
                ? DB::table('dashed__order_return_lines')->whereIn('order_return_id', $ids)->get()->groupBy('order_return_id')
                : collect();
            $export->add('order_returns', array_map(fn (array $return) => $return + [
                'lines' => ($lines[$return['id']] ?? collect())->map(fn ($r) => (array) $r)->values()->all(),
            ], $returns));
        }

        if ($q = $this->quotes($subject)) {
            $quotes = $this->rows($q, ['hash', 'accepted_signature']);
            $ids = array_column($quotes, 'id');
            $lines = $ids !== [] && $this->hasTable('dashed__quote_lines')
                ? DB::table('dashed__quote_lines')->whereIn('quote_id', $ids)->get()->groupBy('quote_id')
                : collect();
            $export->add('quotes', array_map(fn (array $quote) => $quote + [
                'lines' => ($lines[$quote['id']] ?? collect())->map(fn ($r) => (array) $r)->values()->all(),
            ], $quotes));
        }

        if ($q = $this->wishlists($subject)) {
            $wishlists = $this->rows($q, ['token', 'share_token']);
            $ids = array_column($wishlists, 'id');
            $items = $ids !== [] && $this->hasTable('dashed__wishlist_items')
                ? DB::table('dashed__wishlist_items')->whereIn('wishlist_id', $ids)->get()->groupBy('wishlist_id')
                : collect();
            $export->add('wishlists', array_map(fn (array $list) => $list + [
                'items' => ($items[$list['id']] ?? collect())->map(fn ($r) => (array) $r)->values()->all(),
            ], $wishlists));
        }

        if ($q = $this->carts($subject)) {
            $carts = $this->rows($q, ['token']);
            $ids = array_column($carts, 'id');
            $items = $ids !== [] && $this->hasTable('dashed__cart_items')
                ? DB::table('dashed__cart_items')->whereIn('cart_id', $ids)->get()->groupBy('cart_id')
                : collect();
            $export->add('carts', array_map(fn (array $cart) => $cart + [
                'items' => ($items[$cart['id']] ?? collect())->map(fn ($r) => (array) $r)->values()->all(),
            ], $carts));
        }

        foreach (['stock_notifications', 'checkout_abandonments', 'customer_annotations', 'abandoned_cart_emails', 'customer_prices', 'customer_category_prices', 'customer_extra_prices', 'customer_extra_option_prices'] as $key) {
            $source = $this->deletable($subject)[$key] ?? null;
            if ($source) {
                $export->add($key, $this->rows(clone $source['query']));
            }
        }

        if ($q = $this->discountCodes($subject)) {
            $export->add('discount_codes', $this->rows($q));
        }

        if ($q = $this->paymentReminders($subject)) {
            $export->add('order_payment_reminders', $this->rows($q));
        }
    }

    public function erase(PersonalDataSubject $subject): array
    {
        // Eerst vastleggen wat er is, dan pas wijzigen: sommige bronnen
        // vinden elkaar via een andere (winkelwagen-id's, offerte-id's).
        $findings = [];

        if ($q = $this->orders($subject)) {
            $count = (clone $q)->count();
            if ($count > 0) {
                if ($subject->hasUsers()) {
                    DB::table('dashed__orders')->whereIn('user_id', $subject->userIds())->update(['user_id' => null]);
                }
                $findings[] = PersonalDataFinding::keep('orders', __('Bestellingen en facturen'), $count, $this->fiscalReason());
            }
        }

        if ($q = $this->returns($subject)) {
            $this->push($findings, PersonalDataFinding::keep('order_returns', __('Retouren'), (clone $q)->count(), __('Horen bij een bestelling en een creditfactuur; fiscale bewaarplicht.')));
        }

        if ($q = $this->paymentReminders($subject)) {
            $this->push($findings, PersonalDataFinding::keep('order_payment_reminders', __('Betalingsherinneringen'), (clone $q)->count(), __('Horen bij een openstaande factuur; nodig bij een geschil over de betaling.')));
        }

        if ($q = $this->quotes($subject)) {
            $keptIds = (clone $q)->where(fn ($w) => $w->whereNotNull('order_id')->orWhereNotNull('accepted_at'))->pluck('id')->all();
            $otherIds = (clone $q)->whereNull('order_id')->whereNull('accepted_at')->pluck('id')->all();

            if ($keptIds !== [] && Schema::hasColumn('dashed__quotes', 'user_id')) {
                DB::table('dashed__quotes')->whereIn('id', $keptIds)->update(['user_id' => null]);
            }
            foreach ($otherIds as $id) {
                DB::table('dashed__quotes')->where('id', $id)->update($this->anonymousQuote((int) $id));
            }

            $this->push($findings, PersonalDataFinding::keep('quotes_accepted', __('Geaccepteerde offertes'), count($keptIds), __('Een geaccepteerde offerte is de basis onder een overeenkomst en de akkoord-PDF het bewijsstuk.')));
            $this->push($findings, PersonalDataFinding::anonymize('quotes', __('Offertes zonder akkoord'), count($otherIds)));
        }

        // Koppelingen eerst, dan de rijen die weg mogen (anders verdwijnen
        // winkelwagen-id's voordat de mails eraan gevonden zijn).
        foreach ($this->unlinkable($subject) as $key => $source) {
            $count = (clone $source['query'])->update($source['values']);
            $this->push($findings, PersonalDataFinding::unlink($key, $source['label'], $count, $source['reason'] ?? null));
        }

        $deletable = $this->deletable($subject);
        $cartIds = isset($deletable['carts']) ? (clone $deletable['carts']['query'])->pluck('id')->all() : [];
        $wishlistIds = isset($deletable['wishlists']) ? (clone $deletable['wishlists']['query'])->pluck('id')->all() : [];

        foreach ($deletable as $key => $source) {
            if (in_array($key, ['carts', 'wishlists'], true)) {
                continue;
            }
            $this->push($findings, PersonalDataFinding::delete($key, $source['label'], (clone $source['query'])->delete()));
        }

        if ($cartIds !== []) {
            if ($this->hasTable('dashed__cart_items')) {
                DB::table('dashed__cart_items')->whereIn('cart_id', $cartIds)->delete();
            }
            // Bestellingen verwijzen met nullOnDelete naar de winkelwagen; via
            // de query builder doet de database dat, maar SQLite zonder
            // foreign keys niet, dus expliciet.
            DB::table('dashed__orders')->whereIn('cart_id', $cartIds)->update(['cart_id' => null]);
            $this->push($findings, PersonalDataFinding::delete('carts', __('Winkelwagens'), DB::table('dashed__carts')->whereIn('id', $cartIds)->delete()));
        }

        if ($wishlistIds !== []) {
            if ($this->hasTable('dashed__wishlist_items')) {
                DB::table('dashed__wishlist_items')->whereIn('wishlist_id', $wishlistIds)->delete();
            }
            $this->push($findings, PersonalDataFinding::delete('wishlists', __('Verlanglijstjes'), DB::table('dashed__wishlists')->whereIn('id', $wishlistIds)->delete()));
        }

        return $findings;
    }

    protected function fiscalReason(): string
    {
        return __('Fiscale bewaarplicht van zeven jaar: bestellingen en facturen blijven met de gegevens van toen bewaard.');
    }

    /**
     * @param  array<int, PersonalDataFinding>  $findings
     */
    protected function push(array &$findings, PersonalDataFinding $finding): void
    {
        if ($finding->count > 0) {
            $findings[] = $finding;
        }
    }

    protected function orders(PersonalDataSubject $subject): ?Builder
    {
        return $this->hasTable('dashed__orders')
            ? $subject->whereEmailOrUser(DB::table('dashed__orders'), 'email', 'user_id')
            : null;
    }

    protected function orderIds(PersonalDataSubject $subject): Builder
    {
        return $this->orders($subject)->select('id');
    }

    protected function returns(PersonalDataSubject $subject): ?Builder
    {
        if (! $this->hasTable('dashed__order_returns') || ! $this->hasTable('dashed__orders')) {
            return null;
        }

        return DB::table('dashed__order_returns')->where(function ($q) use ($subject) {
            $subject->whereEmail($q, 'email');
            $q->orWhereIn('order_id', $this->orderIds($subject));
        });
    }

    protected function paymentReminders(PersonalDataSubject $subject): ?Builder
    {
        if (! $this->hasTable('dashed__order_payment_reminders') || ! $this->hasTable('dashed__orders')) {
            return null;
        }

        return DB::table('dashed__order_payment_reminders')->whereIn('order_id', $this->orderIds($subject));
    }

    protected function quotes(PersonalDataSubject $subject): ?Builder
    {
        return $this->hasTable('dashed__quotes')
            ? $subject->whereEmailOrUser(DB::table('dashed__quotes'), 'email', 'user_id')
            : null;
    }

    protected function wishlists(PersonalDataSubject $subject): ?Builder
    {
        return $this->hasTable('dashed__wishlists')
            ? $subject->whereEmailOrUser(DB::table('dashed__wishlists'), 'email', 'user_id')
            : null;
    }

    protected function carts(PersonalDataSubject $subject): ?Builder
    {
        return $this->hasTable('dashed__carts')
            ? $subject->whereEmailOrUser(DB::table('dashed__carts'), 'abandoned_email', 'user_id')
            : null;
    }

    protected function discountCodes(PersonalDataSubject $subject): ?Builder
    {
        return $this->hasTable('dashed__discount_codes') && $subject->hasUsers() && Schema::hasColumn('dashed__discount_codes', 'user_id')
            ? DB::table('dashed__discount_codes')->whereIn('user_id', $subject->userIds())
            : null;
    }

    /**
     * Bronnen die bij verwijderen helemaal weg gaan.
     *
     * @return array<string, array{label: string, query: Builder}>
     */
    protected function deletable(PersonalDataSubject $subject): array
    {
        $sources = [];
        $userIds = $subject->userIds();
        $carts = $this->carts($subject);

        if ($carts) {
            $sources['carts'] = ['label' => __('Winkelwagens'), 'query' => $carts];
        }
        if ($q = $this->wishlists($subject)) {
            $sources['wishlists'] = ['label' => __('Verlanglijstjes'), 'query' => $q];
        }
        if ($this->hasTable('dashed__stock_notifications')) {
            $sources['stock_notifications'] = ['label' => __('Terug-op-voorraadmeldingen'), 'query' => $subject->whereEmail(DB::table('dashed__stock_notifications'), 'email')];
        }
        if ($this->hasTable('dashed__abandoned_cart_emails')) {
            $sources['abandoned_cart_emails'] = ['label' => __('Mails over een verlaten winkelwagen'), 'query' => DB::table('dashed__abandoned_cart_emails')->where(function ($q) use ($subject, $carts) {
                $subject->whereEmail($q, 'email');
                if ($carts) {
                    $q->orWhereIn('cart_id', (clone $carts)->select('id'));
                }
            })];
        }
        if ($this->hasTable('dashed__checkout_abandonments')) {
            $sources['checkout_abandonments'] = ['label' => __('Afgebroken afrekenpogingen'), 'query' => DB::table('dashed__checkout_abandonments')->where(function ($q) use ($subject, $carts) {
                $subject->whereEmail($q, 'email');
                if ($carts) {
                    $q->orWhereIn('cart_id', (clone $carts)->select('id'));
                }
            })];
        }
        if ($this->hasTable('dashed__customer_annotations')) {
            $sources['customer_annotations'] = ['label' => __('Klantnotities en tags'), 'query' => $subject->whereEmail(DB::table('dashed__customer_annotations'), 'email')];
        }
        if ($this->hasTable('dashed__order_add_on_offers') && $this->hasTable('dashed__orders')) {
            // Een marketingaanbod na de bestelling; de bestelling zelf blijft.
            $sources['order_add_on_offers'] = ['label' => __('Aanbiedingen na een bestelling'), 'query' => DB::table('dashed__order_add_on_offers')->where(function ($q) use ($subject) {
                $subject->whereEmail($q, 'email');
                $q->orWhereIn('order_id', $this->orderIds($subject));
            })];
        }
        if ($this->hasTable('dashed__api_subscription_logs')) {
            $sources['api_subscription_logs'] = ['label' => __('Logboek van aanmeldingen bij koppelingen'), 'query' => $subject->whereEmail(DB::table('dashed__api_subscription_logs'), 'email')];
        }

        if ($userIds !== []) {
            foreach ([
                'customer_prices' => ['dashed__product_user', __('Klantprijzen per product')],
                'customer_category_prices' => ['dashed__product_category_user', __('Klantkortingen per categorie')],
                'customer_extra_prices' => ['dashed__product_extra_user', __('Klantprijzen voor extra opties')],
                'customer_extra_option_prices' => ['dashed__product_extra_option_user', __('Klantprijzen voor keuzes van extra opties')],
            ] as $key => [$table, $label]) {
                if ($this->hasTable($table)) {
                    $sources[$key] = ['label' => $label, 'query' => DB::table($table)->whereIn('user_id', $userIds)];
                }
            }
        }

        return $sources;
    }

    /**
     * Rijen die blijven, maar niet meer naar het account wijzen.
     *
     * @return array<string, array{label: string, query: Builder, values: array<string, mixed>, reason?: string}>
     */
    protected function unlinkable(PersonalDataSubject $subject): array
    {
        $userIds = $subject->userIds();
        if ($userIds === []) {
            return [];
        }

        $sources = [];

        if ($q = $this->discountCodes($subject)) {
            $sources['discount_codes'] = ['label' => __('Persoonlijke kortingscodes'), 'query' => $q, 'values' => ['user_id' => null], 'reason' => __('De code blijft bestaan omdat bestellingen ernaar verwijzen.')];
        }
        if ($this->hasTable('dashed__pos_carts') && Schema::hasColumn('dashed__pos_carts', 'customer_user_id')) {
            $sources['pos_carts'] = ['label' => __('Kassabonnen in behandeling'), 'query' => DB::table('dashed__pos_carts')->whereIn('customer_user_id', $userIds), 'values' => ['customer_user_id' => null]];
        }
        if ($this->hasTable('dashed__ecommerce_action_logs')) {
            // De rijen voeden de tellers van in-winkelwagen per product; ze
            // blijven, maar zonder account en IP-adres.
            $sources['ecommerce_action_logs'] = ['label' => __('Winkelwagenacties'), 'query' => DB::table('dashed__ecommerce_action_logs')->whereIn('user_id', $userIds), 'values' => ['user_id' => null, 'ip' => null], 'reason' => __('Blijven zonder account en IP-adres staan voor de productstatistieken.')];
        }
        if ($this->hasTable('dashed__order_logs') && Schema::hasColumn('dashed__order_logs', 'user_id')) {
            $sources['order_logs'] = ['label' => __('Regels in de tijdlijn van een bestelling'), 'query' => DB::table('dashed__order_logs')->whereIn('user_id', $userIds), 'values' => ['user_id' => null], 'reason' => __('De tijdlijn hoort bij de bestelling en blijft bewaard.')];
        }

        return $sources;
    }

    /** @return array<string, mixed> */
    protected function anonymousQuote(int $id): array
    {
        $columns = array_flip(Schema::getColumnListing('dashed__quotes'));

        return array_intersect_key([
            'user_id' => null,
            'company_name' => null,
            'btw_id' => null,
            'first_name' => Anonymized::NAME,
            'last_name' => null,
            'email' => Anonymized::email(),
            'phone_number' => null,
            'street' => null,
            'house_nr' => null,
            'zip_code' => null,
            'city' => null,
            'country' => null,
            'invoice_street' => null,
            'invoice_house_nr' => null,
            'invoice_zip_code' => null,
            'invoice_city' => null,
            'invoice_country' => null,
            'accepted_name' => null,
            'accepted_ip' => null,
            'accepted_signature' => null,
            'rejection_reason' => null,
            'notes' => null,
        ], $columns);
    }
}
