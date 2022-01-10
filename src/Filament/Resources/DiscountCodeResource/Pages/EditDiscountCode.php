<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource\Pages;

use Illuminate\Support\Str;
use Qubiqx\QcommerceCore\Classes\Sites;
use Filament\Pages\Actions\ButtonAction;
use Filament\Resources\Pages\EditRecord;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource;

class EditDiscountCode extends EditRecord
{
    protected static string $resource = DiscountCodeResource::class;

    protected function getActions(): array
    {
        return array_merge(parent::getActions() ?: [], [
            ButtonAction::make('Genereer een code')
                ->action('generateRandomCode'),
        ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['site_ids'] = $data['site_ids'] ?? [Sites::getFirstSite()['id']];

        return $data;
    }

    public function generateRandomCode(): void
    {
        $this->data['code'] = Str::upper(Str::random(10));
    }
}
