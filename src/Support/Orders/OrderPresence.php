<?php

namespace Dashed\DashedEcommerceCore\Support\Orders;

use Illuminate\Support\Carbon;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedEcommerceCore\Models\OrderLog;

/**
 * "Bekijkt nu": wie heeft een bestelling open. Alleen in de cache, per
 * bestelling een lijst user_id => laatst gezien (unix-tijd). Een kijker telt
 * mee tot ACTIVE_SECONDS na zijn laatste ping.
 *
 * Daarnaast "is ermee bezig": wie in de laatste ACTIVE_SECONDS een
 * schrijfactie op de bestelling deed (orderlog als medewerker, toewijzing,
 * printstatus, label). Zelfde opslag, eigen cachesleutel.
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

    /** "Is ermee bezig": deze medewerker deed net een schrijfactie op de bestelling. */
    public static function markWorking(int $orderId, int $userId): void
    {
        if ($orderId <= 0 || $userId <= 0) {
            return;
        }

        $seen = self::active(self::read($orderId, self::workingKey($orderId)));
        $seen[$userId] = now()->getTimestamp();

        Cache::put(self::workingKey($orderId), $seen, self::TTL_SECONDS);
    }

    /**
     * Een orderlogregel van een medewerker telt als schrijfactie (zelfde
     * medewerkerregel als OrderAssignment::assignFromLog). Systeemregels en
     * regels van klanten tellen niet.
     */
    public static function markWorkingFromLog(OrderLog $log): void
    {
        if (! $log->order_id || ! $log->user_id || $log->is_system) {
            return;
        }

        if (! OrderAssignment::isStaff((int) $log->user_id)) {
            return;
        }

        self::markWorking((int) $log->order_id, (int) $log->user_id);
    }

    /** @return list<int> user-ids die in de laatste ACTIVE_SECONDS iets aan de bestelling deden */
    public static function workingOn(int $orderId): array
    {
        return array_keys(self::active(self::read($orderId, self::workingKey($orderId))));
    }

    /**
     * Kijkers en wie ermee bezig is, zonder de opgegeven gebruiker. Wie ermee
     * bezig is staat niet ook nog bij de kijkers. Nieuwste eerst.
     *
     * @return array{viewers: list<array<string, mixed>>, working_on: list<array<string, mixed>>}
     */
    public static function presenceFor(int $orderId, ?int $excludeUserId = null): array
    {
        $working = self::active(self::read($orderId, self::workingKey($orderId)));
        $viewing = array_diff_key(self::active(self::read($orderId)), $working);

        if ($excludeUserId !== null) {
            unset($working[$excludeUserId], $viewing[$excludeUserId]);
        }

        arsort($working);
        arsort($viewing);

        $ids = array_merge(array_keys($working), array_keys($viewing));
        $users = $ids === [] ? collect() : User::whereKey($ids)->get()->keyBy('id');

        $map = function (array $seen) use ($users): array {
            $items = [];
            foreach ($seen as $userId => $timestamp) {
                $user = $users->get($userId);
                if (! $user) {
                    continue;
                }
                $items[] = array_merge(UserPayload::for($user), [
                    'last_seen_at' => Carbon::createFromTimestamp((int) $timestamp)->toIso8601String(),
                ]);
            }

            return $items;
        };

        return [
            'viewers' => $map($viewing),
            'working_on' => $map($working),
        ];
    }

    /**
     * presenceFor() voor een hele lijst bestellingen (orderoverzicht, open
     * orderregels): één cache-many en hoogstens één gebruikersquery. Alleen
     * bestellingen met kijkers of iemand die ermee bezig is staan erin.
     *
     * @param  array<int>  $orderIds
     * @return array<int, array{viewers: list<array<string, mixed>>, working_on: list<array<string, mixed>>}>
     */
    public static function presenceForMany(array $orderIds, ?int $excludeUserId = null): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds), fn (int $id) => $id > 0)));
        if ($orderIds === []) {
            return [];
        }

        $keys = [];
        foreach ($orderIds as $orderId) {
            $keys[] = self::key($orderId);
            $keys[] = self::workingKey($orderId);
        }
        $cached = Cache::many($keys);

        $perOrder = [];
        $userIds = [];
        foreach ($orderIds as $orderId) {
            $rawWorking = $cached[self::workingKey($orderId)] ?? null;
            $rawViewing = $cached[self::key($orderId)] ?? null;
            $working = self::active(is_array($rawWorking) ? $rawWorking : []);
            $viewing = array_diff_key(self::active(is_array($rawViewing) ? $rawViewing : []), $working);

            if ($excludeUserId !== null) {
                unset($working[$excludeUserId], $viewing[$excludeUserId]);
            }
            if ($working === [] && $viewing === []) {
                continue;
            }

            arsort($working);
            arsort($viewing);
            $perOrder[$orderId] = [$viewing, $working];
            array_push($userIds, ...array_keys($working), ...array_keys($viewing));
        }

        if ($perOrder === []) {
            return [];
        }

        $users = User::whereKey(array_values(array_unique($userIds)))->get()->keyBy('id');
        $map = function (array $seen) use ($users): array {
            $items = [];
            foreach ($seen as $userId => $timestamp) {
                if ($user = $users->get($userId)) {
                    $items[] = array_merge(UserPayload::for($user), [
                        'last_seen_at' => Carbon::createFromTimestamp((int) $timestamp)->toIso8601String(),
                    ]);
                }
            }

            return $items;
        };

        $result = [];
        foreach ($perOrder as $orderId => [$viewing, $working]) {
            $entry = ['viewers' => $map($viewing), 'working_on' => $map($working)];
            if ($entry['viewers'] !== [] || $entry['working_on'] !== []) {
                $result[$orderId] = $entry;
            }
        }

        return $result;
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
        return array_values(array_filter(array_column(self::otherViewers($orderId, $excludeUserId), 'name')));
    }

    /**
     * Kijkers als {id, name, avatar_url}, zonder de opgegeven gebruiker. Eén cache-get;
     * alleen als er kijkers zijn volgt een gebruikersquery.
     *
     * @return list<array{id: int, name: ?string, avatar_url: ?string}>
     */
    public static function otherViewers(int $orderId, ?int $excludeUserId): array
    {
        $ids = array_values(array_diff(self::viewers($orderId), [(int) $excludeUserId]));

        if ($ids === []) {
            return [];
        }

        // name is een accessor op User (voor- + achternaam), geen kolom.
        return User::whereKey($ids)->get()
            ->map(fn (User $user): array => UserPayload::for($user))
            ->values()
            ->all();
    }

    public static function key(int $orderId): string
    {
        return 'order-presence:' . $orderId;
    }

    public static function workingKey(int $orderId): string
    {
        return 'order-working:' . $orderId;
    }

    /** @return array<int, int> */
    private static function read(int $orderId, ?string $key = null): array
    {
        $seen = Cache::get($key ?? self::key($orderId));

        return is_array($seen) ? $seen : [];
    }

    /** @param array<int, int> $seen */
    private static function active(array $seen): array
    {
        $cutoff = now()->getTimestamp() - self::ACTIVE_SECONDS;

        return array_filter($seen, fn ($lastSeen) => (int) $lastSeen >= $cutoff);
    }
}
