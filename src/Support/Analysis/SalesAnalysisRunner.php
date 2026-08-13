<?php

namespace Dashed\DashedEcommerceCore\Support\Analysis;

use Throwable;
use Dashed\DashedEcommerceCore\Support\Analysis\Contracts\SalesAnalysis;

class SalesAnalysisRunner
{
    public function run(AnalysisContext $context): SalesAnalysisReport
    {
        $results = [];
        $failed = [];

        foreach (SalesAnalysisRegistry::map() as $key => $class) {
            try {
                if (! $class::isAvailable($context)) {
                    continue;
                }

                /** @var SalesAnalysis $analysis */
                $analysis = app($class);
                $results[$key] = $analysis->run($context);
            } catch (Throwable $e) {
                // Eén kapotte query mag geen rapport van vijftien secties
                // omleggen. De sectie meldt zichzelf als mislukt, de rest
                // staat er gewoon.
                report($e);
                $failed[$key] = $this->labelFor($class, $key);
            }
        }

        return new SalesAnalysisReport($results, $failed);
    }

    /**
     * Ook label() kan gooien, en dan mag de melding over de mislukte analyse
     * niet zelf alsnog de pagina omleggen.
     *
     * @param  class-string<SalesAnalysis>  $class
     */
    protected function labelFor(string $class, string $key): string
    {
        try {
            return $class::label();
        } catch (Throwable $e) {
            return $key;
        }
    }
}
