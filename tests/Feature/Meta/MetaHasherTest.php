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

it('laat een nummer uit een onbekend land ongemoeid op de cijfers na en hasht het niet', function () {
    expect(PhoneNormalizer::toE164('0911 123456', 'XX'))->toBe('0911123456')
        ->and(MetaHasher::phone('0911 123456', 'XX'))->toBeNull();
});

it('laat de nul tussen haakjes na de landcode vallen', function (string $invoer, string $land, string $verwacht) {
    expect(PhoneNormalizer::toE164($invoer, $land))->toBe($verwacht)
        ->and(MetaHasher::phone($invoer, $land))->toBe(hash('sha256', ltrim($verwacht, '+')));
})->with([
    ['+31 (0)6 12345678', 'NL', '+31612345678'],
    ['+31(0)612345678', 'NL', '+31612345678'],
    ['0031 (0)6 12345678', 'NL', '+31612345678'],
    ['+32 (0)470 12 34 56', 'BE', '+32470123456'],
    ['06 12345678', 'NL', '+31612345678'],
]);

it('hasht geen nummer zonder landcode of met te weinig cijfers', function () {
    expect(MetaHasher::phone('0', 'NL'))->toBeNull()
        ->and(MetaHasher::phone('06', 'NL'))->toBeNull()
        ->and(MetaHasher::phone('0612345678', null))->toBeNull()
        ->and(MetaHasher::phone('+49 171 1234567', 'XX'))->toBe(hash('sha256', '491711234567'));
});
