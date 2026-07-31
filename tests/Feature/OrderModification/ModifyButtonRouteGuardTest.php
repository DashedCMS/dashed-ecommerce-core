<?php

use Illuminate\Support\Facades\Route;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * De knop "Bestelling wijzigen" wordt opgebouwd tijdens het renderen van de
 * orderpagina. Bestaat de route niet, bijvoorbeeld door een verouderde
 * route-cache na een deploy, dan gooit route() een exception en valt de hele
 * bestelling om in plaats van alleen de knop. De zichtbaarheid moet daarom
 * eerst op het bestaan van de route toetsen.
 */
it('registreert de wijzigroute van de orderresource', function () {
    expect(Route::has('filament.dashed.resources.orders.modify'))->toBeTrue();
});

it('toetst de zichtbaarheid van de wijzigknop op het bestaan van de route', function () {
    $source = file_get_contents(
        __DIR__ . '/../../../src/Filament/Resources/OrderResource/Pages/ViewOrder.php'
    );

    expect($source)->toContain("Route::has('filament.dashed.resources.orders.modify')");
});
