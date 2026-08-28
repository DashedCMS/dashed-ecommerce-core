<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

// Moet vóór de eerste migrate:fresh geladen zijn: meerdere migraties van
// dashed-core en dit package verwijzen hardcoded naar \App\Models\User, een
// klasse die de Testbench-skeleton niet heeft.
require_once __DIR__ . '/Stubs/AppUserStub.php';

uses(\Dashed\DashedEcommerceCore\Tests\TestCase::class, RefreshDatabase::class)->in(__DIR__);
