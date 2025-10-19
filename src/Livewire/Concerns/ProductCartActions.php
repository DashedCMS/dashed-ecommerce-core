<?php

namespace Dashed\DashedEcommerceCore\Livewire\Concerns;

use Illuminate\Support\Str;
use Livewire\WithFileUploads;
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
use Dashed\DashedEcommerceCore\Models\EcommerceActionLog;
use Dashed\DashedEcommerceCore\Models\ProductExtraOption;

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
    public ?Collection $productTabs = null;
    public ?Collection $productExtras = null;
    public ?Collection $productFaqs = null;
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

    public function checkCart(?string $status = null, ?string $message = null)
    {
        if ($status) {
            if ($status == 'error') {
                $status = 'danger';
            }

            Notification::make()
                ->$status()
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
            $this->productCategories = $this->productGroup->productCategories;
            $this->filters = $this->productGroup->simpleFilters();
            $this->paymentMethods = PaymentMethod::active()->where('type', 'online')->orderBy('order', 'asc')->get();
            $this->recentlyViewedProducts = Products::getRecentlyViewed(limit: 4, productGroup: $this->productGroup);
        } else {
            if ($this->productGroup->products()->publicShowable()->count() > 1) {
                $this->product = null;
            } else {
                $this->product = $this->originalProduct;
            }
        }

        $this->fillFilters($isMount);

        if (! $this->product) {
            $this->findVariation();
        }

        if ($this->productGroup->products()->publicShowable()->count() == 1) {
            $this->variationExists = true;
        }

        $characteristics = $this->product ? $this->product->showableCharacteristics() : [];
        foreach ($this->product ? $this->productGroup->showableCharacteristicsWithoutFilters() : $this->productGroup->showableCharacteristics() as $characteristic) {
            if (collect($characteristics)->where('name', $characteristic['name'])->count() > 0) {
                $characteristics[collect($characteristics)->where('name', $characteristic['name'])->keys()[0]]['value'] = $characteristic['value'];
            } else {
                $characteristics[] = $characteristic;
            }
        }
        $this->characteristics = $characteristics;
        $this->suggestedProducts = $this->product ? $this->product->getSuggestedProducts(includeFromProductGroup: true) : $this->productGroup->suggestedProducts;
        $this->crossSellProducts = $this->product ? $this->product->getCrossSellProducts(includeFromProductGroup: true) : $this->productGroup->crossSellProducts;
        $this->productTabs = $this->product ? $this->product->allProductTabs() : $this->productGroup->allProductTabs();
        $this->productFaqs = $this->product ? $this->product->allProductFaqs() : $this->productGroup->allProductFaqs();

        if (($this->product->id ?? 0) != ($previousProduct->id ?? 0) || ! $this->productExtras) {
            if (! $isMount && Customsetting::get('product_redirect_after_new_variation_selected', null, false) && $this->product) {
                return redirect($this->product->getUrl(forceOwnUrl: true));
            }
            $this->productExtras = $this->product?->allProductExtras();
            $this->extras = $this->product?->allProductExtras()->toArray();
        }

        $this->name = $this->product->name ?? $this->productGroup->name;
        $this->images = $this->product ? $this->product->imagesToShow : $this->productGroup->imagesToShow;
        $this->originalImages = $this->product ? $this->product->originalImagesToShow : $this->productGroup->originalImagesToShow;
        $this->description = ($this->product && $this->product->description) ? $this->product->replaceContentVariables($this->product->description, $this->filters) : $this->productGroup->replaceContentVariables($this->productGroup->description, $this->filters, $this->product);
        $this->shortDescription = ($this->product && $this->product->short_description) ? $this->product->replaceContentVariables($this->product->short_description, $this->filters) : $this->productGroup->replaceContentVariables($this->productGroup->short_description, $this->filters, $this->product);
        $this->sku = $this->product->sku ?? '';
        $this->breadcrumbs = $this->product ? $this->product->breadcrumbs() : $this->productGroup->breadcrumbs();
        $this->content = $this->product ? $this->product->content : $this->productGroup->content;
        $this->contentBlocks = $this->product ? $this->product->contentBlocks : $this->productGroup->contentBlocks;
        if ($this->product) {
            if (! count($this->content ?: [])) {
                $this->content = $this->productGroup->content;
            }
            foreach ($this->productGroup->contentBlocks as $block => $contentBlock) {
                if (! isset($this->contentBlocks[$block]) || ! $this->contentBlocks[$block]) {
                    $this->contentBlocks[$block] = $contentBlock;
                }
            }
        }

        $globalDiscounts = [];

        foreach (DiscountCode::isGlobalDiscount()->get() as $discountCode) {
            if ($discountCode->isValidForProduct($this->product)) {
                $globalDiscounts[] = $discountCode;
            }
        }

        $this->globalDiscounts = $globalDiscounts;

        $this->calculateCurrentPrices();

        $this->volumeDiscounts = $this->product ? $this->product->volumeDiscounts : null;
        if ($this->volumeDiscounts) {
            $this->volumeDiscounts = $this->volumeDiscounts->map(function ($volumeDiscount) {
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
        foreach ($this->productGroup->products()->publicShowable()->get() as $product) {
            $productIsValid = true;
            foreach ($this->filters as $filter) {
                if (! $filter['active'] || ! $product->productFilters()->where('product_filter_option_id', $filter['active'])->count()) {
                    $productIsValid = false;
                }
            }

            if (! $product->status) {
                $productIsValid = false;
            }

            if ($productIsValid) {
                $this->variationExists = true;
                $this->product = $product;

                return;
            } else {
                $this->variationExists = false;
            }
        }

        if (! $this->variationExists && $this->productGroup->products->count() && Customsetting::get('fill_with_first_product_if_product_group_loaded', null, false)) {
            if ($this->productGroup->firstSelectedProduct) {
                $this->product = $this->productGroup->firstSelectedProduct;
            } else {
                $this->product = $this->productGroup->products()->publicShowable()->first();
            }
            $this->variationExists = true;
            $this->fillFilters(true);
        }
    }

    public function fillFilters(bool $isMount = false): void
    {
        $this->allFiltersFilled = true;
        $filters = $this->filters;
        foreach ($filters as &$filter) {
            if ($isMount && $this->product) {
                $productFilterResult = $this->product->productFilters()->where('product_filter_id', $filter['id'])->first();
                if ($productFilterResult) {
                    $filter['active'] = $productFilterResult->pivot->product_filter_option_id ?? null;
                } elseif (count($filter['options'] ?? []) === 1) {
                    $filter['active'] = $filter['options'][0]['id'];
                }
            }

            if ($filter['active'] == null) {
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
        foreach ($this->productExtras as $extraKey => $productExtra) {
            if ((($this->extras[$extraKey]['value'] ?? false) || ($this->files[$productExtra->id] ?? false)) && $productExtra->price) {
                $productPrice += $productExtra->price;
            }

            if ($productExtra->type == 'single' || $productExtra->type == 'imagePicker') {
                $productValue = $this->extras[$extraKey]['value'] ?? null;
                if ($productValue) {
                    if ($productValue === true) {
                        $productValue = $this->extras[$extraKey]['id'];
                    }

                    $productExtraOption = ProductExtraOption::find($productValue);
                    if ($productExtraOption->calculate_only_1_quantity) {
                        $productPrice += ($productExtraOption->price / $this->quantity);
                    } else {
                        $productPrice += $productExtraOption->price;
                    }
                }
            } elseif ($productExtra->type == 'checkbox') {
                //As long as this only can have 1 option, this will work
                $productValue = $this->extras[$extraKey]['value'] ?? null;
                if ($productValue) {
                    $productExtraOption = ProductExtraOption::find($this->extras[$extraKey]['product_extra_options'][0]['id'] ?? null);
                    if ($productExtraOption->calculate_only_1_quantity) {
                        $productPrice += ($productExtraOption->price / $this->quantity);
                    } else {
                        $productPrice += $productExtraOption->price;
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
        foreach ($product->allProductExtras() as $extraKey => $productExtra) {
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
                        $productValue = $this->extras[$extraKey]['id'];
                    }

                    $productExtraOption = ProductExtraOption::find($productValue);
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
            } elseif ($productExtra->type == 'checkbox') {
                //As long as this only can have 1 option, this will work
                $productValue = $this->extras[$extraKey]['value'] ?? null;
                if ($productExtra->required && ! $productValue) {
                    return $this->checkCart('danger', Translation::get('select-checkbox-for-product-extra', 'products', 'Select the checkbox for :optionName:', 'text', [
                        'optionName' => $productExtra->name,
                    ]));
                }

                if ($productValue) {
                    $productExtraOption = ProductExtraOption::find($this->extras[$extraKey]['product_extra_options'][0]['id'] ?? null);
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
                        $productExtraOption = ProductExtraOption::find($productValue);
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
                        $productValue = $this->extras[$extraKey]['id'];
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
                    //                    $productOptionValue = $request['product-extra-' . $productExtra->id . '-' . $option->id];
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

        if (!$productPrice) {
            Notification::make()
                ->danger()
                ->title(Translation::get('product-price-zero', $this->cartType, 'De prijs mag niet op 0 staan, neem contact met ons op om de bestelling af te ronden'))
                ->send();
            return;
        }

        $cartItems = cartHelper()->getCartItems();
        foreach ($cartItems as $cartItem) {
            if ($cartItem->model && $cartItem->model->id == $product->id && $attributes['options'] == $cartItem->options['options'] && $attributes['hiddenOptions'] == $cartItem->options['hiddenOptions']) {
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
        $extraId = $attributes['extraId'];
        $value = $attributes['value'];

        foreach ($this->extras as $extraPossibleKey => $extra) {
            if ($extra['id'] == $extraId) {
                $extraKey = $extraPossibleKey;
            }
        }

        if (! $extraKey) {
            return;
        }

        $this->extras[$extraKey]['value'] = $value;
        $this->fillInformation();
    }
}
