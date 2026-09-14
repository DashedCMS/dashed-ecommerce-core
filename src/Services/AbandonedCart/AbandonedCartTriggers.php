<?php

namespace Dashed\DashedEcommerceCore\Services\AbandonedCart;

use Closure;
use InvalidArgumentException;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;

/**
 * Register van triggers voor de verlaten-wagen-flows. De twee ingebouwde
 * triggers (wagen met e-mail, geannuleerde bestelling) registreren zichzelf
 * in de serviceprovider; een pakket of app kan er een bijzetten (de
 * verlanglijst doet dat) zonder de vier plekken te patchen die de trigger
 * vroeger hard kenden: het beheerscherm, de resolver, het model en het
 * verzendcommando.
 */
class AbandonedCartTriggers
{
    /** @var array<string, array{label: string, description: string, resolve: Closure}> */
    protected static array $triggers = [];

    public static function register(string $key, string $label, string $description, Closure $resolve): void
    {
        static::$triggers[$key] = compact('label', 'description', 'resolve');
    }

    public static function reset(): void
    {
        static::$triggers = [];
    }

    public static function has(string $key): bool
    {
        return isset(static::$triggers[$key]);
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return array_map(fn (array $t) => $t['label'], static::$triggers);
    }

    /** @return array<string, string> */
    public static function descriptions(): array
    {
        return array_map(fn (array $t) => $t['description'], static::$triggers);
    }

    public static function resolve(AbandonedCartEmail $record): AbandonedCartSource
    {
        $trigger = static::$triggers[$record->trigger_type] ?? null;

        if (! $trigger) {
            throw new InvalidArgumentException("Unknown trigger_type: {$record->trigger_type}");
        }

        $source = ($trigger['resolve'])($record);

        if (! $source instanceof AbandonedCartSource) {
            throw new InvalidArgumentException("Abandoned cart source missing for #{$record->id} ({$record->trigger_type})");
        }

        return $source;
    }
}
