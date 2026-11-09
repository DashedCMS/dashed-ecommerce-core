<?php

use Illuminate\Support\Carbon;
use Dashed\DashedEcommerceCore\Models\MetaCapiEventCount;

afterEach(fn () => Carbon::setTestNow());

it('telt per site, dag en eventsoort in één rij', function () {
    Carbon::setTestNow('2026-11-09 10:00:00');

    MetaCapiEventCount::record('default', 'ViewContent', MetaCapiEventCount::SENT);
    MetaCapiEventCount::record('default', 'ViewContent', MetaCapiEventCount::SENT);
    MetaCapiEventCount::record('default', 'ViewContent', MetaCapiEventCount::FAILED);
    MetaCapiEventCount::record('default', 'AddToCart', MetaCapiEventCount::SENT);
    MetaCapiEventCount::record('default', 'AddToCart', 'verzonnen_kolom');

    expect(MetaCapiEventCount::count())->toBe(2);

    $view = MetaCapiEventCount::where('event_name', 'ViewContent')->sole();
    expect($view->sent)->toBe(2)
        ->and($view->failed)->toBe(1)
        ->and($view->date->toDateString())->toBe('2026-11-09');
});

it('geeft een overzicht van de laatste dagen, nieuwste eerst, met nullen waar niets gebeurde', function () {
    Carbon::setTestNow('2026-11-08 23:00:00');
    MetaCapiEventCount::record('default', 'InitiateCheckout', MetaCapiEventCount::SENT);

    Carbon::setTestNow('2026-11-09 10:00:00');
    MetaCapiEventCount::record('default', 'ViewContent', MetaCapiEventCount::SENT);
    MetaCapiEventCount::record('default', 'ViewContent', MetaCapiEventCount::FAILED);

    $overview = MetaCapiEventCount::overview(3);

    expect($overview)->toHaveCount(3)
        ->and(array_column($overview, 'date'))->toBe(['2026-11-09', '2026-11-08', '2026-11-07'])
        ->and($overview[0]['events']['ViewContent'])->toBe(['sent' => 1, 'failed' => 1])
        ->and($overview[0]['events']['AddToCart'])->toBe(['sent' => 0, 'failed' => 0])
        ->and($overview[1]['events']['InitiateCheckout'])->toBe(['sent' => 1, 'failed' => 0])
        ->and($overview[2]['events']['ViewContent'])->toBe(['sent' => 0, 'failed' => 0]);
});
