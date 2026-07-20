<?php

declare(strict_types=1);

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Support\Automation\AutomationContext;
use Dashed\DashedEcommerceCore\Support\Automation\ConditionEvaluator;

// -- ConditionEvaluator::matches() -------------------------------------------------

it('matches an empty condition list', function () {
    expect(ConditionEvaluator::matches([], ['total' => 100]))->toBeTrue();
});

it('evaluates the eq operator for numbers', function () {
    $context = ['total' => 100];

    expect(ConditionEvaluator::matches([['field' => 'total', 'operator' => 'eq', 'value' => 100]], $context))->toBeTrue()
        ->and(ConditionEvaluator::matches([['field' => 'total', 'operator' => 'eq', 'value' => 50]], $context))->toBeFalse();
});

it('evaluates the neq operator for numbers', function () {
    $context = ['total' => 100];

    expect(ConditionEvaluator::matches([['field' => 'total', 'operator' => 'neq', 'value' => 50]], $context))->toBeTrue()
        ->and(ConditionEvaluator::matches([['field' => 'total', 'operator' => 'neq', 'value' => 100]], $context))->toBeFalse();
});

it('evaluates the gt operator for numbers', function () {
    $context = ['total' => 100];

    expect(ConditionEvaluator::matches([['field' => 'total', 'operator' => 'gt', 'value' => 50]], $context))->toBeTrue()
        ->and(ConditionEvaluator::matches([['field' => 'total', 'operator' => 'gt', 'value' => 100]], $context))->toBeFalse()
        ->and(ConditionEvaluator::matches([['field' => 'total', 'operator' => 'gt', 'value' => 150]], $context))->toBeFalse();
});

it('evaluates the lt operator for numbers', function () {
    $context = ['total' => 100];

    expect(ConditionEvaluator::matches([['field' => 'total', 'operator' => 'lt', 'value' => 150]], $context))->toBeTrue()
        ->and(ConditionEvaluator::matches([['field' => 'total', 'operator' => 'lt', 'value' => 100]], $context))->toBeFalse()
        ->and(ConditionEvaluator::matches([['field' => 'total', 'operator' => 'lt', 'value' => 50]], $context))->toBeFalse();
});

it('never matches gt/lt when the actual or expected value is not numeric', function () {
    $context = ['country' => 'NL'];

    expect(ConditionEvaluator::matches([['field' => 'country', 'operator' => 'gt', 'value' => 10]], $context))->toBeFalse()
        ->and(ConditionEvaluator::matches([['field' => 'country', 'operator' => 'lt', 'value' => 10]], $context))->toBeFalse();
});

it('evaluates eq/neq for text fields', function () {
    $context = ['country' => 'NL'];

    expect(ConditionEvaluator::matches([['field' => 'country', 'operator' => 'eq', 'value' => 'NL']], $context))->toBeTrue()
        ->and(ConditionEvaluator::matches([['field' => 'country', 'operator' => 'eq', 'value' => 'BE']], $context))->toBeFalse()
        ->and(ConditionEvaluator::matches([['field' => 'country', 'operator' => 'neq', 'value' => 'BE']], $context))->toBeTrue()
        ->and(ConditionEvaluator::matches([['field' => 'country', 'operator' => 'neq', 'value' => 'NL']], $context))->toBeFalse();
});

it('evaluates the in operator for text/choice fields', function () {
    $context = ['country' => 'NL'];

    expect(ConditionEvaluator::matches([['field' => 'country', 'operator' => 'in', 'value' => ['NL', 'BE']]], $context))->toBeTrue()
        ->and(ConditionEvaluator::matches([['field' => 'country', 'operator' => 'in', 'value' => ['DE', 'BE']]], $context))->toBeFalse();
});

it('evaluates is_true and is_false for booleans', function () {
    expect(ConditionEvaluator::matches([['field' => 'has_discount_code', 'operator' => 'is_true', 'value' => null]], ['has_discount_code' => true]))->toBeTrue()
        ->and(ConditionEvaluator::matches([['field' => 'has_discount_code', 'operator' => 'is_true', 'value' => null]], ['has_discount_code' => false]))->toBeFalse()
        ->and(ConditionEvaluator::matches([['field' => 'has_discount_code', 'operator' => 'is_false', 'value' => null]], ['has_discount_code' => false]))->toBeTrue()
        ->and(ConditionEvaluator::matches([['field' => 'has_discount_code', 'operator' => 'is_false', 'value' => null]], ['has_discount_code' => true]))->toBeFalse();
});

it('never matches a field that is absent from the context, without throwing', function () {
    $result = ConditionEvaluator::matches([['field' => 'does_not_exist', 'operator' => 'eq', 'value' => 1]], ['total' => 100]);

    expect($result)->toBeFalse();
});

it('never matches an unknown operator, without throwing', function () {
    $result = ConditionEvaluator::matches([['field' => 'total', 'operator' => 'bogus', 'value' => 100]], ['total' => 100]);

    expect($result)->toBeFalse();
});

it('never matches a malformed condition (missing field/operator keys), without throwing', function () {
    expect(ConditionEvaluator::matches([['operator' => 'eq', 'value' => 1]], ['total' => 100]))->toBeFalse()
        ->and(ConditionEvaluator::matches([['field' => 'total', 'value' => 1]], ['total' => 100]))->toBeFalse();
});

it('combines multiple conditions with AND semantics', function () {
    $context = ['total' => 100, 'country' => 'NL'];

    expect(ConditionEvaluator::matches([
        ['field' => 'total', 'operator' => 'gt', 'value' => 50],
        ['field' => 'country', 'operator' => 'eq', 'value' => 'NL'],
    ], $context))->toBeTrue();

    expect(ConditionEvaluator::matches([
        ['field' => 'total', 'operator' => 'gt', 'value' => 50],
        ['field' => 'country', 'operator' => 'eq', 'value' => 'BE'],
    ], $context))->toBeFalse();

    expect(ConditionEvaluator::matches([
        ['field' => 'total', 'operator' => 'gt', 'value' => 999],
        ['field' => 'country', 'operator' => 'eq', 'value' => 'NL'],
    ], $context))->toBeFalse();
});

// -- AutomationContext::forOrder() -------------------------------------------------

it('builds the order condition context with all declared fields', function () {
    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => 'paid',
        'fulfillment_status' => 'unhandled',
        'country' => 'NL',
        'order_origin' => 'own',
        'total' => 123.45,
    ]);

    $context = AutomationContext::forOrder($order);

    expect($context)->toMatchArray([
        'total' => 123.45,
        'country' => 'NL',
        'origin' => 'own',
        'status' => 'paid',
        'fulfillment_status' => 'unhandled',
        'product_count' => 0,
        'has_discount_code' => false,
    ])->and($context)->toHaveKey('payment_method');
});

it('sums order product quantities for product_count', function () {
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid']);
    OrderProduct::create(['order_id' => $order->id, 'name' => 'Shirt', 'quantity' => 2, 'price' => 20]);
    OrderProduct::create(['order_id' => $order->id, 'name' => 'Broek', 'quantity' => 3, 'price' => 40]);

    $order->refresh();

    expect(AutomationContext::forOrder($order)['product_count'])->toBe(5);
});

it('derives has_discount_code from a linked discount code', function () {
    $discountCode = DiscountCode::create([
        'site_ids' => [Sites::getActive()],
        'name' => 'Korting10',
        'code' => 'KORTING10',
        'type' => 'percentage',
        'discount_percentage' => 10,
    ]);
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid', 'discount_code_id' => $discountCode->id]);

    expect(AutomationContext::forOrder($order)['has_discount_code'])->toBeTrue();
});

it('derives has_discount_code from applied_discount_codes when there is no single discount_code_id', function () {
    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => 'paid',
        'applied_discount_codes' => [['code' => 'GIFT-1', 'amount' => 5]],
    ]);

    expect(AutomationContext::forOrder($order)['has_discount_code'])->toBeTrue();
});

it('merges trigger-specific extra fields such as old_status/new_status', function () {
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid']);

    $context = AutomationContext::forOrder($order, ['old_status' => 'unhandled', 'new_status' => 'packed']);

    expect($context['old_status'])->toBe('unhandled')
        ->and($context['new_status'])->toBe('packed');
});
