<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Collection;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

/**
 * Aankooplimiet bij het afrekenen, bovenop de limiet per winkelwagenregel
 * (limit_purchases_per_customer). Twee uitbreidingen, per product aan te zetten:
 *
 * - limit_purchases_whole_group: de limiet geldt voor alle varianten van de
 *   productgroep samen (anders kon iemand maat M én L van dezelfde box kopen);
 * - limit_purchases_previous_orders: ook betaalde bestellingen van dezelfde
 *   klant tellen mee, herkend op e-mail, afleveradres en/of IP.
 */
class CustomerPurchaseLimit
{
    public const MATCH_OPTIONS = ['email', 'address', 'ip'];

    /**
     * Geeft een melding terug als de winkelwagen de limiet overschrijdt,
     * anders null.
     *
     * @param  iterable  $cartItems  items met ->model en ->qty
     * @param  array{email?: ?string, zip_code?: ?string, house_nr?: ?string, ip?: ?string}  $customer
     */
    public static function refusal(iterable $cartItems, array $customer): ?string
    {
        $lines = collect($cartItems)
            ->filter(fn ($cartItem) => ($cartItem->model ?? null) instanceof Product)
            ->map(fn ($cartItem) => ['product' => $cartItem->model, 'qty' => (int) ($cartItem->qty ?? 0)]);

        $checked = [];
        foreach ($lines as $line) {
            /** @var Product $product */
            $product = $line['product'];

            if (! $product->limit_purchases_per_customer
                || (! $product->limit_purchases_previous_orders && ! $product->limit_purchases_whole_group)) {
                continue;
            }

            $productIds = self::limitedProductIds($product);
            $key = $productIds->sort()->implode(',');
            if (isset($checked[$key])) {
                continue;
            }
            $checked[$key] = true;

            $inCart = $lines->filter(fn ($l) => $productIds->contains($l['product']->id))->sum('qty');
            $bought = $product->limit_purchases_previous_orders
                ? self::previouslyBought($product, $productIds, $customer)
                : 0;
            $limit = (int) $product->limit_purchases_per_customer_limit;

            if ($bought + $inCart <= $limit) {
                continue;
            }

            $name = $product->limit_purchases_whole_group && $product->productGroup
                ? $product->productGroup->name
                : $product->name;
            $left = max(0, $limit - $bought);

            if ($bought === 0) {
                return Translation::get('purchase-limit-group-reached', 'checkout', 'Van :product: mag je maximaal :limit: stuk(s) bestellen, ongeacht de variant.', 'text', [
                    'product' => $name,
                    'limit' => $limit,
                ]);
            }

            return $left === 0
                ? Translation::get('purchase-limit-previous-orders-reached', 'checkout', 'Je hebt :product: al eerder gekocht. Dit product mag maar :limit: keer per klant gekocht worden.', 'text', [
                    'product' => $name,
                    'limit' => $limit,
                ])
                : Translation::get('purchase-limit-previous-orders-left', 'checkout', 'Je mag van :product: nog maar :left: stuk(s) bestellen, omdat je dit product al eerder hebt gekocht.', 'text', [
                    'product' => $name,
                    'left' => $left,
                ]);
        }

        return null;
    }

    /** Het product zelf, of alle varianten van de groep als de limiet daarvoor geldt. */
    public static function limitedProductIds(Product $product): Collection
    {
        if (! $product->limit_purchases_whole_group || ! $product->product_group_id) {
            return collect([$product->id]);
        }

        return Product::withTrashed()
            ->where('product_group_id', $product->product_group_id)
            ->pluck('id');
    }

    /** Aantal stuks van deze producten in betaalde bestellingen van deze klant. */
    public static function previouslyBought(Product $product, Collection $productIds, array $customer): int
    {
        $matchOn = collect($product->limit_purchases_match_on ?: ['email'])
            ->intersect(self::MATCH_OPTIONS);

        $email = mb_strtolower(trim((string) ($customer['email'] ?? '')));
        $zip = self::normalize($customer['zip_code'] ?? null);
        $houseNr = self::normalize($customer['house_nr'] ?? null);
        $ip = trim((string) ($customer['ip'] ?? ''));

        $criteria = collect();
        if ($matchOn->contains('email') && $email !== '') {
            $criteria->push(fn ($q) => $q->orWhereRaw('LOWER(TRIM(email)) = ?', [$email]));
        }
        if ($matchOn->contains('address') && $zip !== '' && $houseNr !== '') {
            $criteria->push(fn ($q) => $q->orWhere(function ($q) use ($zip, $houseNr) {
                $q->whereRaw("UPPER(REPLACE(zip_code, ' ', '')) = ?", [$zip])
                    ->whereRaw("UPPER(REPLACE(house_nr, ' ', '')) = ?", [$houseNr]);
            }));
        }
        if ($matchOn->contains('ip') && $ip !== '') {
            $criteria->push(fn ($q) => $q->orWhere('ip', $ip));
        }

        if ($criteria->isEmpty()) {
            return 0;
        }

        return (int) OrderProduct::query()
            ->whereIn('product_id', $productIds)
            ->whereHas('order', function ($order) use ($criteria, $product) {
                $order->whereIn('status', Order::PAID_STATUSES)
                    ->when($product->limit_purchases_since, fn ($q, $since) => $q->where('created_at', '>=', $since))
                    ->where(function ($q) use ($criteria) {
                        $criteria->each(fn ($criterion) => $criterion($q));
                    });
            })
            ->sum('quantity');
    }

    protected static function normalize(?string $value): string
    {
        return mb_strtoupper(str_replace(' ', '', trim((string) $value)));
    }
}
