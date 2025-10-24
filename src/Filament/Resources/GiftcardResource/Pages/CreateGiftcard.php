<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\GiftcardResource\Pages;

use Dashed\DashedEcommerceCore\Filament\Resources\GiftcardResource;
use Illuminate\Support\Str;
use Filament\Actions\Action;
use Dashed\DashedCore\Classes\Sites;
use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Filament\Resources\DiscountCodeResource;

class CreateGiftcard extends CreateRecord
{
    protected static string $resource = GiftcardResource::class;

    protected function getActions(): array
    {
        return [
            Action::make('Genereer een code')
                ->button()
                ->action('generateRandomCode'),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['site_ids'] = $data['site_ids'] ?? [Sites::getFirstSite()['id']];
        $data['is_giftcard'] = true;

        return $data;
    }

    public function generateRandomCode(): void
    {
        $this->data['code'] = Str::upper(Str::random(10));

        if ($this->data['create_multiple_codes']) {
            $this->data['code'] .= '*****';
        }
    }

    public function afterCreate(): void
    {
        $code = $this->data['code'];
        if ($this->data['create_multiple_codes']) {
            $amountOfCodes = $this->data['amount_of_codes'];
            while ($amountOfCodes > 1) {
                $discountCode = new DiscountCode();
                $discountCode->is_giftcard = true;
                $discountCode->site_ids = $this->record->site_ids;
                $discountCode->name = $this->record->name;
                $discountCode->code = $code;
                $discountCode->minimal_requirements = $this->record->minimal_requirements;
                $discountCode->minimum_amount = $this->record->minimum_amount;
                $discountCode->minimum_products_count = $this->record->minimum_products_count;
                $discountCode->discount_amount = $this->record->discount_amount;
                $discountCode->valid_for = $this->record->valid_for;
                $discountCode->save();

                $selectedProductCategoriesIds = [];
                foreach ($this->record->productCategories as $category) {
                    $selectedProductCategoriesIds[] = $category['id'];
                }
                $discountCode->productCategories()->sync($selectedProductCategoriesIds);

                $selectedProductIds = [];
                foreach ($this->record->products as $product) {
                    $selectedProductIds[] = $product['id'];
                }
                $selectedProducts = Product::find($selectedProductIds);
                $discountCode->products()->sync($selectedProducts);

                $amountOfCodes--;
            }
        }
    }
}
