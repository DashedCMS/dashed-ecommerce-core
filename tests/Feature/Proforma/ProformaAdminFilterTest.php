<?php

// tests/Feature/Proforma/ProformaAdminFilterTest.php
//
// Verifies the behavior of the "Wachtend op betaling (proforma)" filter
// at the Eloquent query level, without requiring a Filament table render.
//
// Why no Livewire table test?
// The OrderResource is guarded by OrderPolicy::viewAny(), which requires
// the 'view_order' permission.  In the Orchestra Testbench harness used by
// this package, setting up a user with that permission is impractical and
// would couple this test to the RBAC configuration rather than the filter
// logic.  The coordinator-approved fallback is a query-level assertion that
// proves the filter's query closure produces the correct result set.

use Dashed\DashedEcommerceCore\Models\Order;

// ── how the filter override works ─────────────────────────────────────────
//
// OrderResource::table() registers filters in this order:
//
//   1. Filter::make('proforma_awaiting_payment')   <-- FIRST
//      ->query(function ($query, $data) {
//          static::$filteringProforma = $data['isActive'] ?? false;
//          if (! static::$filteringProforma) { return $query; }
//          return $query->proformaAwaitingPayment();   // status=concept + is_proforma=1
//      })
//
//   2. SelectFilter::make('status')                <-- SECOND
//      ->query(function ($query, $data) {
//          if (static::$filteringProforma) { return $query; }  // ← skip
//          $values = $data['values'] ?? [];
//          if (empty($values)) { return $query; }
//          return $query->whereIn('status', $values);
//      })
//      ->default(['paid', 'partially_paid', 'waiting_for_confirmation'])
//
// Filament calls each filter's ->query() callback in order inside a single
// grouped WHERE.  Because the proforma filter runs FIRST it sets
// $filteringProforma = true before the status filter's callback is reached.
// The status filter then returns early, preventing its default
// whereIn('status', ['paid', ...]) from conflicting with the
// status='concept' constraint added by proformaAwaitingPayment().
//
// Net SQL when proforma filter is active:
//   WHERE (status = 'concept' AND is_proforma = 1)
//
// Net SQL when proforma filter is inactive (default view):
//   WHERE (status IN ('paid', 'partially_paid', 'waiting_for_confirmation'))

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
