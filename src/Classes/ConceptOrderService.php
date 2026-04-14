<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Str;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Models\Product;

class ConceptOrderService
{
    public static function saveAsConcept(POSCart $posCart, User $cashier, ?Order $existingConcept = null): Order
    {
        return DB::transaction(function () use ($posCart, $cashier, $existingConcept) {
            $products = $posCart->products ?? [];

            $subtotal = 0.0;
            foreach ($products as $row) {
                // $row['price'] is already the line total (singlePrice * quantity).
                $subtotal += (float) ($row['price'] ?? 0);
            }

            if ($existingConcept && $existingConcept->isConcept()) {
                $order = $existingConcept;
                $order->orderProducts()->delete();
            } else {
                $order = new Order();
            }

            $order->status = Order::STATUS_CONCEPT;
            $order->order_origin = 'pos';
            $order->user_id = $cashier->id;
            $order->total = $subtotal;
            $order->subtotal = $subtotal;
            $order->btw = 0;
            $order->discount = 0;
            $order->invoice_id = null;
            $order->save();

            foreach ($products as $row) {
                $quantity = max(1, (int) ($row['quantity'] ?? 1));
                $lineTotal = (float) ($row['price'] ?? 0);

                $order->orderProducts()->create([
                    'product_id' => $row['id'] ?? null,
                    'quantity' => $quantity,
                    'price' => $lineTotal,
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

        $products = [];
        foreach ($order->orderProducts as $op) {
            $quantity = max(1, (int) $op->quantity);
            // OrderProduct.price stores the line total, not the unit price.
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
                'extra' => [],
                'customProduct' => ! $op->product_id,
            ];
        }

        $posCart->products = $products;
        $posCart->save();
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
