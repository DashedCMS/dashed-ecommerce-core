<?php

namespace Dashed\DashedEcommerceCore\Livewire\Concerns;

use Illuminate\Support\Str;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Gloudemans\Shoppingcart\Facades\Cart;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Classes\Products;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Classes\TikTokHelper;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Illuminate\Support\Collection as SupportCollection;
use Dashed\DashedEcommerceCore\Models\EcommerceActionLog;

trait ProductCartActions
{
    use WithFileUploads;

    public ProductGroup $productGroup;
    public ?Product $originalProduct = null;
    public ?Product $product = null;
    public $characteristics;
    public $suggestedProducts;
    public $crossSellProducts;
    public $recentlyViewedProducts;
    public array $filters = [];
    public null|Collection|SupportCollection $productTabs = null;
    public null|Collection|SupportCollection $productExtras = null;
    public null|Collection|SupportCollection $productFaqs = null;
    public ?array $extras = [];
    public ?array $hiddenOptions = [];
    public string|int $quantity = 1;
    public array $files = [];
    public string $cartType = 'default';
    public bool $allFiltersFilled = false;
    public bool $variationExists = false;

    public ?string $name = '';
    public array $images = [];
    public array $originalImages = [];
    public ?string $description = '';
    public ?string $shortDescription = '';
    public ?string $sku = '';
    public $price = 0;
    public $discountPrice = 0;
    public $paymentMethods = [];
    public $breadcrumbs = [];
    public $productCategories = [];
    public $contentBlocks = [];
    public $content = [];
    public null|array|Collection $volumeDiscounts = null;
    public null|array|Collection $globalDiscounts = null;

    /**
     * In-memory cache van public varianten onder de productgroep.
     * LET OP: hier laden we alleen lichte modellen (id's + prijsvelden), geen zware relaties.
     */
    public ?Collection $publicProducts = null;

    protected function getPublicProducts(): Collection
    {
        if ($this->publicProducts !== null) {
            return $this->publicProducts;
        }

        // Alleen de nodige kolommen + geen default $with eager loads
        // Belangrijk: prijsvelden meenemen zodat we geen extra Product queries nodig hebben.
        $products = $this->productGroup
            ->products()
            ->publicShowable()
//            ->select([
//                'id',
//                'product_group_id',
//                'price',
//                'discount_price',
//                // Voeg hier extra kolommen toe als jouw currentPrice accessor ze nodig heeft.
//                // bv: 'tax_rate', 'price_includes_tax', 'currency', etc.
//            ])
            ->setEagerLoads([]) // overschrijft Product::$with (productFilters etc.)
            ->get();

        // KeyBy zodat lookups O(1) worden
        $this->publicProducts = $products->keyBy('id');

        return $this->publicProducts;
    }

    /**
     * Bouwt de filter-structuur op basis van de in-memory varianten.
     * Dit wordt alleen bij mount aangeroepen.
     */
    protected function buildFiltersFromPublicProducts(): array
    {
        $filters = [];
        $publicProducts = $this->getPublicProducts();

        if ($publicProducts->isEmpty()) {
            return [];
        }

        $productIds = $publicProducts->keys()->all();
        $activeFilters = $this->productGroup->activeProductFilters()->with(['productFilterOptions'])->get();

        foreach ($activeFilters as $filter) {
            if (! $filter->pivot->use_for_variations) {
                continue;
            }

            // Welke filter-opties komen daadwerkelijk voor in deze productgroep?
            $productFilterOptionIds = DB::table('dashed__product_filter')
                ->where('product_filter_id', $filter->id)
                ->whereIn('product_id', $productIds)
                ->pluck('product_filter_option_id')
                ->toArray();

            if (! count($productFilterOptionIds)) {
                continue;
            }

            $enabledProductFilterOptions = DB::table('dashed__product_enabled_filter_options')
                ->where('product_filter_id', $filter->id)
                ->where('product_group_id', $this->productGroup->id)
                ->pluck('product_filter_option_id')
                ->toArray();

            if (count($enabledProductFilterOptions)) {
                $productFilterOptionIds = array_intersect($productFilterOptionIds, $enabledProductFilterOptions);
            }

            $filterOptions = $filter->productFilterOptions()
                ->whereIn('id', $productFilterOptionIds)
                ->get()
                ->map(function ($option) {
                    $name = $option->name;
                    $arr = $option->toArray();
                    $arr['name'] = $name;
                    $arr['originalName'] = $name;

                    return $arr;
                })
                ->toArray();

            if (! count($filterOptions)) {
                continue;
            }

            $filters[] = [
                'id' => $filter->id,
                'name' => $filter->name,
                'options' => $filterOptions,
                'type' => $filter->type,
                'active' => null,
                'contentBlocks' => $filter->contentBlocks,
            ];
        }

        return $filters;
    }

    public function checkCart(?string $status = null, ?string $message = null)
    {
        if ($status) {
            if ($status === 'error') {
                $status = 'danger';
            }

            Notification::make()
                ->{$status}()
                ->title($message)
                ->send();
        }

        cartHelper()->removeInvalidItems();

        $this->dispatch('refreshCart');
    }

    public function setQuantity(int $quantity)
    {
        $this->quantity = $quantity;
        $this->updatedQuantity();
    }

    public function addSpecificQuantity(int $quantity)
    {
        $this->quantity = $quantity;
        $this->updatedQuantity();
        $this->addToCart();
    }

    public function updatedQuantity()
    {
        if (! $this->product) {
            $this->quantity = 1;
            Notification::make()
                ->danger()
                ->title(Translation::get('choose-a-product', $this->cartType, 'Selecteer eerst een product'))
                ->send();

            return;
        }

        if (! $this->quantity) {
            $this->quantity = 1;
        } elseif ($this->quantity < 1) {
            $this->quantity = 1;
        } elseif ($this->quantity > $this->product->stock()) {
            $this->quantity = $this->product->stock();
        }
    }

    public function checkFilters()
    {
        foreach ($this->filters as $filter) {
            if ($filter['active'] && $filter['defaultActive'] != $filter['active']) {
                foreach ($filter['values'] as $filterValue) {
                    if ($filterValue['value'] == $filter['active']) {
                        return redirect($filterValue['url']);
                    }
                }
            }
        }
    }

    public function fillInformation($isMount = false)
    {
        $previousProduct = $this->product;

        if ($isMount) {
            // 1x alle varianten (licht) binnenhalen
            $this->getPublicProducts();

            if (! $this->product) {
                $defaultProduct = $this->originalProduct
                    ?? $this->productGroup->firstSelectedProduct
                    ?? $this->getPublicProducts()->first();

                if ($defaultProduct) {
                    $this->product = Product::with([
                        'productFilters',
                        'productCategories',
                        'volumeDiscounts',
                        'productExtras.productExtraOptions',
                    ])->find($defaultProduct->id);

                    $this->originalProduct = $this->product;
                }
            }

            $this->productCategories = $this->productGroup->productCategories;
            $this->filters = $this->buildFiltersFromPublicProducts();

            static $onlinePaymentMethods = null;
            if ($onlinePaymentMethods === null) {
                $onlinePaymentMethods = PaymentMethod::active()
                    ->where('type', 'online')
                    ->orderBy('order', 'asc')
                    ->get();
            }
            $this->paymentMethods = $onlinePaymentMethods;

            $this->recentlyViewedProducts = Products::getRecentlyViewed(
                limit: 4,
                productGroup: $this->productGroup
            );
        } else {
            $publicProducts = $this->getPublicProducts();

            if ($publicProducts->count() > 1) {
                $this->product = null;
            } else {
                $this->product = $this->originalProduct;
            }
        }

        // Filters vullen op basis van huidig product (bij mount dus: startproduct)
        $this->fillFilters($isMount);

        // Alleen bij NIET-mount automatisch variant zoeken op basis van filters
        if (! $isMount && ! $this->product) {
            $this->findVariation();
        }

        // Verrijk filter-opties met variant prijs + diff (via publicProducts)
        $this->enrichFilterOptionPrices();

        if ($this->getPublicProducts()->count() === 1) {
            $this->variationExists = true;
        }

        // Product- en productgroep-specific characteristics combineren
        $characteristics = $this->product ? $this->product->showableCharacteristics() : [];
        foreach ($this->product ? $this->productGroup->showableCharacteristicsWithoutFilters() : $this->productGroup->showableCharacteristics() as $characteristic) {
            if (collect($characteristics)->where('name', $characteristic['name'])->count() > 0) {
                $characteristics[collect($characteristics)->where('name', $characteristic['name'])->keys()[0]]['value'] = $characteristic['value'];
            } else {
                $characteristics[] = $characteristic;
            }
        }
        $this->characteristics = $characteristics;

        $this->suggestedProducts = $this->product
            ? $this->product->getSuggestedProducts(includeFromProductGroup: true)
            : $this->productGroup->suggestedProducts;

        $this->crossSellProducts = $this->product
            ? $this->product->getCrossSellProducts(includeFromProductGroup: true)
            : $this->productGroup->crossSellProducts;

        $this->productTabs = $this->product
            ? $this->product->allProductTabs()
            : $this->productGroup->allProductTabs();

        $this->productFaqs = $this->product
            ? $this->product->allProductFaqs()
            : $this->productGroup->allProductFaqs();

        if (($this->product->id ?? 0) != ($previousProduct->id ?? 0) || ! $this->productExtras) {
            if (! $isMount && Customsetting::get('product_redirect_after_new_variation_selected', null, false) && $this->product) {
                return redirect($this->product->getUrl(forceOwnUrl: true));
            }

            $extrasCollection = $this->product?->allProductExtras() ?? collect();
            $this->productExtras = $extrasCollection;
            $this->extras = $extrasCollection->toArray();
        }

        $this->name = $this->product->name ?? $this->productGroup->name;
        $this->images = $this->product ? $this->product->imagesToShow : $this->productGroup->imagesToShow;
        $this->originalImages = $this->product ? $this->product->originalImagesToShow : $this->productGroup->originalImagesToShow;
        $this->description = ($this->product && $this->product->description && strip_tags(cms()->convertToHtml($this->product->description)))
            ? $this->product->replaceContentVariables($this->product->description, $this->filters)
            : $this->productGroup->replaceContentVariables($this->productGroup->description, $this->filters, $this->product);
        $this->shortDescription = ($this->product && $this->product->short_description)
            ? $this->product->replaceContentVariables($this->product->short_description, $this->filters)
            : $this->productGroup->replaceContentVariables($this->productGroup->short_description, $this->filters, $this->product);
        $this->sku = $this->product->sku ?? '';
        $this->content = $this->product ? $this->product->content : $this->productGroup->content;
        $this->contentBlocks = $this->product ? $this->product->contentBlocks : $this->productGroup->contentBlocks;

        if ($this->product) {
            if (! count($this->content ?: [])) {
                $this->content = $this->productGroup->content;
            }
            foreach ((array)$this->productGroup->contentBlocks as $block => $contentBlock) {
                if (! isset($this->contentBlocks[$block]) || ! $this->contentBlocks[$block]) {
                    $this->contentBlocks[$block] = $contentBlock;
                }
            }
        }

        static $allGlobalDiscounts = null;
        if ($allGlobalDiscounts === null) {
            $allGlobalDiscounts = DiscountCode::isGlobalDiscount()->get();
        }

        $globalDiscounts = [];
        if ($this->product) {
            foreach ($allGlobalDiscounts as $discountCode) {
                if ($discountCode->isValidForProduct($this->product)) {
                    $globalDiscounts[] = $discountCode;
                }
            }
        }
        $this->globalDiscounts = $globalDiscounts;

        $this->calculateCurrentPrices();

        $this->volumeDiscounts = $this->product ? $this->product->volumeDiscounts : null;
        if ($this->volumeDiscounts) {
            $this->volumeDiscounts = $this->volumeDiscounts
                ->map(function ($volumeDiscount) {
                    $volumeDiscount->discount = $volumeDiscount->getDiscountedPrice($this->price, true);
                    $volumeDiscount->price = $volumeDiscount->getPrice($this->price, true);
                    $volumeDiscount->discountString = $volumeDiscount->getDiscountString($this->price);

                    return $volumeDiscount;
                })
                ->toArray();
        }

        if (! $isMount) {
            $this->dispatch('productUpdated', [
                'extras' => $this->extras,
                'name' => $this->name,
                'images' => $this->images,
                'originalImages' => $this->originalImages,
                'description' => $this->description,
                'shortDescription' => $this->shortDescription,
                'sku' => $this->sku,
                'price' => $this->price,
                'discountPrice' => $this->discountPrice,
            ]);
        }

        if ($this->product) {
            $cartTotal = cartHelper()->getTotal();

            $this->dispatch('viewProduct', [
                'product' => $this->product,
                'productName' => $this->product->name,
                'quantity' => $this->quantity,
                'price' => number_format($this->price, 2, '.', ''),
                'cartTotal' => number_format($cartTotal, 2, '.', ''),
                'category' => $this->product->productCategories->first()?->name ?? null,
                'tiktokItems' => TikTokHelper::getShoppingCartItems($cartTotal),
            ]);
        }
    }

    public function findVariation(): void
    {
        $activeFilters = [];

        foreach ($this->filters as $filter) {
            if (! $filter['active']) {
                $this->variationExists = false;
                $this->product = null;

                return;
            }

            $activeFilters[] = $filter['id'] . '-' . $filter['active'];
        }

        sort($activeFilters);
        $key = implode('|', $activeFilters);

        $rawIndex = $this->productGroup->variation_index ?? [];
        if (is_string($rawIndex)) {
            $index = json_decode($rawIndex, true) ?: [];
        } elseif (is_array($rawIndex)) {
            $index = $rawIndex;
        } else {
            $index = [];
        }

        $productId = $index[$key] ?? null;

        if (! $productId) {
            $publicProducts = $this->getPublicProducts();
            $productIds = $publicProducts->keys()->all();

            if (! empty($productIds)) {
                $matchingIds = $productIds;

                foreach ($this->filters as $filter) {
                    $idsForFilter = DB::table('dashed__product_filter')
                        ->where('product_filter_id', $filter['id'])
                        ->where('product_filter_option_id', $filter['active'])
                        ->whereIn('product_id', $productIds)
                        ->pluck('product_id')
                        ->toArray();

                    $matchingIds = array_values(array_intersect($matchingIds, $idsForFilter));

                    if (empty($matchingIds)) {
                        break;
                    }
                }

                if (! empty($matchingIds)) {
                    $productId = $matchingIds[0];
                }
            }
        }

        if (! $productId && Customsetting::get('fill_with_first_product_if_product_group_loaded', null, false)) {
            $fallbackProduct = $this->productGroup->firstSelectedProduct ?: $this->getPublicProducts()->first();

            if ($fallbackProduct) {
                $this->product = Product::with([
                    'productFilters',
                    'productCategories',
                    'volumeDiscounts',
                    'productExtras.productExtraOptions',
                    'suggestedProducts',
                    'crossSellProducts',
                    'tabs',
                    'globalTabs',
                    'ownTabs',
                    'faqs',
                    'globalFaqs',
                    'ownFaqs',
                ])->find($fallbackProduct->id);

                $this->variationExists = (bool)$this->product;

                if ($this->variationExists) {
                    $this->fillFilters(true);
                }

                return;
            }
        }

        if (! $productId) {
            $this->variationExists = false;
            $this->product = null;

            return;
        }

        $this->variationExists = true;

        $this->product = Product::with([
            'productFilters',
            'productCategories',
            'volumeDiscounts',
            'productExtras.productExtraOptions',
            'suggestedProducts',
            'crossSellProducts',
            'tabs',
            'globalTabs',
            'ownTabs',
            'faqs',
            'globalFaqs',
            'ownFaqs',
        ])->find($productId);
    }

    public function fillFilters(bool $isMount = false): void
    {
        $this->allFiltersFilled = true;
        $filters = $this->filters;

        foreach ($filters as &$filter) {
            if ($isMount && $this->product) {
                $productFilterResult = $this->product->productFilters
                    ->first(function ($pf) use ($filter) {
                        return $pf->pivot->product_filter_id == $filter['id'];
                    });

                if ($productFilterResult) {
                    $filter['active'] = $productFilterResult->pivot->product_filter_option_id ?? null;
                } elseif (count($filter['options'] ?? []) === 1) {
                    $filter['active'] = $filter['options'][0]['id'];
                }
            }

            if ($filter['active'] === null) {
                $this->allFiltersFilled = false;
            }
        }

        $this->filters = $filters;
    }

    public function calculateCurrentPrices(): void
    {
        if (! $this->product) {
            $this->price = null;
            $this->discountPrice = null;

            return;
        }

        $productPrice = $this->product->currentPrice;

        if (! $this->productExtras) {
            $this->productExtras = $this->product->allProductExtras();
        }

        foreach ($this->productExtras as $extraKey => $productExtra) {
            if ((($this->extras[$extraKey]['value'] ?? false) || ($this->files[$productExtra->id] ?? false)) && $productExtra->price) {
                $productPrice += $productExtra->price;
            }

            if ($productExtra->type == 'single' || $productExtra->type == 'imagePicker') {
                $productValue = $this->extras[$extraKey]['value'] ?? null;

                if ($productValue) {
                    if ($productValue === true) {
                        $productValue = $this->extras[$extraKey]['id'] ?? null;
                    }

                    if ($productValue) {
                        $productExtraOption = $productExtra->productExtraOptions
                            ->firstWhere('id', $productValue);

                        if ($productExtraOption) {
                            if ($productExtraOption->calculate_only_1_quantity) {
                                $productPrice += ($productExtraOption->price / $this->quantity);
                            } else {
                                $productPrice += $productExtraOption->price;
                            }
                        }
                    }
                }
            } elseif ($productExtra->type == 'checkbox') {
                $productValue = $this->extras[$extraKey]['value'] ?? null;

                if ($productValue) {
                    $optionId = $this->extras[$extraKey]['product_extra_options'][0]['id'] ?? null;

                    if ($optionId) {
                        $productExtraOption = $productExtra->productExtraOptions
                            ->firstWhere('id', $optionId);

                        if ($productExtraOption) {
                            if ($productExtraOption->calculate_only_1_quantity) {
                                $productPrice += ($productExtraOption->price / $this->quantity);
                            } else {
                                $productPrice += $productExtraOption->price;
                            }
                        }
                    }
                }
            } else {
                foreach ($productExtra->productExtraOptions as $option) {
                    $productOptionValue = $option['value'] ?? null;
                    if ($productOptionValue) {
                        if ($option->calculate_only_1_quantity) {
                            $productPrice = $productPrice + ($option->price / $this->quantity);
                        } else {
                            $productPrice = $productPrice + $option->price;
                        }
                    }
                }
            }
        }

        $this->price = $productPrice;
        $this->discountPrice = $this->product->discountPrice;
    }

    public function addToCart(?int $productId = null)
    {
        if ($productId) {
            $product = Product::find($productId);
        } else {
            $product = $this->product;
        }

        cartHelper()->setCartType($this->cartType);

        if (! $product) {
            return $this->checkCart('danger', Translation::get('choose-a-product', $this->cartType, 'Please select a product'));
        }

        $cartUpdated = false;
        $productPrice = $product->currentPrice;
        $discountedProductPrice = $product->discountPrice;
        $options = [];

        $productExtras = $product->allProductExtras();

        foreach ($productExtras as $extraKey => $productExtra) {
            $productExtraPrice = 0;

            if ((($this->extras[$extraKey]['value'] ?? false) || ($this->files[$productExtra->id] ?? false)) && $productExtra->price) {
                $productPrice += $productExtra->price;
                $productExtraPrice += $productExtra->price;
                $discountedProductPrice += $productExtra->price;
            }

            if ($productExtra->type == 'single' || $productExtra->type == 'imagePicker') {
                $productValue = $this->extras[$extraKey]['value'] ?? null;

                if ($productExtra->required && ! $productValue) {
                    return $this->checkCart('danger', Translation::get('select-option-for-product-extra', 'products', 'Select an option for :optionName:', 'text', [
                        'optionName' => $productExtra->name,
                    ]));
                }

                if ($productValue) {
                    if ($productValue === true) {
                        $productValue = $this->extras[$extraKey]['id'] ?? null;
                    }

                    if ($productValue) {
                        $productExtraOption = $productExtra->productExtraOptions
                            ->firstWhere('id', $productValue);

                        if ($productExtraOption) {
                            if ($productExtraOption->calculate_only_1_quantity) {
                                $productPrice += ($productExtraOption->price / $this->quantity);
                                $productExtraPrice += ($productExtraOption->price / $this->quantity);
                                $discountedProductPrice += ($productExtraOption->price / $this->quantity);
                            } else {
                                $productPrice += $productExtraOption->price;
                                $productExtraPrice += $productExtraOption->price;
                                $discountedProductPrice += $productExtraOption->price;
                            }

                            $options[$productExtraOption->id] = [
                                'name' => $productExtra->name,
                                'value' => $productExtraOption->value,
                                'price' => $productExtraPrice,
                            ];
                        }
                    }
                }
            } elseif ($productExtra->type == 'checkbox') {
                $productValue = $this->extras[$extraKey]['value'] ?? null;

                if ($productExtra->required && ! $productValue) {
                    return $this->checkCart('danger', Translation::get('select-checkbox-for-product-extra', 'products', 'Select the checkbox for :optionName:', 'text', [
                        'optionName' => $productExtra->name,
                    ]));
                }

                if ($productValue) {
                    $optionId = $this->extras[$extraKey]['product_extra_options'][0]['id'] ?? null;

                    if ($optionId) {
                        $productExtraOption = $productExtra->productExtraOptions
                            ->firstWhere('id', $optionId);

                        if ($productExtraOption) {
                            if ($productExtraOption->calculate_only_1_quantity) {
                                $productPrice += ($productExtraOption->price / $this->quantity);
                                $productExtraPrice += ($productExtraOption->price / $this->quantity);
                                $discountedProductPrice += ($productExtraOption->price / $this->quantity);
                            } else {
                                $productPrice += $productExtraOption->price;
                                $productExtraPrice += $productExtraOption->price;
                                $discountedProductPrice += $productExtraOption->price;
                            }

                            $options[$productExtraOption->id] = [
                                'name' => $productExtra->name,
                                'value' => $productExtraOption->value,
                                'price' => $productExtraPrice,
                            ];
                        }
                    }
                }
            } elseif ($productExtra->type == 'multiple') {
                $productValues = $this->extras[$extraKey]['value'] ?? null;

                if ($productExtra->required && ! $productValues) {
                    return $this->checkCart('danger', Translation::get('select-multiple-for-product-extra', 'products', 'Select at least 1 option for :optionName:', 'text', [
                        'optionName' => $productExtra->name,
                    ]));
                }

                if ($productValues) {
                    $customValues = [];

                    foreach ($productValues as $productValue) {
                        $productExtraOption = $productExtra->productExtraOptions
                            ->firstWhere('id', $productValue);

                        if ($productExtraOption) {
                            if ($productExtraOption->calculate_only_1_quantity) {
                                $productPrice += ($productExtraOption->price / $this->quantity);
                                $productExtraPrice += ($productExtraOption->price / $this->quantity);
                                $discountedProductPrice += ($productExtraOption->price / $this->quantity);
                            } else {
                                $productPrice += $productExtraOption->price;
                                $productExtraPrice += $productExtraOption->price;
                                $discountedProductPrice += $productExtraOption->price;
                            }

                            $options[$productExtraOption->id] = [
                                'name' => $productExtra->name,
                                'value' => $productExtraOption->value,
                                'price' => $productExtraPrice,
                            ];
                        } else {
                            $customValues[] = $productValue;
                        }
                    }

                    if ($customValues) {
                        $options['custom-value-' . rand(0, 1000000)] = [
                            'name' => $productExtra->name,
                            'value' => $customValues,
                            'price' => $productExtraPrice,
                        ];
                    }
                }
            } elseif ($productExtra->type == 'input') {
                $productValue = $this->extras[$extraKey]['value'] ?? null;

                if ($productExtra->required && ! $productValue) {
                    return $this->checkCart('danger', Translation::get('fill-option-for-product-extra', 'products', 'Fill the input field for :optionName:', 'text', [
                        'optionName' => $productExtra->name,
                    ]));
                }

                if ($productValue) {
                    if ($productValue === true) {
                        $productValue = $this->extras[$extraKey]['id'] ?? null;
                    }

                    $options['product-extra-input-' . $productExtra->id] = [
                        'name' => $productExtra->name,
                        'value' => $productValue,
                        'price' => $productExtraPrice,
                    ];
                }
            } elseif ($productExtra->type == 'file') {
                $productValue = $this->files[$productExtra->id] ?? null;

                if (! $productValue) {
                    $productValue = $this->extras[$extraKey]['value'] ?? null;
                }

                if ($productExtra->required && ! ($productValue['value'] ?? null)) {
                    return $this->checkCart('danger', Translation::get('file-upload-option-for-product-extra', 'products', 'Upload an file for option :optionName:', 'text', [
                        'optionName' => $productExtra->name,
                    ]));
                }

                if ($productValue['value'] ?? false) {
                    if (Storage::disk('dashed')->exists($productValue['value'])) {
                        $path = $productValue['value'];
                        $value = str($path)->explode('/')->last();
                    } else {
                        $value = Str::uuid() . '-' . $productValue['value']->getClientOriginalName();
                        $path = $productValue['value']->storeAs('dashed/product-extras', $value, 'dashed');
                    }
                }

                if (($value ?? false) && ($path ?? false)) {
                    $options['product-extra-file-' . $productExtra->id] = [
                        'name' => $productExtra->name,
                        'value' => $value,
                        'path' => $path,
                        'price' => $productExtraPrice,
                    ];
                }
            } else {
                foreach ($productExtra->productExtraOptions as $option) {
                    $productOptionValue = $option['value'] ?? null;

                    if ($productExtra->required && ! $productOptionValue) {
                        return $this->checkCart('danger', Translation::get('select-multiple-options-for-product-extra', 'products', 'Select one or more options for :optionName:', 'text', [
                            'optionName' => $productExtra->name,
                        ]));
                    }

                    if ($productOptionValue) {
                        if ($option->calculate_only_1_quantity) {
                            $productPrice = $productPrice + ($option->price / $this->quantity);
                            $productExtraPrice = $productPrice + ($option->price / $this->quantity);
                            $discountedProductPrice = $productPrice + ($option->price / $this->quantity);
                        } else {
                            $productPrice = $productPrice + $option->price;
                            $productExtraPrice = $productPrice + $option->price;
                            $discountedProductPrice = $productPrice + $option->price;
                        }

                        $options[$option->id] = [
                            'name' => $productExtra->name,
                            'value' => $option->value,
                            'price' => $productExtraPrice,
                        ];
                    }
                }
            }
        }

        $attributes['discountPrice'] = $discountedProductPrice;
        $attributes['originalPrice'] = $productPrice;
        $attributes['options'] = $options;
        $attributes['hiddenOptions'] = $this->hiddenOptions;

        if (! $productPrice) {
            Notification::make()
                ->danger()
                ->title(Translation::get('product-price-zero', $this->cartType, 'De prijs mag niet op 0 staan, neem contact met ons op om de bestelling af te ronden'))
                ->send();

            return;
        }

        $cartItems = cartHelper()->getCartItems();
        foreach ($cartItems as $cartItem) {
            if (
                $cartItem->model
                && $cartItem->model->id == $product->id
                && $attributes['options'] == $cartItem->options['options']
                && $attributes['hiddenOptions'] == $cartItem->options['hiddenOptions']
            ) {
                $newQuantity = $cartItem->qty + $this->quantity;

                if ($product->limit_purchases_per_customer && $newQuantity > $product->limit_purchases_per_customer_limit) {
                    Cart::update($cartItem->rowId, $product->limit_purchases_per_customer_limit);
                    EcommerceActionLog::createLog('add_to_cart', $product->limit_purchases_per_customer_limit - $cartItem->qty, productId: $product->id);

                    return $this->checkCart('danger', Translation::get('product-only-x-purchase-per-customer', $this->cartType, 'You can only purchase :quantity: of this product', 'text', [
                        'quantity' => $product->limit_purchases_per_customer_limit,
                    ]));
                }

                EcommerceActionLog::createLog('add_to_cart', $newQuantity - $cartItem->qty, productId: $product->id);
                Cart::update($cartItem->rowId, $newQuantity);
                $cartUpdated = true;
            }
        }

        if (! $cartUpdated) {
            if ($product->limit_purchases_per_customer && $this->quantity > $product->limit_purchases_per_customer_limit) {
                Cart::add($product->id, $product->name, $product->limit_purchases_per_customer_limit, $productPrice, $attributes)
                    ->associate(Product::class);
                EcommerceActionLog::createLog('add_to_cart', $product->limit_purchases_per_customer_limit, productId: $product->id);

                return $this->checkCart('danger', Translation::get('product-only-x-purchase-per-customer', $this->cartType, 'You can only purchase :quantity: of this product', 'text', [
                    'quantity' => $product->limit_purchases_per_customer_limit,
                ]));
            }

            EcommerceActionLog::createLog('add_to_cart', $this->quantity, productId: $product->id);
            Cart::add($product->id, $product->name, $this->quantity, $productPrice, $attributes)
                ->associate(Product::class);
        }

        $quantity = $this->quantity;
        $this->quantity = 1;
        $this->hiddenOptions = [];

        $redirectChoice = Customsetting::get('add_to_cart_redirect_to', Sites::getActive(), 'same');

        $cartTotal = cartHelper()->getTotal();

        $this->dispatch('productAddedToCart', [
            'product' => $product,
            'productName' => $product->name,
            'quantity' => $quantity,
            'price' => number_format($productPrice, 2, '.', ''),
            'options' => $options,
            'cartTotal' => number_format($cartTotal, 2, '.', ''),
            'category' => $product->productCategories->first()?->name ?? null,
            'tiktokItems' => TikTokHelper::getShoppingCartItems($cartTotal),
        ]);

        session(['lastAddedProductInCart' => $product]);

        if ($redirectChoice == 'same') {
            return $this->checkCart('success', Translation::get('product-added-to-cart', $this->cartType, 'The product has been added to your cart'));
        } elseif ($redirectChoice == 'cart') {
            $this->checkCart();

            Notification::make()
                ->success()
                ->title(Translation::get('product-added-to-cart', $this->cartType, 'The product has been added to your cart'))
                ->send();

            return redirect(ShoppingCart::getCartUrl());
        } elseif ($redirectChoice == 'checkout') {
            $this->checkCart();

            Notification::make()
                ->success()
                ->title(Translation::get('product-added-to-cart', $this->cartType, 'The product has been added to your cart'))
                ->send();

            return redirect(ShoppingCart::getCheckoutUrl());
        }
    }

    public function setProductExtraValue($extraKey, $value)
    {
        $this->extras[$extraKey]['value'] = $value;
        $this->fillInformation();
    }

    public function setFilterValue($filterKey, $value)
    {
        $this->filters[$filterKey]['active'] = $value;
        $this->fillInformation();
    }

    public function setProductExtraCustomValue($attributes)
    {
        $extraId = $attributes['extraId'] ?? null;
        $value = $attributes['value'] ?? null;

        if (! $extraId) {
            return;
        }

        $extraKey = null;

        foreach ($this->extras as $extraPossibleKey => $extra) {
            if (($extra['id'] ?? null) == $extraId) {
                $extraKey = $extraPossibleKey;

                break;
            }
        }

        if ($extraKey === null) {
            return;
        }

        $this->extras[$extraKey]['value'] = $value;
        $this->fillInformation();
    }

    /**
     * Verrijk filter opties met:
     * - variantProductId
     * - variantPrice / variantDiscountPrice
     * - priceDiff / discountPriceDiff (t.o.v. huidige variant)
     *
     * Gebruikt alleen $this->publicProducts (geen extra Product queries).
     */
    protected function enrichFilterOptionPrices(): void
    {
        if (! $this->filters || $this->getPublicProducts()->isEmpty()) {
            return;
        }

        $publicProducts = $this->getPublicProducts(); // keyBy id
        $publicProductIds = $publicProducts->keys()->all();

        $currentBasePrice = $this->product?->currentPrice;
        $currentDiscountPrice = $this->product?->discountPrice;

        $rawIndex = $this->productGroup->variation_index ?? [];
        if (is_string($rawIndex)) {
            $index = json_decode($rawIndex, true) ?: [];
        } elseif (is_array($rawIndex)) {
            $index = $rawIndex;
        } else {
            $index = [];
        }

        $variationFilters = collect($this->filters)->map(fn ($f) => [
            'id' => $f['id'],
            'active' => $f['active'] ?? null,
        ])->values();

        $filters = $this->filters;
        foreach ($filters as $filterKey => $filter) {
            foreach (($filter['options'] ?? []) as $optionKey => $option) {

                // Defaults
                $filters[$filterKey]['options'][$optionKey]['variantProductId'] = null;
                $filters[$filterKey]['options'][$optionKey]['variantPrice'] = null;
                $filters[$filterKey]['options'][$optionKey]['variantDiscountPrice'] = null;
                $filters[$filterKey]['options'][$optionKey]['priceDiff'] = null;
                $filters[$filterKey]['options'][$optionKey]['discountPriceDiff'] = null;
                $filters[$filterKey]['options'][$optionKey]['priceDiffType'] = 'plus';
                $filters[$filterKey]['options'][$optionKey]['discountPriceDiffType'] = 'plus';

                // Bouw selection key alsof user deze optie kiest
                $selection = [];

                foreach ($variationFilters as $vf) {
                    $fid = $vf['id'];

                    $oid = ($fid == $filter['id'])
                        ? ($option['id'] ?? null)
                        : ($vf['active'] ?? null);

                    if (! $oid) {
                        $selection = [];

                        break;
                    }

                    $selection[] = $fid . '-' . $oid;
                }

                if (! $selection) {
                    continue;
                }

                sort($selection);
                $key = implode('|', $selection);

                $productId = $index[$key] ?? null;

                if (! $productId) {
                    $productId = $this->lookupVariationProductIdFromDb($selection, $publicProductIds);
                }

                if (! $productId) {
                    continue;
                }

                $variant = $publicProducts->get((int)$productId);

                if (! $variant) {
                    continue;
                }

                $variantPrice = $variant->currentPrice;
                $variantDiscountPrice = $variant->discountPrice;

                $filters[$filterKey]['options'][$optionKey]['variantProductId'] = (int)$productId;
                $filters[$filterKey]['options'][$optionKey]['variantPrice'] = $variantPrice;
                $filters[$filterKey]['options'][$optionKey]['variantDiscountPrice'] = $variantDiscountPrice;

                if ($currentBasePrice !== null && $variantPrice !== null) {
                    $filters[$filterKey]['options'][$optionKey]['priceDiff'] = $variantPrice - $currentBasePrice;
                    if ($filters[$filterKey]['options'][$optionKey]['priceDiff'] > 0) {
                        $filters[$filterKey]['options'][$optionKey]['priceDiffType'] = 'plus';
                    } elseif ($filters[$filterKey]['options'][$optionKey]['priceDiff'] < 0) {
                        $filters[$filterKey]['options'][$optionKey]['priceDiffType'] = 'min';
                        $filters[$filterKey]['options'][$optionKey]['priceDiff'] = 0 - $filters[$filterKey]['options'][$optionKey]['priceDiff'];
                    }
                }

                if ($currentDiscountPrice !== null && $variantDiscountPrice !== null) {
                    $filters[$filterKey]['options'][$optionKey]['discountPriceDiff'] = $variantDiscountPrice - $currentDiscountPrice;
                    if ($filters[$filterKey]['options'][$optionKey]['discountPriceDiff'] > 0) {
                        $filters[$filterKey]['options'][$optionKey]['discountPriceDiffType'] = 'plus';
                    } elseif ($filters[$filterKey]['options'][$optionKey]['discountPriceDiff'] < 0) {
                        $filters[$filterKey]['options'][$optionKey]['discountPriceDiffType'] = 'min';
                        $filters[$filterKey]['options'][$optionKey]['discountPriceDiff'] = 0 - $filters[$filterKey]['options'][$optionKey]['discountPriceDiff'];
                    }
                }

                if ($filters[$filterKey]['options'][$optionKey]['priceDiff']) {
                    $filters[$filterKey]['options'][$optionKey]['name'] = Translation::get('product-filter-option-name', 'products', ':optionName: (:type: :priceDiff:)', 'text', [
                        'optionName' => $filters[$filterKey]['options'][$optionKey]['originalName'],
                        'type' => $filters[$filterKey]['options'][$optionKey]['priceDiffType'] == 'plus' ? '+' : '-',
                        'priceDiff' => CurrencyHelper::formatPrice($filters[$filterKey]['options'][$optionKey]['priceDiff']),
                    ]);
                }

                if($filters[$filterKey]['options'][$optionKey]['id'] == ($filter['active'] ?? null)){
                    $filters[$filterKey]['options'][$optionKey]['name'] = $filters[$filterKey]['options'][$optionKey]['originalName'];
                }
            }
        }

        foreach($this->filters as $filter){
            foreach (($filter['options'] ?? []) as $optionKey => $option) {
                if($filter['active'] && ($option['id'] == $filter['active']) && isset($filters[$filterKey]['options'][$optionKey])){
                    $filters[$filterKey]['options'][$optionKey]['name'] =
                        $filters[$filterKey]['options'][$optionKey]['originalName'];
                }
            }
        }

        $this->filters = $filters;
    }

    /**
     * DB fallback: vind de product_id die EXACT matcht op alle (filterId-optionId) pairs.
     *
     * @param array $selection e.g. ['12-55','13-80']
     * @param array $productIds public product ids binnen de groep
     */
    protected function lookupVariationProductIdFromDb(array $selection, array $productIds): ?int
    {
        if (! count($selection) || ! count($productIds)) {
            return null;
        }

        $pairs = array_map(function ($item) {
            [$fid, $oid] = explode('-', $item);

            return [(int)$fid, (int)$oid];
        }, $selection);

        $query = DB::table('dashed__product_filter')
            ->select('product_id')
            ->whereIn('product_id', $productIds)
            ->where(function ($q) use ($pairs) {
                foreach ($pairs as [$fid, $oid]) {
                    $q->orWhere(function ($q2) use ($fid, $oid) {
                        $q2->where('product_filter_id', $fid)
                            ->where('product_filter_option_id', $oid);
                    });
                }
            })
            ->groupBy('product_id')
            ->havingRaw('COUNT(DISTINCT product_filter_id) = ?', [count($pairs)])
            ->limit(1);

        $productId = $query->value('product_id');

        return $productId ? (int)$productId : null;
    }
}
