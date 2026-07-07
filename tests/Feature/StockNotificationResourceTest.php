<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Models\StockNotification;
use Dashed\DashedEcommerceCore\Filament\Resources\StockNotificationResource;

it('de terug-op-voorraad-resource is correct opgezet (read-only, index)', function () {
    expect(StockNotificationResource::getModel())->toBe(StockNotification::class)
        ->and(StockNotificationResource::canCreate())->toBeFalse()
        ->and(StockNotificationResource::getPages())->toHaveKey('index');
});
