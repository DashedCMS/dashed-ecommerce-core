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
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Models\ProductExtraOption;

trait ProductCartActions
{
    use WithFileUploads;

    public Product $parentProduct;
    public Product $originalProduct;
    public ?Product $product = null;
    public $characteristics;
    public $suggestedProducts;
    public $crossSellProducts;
    public array $filters = [];
    public ?Collection $productTabs = null;
    public ?Collection $productExtras = null;
    public ?array $extras = [];
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

        ShoppingCart::removeInvalidItems($this->cartType);

        $this->dispatch('refreshCart');
    }

    public function setQuantity(int $quantity)
    {
        $this->quantity = $quantity;
        $this->updatedQuantity();
    }

    public function updatedQuantity()
    {
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
        if ($this->parentProduct->childProducts->count()) {
            $this->product = null;
        } else {
            $this->product = $this->parentProduct;
        }

        if ($isMount) {
            $this->filters = Customsetting::get('product_use_simple_variation_style', null, false) ? $this->parentProduct->simpleFilters() : $this->parentProduct->filters();
            $this->paymentMethods = PaymentMethod::active()->where('type', 'online')->orderBy('order', 'asc')->get();
        }

        $this->allFiltersFilled = true;
        foreach ($this->filters as &$filter) {
            if ($isMount) {
                $productFilterResult = $this->originalProduct->productFilters()->where('product_filter_id', $filter['id'])->first();
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

        if (! $this->product) {
            $this->findVariation();
            if (! $this->product) {
                $this->product = $this->originalProduct;
            }
        }

        if ($this->originalProduct->type == 'simple') {
            $this->variationExists = true;
        }

        $this->characteristics = $this->product->showableCharacteristics();
        $this->suggestedProducts = $this->product->getSuggestedProducts();
        $this->crossSellProducts = $this->product->getCrossSellProducts();
        $this->productTabs = $this->product->tabs;
        if (($this->product->id ?? 0) != ($previousProduct->id ?? 0) || ! $this->allProductExtras()) {
            if (! $isMount && Customsetting::get('product_redirect_after_new_variation_selected', null, false)) {
                return redirect($this->product->getUrl(forceOwnUrl: true));
            }
            $this->productExtras = $this->product->allProductExtras();
            $this->extras = $this->product->allProductExtras()->toArray();
        }

        $this->name = $this->product->name ?? $this->parentProduct->name;
        $this->images = ($this->product->images && is_array($this->product->images)) ? $this->product->images : (($this->parentProduct->images && is_array($this->parentProduct->images)) ? $this->parentProduct->images : []);
        foreach ($this->images as $image) {
            $this->originalImages[] = mediaHelper()->getSingleMedia($image, 'original')->url ?? '';
        }
        $this->description = (isset($this->product->description) && $this->product->description) ? tiptap_converter()->asHTML($this->product->description) : ((isset($this->parentProduct->description) && $this->parentProduct->description) ? $this->parentProduct->description : '');
        $this->shortDescription = $this->product->short_description ?? $this->parentProduct->short_description;
        $this->sku = $this->product->sku ?? $this->parentProduct->sku;
        $this->calculateCurrenctPrices();
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
    }

    public function findVariation(): void
    {
        foreach ($this->parentProduct->childProducts as $childProduct) {
            $productIsValid = true;
            foreach ($this->filters as $filter) {
                if (! $filter['active'] || ! $childProduct->productFilters()->where('product_filter_option_id', $filter['active'])->count()) {
                    $productIsValid = false;
                }
            }

            if (! $childProduct->status) {
                $productIsValid = false;
            }

            if ($productIsValid) {
                $this->variationExists = true;
                $this->product = $childProduct;

                return;
            } else {
                $this->variationExists = false;
            }
        }
    }

    public function calculateCurrenctPrices(): void
    {
        if (! $this->product || (! $this->product->parent_id && $this->product->type == 'variable')) {
            $this->price = null;
            $this->discountPrice = null;

            return;
        }

        $productPrice = $this->product->currentPrice;
        foreach ($this->allProductExtras() as $extraKey => $productExtra) {
            if ($productExtra->type == 'single' || $productExtra->type == 'imagePicker' || $productExtra->type == 'checkbox') {
                $productValue = $this->extras[$extraKey]['value'] ?? null;
                if ($productValue) {
                    $productExtraOption = ProductExtraOption::find($productValue);
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

        ShoppingCart::setInstance($this->cartType);

        if (! $product || ($product->type == 'variable' && ! $product->parent_id)) {
            return $this->checkCart('danger', Translation::get('choose-a-product', $this->cartType, 'Please select a product'));
        }

        $cartUpdated = false;
        $productPrice = $product->currentPrice;
        $discountedProductPrice = $product->discountPrice;
        $options = [];
        foreach ($product->allProductExtras() as $extraKey => $productExtra) {
            if ($productExtra->type == 'single' || $productExtra->type == 'imagePicker') {
                $productValue = $this->extras[$extraKey]['value'] ?? null;
                if ($productExtra->required && ! $productValue) {
                    return $this->checkCart('danger', Translation::get('select-option-for-product-extra', 'products', 'Select an option for :optionName:', 'text', [
                        'optionName' => $productExtra->name,
                    ]));
                }

                if ($productValue) {
                    $productExtraOption = ProductExtraOption::find($productValue);
                    $options[$productExtraOption->id] = [
                        'name' => $productExtra->name,
                        'value' => $productExtraOption->value,
                    ];
                    if ($productExtraOption->calculate_only_1_quantity) {
                        $productPrice += ($productExtraOption->price / $this->quantity);
                        $discountedProductPrice += ($productExtraOption->price / $this->quantity);
                    } else {
                        $productPrice += $productExtraOption->price;
                        $discountedProductPrice += $productExtraOption->price;
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
                    $productExtraOption = ProductExtraOption::find($productValue);
                    $options[$productExtraOption->id] = [
                        'name' => $productExtra->name,
                        'value' => $productExtraOption->value,
                    ];
                    if ($productExtraOption->calculate_only_1_quantity) {
                        $productPrice += ($productExtraOption->price / $this->quantity);
                        $discountedProductPrice += ($productExtraOption->price / $this->quantity);
                    } else {
                        $productPrice += $productExtraOption->price;
                        $discountedProductPrice += $productExtraOption->price;
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
                        $productExtraOption = ProductExtraOption::find($productValue);
                        if ($productExtraOption) {
                            $options[$productExtraOption->id] = [
                                'name' => $productExtra->name,
                                'value' => $productExtraOption->value,
                            ];
                            if ($productExtraOption->calculate_only_1_quantity) {
                                $productPrice += ($productExtraOption->price / $this->quantity);
                                $discountedProductPrice += ($productExtraOption->price / $this->quantity);
                            } else {
                                $productPrice += $productExtraOption->price;
                                $discountedProductPrice += $productExtraOption->price;
                            }
                        } else {
                            $customValues[] = $productValue;
                        }
                    }

                    if ($customValues) {
                        $options['custom-value-' . rand(0, 1000000)] = [
                            'name' => $productExtra->name,
                            'value' => $customValues,
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
                    $options['product-extra-input-' . $productExtra->id] = [
                        'name' => $productExtra->name,
                        'value' => $productValue,
                    ];
                }
            } elseif ($productExtra->type == 'file') {
                $productValue = $this->files[$productExtra->id] ?? null;
                if (! $productValue) {
                    $productValue = $this->extras[$extraKey]['value'] ?? null;
                }

                if ($productExtra->required && ! $productValue) {
                    return $this->checkCart('danger', Translation::get('file-upload-option-for-product-extra', 'products', 'Upload an file for option :optionName:', 'text', [
                        'optionName' => $productExtra->name,
                    ]));
                }

                if (Storage::disk('dashed')->exists($productValue)) {
                    $path = $productValue;
                    $value = str($path)->explode('/')->last();
                } else {
                    $value = Str::uuid() . '-' . $productValue['value']->getClientOriginalName();
                    $path = $productValue['value']->storeAs('dashed/product-extras', $value, 'dashed');
                }
                if ($value && $path) {
                    $options['product-extra-file-' . $productExtra->id] = [
                        'name' => $productExtra->name,
                        'value' => $value,
                        'path' => $path,
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
                        $options[$option->id] = [
                            'name' => $productExtra->name,
                            'value' => $option->value,
                        ];
                        if ($option->calculate_only_1_quantity) {
                            $productPrice = $productPrice + ($option->price / $this->quantity);
                            $discountedProductPrice = $productPrice + ($option->price / $this->quantity);
                        } else {
                            $productPrice = $productPrice + $option->price;
                            $discountedProductPrice = $productPrice + $option->price;
                        }
                    }
                }
            }
        }

        $attributes['discountPrice'] = $discountedProductPrice;
        $attributes['options'] = $options;

        $cartItems = ShoppingCart::cartItems($this->cartType);
        foreach ($cartItems as $cartItem) {
            if ($cartItem->model && $cartItem->model->id == $product->id && $attributes['options'] == $cartItem->options['options']) {
                $newQuantity = $cartItem->qty + $this->quantity;

                if ($product->limit_purchases_per_customer && $newQuantity > $product->limit_purchases_per_customer_limit) {
                    Cart::update($cartItem->rowId, $product->limit_purchases_per_customer_limit);

                    return $this->checkCart('danger', Translation::get('product-only-x-purchase-per-customer', $this->cartType, 'You can only purchase :quantity: of this product', 'text', [
                        'quantity' => $product->limit_purchases_per_customer_limit,
                    ]));
                }

                Cart::update($cartItem->rowId, $newQuantity);
                $cartUpdated = true;
            }
        }

        if (! $cartUpdated) {
            if ($product->limit_purchases_per_customer && $this->quantity > $product->limit_purchases_per_customer_limit) {
                Cart::add($product->id, $product->name, $product->limit_purchases_per_customer_limit, $productPrice, $attributes)
                    ->associate(Product::class);

                return $this->checkCart('danger', Translation::get('product-only-x-purchase-per-customer', $this->cartType, 'You can only purchase :quantity: of this product', 'text', [
                    'quantity' => $product->limit_purchases_per_customer_limit,
                ]));
            }

            Cart::add($product->id, $product->name, $this->quantity, $productPrice, $attributes)
                ->associate(Product::class);
        }

        $this->quantity = 1;

        $redirectChoice = Customsetting::get('add_to_cart_redirect_to', Sites::getActive(), 'same');

        $this->dispatch('productAddedToCart');

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
