<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\DiscountCodeResource\Pages;

use Illuminate\Support\Str;
use Filament\Pages\Actions\Action;
use Dashed\DashedCore\Classes\Sites;
use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Classes\ProductCategories;
use Dashed\DashedEcommerceCore\Filament\Resources\DiscountCodeResource;

class CreateDiscountCode extends CreateRecord
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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['site_ids'] = $data['site_ids'] ?? [Sites::getFirstSite()['id']];

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
            $amountOfCodes = $this->data['amount_of_codes'] - 1;
            while ($amountOfCodes > 1) {
                $discountCode = new DiscountCode();
                $discountCode->site_ids = $this->record->site_ids;
                $discountCode->name = $this->record->name;
                $discountCode->code = $code;
                $discountCode->limit_use_per_customer = $this->record->limit_use_per_customer;
                $discountCode->use_stock = $this->record->use_stock;
                $discountCode->stock = $this->record->getRawOriginal('stock');
                $discountCode->minimal_requirements = $this->record->minimal_requirements;
                $discountCode->minimum_amount = $this->record->minimum_amount;
                $discountCode->minimum_products_count = $this->record->minimum_products_count;
                $discountCode->type = $this->record->type;
                $discountCode->discount_percentage = $this->record->discount_percentage;
                $discountCode->discount_amount = $this->record->discount_amount;
                $discountCode->valid_for = $this->record->valid_for;
                $discountCode->valid_for_customers = $this->record->valid_for_customers ?: 'all';
                $discountCode->valid_customers = $this->record->valid_customers ?: [];
                $discountCode->start_date = $this->record->start_date;
                $discountCode->end_date = $this->record->end_date;
                $discountCode->save();

                $selectedProductCategoriesIds = [];
                foreach ($this->record->productCategories as $category) {
                    $selectedProductCategoriesIds[] = $category['id'];
                }
                //                $selectedProductCategories = ProductCategories::getFromIdsWithParents($selectedProductCategoriesIds);
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
