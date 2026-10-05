<?php

use Illuminate\Support\Facades\View;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedEcommerceCore\Services\Quotes\QuoteTotals;

// De PDF-view gebruikt de globale alias `Translation` van dashed-translations; het testharnas registreert die niet.
if (! class_exists('Translation')) {
    class_alias(\Dashed\DashedTranslations\Models\Translation::class, 'Translation');
}

function fotoPdfHtml(Quote $offerte): string
{
    $offerte = $offerte->fresh('lines');

    return View::make('dashed-ecommerce-core::quotes.quote', [
        'quote' => $offerte,
        'totals' => QuoteTotals::for($offerte),
        'accepted' => false,
    ])->render();
}

it('toont de foto\'s van een regel in de pdf en niets bij een regel zonder', function () {
    $offerte = Quote::create(['title' => 'Bloempotten', 'email' => 'klant@example.test', 'valid_until' => now()->addDays(14)]);
    $offerte->lines()->create([
        'name' => 'Pot',
        'quantity' => 10,
        'unit_price' => 39.51,
        'sort_order' => 1,
        'images' => ['https://cdn.example.test/pot-voor.jpg', 'https://cdn.example.test/pot-zij.jpg', 'javascript:alert(1)'],
    ]);
    $offerte->lines()->create(['name' => 'Ontwerp', 'quantity' => 1, 'unit_price' => 90.75, 'sort_order' => 2]);

    $html = fotoPdfHtml($offerte);

    expect(substr_count($html, '<div class="photos">'))->toBe(1)
        ->and(substr_count($html, 'class="photo"'))->toBe(2)
        ->and($html)->toContain('src="https://cdn.example.test/pot-voor.jpg"')
        ->and($html)->toContain('src="https://cdn.example.test/pot-zij.jpg"')
        ->and($html)->not->toContain('javascript:alert');

    // De foto's staan bij hun eigen regel: na "Pot" en voor "Ontwerp".
    expect(strpos($html, 'pot-voor.jpg'))->toBeGreaterThan(strpos($html, '>Pot<'))
        ->and(strpos($html, 'pot-voor.jpg'))->toBeLessThan(strpos($html, '>Ontwerp<'));
});

it('laat de pdf zonder fotoblok als geen enkele regel foto\'s heeft', function () {
    $offerte = Quote::create(['title' => 'Zonder foto', 'email' => 'klant@example.test', 'valid_until' => now()->addDays(14)]);
    $offerte->lines()->create(['name' => 'Pot', 'quantity' => 10, 'unit_price' => 39.51]);
    $offerte->lines()->create(['name' => 'Deksel', 'quantity' => 10, 'unit_price' => 4.83, 'images' => []]);

    $html = fotoPdfHtml($offerte);

    expect($html)->not->toContain('<div class="photos">')
        ->and($html)->not->toContain('class="photo"');
});

it('toont de foto\'s op de online pagina als links naar het origineel zonder de keuze te raken', function () {
    $view = file_get_contents(__DIR__.'/../../../resources/views/livewire/frontend/quotes/quote-page.blade.php');

    preg_match('/<div class="dq-photos">.*?<\/div>/s', $view, $blok);

    expect($blok)->not->toBeEmpty()
        ->and($blok[0])->toContain('class="dq-photo"')
        ->and($blok[0])->toContain('target="_blank"')
        ->and($blok[0])->toContain('rel="noopener"')
        ->and($blok[0])->not->toContain('wire:click');

    expect($view)->toContain('$line->imagePairs()')->and($view)->not->toContain('$originals[');

    $stijl = file_get_contents(__DIR__.'/../../../resources/views/quotes/partials/styles.blade.php');
    expect($stijl)->toContain('.dq-photos')->and($stijl)->toContain('.dq-photo img');
});

it('gebruikt in de pdf de kleine conversie en zet de foto\'s bovenaan de regel', function () {
    $GLOBALS['mediahelper_stub_urls'] = [
        '11:medium' => 'https://cdn.example.test/11-medium.jpg',
        '11:small' => 'https://cdn.example.test/11-small.jpg',
    ];

    try {
        $offerte = Quote::create(['title' => 'Conversie', 'email' => 'klant@example.test', 'valid_until' => now()->addDays(14)]);
        $offerte->lines()->create(['name' => 'Pot', 'quantity' => 1, 'unit_price' => 10, 'images' => [11]]);

        $html = fotoPdfHtml($offerte);
    } finally {
        unset($GLOBALS['mediahelper_stub_urls']);
    }

    expect($html)->toContain('src="https://cdn.example.test/11-small.jpg"')
        ->and($html)->not->toContain('11-medium.jpg');

    preg_match('/\.lines \.photo \{[^}]*\}/', $html, $stijl);
    expect($stijl[0] ?? '')->toContain('vertical-align: top')->and($stijl[0] ?? '')->toContain('max-width: 100%');
});
