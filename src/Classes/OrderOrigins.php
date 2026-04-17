<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedCore\Models\Customsetting;

class OrderOrigins
{
    /**
     * @var array<string, array{label: string, default_notify: bool}>
     */
    private static array $registered = [];

    public static function register(string $key, string $label, bool $defaultNotify = true): void
    {
        self::$registered[$key] = [
            'label' => $label,
            'default_notify' => $defaultNotify,
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, default_notify: bool, current_value: bool}>
     */
    public static function all(?string $siteId = null): array
    {
        $overrides = is_array($raw = Customsetting::get('admin_notify_per_order_origin', $siteId, [])) ? $raw : [];
        $seen = is_array($rawSeen = Customsetting::get('order_origins_seen', $siteId, [])) ? $rawSeen : [];

        $items = [];
        foreach (self::$registered as $key => $meta) {
            $items[$key] = [
                'key' => $key,
                'label' => $meta['label'],
                'default_notify' => $meta['default_notify'],
                'current_value' => array_key_exists($key, $overrides) ? (bool) $overrides[$key] : $meta['default_notify'],
            ];
        }
        foreach ($seen as $key) {
            if (! is_string($key) || isset($items[$key])) {
                continue;
            }
            $items[$key] = [
                'key' => $key,
                'label' => $key,
                'default_notify' => true,
                'current_value' => array_key_exists($key, $overrides) ? (bool) $overrides[$key] : true,
            ];
        }

        $list = array_values($items);
        usort($list, fn ($a, $b) => strcasecmp($a['label'], $b['label']));

        return $list;
    }

    public static function shouldNotifyAdmin(?string $origin, ?string $siteId = null): bool
    {
        if ($origin === null || $origin === '') {
            return true;
        }

        $overrides = Customsetting::get('admin_notify_per_order_origin', $siteId, []);
        if (is_array($overrides) && array_key_exists($origin, $overrides)) {
            return (bool) $overrides[$origin];
        }

        if (isset(self::$registered[$origin])) {
            return self::$registered[$origin]['default_notify'];
        }

        // Unknown origin: persist it to the seen-list so admins can toggle it in the UI.
        $seen = Customsetting::get('order_origins_seen', $siteId, []);
        $seen = is_array($seen) ? $seen : [];
        if (! in_array($origin, $seen, true)) {
            $seen[] = $origin;
            Customsetting::set('order_origins_seen', $seen, $siteId);
        }

        return true;
    }

    public static function labelFor(string $origin): string
    {
        return self::$registered[$origin]['label'] ?? $origin;
    }
}
