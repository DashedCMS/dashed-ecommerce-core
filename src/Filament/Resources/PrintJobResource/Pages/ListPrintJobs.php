<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Filament\Resources\PrintJobResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Dashed\DashedEcommerceCore\Filament\Resources\PrintJobResource;

class ListPrintJobs extends ListRecords
{
    protected static string $resource = PrintJobResource::class;
}
