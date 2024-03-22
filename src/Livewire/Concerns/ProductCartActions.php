<?php

namespace Dashed\DashedEcommerceCore\Livewire\Concerns;

use Livewire\WithFileUploads;
use Dashed\DashedCore\Classes\Sites;
use Filament\Notifications\Notification;
use Gloudemans\Shoppingcart\Facades\Cart;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Models\ProductExtraOption;

trait ProductCartActions
{
    use WithFileUploads;

    public Product $parentProduct;
    public Product $originalProduct;
    public ?Product $product = null;
    public $characteristics;
    public $suggestedProducts;
    public array $filters = [];
    public ?Collection $productExtras = null;
    public ?array $extras = [];
    public string|int $quantity = 1;
    public array $files = [];
    public string $cartType = 'default';
    public bool $allFiltersFilled = false;
    public $price = 0;
    public $discountPrice = 0;

    public ?string $name = '';
    public \Illuminate\Support\Collection $images;
    public ?string $description = '';
    public ?string $shortDescription = '';
    public ?string $sku = '';

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
        }

        $this->allFiltersFilled = true;
        foreach ($this->filters as &$filter) {
            if ($isMount) {
                $productFilterResult = $this->originalProduct->productFilters()->where('product_filter_id', $filter['id'])->first();
                if ($productFilterResult) {
                    $filter['active'] = $productFilterResult->pivot->product_filter_option_id ?? null;
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


        if ($this->product) {
            $this->characteristics = $this->product->showableCharacteristics();
            $this->suggestedProducts = $this->product->getSuggestedProducts();
            if (($this->product->id ?? 0) != ($previousProduct->id ?? 0) || ! $this->productExtras) {
                if (! $isMount && Customsetting::get('product_redirect_after_new_variation_selected', null, false)) {
                    return redirect($this->product->getUrl());
                }
                $this->productExtras = $this->product->allProductExtras();
                $this->extras = $this->product->allProductExtras()->toArray();
            }
        }

        $this->name = $this->product->name ?? $this->parentProduct->name;
        $this->images = $this->product->allImages ?? $this->parentProduct->allImages;
        $this->description = $this->product->description ?? $this->parentProduct->description;
        $this->shortDescription = $this->product->shortDescription ?? $this->parentProduct->shortDescription;
        $this->sku = $this->product->sku ?? $this->parentProduct->sku;
        $this->calculateCurrenctPrices();
        $this->dispatch('productUpdated');
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

            if ($productIsValid) {
                $this->product = $childProduct;

                return;
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
        foreach ($this->productExtras as $extraKey => $productExtra) {
            if ($productExtra->type == 'single') {
                $productValue = $this->extras[$extraKey]['value'] ?? null;

                if ($productValue) {
                    $productExtraOption = ProductExtraOption::find($productValue);
                    if ($productExtraOption->calculate_only_1_quantity) {
                        $productPrice += ($productExtraOption->price / $this->quantity);
                    } else {
                        $productPrice += $productExtraOption->price;
                    }
                }
            } elseif ($productExtra->type == 'checkbox') {
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
    }

    public function addToCart()
    {
        ShoppingCart::setInstance($this->cartType);

        if (! $this->product) {
            return $this->checkCart('danger', Translation::get('choose-a-product', $this->cartType, 'Please select a product'));
        }

        $cartUpdated = false;
        $productPrice = $this->product->currentPrice;
        $options = [];
        foreach ($this->productExtras as $extraKey => $productExtra) {
            if ($productExtra->type == 'single') {
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
                    } else {
                        $productPrice += $productExtraOption->price;
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
                    } else {
                        $productPrice += $productExtraOption->price;
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
                if ($productExtra->required && ! $productValue) {
                    return $this->checkCart('danger', Translation::get('file-upload-option-for-product-extra', 'products', 'Upload an file for option :optionName:', 'text', [
                        'optionName' => $productExtra->name,
                    ]));
                }

                $value = $productValue['value']->getClientOriginalName();
                $path = $productValue['value']->storeAs('dashed/product-extras', $value, 'dashed');
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
                        } else {
                            $productPrice = $productPrice + $option->price;
                        }
                    }
                }
            }
        }

        $cartItems = ShoppingCart::cartItems($this->cartType);
        foreach ($cartItems as $cartItem) {
            if ($cartItem->model && $cartItem->model->id == $this->product->id && $options == $cartItem->options) {
                $newQuantity = $cartItem->qty + $this->quantity;

                if ($this->product->limit_purchases_per_customer && $newQuantity > $this->product->limit_purchases_per_customer_limit) {
                    Cart::update($cartItem->rowId, $this->product->limit_purchases_per_customer_limit);

                    return $this->checkCart('danger', Translation::get('product-only-x-purchase-per-customer', $this->cartType, 'You can only purchase :quantity: of this product', 'text', [
                        'quantity' => $this->product->limit_purchases_per_customer_limit,
                    ]));
                }

                Cart::update($cartItem->rowId, $newQuantity);
                $cartUpdated = true;
            }
        }

        if (! $cartUpdated) {
            if ($this->product->limit_purchases_per_customer && $this->quantity > $this->product->limit_purchases_per_customer_limit) {
                Cart::add($this->product->id, $this->product->name, $this->product->limit_purchases_per_customer_limit, $productPrice, $options)->associate(Product::class);

                return $this->checkCart('danger', Translation::get('product-only-x-purchase-per-customer', $this->cartType, 'You can only purchase :quantity: of this product', 'text', [
                    'quantity' => $this->product->limit_purchases_per_customer_limit,
                ]));
            }

            Cart::add($this->product->id, $this->product->name, $this->quantity, $productPrice, $options)->associate(Product::class);
        }

        $this->quantity = 1;

        $redirectChoice = Customsetting::get('add_to_cart_redirect_to', Sites::getActive(), 'same');
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
}
