<?php

use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Support\Facades\RateLimiter;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Services\Meta\MetaCapiSettings;
use Dashed\DashedEcommerceCore\Services\Meta\MetaBrowserEvents;

if (! function_exists('metaCapiKlaar')) {
    /** Zet de Conversions API aan met pixel-ID en token, en begint met een lege cache en rem. */
    function metaCapiKlaar(): string
    {
        $site = Sites::getActive();
        Customsetting::set('meta_capi_enabled', true, $site);
        Customsetting::set('facebook_pixel_conversion_id', '599475696120398', $site);
        MetaCapiSettings::for($site)->storeAccessToken('EAAB-geheim');
        Cache::flush();
        RateLimiter::clear(MetaBrowserEvents::rateKey('203.0.113.7'));

        return $site;
    }
}

if (! function_exists('metaBezoekerVerzoek')) {
    /** Een verzoek zoals een Livewire-update van een bezoeker op een shoppagina. */
    function metaBezoekerVerzoek(array $server = [], string $uri = 'https://shop.test/livewire/update', string $method = 'POST'): Request
    {
        $request = Request::create($uri, $method, [], [], [], array_merge([
            'HTTP_X_LIVEWIRE' => '1',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Safari/605.1.15',
            'HTTP_REFERER' => 'https://shop.test/vazen/bluma?utm_source=ig&email=klant%40voorbeeld.nl',
            'REMOTE_ADDR' => '203.0.113.7',
        ], $server));
        $session = new Store('meta-browser', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);
        app()->instance('request', $request);

        return $request;
    }
}

if (! function_exists('metaProduct')) {
    function metaProduct(float $prijs = 12.95, array $attributes = []): Product
    {
        return Product::withoutEvents(fn () => Product::create(array_merge([
            'name' => ['en' => 'Bluma vaas'],
            'slug' => ['en' => 'bluma-vaas-' . uniqid()],
            'site_ids' => ['default'],
            'public' => true,
            'use_stock' => false,
            'stock_status' => 'in_stock',
            'price' => $prijs,
            'current_price' => $prijs,
            'vat_rate' => 21,
        ], $attributes)))->fresh();
    }
}
