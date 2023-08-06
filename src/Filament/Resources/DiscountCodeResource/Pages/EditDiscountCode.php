<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\DiscountCodeResource\Pages;

use Illuminate\Support\Str;
use Filament\Pages\Actions\Action;
use Dashed\DashedCore\Classes\Sites;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedEcommerceCore\Filament\Resources\DiscountCodeResource;

class EditDiscountCode extends EditRecord
{
    protected static string $resource = DiscountCodeResource::class;

    protected function getActions(): array
    {
        return array_merge(parent::getActions() ?: [], [
            Action::make('Genereer een code')
                ->button()
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
