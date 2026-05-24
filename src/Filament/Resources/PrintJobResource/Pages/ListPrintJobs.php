<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Filament\Resources\PrintJobResource\Pages;

use Dashed\DashedEcommerceCore\Filament\Resources\PrintJobResource;
use Filament\Resources\Pages\ListRecords;

class ListPrintJobs extends ListRecords
{
    protected static string $resource = PrintJobResource::class;
}
