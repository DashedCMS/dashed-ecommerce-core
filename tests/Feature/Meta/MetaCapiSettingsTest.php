<?php

use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Crypt;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Services\Meta\MetaCapiSettings;

beforeEach(function () {
    $this->site = Sites::getActive();
});

it('staat standaard uit en is dan niet klaar om te versturen', function () {
    $settings = MetaCapiSettings::for($this->site);

    expect($settings->enabled())->toBeFalse()
        ->and($settings->ready())->toBeFalse()
        ->and($settings->valueMode())->toBe('incl_vat')
        ->and($settings->graphVersion())->toBe('v26.0');
});

it('slaat het token versleuteld op en leest het ontsleuteld terug', function () {
    MetaCapiSettings::for($this->site)->storeAccessToken('EAAB-geheim');

    $raw = Customsetting::get('meta_capi_access_token', $this->site, null, null, 'default', true);

    expect($raw)->not->toContain('EAAB-geheim')
        ->and(Crypt::decryptString($raw))->toBe('EAAB-geheim')
        ->and(MetaCapiSettings::for($this->site)->accessToken())->toBe('EAAB-geheim');
});

it('laat het bestaande token staan als het veld leeg wordt opgeslagen', function () {
    MetaCapiSettings::for($this->site)->storeAccessToken('EAAB-geheim');
    MetaCapiSettings::for($this->site)->storeAccessToken('');
    MetaCapiSettings::for($this->site)->storeAccessToken(null);

    expect(MetaCapiSettings::for($this->site)->accessToken())->toBe('EAAB-geheim');
});

it('geeft geen token bij een waarde die niet te ontsleutelen is', function () {
    Customsetting::set('meta_capi_access_token', 'geen-geldige-versleuteling', $this->site);

    expect(MetaCapiSettings::for($this->site)->accessToken())->toBeNull()
        ->and(MetaCapiSettings::for($this->site)->hasAccessToken())->toBeFalse();
});

it('neemt het pixel-ID uit conversion_id en valt terug op site_id', function () {
    Customsetting::set('facebook_pixel_site_id', '222', $this->site);
    expect(MetaCapiSettings::for($this->site)->pixelId())->toBe('222');

    Customsetting::set('facebook_pixel_conversion_id', '111', $this->site);
    expect(MetaCapiSettings::for($this->site)->pixelId())->toBe('111');
});

it('is klaar met toggle, pixel-ID en token, en valt bij een onbekende value mode terug op incl_vat', function () {
    Customsetting::set('meta_capi_enabled', true, $this->site);
    Customsetting::set('facebook_pixel_conversion_id', '111', $this->site);
    Customsetting::set('meta_capi_value_mode', 'onzin', $this->site);
    MetaCapiSettings::for($this->site)->storeAccessToken('EAAB-geheim');

    $settings = MetaCapiSettings::for($this->site);

    expect($settings->ready())->toBeTrue()
        ->and($settings->valueMode())->toBe('incl_vat');
});

it('leest de Graph-versie uit de config van de app', function () {
    config()->set('services.meta.graph_version', 'v27.0');

    expect(MetaCapiSettings::for($this->site)->graphVersion())->toBe('v27.0');
});
