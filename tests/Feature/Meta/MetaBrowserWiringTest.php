<?php

use function Livewire\wrap;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Exceptions;
use Livewire\Features\SupportEvents\Event;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Jobs\SendMetaEventJob;
use Dashed\DashedEcommerceCore\Services\Meta\AddToCartPayload;
use Dashed\DashedEcommerceCore\Services\Meta\MetaBrowserEvents;

require_once __DIR__ . '/../../Support/MetaBrowserFixtures.php';

/** Een PHP-listener zoals AddedToCart en de CartPopup van een shop: model-binding op het product. */
class WiringProductListener
{
    public ?Product $ontvangen = null;

    public function productAddedToCart(Product $product): void
    {
        $this->ontvangen = $product;
    }
}

/**
 * Het rondje dat Livewire met een gedispatcht event maakt: de server zet de
 * parameters als JSON in het effect, de browser stuurt ze terug en
 * SupportEvents roept de listener aan met `wrap($component)->$method(...$params)`.
 */
function wiringOntvangenProduct(array $payload): ?Product
{
    $params = (new Event('productAddedToCart', [$payload]))->serialize()['params'];
    $detail = json_decode(json_encode($params), true);
    $listener = new WiringProductListener();

    wrap($listener)->productAddedToCart(...$detail);

    return $listener->ontvangen;
}

function bron(string $pad): string
{
    return file_get_contents(__DIR__ . '/../../../' . $pad);
}

beforeEach(function () {
    Http::preventStrayRequests();
    Bus::fake([SendMetaEventJob::class]);
});

it('bouwt in AddToCartPayload de velden van de pixel op, zonder Meta-velden als de Conversions API uit staat', function () {
    $product = metaProduct(12.95);

    $payload = AddToCartPayload::for($product, 1, 12.949, ['kleur' => 'groen']);

    expect(array_keys($payload))->toBe(['product', 'productName', 'quantity', 'price', 'options', 'cartTotal', 'category', 'tiktokItems'])
        ->and($payload['product']->is($product))->toBeTrue()
        ->and($payload['quantity'])->toBe(1)
        ->and($payload['price'])->toBe('12.95')
        ->and($payload['options'])->toBe(['kleur' => 'groen'])
        ->and($payload['tiktokItems'])->toBeArray();

    Bus::assertNotDispatched(SendMetaEventJob::class);
});

it('geeft in AddToCartPayload het event-ID en de waarde van het server-event mee', function () {
    metaCapiKlaar();
    metaBezoekerVerzoek();
    $product = metaProduct(12.95);

    $payload = AddToCartPayload::for($product, 2, 12.95);

    expect($payload['metaEventId'])->toMatch('/^atc_[0-9a-f\-]{36}$/')
        ->and($payload['metaValue'])->toBe('25.90');

    Bus::assertDispatched(SendMetaEventJob::class, fn (SendMetaEventJob $job) => $job->event['event_name'] === 'AddToCart'
        && $job->event['event_id'] === $payload['metaEventId']
        && $job->event['custom_data']['value'] === 25.9
        && $job->event['custom_data']['content_ids'] === [(string) $product->id]);
});

it('levert een listener met productAddedToCart(Product $product) het toegevoegde product, ook met Meta-velden in de payload', function () {
    // Livewire bindt de hele payload aan `Product $product` en zoekt het product op met de
    // EERSTE waarde uit die array. Staat het event-ID vooraan, dan zoekt hij op "atc_…".
    metaCapiKlaar();
    metaBezoekerVerzoek();
    metaProduct();
    $product = metaProduct();

    $payload = AddToCartPayload::for($product, 1, 12.95);

    expect($payload)->toHaveKey('metaEventId')
        ->and(array_key_first($payload))->toBe('product')
        ->and(array_key_last($payload))->toBe('metaEventId')
        ->and(wiringOntvangenProduct($payload)?->id)->toBe($product->id);
});

it('geeft in AddToCartPayload alleen het product terug als het opbouwen mislukt, en meldt de fout', function () {
    // Het product ligt dan al in de wagen: tracking mag het toevoegen niet alsnog laten mislukken.
    Exceptions::fake();
    $product = metaProduct(12.95);
    app()->bind(MetaBrowserEvents::class, fn () => new class () extends MetaBrowserEvents {
        public function addToCart(Product|int|string $product, int $quantity, float $unitPrice, ?string $name = null, ?string $category = null): array
        {
            throw new RuntimeException('Tracking is stuk');
        }
    });

    expect(AddToCartPayload::for($product, 1, 12.95))->toBe(['product' => $product]);
    Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'Tracking is stuk');
});

it('neemt ook een los product-id met naam en categorie aan, zoals een shop met een eigen payload het aanroept', function () {
    metaCapiKlaar();
    metaBezoekerVerzoek();
    $product = metaProduct(4.95);

    $result = app(MetaBrowserEvents::class)->addToCart($product->id, 1, 4.95, 'Eigen naam', 'Eigen categorie');

    expect(array_key_last($result))->toBe('metaEventId');
    Bus::assertDispatched(SendMetaEventJob::class, fn (SendMetaEventJob $job) => $job->event['custom_data']['value'] === 4.95
        && $job->event['custom_data']['content_ids'] === [(string) $product->id]
        && $job->event['custom_data']['content_name'] === 'Eigen naam'
        && $job->event['custom_data']['content_category'] === 'Eigen categorie');

    expect(app(MetaBrowserEvents::class)->addToCart(999999, 1, 4.95))->toBe([]);
});

it('stuurt elke toevoeging vanuit de shop via AddToCartPayload', function (string $pad) {
    $code = bron($pad);

    expect($code)->toContain("dispatch('productAddedToCart', AddToCartPayload::for(")
        ->and($code)->not->toContain("dispatch('productAddedToCart', product:")
        ->and($code)->not->toContain("dispatch('productAddedToCart', [");
})->with([
    'src/Livewire/Concerns/ProductCartActions.php',
    'src/Livewire/Concerns/CartActions.php',
    'src/Livewire/Frontend/Cart/CartSuggestions.php',
    'src/Livewire/Frontend/Wishlist/WishlistPage.php',
]);

it('meldt een toevoeging niet vanuit de wagenhelper zelf: kassa, API en een herstelde wagen sturen niets', function () {
    expect(bron('src/Classes/CartHelper.php'))->not->toContain('MetaBrowserEvents')
        ->and(bron('src/Classes/CartHelper.php'))->not->toContain('AddToCartPayload');
});

it('geeft in de checkout het event-ID mee vanuit mount() en zet in de viewProduct-payload geen event-ID', function () {
    $checkout = bron('src/Livewire/Frontend/Checkout/Checkout.php');
    $product = bron('src/Livewire/Concerns/ProductCartActions.php');

    expect($checkout)->toContain("dispatch('checkoutInitiated', array_merge(")
        ->and($checkout)->toContain('MetaBrowserEvents::class)->initiateCheckout(')
        ->and(substr_count($checkout, '->initiateCheckout('))->toBe(1)
        ->and($product)->toContain("dispatch('viewProduct', array_merge(")
        ->and($product)->toContain('MetaBrowserEvents::class)->viewContentPayload(');
});

it('geeft in de browser het event-ID door aan fbq en meldt ViewContent met een zelfgemaakt ID aan de server', function () {
    $site = metaCapiKlaar();

    $html = view('dashed-ecommerce-core::components.frontend.body-extend', [
        'trackingSettings' => ['facebook_pixel_conversion_id' => '599475696120398'],
    ])->render();

    expect($html)->toContain('metaViewContentUrl: "\/ecommerce\/meta\/view-content"')
        ->and($html)->toMatch("/fbq\('track', 'AddToCart', \{.*?\}, atcOptions\);/s")
        ->and($html)->toMatch("/fbq\('track', 'InitiateCheckout', \{.*?\}, checkoutOptions\);/s")
        ->and($html)->toMatch("/fbq\('track', 'ViewContent', \{.*?\}, viewContentEventId \? \{eventID: viewContentEventId\} : undefined\);/s")
        ->and($html)->toContain("'vc_' + random")
        ->and($html)->toContain('navigator.sendBeacon(tracking.metaViewContentUrl')
        // Niets wat per bezoeker verschilt: deze HTML mag uit de paginacache komen.
        ->and($html)->not->toMatch('/(atc|ic|vc)_[0-9a-f]{8}-/')
        ->and($html)->not->toContain('csrf');

    // Staat ViewContent uit, dan krijgt de browser geen adres en dus ook geen event-ID.
    Customsetting::set('meta_capi_event_view_content', false, $site);
    $html = view('dashed-ecommerce-core::components.frontend.body-extend', [
        'trackingSettings' => ['facebook_pixel_conversion_id' => '599475696120398'],
    ])->render();

    expect($html)->toContain('metaViewContentUrl: null');
});
