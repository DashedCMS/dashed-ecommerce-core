<?php

// tests/Feature/Proforma/ProformaAdminFilterTest.php
//
// Verifies the behavior of the "Wachtend op betaling (proforma)" filter
// at the Eloquent query level and via direct invocation of the status
// filter's logic in OrderResource::statusFilterQuery().
//
// Why no Livewire table test?
// The OrderResource is guarded by OrderPolicy::viewAny(), which requires
// the 'view_order' permission.  In the Orchestra Testbench harness used by
// this package, setting up a user with that permission is impractical and
// would couple this test to the RBAC configuration rather than the filter
// logic.  The coordinator-approved fallback is a query-level assertion that
// proves the filter's query closure produces the correct result set.

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;

// Scope assertions

it('proforma filter query includes concept proformas and excludes plain concepts and paid orders', function () {
    $proforma = Order::create(['email' => 'p@test.nl', 'status' => Order::STATUS_CONCEPT, 'is_proforma' => true]);
    $plainConcept = Order::create(['email' => 'c@test.nl', 'status' => Order::STATUS_CONCEPT, 'is_proforma' => false]);
    $paid = Order::create(['email' => 'a@test.nl', 'status' => 'paid',                'is_proforma' => false]);

    // Simulate what the proforma filter applies when active:
    // only proformaAwaitingPayment() scope, no status IN constraint.
    $ids = Order::query()
        ->where(function ($q) {
            $q->proformaAwaitingPayment();
        })
        ->pluck('id')
        ->all();

    expect($ids)->toContain($proforma->id)
        ->and($ids)->not->toContain($plainConcept->id)
        ->and($ids)->not->toContain($paid->id);
});

it('default status filter would exclude concept proformas (confirming the override is needed)', function () {
    $proforma = Order::create(['email' => 'p@test.nl', 'status' => Order::STATUS_CONCEPT, 'is_proforma' => true]);
    $paid = Order::create(['email' => 'a@test.nl', 'status' => 'paid',                'is_proforma' => false]);

    // Simulate the status filter's default (concept is NOT in the default list):
    $defaultStatuses = ['paid', 'partially_paid', 'waiting_for_confirmation'];
    $ids = Order::query()
        ->where(function ($q) use ($defaultStatuses) {
            $q->whereIn('status', $defaultStatuses);
        })
        ->pluck('id')
        ->all();

    // Proforma concept orders are invisible with the default status filter.
    // This confirms why the proforma filter must suppress the status constraint.
    expect($ids)->not->toContain($proforma->id)
        ->and($ids)->toContain($paid->id);
});

// Per-request state tests - directly invoke OrderResource::statusFilterQuery()
// with a stub $livewire so the fix is locked without a full Filament table render.

function makeLivewireStub(bool $proformaActive): object
{
    return new class($proformaActive) {
        public function __construct(private bool $active) {}

        public function getTableFilterState(string $name): ?array
        {
            if ($name === 'proforma_awaiting_payment') {
                return ['isActive' => $this->active];
            }

            return null;
        }
    };
}

it('statusFilterQuery skips whereIn when proforma filter is active', function () {
    $paid = Order::create(['email' => 'paid@test.nl', 'status' => 'paid',    'is_proforma' => false]);
    $concept = Order::create(['email' => 'conc@test.nl', 'status' => Order::STATUS_CONCEPT, 'is_proforma' => true]);

    $livewire = makeLivewireStub(true);

    // Even though values excludes 'concept', the proforma-active path must NOT
    // apply a whereIn, so both rows survive.
    $ids = OrderResource::statusFilterQuery(
        Order::query(),
        ['values' => ['paid', 'partially_paid', 'waiting_for_confirmation']],
        $livewire,
    )->pluck('id')->all();

    expect($ids)->toContain($paid->id)
        ->and($ids)->toContain($concept->id);
});

it('statusFilterQuery applies whereIn when proforma filter is inactive', function () {
    $paid = Order::create(['email' => 'paid@test.nl', 'status' => 'paid',    'is_proforma' => false]);
    $concept = Order::create(['email' => 'conc@test.nl', 'status' => Order::STATUS_CONCEPT, 'is_proforma' => false]);

    $livewire = makeLivewireStub(false);

    $ids = OrderResource::statusFilterQuery(
        Order::query(),
        ['values' => ['paid']],
        $livewire,
    )->pluck('id')->all();

    expect($ids)->toContain($paid->id)
        ->and($ids)->not->toContain($concept->id);
});
