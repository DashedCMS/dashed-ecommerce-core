<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductResource\Pages;

use Illuminate\Support\Str;
use Qubiqx\QcommerceCore\Classes\Sites;
use Filament\Pages\Actions\ButtonAction;
use Filament\Resources\Pages\CreateRecord;
use Qubiqx\QcommerceEcommerceCore\Models\Product;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductResource;

class CreateProduct extends CreateRecord
{
    use Translatable;

    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);

        while (Product::where('slug->' . $this->activeFormLocale, $data['slug'])->count()) {
            $data['slug'] .= Str::random(1);
        }

        $data['site_ids'] = $data['site_ids'] ?? (isset($data['parent_id']) && $data['parent_id'] ? Product::find($data['parent_id'])->site_ids : [Sites::getFirstSite()['id']]);
        //        $content = $data['content'] ?? [];
        //        $data['content'] = null;
        //        $data['content'][$this->activeFormLocale] = $content;

        //        $images = $data['images'] ?? [];
        //        $data['images'] = null;
        //        $data['images'][$this->activeFormLocale] = $images;

        return $data;
    }

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
    //
    //        if ($this->data['create_multiple_codes']) {
    //            $this->data['code'] .= '*****';
    //        }
    //    }
}
