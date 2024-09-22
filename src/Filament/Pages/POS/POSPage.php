<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\POS;

use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;

class POSPage extends page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Point of Sale';
    protected static ?string $navigationGroup = 'E-commerce';
    protected static ?string $title = 'Point of Sale';
    protected static ?string $slug = 'point-of-sale';
    protected static ?int $navigationSort = 3;
    protected ?string $maxContentWidth = 'full';

    protected static string $view = 'dashed-ecommerce-core::pos.pages.point-of-sale';

    public Collection $products;
    public $subTotal = 0;
    public $discount = 0;
    public $vat = 0;
    public $total = 0;
    public $totalUnformatted = 0;

    public $user_id;
    public $marketing;
    public $password;
    public $password_confirmation;
    public $first_name;
    public $last_name;
    public $email;
    public $phone_number;
    public $date_of_birth;
    public $gender;
    public $street;
    public $house_nr;
    public $zip_code;
    public $city;
    public $country;
    public $company_name;
    public $btw_id;
    public $invoice_street;
    public $invoice_house_nr;
    public $invoice_zip_code;
    public $invoice_city;
    public $invoice_country;
    public $note;
    public $discount_code;
    public ?string $activeDiscountCode = '';
    public $orderProducts = [];
    public $shipping_method_id;
    public $payment_method_id;
    public $activatedProducts = [];

    public function mount(): void
    {
        $this->products = $this->getAllProducts();
        ShoppingCart::setInstance('point-of-sale');
    }

    public function getAllProducts()
    {
        $products = Product::handOrderShowable()->with(['childProducts', 'parent'])->get();

        foreach ($products as &$product) {
            $product['stock'] = $product->stock();
            $product['price'] = CurrencyHelper::formatPrice($product->price);
            $product['productExtras'] = $product->allProductExtras();
        }

        return $products;
    }

    public function updated()
    {
    }
}
