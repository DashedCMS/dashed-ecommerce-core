<?php

namespace Dashed\DashedEcommerceCore\Tests\Support;

use Illuminate\Support\Facades\Queue;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;

/**
 * Testgegevens voor de verkoopanalyse.
 *
 * Eén klasse in plaats van globale functies per testbestand: alle
 * testbestanden in deze map draaien in hetzelfde PHP-proces, dus globale
 * functies zouden botsen.
 */
class AnalysisFixtures
{
    /**
     * Een product. Model-events uit en de queue gefaket omdat
     * UpdateProductInformationJob MySQL-only SQL gebruikt en de tests op
     * SQLite draaien.
     */
    public static function product(string $name, float $price = 10.0, int $stock = 0, ?ProductGroup $group = null): Product
    {
        Queue::fake();

        $group ??= self::productGroup();

        return Product::withoutEvents(fn () => Product::create([
            'product_group_id' => $group->id,
            'name' => ['en' => $name, 'nl' => $name],
            'slug' => ['en' => 'analyse-' . uniqid(), 'nl' => 'analyse-' . uniqid()],
            'site_ids' => ['site'],
            'price' => $price,
            'current_price' => $price,
            'vat_rate' => 21,
            'use_stock' => $stock > 0,
            'stock' => $stock,
            'images' => [],
        ]));
    }

    public static function productGroup(string $name = 'Groep'): ProductGroup
    {
        return ProductGroup::create([
            'name' => ['en' => $name, 'nl' => $name],
            'slug' => ['en' => 'groep-' . uniqid(), 'nl' => 'groep-' . uniqid()],
            'short_description' => ['en' => ''],
            'description' => ['en' => ''],
            'content' => ['en' => ''],
            'search_terms' => ['en' => ''],
            'site_ids' => ['site'],
        ]);
    }

    /**
     * Een betaalde order op een datum, met regels.
     *
     * created_at wordt na het opslaan gezet: de creating-hook op Order
     * overschrijft site_id met de actieve site, en Eloquent zet zelf een
     * created_at van nu.
     *
     * @param  array<int, array{product?: Product, quantity?: int, price?: float, sku?: string|null, name?: string}>  $lines
     */
    public static function paidOrder(string $date, array $lines, string $siteId = 'site'): Order
    {
        $total = 0.0;

        $order = new Order();
        $order->email = 'klant@example.com';
        $order->status = 'paid';
        $order->invoice_id = 'PROFORMA';
        $order->save();

        foreach ($lines as $line) {
            $product = $line['product'] ?? null;
            $quantity = (int) ($line['quantity'] ?? 1);
            $price = (float) ($line['price'] ?? 10.0);
            $total += $price;

            OrderProduct::create([
                'order_id' => $order->id,
                'product_id' => $product?->id,
                'name' => $line['name'] ?? ($product?->name ?? 'Regel'),
                'quantity' => $quantity,
                'price' => $price,
                'vat_rate' => 21,
                'sku' => $line['sku'] ?? null,
            ]);
        }

        $order->site_id = $siteId;
        $order->total = $total;
        $order->subtotal = $total;
        $order->created_at = $date . ' 12:00:00';
        $order->save();

        return $order->fresh();
    }
}
