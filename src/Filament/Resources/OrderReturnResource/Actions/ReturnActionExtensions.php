<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderReturnResource\Actions;

use Closure;
use Dashed\DashedEcommerceCore\Models\OrderReturn;

/**
 * Uitbreidingspunt op de retouracties: een ander package (Bol bijvoorbeeld)
 * kan velden aan de sluit- en afkeurmodal toevoegen en vlak vóór close() of
 * reject() iets op het record zetten. Ec-core kent dat package niet.
 */
class ReturnActionExtensions
{
    /** @var array<string, array<int, array{fields: Closure, before: ?Closure}>> */
    protected static array $extensions = [];

    public static function register(string $action, Closure $fields, ?Closure $beforeAction = null): void
    {
        static::$extensions[$action][] = ['fields' => $fields, 'before' => $beforeAction];
    }

    /** @return array<int, mixed> Filament-componenten */
    public static function fields(string $action, OrderReturn $record): array
    {
        $fields = [];
        foreach (static::$extensions[$action] ?? [] as $extension) {
            $fields = array_merge($fields, (array) ($extension['fields'])($record));
        }

        return $fields;
    }

    public static function runBefore(string $action, OrderReturn $record, array $data): void
    {
        foreach (static::$extensions[$action] ?? [] as $extension) {
            if ($extension['before']) {
                ($extension['before'])($record, $data);
            }
        }
    }

    public static function flush(): void
    {
        static::$extensions = [];
    }
}
