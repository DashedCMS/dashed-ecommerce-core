<?php

namespace Dashed\DashedEcommerceCore\Support\Orders;

use Illuminate\Support\Facades\Cache;

/**
 * "Bekijkt nu": wie heeft een bestelling open. Alleen in de cache, per
 * bestelling een lijst user_id => laatst gezien (unix-tijd). Een kijker telt
 * mee tot ACTIVE_SECONDS na zijn laatste ping.
 */
class OrderPresence
{
    public const ACTIVE_SECONDS = 90;

    public const TTL_SECONDS = 300;

    public static function ping(int $orderId, int $userId): void
    {
        $seen = self::active(self::read($orderId));
        $seen[$userId] = now()->getTimestamp();

        Cache::put(self::key($orderId), $seen, self::TTL_SECONDS);
    }

    public static function leave(int $orderId, int $userId): void
    {
        $seen = self::active(self::read($orderId));
        unset($seen[$userId]);

        if ($seen === []) {
            Cache::forget(self::key($orderId));

            return;
        }

        Cache::put(self::key($orderId), $seen, self::TTL_SECONDS);
    }

    /** @return list<int> user-ids die de bestelling nu open hebben */
    public static function viewers(int $orderId): array
    {
        return array_keys(self::active(self::read($orderId)));
    }

    /**
     * @param  array<int>  $orderIds
     * @return array<int, list<int>> order-id => user-ids (lege lijst zonder kijkers)
     */
    public static function viewersFor(array $orderIds): array
    {
        $result = [];

        foreach (array_unique(array_map('intval', $orderIds)) as $orderId) {
            $result[$orderId] = self::viewers($orderId);
        }

        return $result;
    }

    public static function key(int $orderId): string
    {
        return 'order-presence:' . $orderId;
    }

    /** @return array<int, int> */
    private static function read(int $orderId): array
    {
        $seen = Cache::get(self::key($orderId));

        return is_array($seen) ? $seen : [];
    }

    /** @param array<int, int> $seen */
    private static function active(array $seen): array
    {
        $cutoff = now()->getTimestamp() - self::ACTIVE_SECONDS;

        return array_filter($seen, fn ($lastSeen) => (int) $lastSeen >= $cutoff);
    }
}
