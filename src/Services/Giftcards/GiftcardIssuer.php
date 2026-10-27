<?php

namespace Dashed\DashedEcommerceCore\Services\Giftcards;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Events\GiftcardIssuedEvent;

class GiftcardIssuer
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Geeft per stuk van elke cadeaubonregel een verkochte bon uit.
     * Idempotent: een regel met al `quantity` bonnen krijgt er geen bij.
     *
     * @return Collection<int, DiscountCode>
     */
    public function issueFor(Order $order): Collection
    {
        if ($order->credit_for_order_id
            || (float) $order->total < 0
            || ! in_array($order->order_origin ?? 'own', ['own', 'pos'], true)) {
            return collect();
        }

        $issued = collect();
        $pairs = [];

        DB::transaction(function () use ($order, &$issued, &$pairs) {
            // Rijlock op de order, zodat twee gelijktijdige triggers niet dubbel uitgeven.
            Order::query()->whereKey($order->id)->lockForUpdate()->first();

            $lines = OrderProduct::query()->where('order_id', $order->id)->with('product')->get();

            foreach ($lines as $line) {
                if (! $line->product || ! $line->product->isGiftcardProduct() || $line->quantity < 1) {
                    continue;
                }

                $existing = DiscountCode::withTrashed()->where('source_order_product_id', $line->id)->count();
                $amount = round((float) $line->price / $line->quantity, 2);

                for ($i = $existing; $i < $line->quantity; $i++) {
                    $card = new DiscountCode();
                    $card->forceFill([
                        'site_ids' => [$order->site_id ?: Sites::getActive()],
                        'name' => 'Cadeaubon order ' . $order->invoice_id,
                        'code' => self::newCode(),
                        'type' => 'amount',
                        'is_giftcard' => 1,
                        'giftcard_type' => 'verkocht',
                        'discount_amount' => $amount,
                        'initial_amount' => $amount,
                        'source_order_product_id' => $line->id,
                        'start_date' => now(),
                        'end_date' => null,
                    ]);
                    $card->save();

                    $issued->push($card->fresh());
                    $pairs[] = [$card->fresh(), $line];
                }
            }
        });

        foreach ($pairs as [$card, $line]) {
            GiftcardIssuedEvent::dispatch($card, $line);
        }

        return $issued;
    }

    public static function newCode(): string
    {
        do {
            $code = 'LOV-' . self::randomBlock() . '-' . self::randomBlock();
        } while (DiscountCode::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    private static function randomBlock(): string
    {
        $block = '';
        for ($i = 0; $i < 4; $i++) {
            $block .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $block;
    }
}
