<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Classes\BolTitleTemplate;

it('collapses a hyphen after an en dash when a placeholder stays empty', function () {
    $template = 'Familie beeldjes – :familie: - :hoogte: - :kleurencombinaties: - 3d geprint - Decoratief cadeau';

    expect(BolTitleTemplate::render($template, ['Hoogte' => '30CM', 'Kleurencombinaties' => 'Bruin, Mokka en Wit']))
        ->toBe('Familie beeldjes – 30CM - Bruin, Mokka en Wit - 3d geprint - Decoratief cadeau');
});

it('collapses an en dash or em dash that follows another separator', function () {
    expect(BolTitleTemplate::tidy('Vaas - – Mint'))->toBe('Vaas - Mint')
        ->and(BolTitleTemplate::tidy('Vaas — - Mint'))->toBe('Vaas — Mint')
        ->and(BolTitleTemplate::tidy('Vaas – – Mint'))->toBe('Vaas – Mint');
});

it('strips an en dash at the start or end', function () {
    expect(BolTitleTemplate::tidy('– Vaas – Mint –'))->toBe('Vaas – Mint');
});

it('keeps a hyphen inside a word and a single separator between parts', function () {
    expect(BolTitleTemplate::tidy('Dual-color vaas – Mint - 20CM'))->toBe('Dual-color vaas – Mint - 20CM');
});
