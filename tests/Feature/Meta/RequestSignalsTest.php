<?php

use Illuminate\Http\Request;
use Dashed\DashedEcommerceCore\Services\Meta\RequestSignals;
use Dashed\DashedEcommerceCore\Http\Middleware\CaptureMetaClickId;

require_once __DIR__ . '/../../Support/MetaBrowserFixtures.php';

it('laat alleen cookies in het formaat van Meta door', function () {
    expect(RequestSignals::fbp(['_fbp' => 'fb.1.1700000000000.123']))->toBe('fb.1.1700000000000.123')
        ->and(RequestSignals::fbp(['_fbp' => '<script>']))->toBeNull()
        ->and(RequestSignals::fbp(['_fbp' => ['array']]))->toBeNull()
        ->and(RequestSignals::fbp([]))->toBeNull();
});

it('neemt de fbc uit de cookie, anders uit de sessie, anders uit de fbclid in de pagina-URL', function () {
    $request = metaBezoekerVerzoek(['HTTP_REFERER' => 'https://shop.test/vazen?fbclid=AbC123']);

    expect(RequestSignals::fbc($request, ['_fbc' => 'fb.1.1700000000000.cookie']))->toBe('fb.1.1700000000000.cookie')
        ->and(RequestSignals::fbc($request, []))->toMatch('/^fb\.1\.\d+\.AbC123$/');

    $request->session()->put(CaptureMetaClickId::SESSION_KEY, 'fb.1.1700000000000.sessie');
    expect(RequestSignals::fbc($request, []))->toBe('fb.1.1700000000000.sessie');

    expect(RequestSignals::fbc(metaBezoekerVerzoek(), []))->toBeNull();
});

it('geeft de pagina van de bezoeker zonder querystring, en nooit het Livewire-endpoint', function () {
    // Livewire-update: de Referer is de pagina.
    expect(RequestSignals::pageUrl(metaBezoekerVerzoek()))->toBe('https://shop.test/vazen/bluma');

    // Gewone paginaweergave: de URL van het verzoek zelf, niet de vorige pagina.
    $pagina = Request::create('https://shop.test/afrekenen?token=geheim#stap', 'GET', [], [], [], ['HTTP_REFERER' => 'https://shop.test/winkelwagen']);
    expect(RequestSignals::pageUrl($pagina))->toBe('https://shop.test/afrekenen');

    // Een POST van de browser (ViewContent): de Referer.
    $post = Request::create('https://shop.test/ecommerce/meta/view-content', 'POST', [], [], [], ['HTTP_REFERER' => 'https://shop.test/producten/vaas?x=1']);
    expect(RequestSignals::pageUrl($post))->toBe('https://shop.test/producten/vaas');

    expect(RequestSignals::pageUrl(metaBezoekerVerzoek(['HTTP_REFERER' => 'https://shop.test/livewire/update'])))->toBeNull()
        ->and(RequestSignals::pageUrl(metaBezoekerVerzoek(['HTTP_REFERER' => 'javascript:alert(1)'])))->toBeNull();
});

it('herkent bots en verzoeken zonder user agent', function (string $userAgent, bool $bot) {
    $request = Request::create('https://shop.test/', 'GET', [], [], [], ['HTTP_USER_AGENT' => $userAgent]);

    expect(RequestSignals::isBot($request))->toBe($bot);
})->with([
    ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', true],
    ['facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', true],
    ['Mozilla/5.0 (Linux; Android 11) Chrome/119.0 Mobile Safari/537.36 Chrome-Lighthouse', true],
    ['Mozilla/5.0 (X11; Linux x86_64) HeadlessChrome/120.0.0.0 Safari/537.36', true],
    ['curl/8.4.0', true],
    ['python-requests/2.31', true],
    ['', true],
    ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1', false],
    ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36', false],
    ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Instagram 310.0.0.0', false],
]);

it('geeft alleen een geldig IP-adres door', function () {
    expect(RequestSignals::ip(metaBezoekerVerzoek()))->toBe('203.0.113.7')
        ->and(RequestSignals::ip(metaBezoekerVerzoek(['REMOTE_ADDR' => 'geen-ip'])))->toBeNull();
});
