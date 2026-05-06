<?php

namespace Dashed\DashedEcommerceCore\Services;

use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\CartLog;

class CartActivityLogger
{
    public static function log(Cart|int|null $cart, string $event, ?string $message = null, array $data = []): ?CartLog
    {
        $cartId = $cart instanceof Cart ? $cart->id : (int) $cart;
        if (! $cartId) {
            return null;
        }

        return CartLog::create([
            'cart_id' => $cartId,
            'event' => $event,
            'message' => $message,
            'data' => $data ?: null,
            'created_at' => now(),
        ]);
    }

    public static function productAdded(Cart|int|null $cart, $product, int $quantity, array $options = []): void
    {
        $name = is_object($product) ? ($product->name ?? null) : null;
        self::log(
            $cart,
            'cart.product.added',
            $name ? sprintf('Product "%s" toegevoegd (x%d)', $name, $quantity) : sprintf('Product toegevoegd (x%d)', $quantity),
            array_filter([
                'product_id' => is_object($product) ? ($product->id ?? null) : $product,
                'product_name' => $name,
                'quantity' => $quantity,
                'options' => $options ?: null,
            ], fn ($v) => $v !== null)
        );
    }

    public static function quantityChanged(Cart|int|null $cart, string $rowId, int $from, int $to, ?string $productName = null): void
    {
        self::log(
            $cart,
            'cart.product.quantity-changed',
            $productName
                ? sprintf('Aantal van "%s" gewijzigd: %d → %d', $productName, $from, $to)
                : sprintf('Aantal gewijzigd: %d → %d', $from, $to),
            ['row_id' => $rowId, 'from' => $from, 'to' => $to, 'product_name' => $productName]
        );
    }

    public static function productRemoved(Cart|int|null $cart, string $rowId, ?string $productName = null): void
    {
        self::log(
            $cart,
            'cart.product.removed',
            $productName ? sprintf('Product "%s" verwijderd', $productName) : 'Product verwijderd',
            ['row_id' => $rowId, 'product_name' => $productName]
        );
    }

    public static function cartEmptied(Cart|int|null $cart): void
    {
        self::log($cart, 'cart.emptied', 'Winkelwagen geleegd');
    }

    public static function discountApplied(Cart|int|null $cart, string $code, ?string $status = null, ?string $message = null): void
    {
        // Truncate vrije-tekst-input naar een sane lengte voor de message-kolom.
        // Een URL of andere bagger kan via querystring-injectie of tracker-rewrites
        // in $code belanden; een MySQL VARCHAR(255) liep daardoor over ("Data too
        // long for column 'message'"). De volledige code blijft in de json data.
        $shortCode = mb_strimwidth((string) $code, 0, 60, '...');

        self::log(
            $cart,
            'cart.discount.applied',
            $status === 'success' ? sprintf('Kortingscode "%s" toegepast', $shortCode) : sprintf('Kortingscode "%s" geweigerd', $shortCode),
            array_filter(['code' => $code, 'status' => $status, 'message' => $message])
        );
    }

    public static function shippingMethodChanged(Cart|int|null $cart, ?int $shippingMethodId, ?string $methodName = null): void
    {
        self::log(
            $cart,
            'cart.shipping-method.changed',
            $methodName ? sprintf('Verzendmethode ingesteld op "%s"', $methodName) : 'Verzendmethode aangepast',
            ['shipping_method_id' => $shippingMethodId, 'name' => $methodName]
        );
    }

    public static function paymentMethodChanged(Cart|int|null $cart, ?int $paymentMethodId, ?string $methodName = null): void
    {
        self::log(
            $cart,
            'cart.payment-method.changed',
            $methodName ? sprintf('Betaalmethode ingesteld op "%s"', $methodName) : 'Betaalmethode aangepast',
            ['payment_method_id' => $paymentMethodId, 'name' => $methodName]
        );
    }

    public static function emailCaptured(Cart|int|null $cart, string $email, string $source = 'checkout'): void
    {
        self::log(
            $cart,
            'cart.email.captured',
            sprintf('E-mailadres vastgelegd: %s (via %s)', $email, $source),
            ['email' => $email, 'source' => $source]
        );
    }

    public static function abandonedEmailsScheduled(Cart|int|null $cart, $flow, int $stepCount): void
    {
        self::log(
            $cart,
            'cart.abandoned-email.scheduled',
            sprintf('Abandoned cart flow "%s" ingepland (%d mail(s))', $flow->name ?? '?', $stepCount),
            ['flow_id' => $flow->id ?? null, 'flow_name' => $flow->name ?? null, 'step_count' => $stepCount]
        );
    }

    public static function abandonedEmailSent(Cart|int|null $cart, $step, $discountCode = null): void
    {
        $stepLabel = $step->name ?? ('Stap ' . ($step->sort_order ?? '?'));
        $codeValue = $discountCode?->code;

        self::log(
            $cart,
            'cart.abandoned-email.sent',
            $codeValue
                ? sprintf('Abandoned cart mail verzonden (%s) met kortingscode %s', $stepLabel, $codeValue)
                : sprintf('Abandoned cart mail verzonden (%s)', $stepLabel),
            array_filter([
                'flow_step_id' => $step->id ?? null,
                'flow_step_name' => $step->name ?? null,
                'discount_code' => $codeValue,
                'discount_code_id' => $discountCode?->id,
            ])
        );
    }

    public static function orderConverted(Cart|int|null $cart, int $orderId): void
    {
        self::log(
            $cart,
            'cart.converted-to-order',
            sprintf('Winkelwagen omgezet naar bestelling #%d', $orderId),
            ['order_id' => $orderId]
        );
    }
}
