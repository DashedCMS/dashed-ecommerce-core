<?php

namespace Dashed\DashedEcommerceCore\Services\Meta;

use Throwable;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Classes\TikTokHelper;

/**
 * De payload van het Livewire-event `productAddedToCart`, en daarmee het ene
 * punt waar een toevoeging vanuit de shop een AddToCart wordt: voor de pixel
 * (browser) en voor de Conversions API (server), met hetzelfde event-ID.
 * Toevoegingen die dit event niet sturen (kassa, API, Bol, een herstelde wagen,
 * automatische toevoegingen) melden dus ook niets aan Meta.
 */
class AddToCartPayload
{
    /**
     * @param  array<int|string, mixed>  $options
     * @return array<string, mixed>
     */
    public static function for(Product $product, int $quantity, float $unitPrice, array $options = []): array
    {
        try {
            $cartTotal = cartHelper()->getTotal();

            // Het product staat vooraan en de Meta-velden achteraan: een PHP-listener met
            // `productAddedToCart(Product $product)` krijgt van Livewire deze hele array
            // en zoekt het product op met de eerste waarde erin.
            return array_merge([
                'product' => $product,
                'productName' => $product->name,
                'quantity' => $quantity,
                'price' => number_format($unitPrice, 2, '.', ''),
                'options' => $options,
                'cartTotal' => number_format($cartTotal, 2, '.', ''),
                'category' => $product->productCategories->first()?->name ?? null,
                'tiktokItems' => TikTokHelper::getShoppingCartItems($cartTotal),
            ], app(MetaBrowserEvents::class)->addToCart($product, $quantity, $unitPrice));
        } catch (Throwable $e) {
            // Het product ligt al in de wagen: tracking mag dat niet alsnog laten
            // mislukken. De PHP-listeners hebben genoeg aan het product.
            report($e);

            return ['product' => $product];
        }
    }
}
