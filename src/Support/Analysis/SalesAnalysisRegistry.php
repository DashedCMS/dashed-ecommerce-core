<?php

namespace Dashed\DashedEcommerceCore\Support\Analysis;

use Throwable;
use Dashed\DashedEcommerceCore\Support\Analysis\Contracts\SalesAnalysis;

/**
 * Zelfde vorm als SummaryContributorRegistry in dashed-core: analyses melden
 * zich aan via cms()->builder('salesAnalyses', [...]) en worden hier
 * opgehaald en gefilterd. Zo kan een ander pakket er een aanmelden zonder
 * dit pakket te wijzigen.
 */
class SalesAnalysisRegistry
{
    /** @var array<string, class-string<SalesAnalysis>>|null */
    protected static ?array $override = null;

    /** @return array<string, class-string<SalesAnalysis>> */
    public static function map(): array
    {
        if (static::$override !== null) {
            return static::$override;
        }

        $registered = function_exists('cms') ? (cms()->builder('salesAnalyses', null) ?? []) : [];
        if (! is_array($registered)) {
            return [];
        }

        $map = [];
        foreach ($registered as $class) {
            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }
            if (! is_subclass_of($class, SalesAnalysis::class)) {
                continue;
            }

            try {
                /** @var class-string<SalesAnalysis> $class */
                $map[$class::key()] = $class;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $map;
    }

    /**
     * Alleen voor tests: forceer (of wis met null) de kaart.
     *
     * @param  array<string, class-string<SalesAnalysis>>|null  $map
     */
    public static function fakeMap(?array $map): void
    {
        static::$override = $map;
    }
}
