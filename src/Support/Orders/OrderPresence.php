<?php

namespace Dashed\DashedEcommerceCore\Support\Orders;

use Dashed\DashedCore\Models\User;
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

    /**
     * Namen van de kijkers, zonder de opgegeven gebruiker (meestal de ingelogde).
     * Eén cache-get; alleen als er kijkers zijn volgt een gebruikersquery.
     *
     * @return list<string>
     */
    public static function otherViewerNames(int $orderId, ?int $excludeUserId): array
    {
        $ids = array_values(array_diff(self::viewers($orderId), [(int) $excludeUserId]));

        if ($ids === []) {
            return [];
        }

        // name is een accessor op User (voor- + achternaam), geen kolom.
        return User::whereKey($ids)->get()->pluck('name')->filter()->values()->all();
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
