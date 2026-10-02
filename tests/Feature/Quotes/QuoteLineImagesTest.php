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

it('laat onveilige of onbruikbare waarden vallen', function () {
    $regel = new QuoteLine(['images' => [
        'https://x.test/a"onerror="alert(1)',
        'https://x.test/a"><script>alert(1)</script>',
        '/\evil.example/x.jpg',
        'https:///x',
        "https://x.test/a\x00b",
        'HTTPS://',
        'ftp://x.test/a.jpg',
        0,
        -3,
    ]]);

    expect($regel->imageUrls())->toBe([]);
});

it('codeert tekens die in een bestandsnaam mogen maar in een attribuut gevaarlijk zijn', function () {
    $regel = new QuoteLine(['images' => [
        'https://cdn.test/uploads/pot rood.jpg',
        'https://cdn.test/foto (1).jpg',
        "https://x.test/a'),url(//evil/x.png",
        'HTTPS://cdn.test/a.jpg',
    ]]);

    expect($regel->imageUrls())->toBe([
        'https://cdn.test/uploads/pot%20rood.jpg',
        'https://cdn.test/foto%20%281%29.jpg',
        'https://x.test/a%27%29,url%28//evil/x.png',
        'HTTPS://cdn.test/a.jpg',
    ]);
});

it('laat een kapot media-item vallen zonder de rest te breken', function () {
    $GLOBALS['mediahelper_stub_gooit_voor_id'] = 77;

    try {
        $regel = new QuoteLine(['images' => [77, 'https://cdn.example.test/pot.jpg']]);

        expect($regel->imageUrls())->toBe(['https://cdn.example.test/pot.jpg']);
    } finally {
        unset($GLOBALS['mediahelper_stub_gooit_voor_id']);
    }
});

it('geeft per foto een paar van thumb en origineel en laat onbruikbare foto\'s vallen', function () {
    $regel = new QuoteLine(['images' => [
        'https://cdn.example.test/a.jpg',
        'https://cdn.example.test/b.jpg',
        999999,                  // onbekend media-id: de stub geeft ''
        'javascript:alert(1)',
    ]]);

    expect($regel->imagePairs())->toBe([
        ['thumb' => 'https://cdn.example.test/a.jpg', 'original' => 'https://cdn.example.test/a.jpg'],
        ['thumb' => 'https://cdn.example.test/b.jpg', 'original' => 'https://cdn.example.test/b.jpg'],
    ])
        ->and((new QuoteLine())->imagePairs())->toBe([])
        ->and((new QuoteLine(['images' => []]))->imagePairs())->toBe([]);
});

it('houdt thumb en origineel bij elkaar als alleen het origineel niet op te halen is', function () {
    $GLOBALS['mediahelper_stub_urls'] = [
        '5:medium' => 'https://cdn.example.test/5-medium.jpg',
        // 5:original ontbreekt: de stub geeft ''
        '6:medium' => 'https://cdn.example.test/6-medium.jpg',
        '6:original' => 'https://cdn.example.test/6-origineel.jpg',
    ];

    try {
        $regel = new QuoteLine(['images' => [5, 6]]);

        expect($regel->imagePairs())->toBe([
            ['thumb' => 'https://cdn.example.test/5-medium.jpg', 'original' => 'https://cdn.example.test/5-medium.jpg'],
            ['thumb' => 'https://cdn.example.test/6-medium.jpg', 'original' => 'https://cdn.example.test/6-origineel.jpg'],
        ]);
    } finally {
        unset($GLOBALS['mediahelper_stub_urls']);
    }
});

it('geeft een media-id via de url van het media-object terug, zoals de echte helper', function () {
    $GLOBALS['mediahelper_stub_urls'] = [
        '8:medium' => 'https://cdn.example.test/8-medium.jpg',
        '8:original' => 'https://cdn.example.test/8-origineel.jpg',
    ];

    try {
        // De echte helper geeft een object met `url`; de stub doet dat nu ook.
        expect(mediaHelper()->getSingleMedia(8, 'medium'))->toBeObject();

        $regel = new QuoteLine(['images' => [8]]);

        expect($regel->imageUrls())->toBe(['https://cdn.example.test/8-medium.jpg'])
            ->and($regel->imagePairs())->toBe([
                ['thumb' => 'https://cdn.example.test/8-medium.jpg', 'original' => 'https://cdn.example.test/8-origineel.jpg'],
            ]);
    } finally {
        unset($GLOBALS['mediahelper_stub_urls']);
    }
});

it('vraagt bij het opslaan van een regel met foto\'s de conversies alvast op', function () {
    $GLOBALS['mediahelper_stub_urls'] = ['9:medium' => 'https://cdn.example.test/9-medium.jpg'];
    $GLOBALS['mediahelper_stub_aanroepen'] = [];

    try {
        fotoOfferte()->lines()->create(['name' => 'Pot', 'quantity' => 1, 'unit_price' => 10, 'images' => [9]]);

        expect($GLOBALS['mediahelper_stub_aanroepen'])->toContain(
            [9, 'medium'],
            [9, 'original'],
            [9, QuoteLine::PDF_IMAGE_CONVERSION],
        );

        $GLOBALS['mediahelper_stub_aanroepen'] = [];
        fotoOfferte()->lines()->create(['name' => 'Deksel', 'quantity' => 1, 'unit_price' => 4]);
        fotoOfferte()->lines()->create(['name' => 'Schotel', 'quantity' => 1, 'unit_price' => 4, 'images' => []]);

        expect($GLOBALS['mediahelper_stub_aanroepen'])->toBe([]);
    } finally {
        unset($GLOBALS['mediahelper_stub_urls'], $GLOBALS['mediahelper_stub_aanroepen']);
    }
});

it('laat het opslaan niet mislukken als de mediahelper gooit', function () {
    $GLOBALS['mediahelper_stub_gooit_voor_id'] = 10;

    try {
        $regel = fotoOfferte()->lines()->create(['name' => 'Pot', 'quantity' => 1, 'unit_price' => 10, 'images' => [10]]);

        expect($regel->exists)->toBeTrue()
            ->and($regel->fresh()->images)->toBe([10]);
    } finally {
        unset($GLOBALS['mediahelper_stub_gooit_voor_id']);
    }
});
