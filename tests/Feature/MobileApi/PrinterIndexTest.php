<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Printer;
use Dashed\DashedEcommerceCore\Enums\PrinterType;
use Illuminate\Support\Facades\DB;

/**
 * GET /printers serialiseert elke rij voor het app-printerscherm. `plain_token`
 * is encrypted-cast en `type` enum-cast; één rotte rij (niet te decrypten token
 * na APP_KEY-wissel/legacy, of een onbekende type-waarde) mag de hele lijst niet
 * met een 500 laten crashen. Het CMS raakt het token niet aan, de app wel via
 * `is_paired` — vandaar dat alleen dit endpoint er last van had.
 */
function printersIndex(object $test): \Illuminate\Testing\TestResponse
{
    return $test->getJson('/api/v1/printers', ['X-Site-Id' => 'default']);
}

it('geeft een gepairde printer terug met is_paired true', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $printer = Printer::factory()->create(['name' => 'Pi pakbon', 'type' => PrinterType::PackingSlip]);
    $printer->forceFill(['plain_token' => 'plain-text-token-value'])->save();

    $res = printersIndex($this);

    $res->assertOk();
    expect(collect($res->json('data'))->firstWhere('id', $printer->id)['is_paired'])->toBeTrue();
});

it('laat de lijst niet crashen op een onleesbaar (niet te decrypten) token', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $ok = Printer::factory()->create(['name' => 'Goede printer']);
    $broken = Printer::factory()->create(['name' => 'Kapotte printer']);

    // Schrijf rauwe, niet-versleutelde rommel in de encrypted kolom, net als een
    // rij die vóór de encrypted-cast of met een ander APP_KEY is gepaird.
    DB::table('dashed__printers')->where('id', $broken->id)->update(['plain_token' => 'niet-versleuteld']);

    $res = printersIndex($this);

    $res->assertOk();
    $byId = collect($res->json('data'))->keyBy('id');
    expect($byId->has($ok->id))->toBeTrue();
    expect($byId->has($broken->id))->toBeTrue();
    expect($byId[$broken->id]['is_paired'])->toBeFalse();
});

it('laat de lijst niet crashen op een onbekende type-waarde', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $printer = Printer::factory()->create(['name' => 'Legacy type']);
    DB::table('dashed__printers')->where('id', $printer->id)->update(['type' => 'legacy_bogus']);

    $res = printersIndex($this);

    $res->assertOk();
    expect(collect($res->json('data'))->firstWhere('id', $printer->id)['type'])->toBeNull();
});
