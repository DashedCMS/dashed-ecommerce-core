<?php

namespace Dashed\DashedEcommerceCore\Support\Analysis;

use Throwable;
use Illuminate\Support\Facades\Cache;

/**
 * Bouwt de volledige verkoopanalyse-payload (secties + signalen + narratief) uit
 * een context, met dezelfde cache-sleutel als de Filament-pagina. Zo delen de
 * CMS-pagina en de mobile-api dezelfde (AI-)berekening: één keer rekenen per
 * site/taal/periode, niet twee keer betalen.
 */
class SalesAnalysisPayload
{
    /**
     * @return array{sections: array<string, mixed>, signals: array<int, array<string, mixed>>, narrative: mixed, failed: mixed}
     */
    public static function build(AnalysisContext $context, bool $fresh = false): array
    {
        $cacheKey = 'sales-analysis:' . $context->siteId . ':' . app()->getLocale() . ':' . $context->period->cacheKey();

        if ($fresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addHour(), function () use ($context): array {
            $report = (new SalesAnalysisRunner())->run($context);

            return [
                'sections' => self::sectionsFrom($report),
                'signals' => array_map(fn (Signal $signal) => [
                    'severity' => $signal->severity,
                    'title' => $signal->title,
                    'explanation' => $signal->explanation,
                    'numbers' => $signal->numbers,
                    'url' => $signal->url,
                ], $report->signals()),
                'narrative' => SalesAnalysisNarrator::narrate($report, $context),
                'failed' => $report->failed,
            ];
        });
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    protected static function sectionsFrom(SalesAnalysisReport $report): array
    {
        $map = SalesAnalysisRegistry::map();
        $sections = [];

        foreach ($report->results as $key => $result) {
            $class = $map[$key] ?? null;
            if (! $class) {
                continue;
            }

            try {
                $group = $class::group();
            } catch (Throwable $e) {
                report($e);

                continue;
            }

            try {
                $label = $class::label();
            } catch (Throwable $e) {
                report($e);
                $label = $key;
            }

            $sections[$group][$key] = [
                'label' => $label,
                'facts' => $result->facts,
            ];
        }

        return $sections;
    }
}
