<?php

namespace Dashed\DashedEcommerceCore\Contracts;

use Dashed\DashedEcommerceCore\Models\OrderPayment;

interface PaymentProviderContract
{
    public static function initialize(?string $siteId = null): void;

    public static function isConnected(?string $siteId = null): bool;

    public static function syncPaymentMethods(?string $siteId = null): void;

    public static function syncPinTerminals(?string $siteId = null): void;

    public static function startTransaction(OrderPayment $orderPayment): array;

    public static function getOrderStatus(OrderPayment $orderPayment): string;

    public static function getTransaction(OrderPayment $orderPayment);
}
