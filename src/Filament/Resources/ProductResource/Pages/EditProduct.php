<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductResource\Pages;

use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Illuminate\Support\Str;
use Filament\Pages\Actions\ButtonAction;
use Filament\Resources\Pages\EditRecord;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductResource;

class EditProduct extends EditRecord
{
    use Translatable;

    protected static string $resource = ProductResource::class;

//    protected function getActions(): array
//    {
//        return array_merge(parent::getActions() ?: [], [
//            ButtonAction::make('Genereer een code')
//                ->action('generateRandomCode'),
//        ]);
//    }
//
//    public function generateRandomCode(): void
//    {
//        $this->data['code'] = Str::upper(Str::random(10));
//    }
}
