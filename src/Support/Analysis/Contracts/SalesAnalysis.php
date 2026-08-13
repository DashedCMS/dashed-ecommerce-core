<?php

namespace Dashed\DashedEcommerceCore\Support\Analysis\Contracts;

use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisResult;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisContext;

interface SalesAnalysis
{
    /** Unieke sleutel, ook de sleutel in het register. */
    public static function key(): string;

    /** Kop boven de sectie. Vertaalbaar. */
    public static function label(): string;

    /** verkoop | assortiment | inkoop | marketing | marge | retouren */
    public static function group(): string;

    /**
     * Kan deze analyse iets zinnigs zeggen op deze shop? Zo niet, dan
     * verdwijnt de sectie in plaats van leeg te blijven staan.
     */
    public static function isAvailable(AnalysisContext $context): bool;

    public function run(AnalysisContext $context): AnalysisResult;
}
