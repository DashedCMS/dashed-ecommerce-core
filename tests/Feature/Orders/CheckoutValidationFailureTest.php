<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Notifications\AdminNotifier;
use Dashed\DashedEcommerceCore\Support\UserCountryGuard;
use Dashed\DashedEcommerceCore\Models\CheckoutAbandonment;
use Dashed\DashedEcommerceCore\Classes\CheckoutValidationFailure;
use Dashed\DashedEcommerceCore\Mail\AdminCheckoutErrorMail;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function failCheckout(array $data, array $rules, int $cartId = 42): ?CheckoutAbandonment
{
    $validator = Validator::make($data, $rules);
    expect($validator->fails())->toBeTrue();

    return CheckoutValidationFailure::record($validator, $data, $cartId, 'klant@example.com', 194.11);
}

function unknownCountryRule(): Closure
{
    return function (string $attribute, $value, Closure $fail) {
        $fail('We herkennen dit land niet, controleer de spelling');
    };
}

beforeEach(function () {
    Cache::flush();
    Customsetting::set('notification_checkout_error_emails', ['beheer@example.com']);
});

it('stores the failed fields with message, rule and shortened value', function () {
    $row = failCheckout(
        ['invoiceCountry' => 'info@huize-elroi.nl', 'street' => '', 'password' => 'geheim'],
        ['invoiceCountry' => [unknownCountryRule()], 'street' => ['required'], 'password' => ['min:20']],
    );

    expect($row->reason)->toBe('validation_failed')
        ->and($row->context['attempts'])->toBe(1)
        ->and($row->context['errors']['invoiceCountry'])->toBe([
            'rules' => ['Custom'],
            'message' => 'We herkennen dit land niet, controleer de spelling',
            'value' => 'info@huize-elroi.nl',
        ])
        ->and($row->context['errors']['street']['rules'])->toBe(['Required'])
        ->and($row->context['errors']['password']['value'])->toBeNull()
        ->and($row->errorLines()[0])->toContain('(waarde: "info@huize-elroi.nl")');
});

it('counts repeated attempts on one row per cart', function () {
    foreach (range(1, 3) as $i) {
        failCheckout(['street' => ''], ['street' => ['required']]);
    }

    $rows = CheckoutAbandonment::where('cart_id', 42)->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->attempts())->toBe(3)
        ->and($rows->first()->context['field_failures'])->toBe(['street' => 3]);
});

it('mails the admin once per cart on an unusual error, after the response', function () {
    Mail::fake();

    failCheckout(['invoiceCountry' => 'x@y.nl'], ['invoiceCountry' => [unknownCountryRule()]]);
    failCheckout(['invoiceCountry' => 'x@y.nl'], ['invoiceCountry' => [unknownCountryRule()]]);
    Mail::assertNothingSent();

    app()->terminate();

    Mail::assertSent(AdminCheckoutErrorMail::class, 1);
    Mail::assertSent(AdminCheckoutErrorMail::class, fn ($mail) => $mail->hasTo('beheer@example.com')
        && $mail->abandonment->attempts() === 2);
});

it('does not mail on a single forgotten required field or when switched off', function () {
    Mail::fake();

    failCheckout(['street' => ''], ['street' => ['required']], cartId: 1);

    Customsetting::set('checkout_error_alert_enabled', '0');
    failCheckout(['invoiceCountry' => 'x@y.nl'], ['invoiceCountry' => [unknownCountryRule()]], cartId: 2);

    app()->terminate();

    Mail::assertNothingSent();
});

it('treats an unknown country as unusual and a forgotten field as ordinary', function () {
    $unusual = failCheckout(['invoiceCountry' => 'x@y.nl'], ['invoiceCountry' => [unknownCountryRule()]], cartId: 1);
    $ordinary = failCheckout(['street' => ''], ['street' => ['required']], cartId: 2);

    expect(CheckoutValidationFailure::isUnusual($unusual))->toBeTrue()
        ->and(CheckoutValidationFailure::isUnusual($ordinary))->toBeFalse();
});

it('treats the same required field failing three times as unusual', function () {
    failCheckout(['street' => ''], ['street' => ['required']]);
    failCheckout(['street' => ''], ['street' => ['required']]);
    $row = failCheckout(['street' => ''], ['street' => ['required']]);

    expect(CheckoutValidationFailure::isUnusual($row))->toBeTrue();
});

it('falls back to the order notification emails when no checkout error emails are set', function () {
    Customsetting::set('notification_checkout_error_emails', []);
    Customsetting::set('notification_invoice_emails', ['orders@example.com']);

    expect(CheckoutValidationFailure::recipients())->toBe(['orders@example.com']);
});

it('clears an e-mail address or postcode stored as country on a user', function () {
    $user = new \Illuminate\Foundation\Auth\User();
    $user->forceFill(['country' => 'Nederland', 'invoice_country' => 'info@huize-elroi.nl']);

    UserCountryGuard::clean($user);

    expect($user->country)->toBe('Nederland')
        ->and($user->invoice_country)->toBeNull()
        ->and(UserCountryGuard::looksLikeCountry('9203 BB'))->toBeFalse()
        ->and(UserCountryGuard::looksLikeCountry('België'))->toBeTrue();
});
