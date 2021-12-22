<?php

namespace Qubiqx\QcommerceCore\Filament\Resources\PageResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Qubiqx\QcommerceCore\Filament\Resources\PageResource;

class ListPages extends ListRecords
{
    use Translatable;

    protected static string $resource = PageResource::class;
}
