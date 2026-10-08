<?php

use Dashed\DashedEcommerceCore\Classes\PhoneNormalizer;
use Dashed\DashedEcommerceCore\Services\Meta\MetaHasher;

it('hasht een e-mailadres getrimd en in kleine letters', function () {
    expect(MetaHasher::email('  Jan.Jansen@Example.COM '))->toBe(hash('sha256', 'jan.jansen@example.com'));
});

it('zet een Nederlands mobiel nummer om naar 316', function (string $invoer) {
    expect(MetaHasher::phone($invoer, 'NL'))->toBe(hash('sha256', '31612345678'));
})->with(['06 12345678', '06-12345678', '+31 6 12345678', '0031612345678', '31612345678']);

it('zet een Belgisch mobiel nummer om naar 324', function (string $invoer) {
    expect(MetaHasher::phone($invoer, 'BE'))->toBe(hash('sha256', '32470123456'));
})->with(['0470 12 34 56', '+32 470 12 34 56', '0032470123456']);

it('normaliseert namen: kleine letters, geen accenten, geen leestekens', function () {
    expect(MetaHasher::name(' Zoë '))->toBe(hash('sha256', 'zoe'))
        ->and(MetaHasher::name("O'Brien-Smit"))->toBe(hash('sha256', 'obriensmit'))
        ->and(MetaHasher::name('van der Berg'))->toBe(hash('sha256', 'van der berg'));
});

it('normaliseert een plaats zonder spaties en leestekens', function () {
    expect(MetaHasher::city("'s-Hertogenbosch"))->toBe(hash('sha256', 'shertogenbosch'))
        ->and(MetaHasher::city('Den Haag'))->toBe(hash('sha256', 'denhaag'));
});

it('normaliseert een postcode zonder spaties en streepjes', function () {
    expect(MetaHasher::zip('1234 AB'))->toBe(hash('sha256', '1234ab'))
        ->and(MetaHasher::zip('B-9000'))->toBe(hash('sha256', 'b9000'));
});

it('hasht het land als ISO-2 in kleine letters', function () {
    expect(MetaHasher::country('NL'))->toBe(hash('sha256', 'nl'));
});

it('geeft null voor lege of onbruikbare waarden in plaats van een hash van niets', function () {
    expect(MetaHasher::email(null))->toBeNull()
        ->and(MetaHasher::email('   '))->toBeNull()
        ->and(MetaHasher::phone('geen nummer', 'NL'))->toBeNull()
        ->and(MetaHasher::phone('', 'NL'))->toBeNull()
        ->and(MetaHasher::name('!!!'))->toBeNull()
        ->and(MetaHasher::city(null))->toBeNull()
        ->and(MetaHasher::zip(' '))->toBeNull()
        ->and(MetaHasher::country(''))->toBeNull()
        ->and(MetaHasher::externalId(null))->toBeNull();
});

it('laat een nummer uit een onbekend land ongemoeid op de cijfers na', function () {
    expect(PhoneNormalizer::toE164('0911 123456', 'XX'))->toBe('0911123456')
        ->and(MetaHasher::phone('0911 123456', 'XX'))->toBe(hash('sha256', '911123456'));
});
