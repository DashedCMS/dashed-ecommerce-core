<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

// Moet vóór de eerste migrate:fresh geladen zijn: meerdere migraties van
// dashed-core en dit package verwijzen hardcoded naar \App\Models\User, een
// klasse die de Testbench-skeleton niet heeft.
require_once __DIR__ . '/Stubs/AppUserStub.php';

// Idem voor mediaHelper(): geleverd door dashed-files, dat geen dependency van
// dit package is. WishlistBlockTest rendert via ProductsBlock, dat de functie
// aanroept.
require_once __DIR__ . '/Stubs/MediaHelperStub.php';

uses(\Dashed\DashedEcommerceCore\Tests\TestCase::class, RefreshDatabase::class)->in(__DIR__);
