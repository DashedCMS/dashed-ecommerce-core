<?php

use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedEcommerceCore\Models\QuoteLine;
use Dashed\DashedEcommerceCore\Services\Quotes\QuoteRevision;

function fotoOfferte(): Quote
{
    return Quote::create([
        'title' => 'Bloempotten op maat',
        'email' => 'klant@example.test',
        'valid_until' => now()->addDays(14),
    ]);
}

it('bewaart de foto\'s van een regel als lijst en leest ze zo terug', function () {
    $regel = fotoOfferte()->lines()->create([
        'name' => 'Pot',
        'quantity' => 10,
        'unit_price' => 39.51,
        'images' => [12, 'https://cdn.example.test/pot.jpg'],
    ]);

    expect($regel->fresh()->images)->toBe([12, 'https://cdn.example.test/pot.jpg']);

    $zonder = fotoOfferte()->lines()->create(['name' => 'Deksel', 'quantity' => 1, 'unit_price' => 4.83]);

    expect($zonder->fresh()->images)->toBeNull()
        ->and($zonder->fresh()->imageUrls())->toBe([]);
});

it('geeft alleen veilige urls terug en laat een onbekend media-id vallen', function () {
    $regel = new QuoteLine(['images' => [
        'https://cdn.example.test/pot.jpg',
        '//cdn.example.test/deksel.jpg',
        '/storage/schotel.jpg',
        'http://cdn.example.test/oud.jpg',
        999999,                      // media-id zonder bestand: de stub geeft ''
        '424242',                    // idem, als cijferreeks
        'javascript:alert(1)',
        'data:image/png;base64,AAAA',
        '',
        null,
        ['genest'],
    ]]);

    expect($regel->imageUrls())->toBe([
        'https://cdn.example.test/pot.jpg',
        '//cdn.example.test/deksel.jpg',
        '/storage/schotel.jpg',
        'http://cdn.example.test/oud.jpg',
    ]);
});

it('neemt de foto\'s mee naar een revisie', function () {
    $offerte = fotoOfferte();
    $offerte->lines()->create([
        'name' => 'Pot',
        'quantity' => 10,
        'unit_price' => 39.51,
        'images' => ['https://cdn.example.test/pot.jpg'],
    ]);
    $offerte->lines()->create(['name' => 'Ontwerp', 'quantity' => 1, 'unit_price' => 90.75]);

    $revisie = QuoteRevision::create($offerte->fresh());

    expect($revisie->lines->pluck('images', 'name')->all())->toBe([
        'Pot' => ['https://cdn.example.test/pot.jpg'],
        'Ontwerp' => null,
    ]);
});
