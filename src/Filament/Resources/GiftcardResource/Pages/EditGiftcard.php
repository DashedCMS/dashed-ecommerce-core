<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\GiftcardResource\Pages;

use Dashed\DashedEcommerceCore\Filament\Resources\GiftcardResource;
use Filament\Actions\ViewAction;
use Illuminate\Support\Str;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Dashed\DashedCore\Classes\Sites;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedEcommerceCore\Filament\Resources\DiscountCodeResource;

class EditGiftcard extends EditRecord
{
    protected static string $resource = GiftcardResource::class;

    protected function getActions(): array
    {
        return [
//            Action::make('Genereer een code')
//                ->button()
//                ->visible(fn (): bool => ! $this->data['is_global_discount'])
//                ->action('generateRandomCode'),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['site_ids'] = $data['site_ids'] ?? [Sites::getFirstSite()['id']];

        if ($data['discount_amount'] != $this->record->discount_amount) {
            $this->record->createLog(tag: 'giftcard.amount.changed.by.admin', oldAmount: $this->record->discount_amount, newAmount: $data['discount_amount']);
        }

        return $data;
    }

    public function generateRandomCode(): void
    {
        $this->data['code'] = Str::upper(Str::random(10));
    }
}
