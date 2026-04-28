<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Services\CustomerMatch\CustomerMatchHasher;

beforeEach(function () {
    $this->hasher = new CustomerMatchHasher();
});

it('lowercases and trims email before hashing', function () {
    $a = $this->hasher->hashEmail('  Test@Example.COM  ');
    $b = $this->hasher->hashEmail('test@example.com');
    expect($a)->toBe($b);
    expect($a)->toBe(hash('sha256', 'test@example.com'));
});

it('returns empty string for null or empty email', function () {
    expect($this->hasher->hashEmail(null))->toBe('');
    expect($this->hasher->hashEmail(''))->toBe('');
    expect($this->hasher->hashEmail('   '))->toBe('');
});

it('normalizes Dutch phone to E.164 before hashing', function () {
    $variants = [
        '+31 6 12345678',
        '+31612345678',
        '0612345678',
        '06-12345678',
        '0031612345678',
    ];

    $hashes = array_map(fn ($p) => $this->hasher->hashPhone($p, 'NL'), $variants);

    foreach ($hashes as $hash) {
        expect($hash)->toBe(hash('sha256', '+31612345678'));
    }
});

it('returns empty string for invalid phone', function () {
    expect($this->hasher->hashPhone(null, 'NL'))->toBe('');
    expect($this->hasher->hashPhone('', 'NL'))->toBe('');
    expect($this->hasher->hashPhone('not-a-phone', 'NL'))->toBe('');
});

it('lowercases and trims names but keeps accents before hashing', function () {
    $hash = $this->hasher->hashName('  Renée  ');
    expect($hash)->toBe(hash('sha256', 'renée'));
});

it('returns empty string for empty name', function () {
    expect($this->hasher->hashName(null))->toBe('');
    expect($this->hasher->hashName(''))->toBe('');
    expect($this->hasher->hashName('   '))->toBe('');
});

it('normalizes country to ISO-2 uppercase without hashing', function () {
    expect($this->hasher->normalizeCountry('nl'))->toBe('NL');
    expect($this->hasher->normalizeCountry(' BE '))->toBe('BE');
    expect($this->hasher->normalizeCountry('Netherlands'))->toBe('NL');
    expect($this->hasher->normalizeCountry(null))->toBe('');
});

it('trims zip without hashing', function () {
    expect($this->hasher->normalizeZip('  1234 AB  '))->toBe('1234 AB');
    expect($this->hasher->normalizeZip(null))->toBe('');
});

it('formats a full row with all expected columns', function () {
    $row = $this->hasher->formatRow([
        'email' => 'Customer@Example.com',
        'phone' => '0612345678',
        'first_name' => 'Renée',
        'last_name' => 'van der Berg',
        'country' => 'nl',
        'zip' => '1234 AB',
    ]);

    expect($row)->toMatchArray([
        'Email' => hash('sha256', 'customer@example.com'),
        'Phone' => hash('sha256', '+31612345678'),
        'First Name' => hash('sha256', 'renée'),
        'Last Name' => hash('sha256', 'van der berg'),
        'Country' => 'NL',
        'Zip' => '1234 AB',
    ]);
});

it('leaves columns empty when source is null', function () {
    $row = $this->hasher->formatRow([
        'email' => 'a@b.nl',
        'phone' => null,
        'first_name' => null,
        'last_name' => null,
        'country' => null,
        'zip' => null,
    ]);

    expect($row['Phone'])->toBe('');
    expect($row['First Name'])->toBe('');
    expect($row['Last Name'])->toBe('');
    expect($row['Country'])->toBe('');
    expect($row['Zip'])->toBe('');
});
