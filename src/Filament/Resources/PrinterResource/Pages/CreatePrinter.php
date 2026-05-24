<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Filament\Resources\PrinterResource\Pages;

use Dashed\DashedEcommerceCore\Filament\Resources\PrinterResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePrinter extends CreateRecord
{
    protected static string $resource = PrinterResource::class;
}
