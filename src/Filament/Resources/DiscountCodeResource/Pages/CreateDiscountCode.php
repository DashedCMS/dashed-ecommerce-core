<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource\Pages;

use Filament\Pages\Actions\ButtonAction;
use Filament\Resources\Pages\CreateRecord;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource;

class CreateDiscountCode extends CreateRecord
{
    protected static string $resource = DiscountCodeResource::class;

    protected function getActions(): array
    {
        return array_merge(parent::getActions() ?: [], [
            ButtonAction::make('Genereer een code')
                ->action('generateRandomCode'),
        ]);
    }

    public function generateRandomCode(): void
    {
//        dd($this->form->get());
        return;
        $this->fillForm([
            'code' => 'asdf',
        ]);
//        dd($this->fillForm([
//            'code' => 'asdf'
//        ]));
        $this->form->fill();
//        dd();
    }
}
