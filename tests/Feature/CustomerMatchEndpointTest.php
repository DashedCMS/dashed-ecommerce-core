<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Models\CustomerMatchAccessLog;
use Dashed\DashedEcommerceCore\Models\CustomerMatchEndpoint;
use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

function makeEndpoint(array $overrides = []): array
{
    $plainPassword = 'super-secret-password-1234';

    $endpoint = CustomerMatchEndpoint::create(array_merge([
        'name' => 'Test',
        'slug' => 'test'.bin2hex(random_bytes(4)),
        'username' => 'google-ads-test',
        'password' => Hash::make($plainPassword),
        'is_active' => true,
        'customer_filter' => [
            'min_orders' => 1,
            'since' => null,
            'until' => null,
            'countries' => [],
        ],
    ], $overrides));

    return [$endpoint, $plainPassword];
}

function makePaidOrder(array $overrides = []): Order
{
    return Order::create(array_merge([
        'status' => 'paid',
        'email' => 'customer@example.com',
        'first_name' => 'Renée',
        'last_name' => 'van der Berg',
        'phone_number' => '0612345678',
        'country' => 'NL',
        'invoice_country' => 'NL',
        'invoice_zip_code' => '1234 AB',
        'zip_code' => '1234 AB',
    ], $overrides));
}

beforeEach(function () {
    RateLimiter::clear('google-ads-customer-match');
});

it('returns 401 without authentication', function () {
    [$endpoint] = makeEndpoint();

    $response = $this->get('/google-ads/customer-match/'.$endpoint->slug.'.csv');

    $response->assertStatus(401);
    $response->assertHeader('WWW-Authenticate', 'Basic realm="Google Ads Customer Match"');

    expect(CustomerMatchAccessLog::where('status', 401)->count())->toBe(1);
});

it('returns 401 with wrong credentials', function () {
    [$endpoint] = makeEndpoint();

    $response = $this->withBasicAuth('wrong', 'wrong')
        ->get('/google-ads/customer-match/'.$endpoint->slug.'.csv');

    $response->assertStatus(401);
});

it('returns 404 when endpoint is inactive', function () {
    [$endpoint, $password] = makeEndpoint(['is_active' => false]);

    $response = $this->withBasicAuth($endpoint->username, $password)
        ->get('/google-ads/customer-match/'.$endpoint->slug.'.csv');

    $response->assertStatus(404);
});

it('streams a hashed CSV with valid credentials', function () {
    [$endpoint, $password] = makeEndpoint();
    makePaidOrder();

    $response = $this->withBasicAuth($endpoint->username, $password)
        ->get('/google-ads/customer-match/'.$endpoint->slug.'.csv');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $body = $response->streamedContent();
    $lines = array_values(array_filter(explode("\n", trim($body))));

    expect($lines[0])->toBe('Email,Phone,"First Name","Last Name",Country,Zip');
    expect($lines)->toHaveCount(2);

    $expectedEmailHash = hash('sha256', 'customer@example.com');
    expect($lines[1])->toContain($expectedEmailHash);
    expect($lines[1])->toContain('NL');
    expect($lines[1])->toContain('1234 AB');

    expect(CustomerMatchAccessLog::where('status', 200)->count())->toBe(1);
});

it('deduplicates rows by email', function () {
    [$endpoint, $password] = makeEndpoint();

    makePaidOrder(['email' => 'a@b.nl']);
    makePaidOrder(['email' => 'a@b.nl']);
    makePaidOrder(['email' => 'c@d.nl']);

    $response = $this->withBasicAuth($endpoint->username, $password)
        ->get('/google-ads/customer-match/'.$endpoint->slug.'.csv');

    $response->assertOk();
    $body = $response->streamedContent();
    $lines = array_values(array_filter(explode("\n", trim($body))));

    expect($lines)->toHaveCount(3);
});

it('respects min_orders filter', function () {
    [$endpoint, $password] = makeEndpoint([
        'customer_filter' => [
            'min_orders' => 2,
            'since' => null,
            'until' => null,
            'countries' => [],
        ],
    ]);

    makePaidOrder(['email' => 'a@b.nl']);
    makePaidOrder(['email' => 'a@b.nl']);
    makePaidOrder(['email' => 'c@d.nl']);

    $response = $this->withBasicAuth($endpoint->username, $password)
        ->get('/google-ads/customer-match/'.$endpoint->slug.'.csv');

    $response->assertOk();
    $body = $response->streamedContent();
    $lines = array_values(array_filter(explode("\n", trim($body))));

    expect($lines)->toHaveCount(2);
});

it('rate limits after 10 requests per minute', function () {
    [$endpoint, $password] = makeEndpoint();

    for ($i = 0; $i < 10; $i++) {
        $this->withBasicAuth($endpoint->username, $password)
            ->get('/google-ads/customer-match/'.$endpoint->slug.'.csv');
    }

    $response = $this->withBasicAuth($endpoint->username, $password)
        ->get('/google-ads/customer-match/'.$endpoint->slug.'.csv');

    $response->assertStatus(429);
});
