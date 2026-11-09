<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Services\Meta\MetaCapiSettings;

require_once __DIR__ . '/../../Support/MetaBrowserFixtures.php';

beforeEach(function () {
    $this->site = metaCapiKlaar();
});

it('heeft de drie browser-events standaard aan zodra de Conversions API aan staat', function () {
    $settings = MetaCapiSettings::for($this->site);

    expect($settings->eventEnabled('ViewContent'))->toBeTrue()
        ->and($settings->eventEnabled('AddToCart'))->toBeTrue()
        ->and($settings->eventEnabled('InitiateCheckout'))->toBeTrue()
        ->and($settings->readyFor('AddToCart'))->toBeTrue()
        // Purchase heeft geen eigen schakelaar en is geen browser-event.
        ->and($settings->eventEnabled('Purchase'))->toBeFalse();
});

it('zet elk browser-event apart uit', function () {
    Customsetting::set('meta_capi_event_view_content', false, $this->site);

    foreach (['for', 'cached', 'fresh'] as $mode) {
        $settings = MetaCapiSettings::{$mode}($this->site);

        expect($settings->eventEnabled('ViewContent'))->toBeFalse()
            ->and($settings->readyFor('ViewContent'))->toBeFalse()
            ->and($settings->readyFor('AddToCart'))->toBeTrue();
    }
});

it('is voor geen enkel browser-event klaar als de hoofdschakelaar uit staat', function () {
    Customsetting::set('meta_capi_enabled', false, $this->site);

    expect(MetaCapiSettings::fresh($this->site)->readyFor('AddToCart'))->toBeFalse()
        ->and(MetaCapiSettings::cached($this->site)->readyFor('ViewContent'))->toBeFalse();
});

it('leest met fresh() rechtstreeks uit de database, ook als de cache iets anders zegt', function () {
    // Warm de cache op met "aan", en zet de rij dan buiten Customsetting om uit: zo ziet een
    // lang levende wachtrijworker de wereld.
    expect(MetaCapiSettings::cached($this->site)->enabled())->toBeTrue();

    DB::table('dashed__custom_settings')->where('name', 'meta_capi_enabled')->where('site_id', $this->site)->update(['value' => '0']);

    expect(MetaCapiSettings::cached($this->site)->enabled())->toBeTrue()
        ->and(MetaCapiSettings::fresh($this->site)->enabled())->toBeFalse()
        ->and(MetaCapiSettings::fresh($this->site)->accessToken())->toBe('EAAB-geheim')
        ->and(MetaCapiSettings::fresh($this->site)->pixelId())->toBe('599475696120398');
});

it('laat de instellingencache met rust bij cached() en fresh()', function () {
    MetaCapiSettings::cached($this->site)->enabled();
    $spy = Cache::spy();

    MetaCapiSettings::cached($this->site)->ready();
    MetaCapiSettings::fresh($this->site)->ready();

    $spy->shouldNotHaveReceived('forget');
});
