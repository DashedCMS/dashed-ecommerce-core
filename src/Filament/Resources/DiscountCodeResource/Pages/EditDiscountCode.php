<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\DiscountCodeResource\Pages;

use Illuminate\Support\Str;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Filament\Resources\DiscountCodeResource;

class EditDiscountCode extends EditRecord
{
    protected static string $resource = DiscountCodeResource::class;

    /** @var array<int, int> */
    protected array $productGroupIdsBeforeSave = [];

    protected function getActions(): array
    {
        return [
            Action::make('Genereer een code')
                ->button()
                ->visible(fn (): bool => ! $this->data['is_global_discount'])
                ->action('generateRandomCode'),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['site_ids'] = $data['site_ids'] ?? [Sites::getFirstSite()['id']];

        return $data;
    }

    // Filament schrijft de koppelingen hierna en vóór de update van de rij, dus
    // dit is het laatste moment waarop de oude selectie nog te lezen is.
    protected function beforeSave(): void
    {
        $this->productGroupIdsBeforeSave = $this->getRecord()->productGroupIdsForPrices();
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DiscountCode::withoutPriceUpdatesOnSave(fn () => parent::handleRecordUpdate($record, $data));
    }

    protected function afterSave(): void
    {
        $this->getRecord()->updateProductPrices($this->productGroupIdsBeforeSave);
    }

    public function generateRandomCode(): void
    {
        $this->data['code'] = Str::upper(Str::random(10));
    }
}
