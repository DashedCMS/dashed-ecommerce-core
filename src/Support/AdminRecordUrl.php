<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Support;

use Throwable;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderReturnResource;

/**
 * Absolute CMS URL that a push opens when tapped (`data.url`). Never throws:
 * without a resolvable route it falls back to the CMS dashboard.
 */
class AdminRecordUrl
{
    public const PANEL = 'dashed';

    public static function order(Order|int $order): string
    {
        return self::resolve(fn () => OrderResource::getUrl('view', ['record' => $order instanceof Order ? $order : (string) $order], panel: self::PANEL));
    }

    public static function orderReturn(OrderReturn $return): string
    {
        return self::resolve(fn () => OrderReturnResource::getUrl('view', ['record' => $return], panel: self::PANEL));
    }

    public static function dashboard(): string
    {
        return url('/' . trim((string) config('dashed-core.dashed_cms.path', 'dashed'), '/'));
    }

    private static function resolve(callable $url): string
    {
        try {
            return (string) $url() ?: self::dashboard();
        } catch (Throwable) {
            return self::dashboard();
        }
    }
}
