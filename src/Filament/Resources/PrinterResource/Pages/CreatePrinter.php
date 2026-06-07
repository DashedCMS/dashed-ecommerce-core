<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Filament\Resources\PrinterResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedEcommerceCore\Filament\Resources\PrinterResource;

class CreatePrinter extends CreateRecord
{
    protected static string $resource = PrinterResource::class;
}
