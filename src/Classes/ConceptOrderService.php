<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Str;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Controllers\Api\PointOfSale\PointOfSaleApiController;

class ConceptOrderService
{
    public static function saveAsConcept(POSCart $posCart, User $cashier, ?Order $existingConcept = null): Order
    {
        return DB::transaction(function () use ($posCart, $cashier, $existingConcept) {
            $products = $posCart->products ?? [];

            // Zelfde totaal-berekening als de kassa zelf (incl. kortingscodes en
            // btw), zodat een ingestelde korting exact meegaat naar het concept en
            // de proforma-afrekenlink. Voorheen werd hier het volle bedrag gesomd
            // en discount hard op 0 gezet, waardoor de korting wegviel.
            $totals = app(PointOfSaleApiController::class)->calculatePosCartTotals($posCart);

            if ($existingConcept && $existingConcept->isConcept()) {
                $order = $existingConcept;
                // Hard-delete any existing items (including soft-deleted ghosts) so the concept
                // is fully rewritten instead of accumulating stale rows.
                $order->orderProducts()->withTrashed()->forceDelete();
            } else {
                $order = new Order();
            }

            $order->status = Order::STATUS_CONCEPT;
            $order->order_origin = 'pos';
            $order->user_id = $posCart->customer_user_id ?: $cashier->id;
            $order->first_name = $posCart->first_name;
            $order->last_name = $posCart->last_name;
            $order->email = $posCart->email;
            $order->phone_number = $posCart->phone_number;
            $order->street = $posCart->street;
            $order->house_nr = $posCart->house_nr;
            $order->zip_code = $posCart->zip_code;
            $order->city = $posCart->city;
            $order->country = $posCart->country;
            $order->company_name = $posCart->company;
            $order->btw_id = $posCart->btw_id;
            $order->invoice_street = $posCart->invoice_street;
            $order->invoice_house_nr = $posCart->invoice_house_nr;
            $order->invoice_zip_code = $posCart->invoice_zip_code;
            $order->invoice_city = $posCart->invoice_city;
            $order->invoice_country = $posCart->invoice_country;
            $order->note = $posCart->note;
            $order->prices_ex_vat = (bool) ($posCart->prices_ex_vat ?? false);
            $order->shipping_method_id = $posCart->shipping_method_id ?? null;
            $order->concept_discount_code = $posCart->discount_code ?? null;
            // Full-fidelity snapshot so hydrate() can restore the cart exactly as it was saved.
            $order->concept_cart_snapshot = array_values($products);
            // subtotal = product-totaal NA korting (zelfde semantiek als de
            // definitieve POS-order in createOrder); total = subtotal (shipping komt
            // pas bij het afrekenen erbij); discount = het kortingsbedrag.
            $order->subtotal = $totals['subtotal'];
            $order->total = $totals['subtotal'];
            $order->btw = $totals['vat'];
            $order->discount = $totals['discount'];
            $order->applied_discount_codes = ($totals['discountCodes'] ?? []) ?: null;
            $order->invoice_id = null;
            $order->save();

            foreach ($products as $row) {
                $quantity = max(1, (int) ($row['quantity'] ?? 1));
                $lineTotal = (float) ($row['price'] ?? 0);
                $vatRate = (float) ($row['vat_rate'] ?? 21);

                $order->orderProducts()->create([
                    'product_id' => $row['id'] ?? null,
                    'quantity' => $quantity,
                    'price' => $lineTotal,
                    'vat_rate' => $vatRate,
                    'product_extras' => $row['extra'] ?? [],
                    'name' => $row['name'] ?? (isset($row['id']) ? (Product::find($row['id'])?->name ?? 'Product') : 'Product'),
                ]);
            }

            $posCart->products = [];
            $posCart->discount_code = null;
            $posCart->save();

            return $order;
        });
    }

    public static function hydrate(POSCart $posCart, Order $order): void
    {
        if (! $order->isConcept()) {
            throw new \LogicException('Cannot hydrate POS cart from a non-concept order.');
        }

        $posCart->products = self::buildCartProducts($order);
        $posCart->prices_ex_vat = (bool) ($order->prices_ex_vat ?? false);
        $posCart->shipping_method_id = $order->shipping_method_id ?? null;
        $posCart->discount_code = $order->concept_discount_code ?? null;
        $posCart->save();
    }

    /**
     * Laadt een willekeurige order (concept of normaal/betaald) als NIEUWE,
     * niet-gekoppelde winkelwagen. Opslaan in de POS maakt zo een nieuw concept
     * aan; de bronorder blijft ongemoeid.
     */
    public static function copyIntoCart(POSCart $posCart, Order $order): void
    {
        $posCart->products = self::buildCartProducts($order);
        $posCart->loaded_concept_order_id = null;
        $posCart->prices_ex_vat = (bool) ($order->prices_ex_vat ?? false);
        $posCart->shipping_method_id = $order->shipping_method_id ?? null;
        $posCart->discount_code = null;
        $posCart->save();
    }

    /**
     * Bouwt de POS-winkelwagenrijen voor een order: verbatim uit de concept-snapshot
     * indien aanwezig, anders gereconstrueerd uit de orderProducts (concepten zonder
     * snapshot en normale orders). Elke rij krijgt een verse identifier.
     */
    protected static function buildCartProducts(Order $order): array
    {
        $snapshot = $order->isConcept() ? $order->concept_cart_snapshot : null;

        if (is_array($snapshot) && count($snapshot) > 0) {
            // Verbatim restore - every field (vat_rate, extras, custom flags, formatted prices)
            // is preserved exactly as it was when the cashier saved the concept.
            return array_map(function (array $row): array {
                // Refresh identifier so hydrated rows don't collide with anything in memory.
                $row['identifier'] = (string) Str::random();

                return $row;
            }, $snapshot);
        }

        // Reconstruct from orderProducts (concepts that predate the snapshot column and normal orders).
        $products = [];
        foreach ($order->orderProducts as $op) {
            $quantity = max(1, (int) $op->quantity);
            $lineTotal = (float) $op->price;
            $unitPrice = $quantity > 0 ? $lineTotal / $quantity : $lineTotal;

            $product = $op->product_id ? Product::find($op->product_id) : null;
            $image = '';
            if ($product && $product->firstImage) {
                $image = mediaHelper()->getSingleMedia($product->firstImage, ['widen' => 300])->url ?? '';
            }

            $products[] = [
                'id' => $op->product_id,
                'identifier' => (string) Str::random(),
                'name' => $op->name ?: ($product?->name ?? 'Product'),
                'image' => $image,
                'quantity' => $quantity,
                'singlePrice' => $unitPrice,
                'price' => $lineTotal,
                'priceFormatted' => CurrencyHelper::formatPrice($lineTotal),
                'vat_rate' => (float) ($op->vat_rate ?? 21),
                'extra' => is_array($op->product_extras) ? $op->product_extras : [],
                'customProduct' => ! $op->product_id,
            ];
        }

        return $products;
    }

    public static function cancel(Order $order): void
    {
        if (! $order->isConcept()) {
            throw new \LogicException('Cannot cancel a non-concept order via ConceptOrderService::cancel().');
        }

        DB::transaction(function () use ($order) {
            $order->orderProducts()->delete();
            $order->delete();
        });
    }
}
