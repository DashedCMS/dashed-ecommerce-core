<?php

use Dashed\DashedEcommerceCore\Mail\AdminNewOrderReturnMail;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnCustomMail;

it('greets the customer by first name in the default custom message', function () {
    $message = OrderReturnCustomMail::defaultMessage();

    expect($message)->toContain(':firstName:')
        ->and($message)->not->toContain('Beste klant');
});

it('builds a usable variables hint without the internal message variable', function () {
    $hint = OrderReturnCustomMail::usableVariablesHint();

    expect($hint)->toContain(':firstName:')
        ->and($hint)->toContain(':orderNumber:')
        ->and($hint)->not->toContain(':message:');
});

it('exposes adminReturnUrl as an available variable on the admin return mail', function () {
    expect(AdminNewOrderReturnMail::availableVariables())->toContain('adminReturnUrl');
});

it('includes a CMS button linking to the return in the admin mail blocks', function () {
    $blocks = AdminNewOrderReturnMail::defaultBlocks();

    $button = collect($blocks)->first(fn ($block) => ($block['type'] ?? null) === 'button');

    expect($button)->not->toBeNull()
        ->and($button['data']['url'])->toBe(':adminReturnUrl:')
        ->and($button['data']['label'])->toBe('Bekijk retourverzoek in CMS');
});
