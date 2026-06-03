<?php

it('registers a shipping status command and exposes it', function () {
    ecommerce()->registerShippingStatusCommand('test:fake-status-command');

    expect(ecommerce()->shippingStatusCommands())->toContain('test:fake-status-command');
});

it('does not register the same command twice', function () {
    ecommerce()->registerShippingStatusCommand('test:dedupe-command');
    ecommerce()->registerShippingStatusCommand('test:dedupe-command');

    $occurrences = count(array_filter(
        ecommerce()->shippingStatusCommands(),
        fn ($command) => $command === 'test:dedupe-command'
    ));

    expect($occurrences)->toBe(1);
});
