<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ProductFilter;
use Dashed\DashedEcommerceCore\Models\ProductFilterOption;
use Dashed\DashedEcommerceCore\Jobs\CreateMissingProductVariationsJob;

/**
 * De "Ontbrekende variaties aanmaken"-knop laat de gebruiker kiezen welke
 * variaties aangemaakt worden. De job maakt alleen de meegegeven selectie aan
 * (niet alle mogelijke), zodat je bv. 2 van de 14 kunt aanmaken.
 */
it('creates only the passed variation selection, not all possible ones', function () {
    Queue::fake(); // onderdruk UpdateProductInformationJob (Product::saved observer + job-einde)

    $group = ProductGroup::create([
        'name' => ['nl' => 'Shirt'],
        'slug' => ['nl' => 'shirt-' . uniqid()],
        'short_description' => ['nl' => ''],
        'description' => ['nl' => ''],
        'content' => ['nl' => ''],
        'search_terms' => ['nl' => ''],
        'site_ids' => ['site'],
    ]);

    $maat = ProductFilter::create(['name' => ['nl' => 'Maat']]);
    $s = ProductFilterOption::create(['product_filter_id' => $maat->id, 'name' => ['nl' => 'S']]);
    $m = ProductFilterOption::create(['product_filter_id' => $maat->id, 'name' => ['nl' => 'M']]);
    $l = ProductFilterOption::create(['product_filter_id' => $maat->id, 'name' => ['nl' => 'L']]);

    // Er zijn 3 mogelijke; we kiezen er 2.
    (new CreateMissingProductVariationsJob($group, [[$s->id], [$m->id]]))->handle();

    expect($group->products()->count())->toBe(2);

    $createdOptionIds = DB::table('dashed__product_filter')
        ->whereIn('product_id', $group->products()->pluck('id'))
        ->pluck('product_filter_option_id')
        ->all();

    expect($createdOptionIds)->toContain($s->id)
        ->and($createdOptionIds)->toContain($m->id)
        ->and($createdOptionIds)->not->toContain($l->id);
});

it('builds a readable checklist of every missing variation for the modal', function () {
    $group = ProductGroup::create([
        'name' => ['nl' => 'Shirt'], 'slug' => ['nl' => 'shirt-' . uniqid()],
        'short_description' => ['nl' => ''], 'description' => ['nl' => ''],
        'content' => ['nl' => ''], 'search_terms' => ['nl' => ''], 'site_ids' => ['site'],
    ]);

    $maat = ProductFilter::create(['name' => ['nl' => 'Maat']]);
    $s = ProductFilterOption::create(['product_filter_id' => $maat->id, 'name' => ['nl' => 'S']]);
    $m = ProductFilterOption::create(['product_filter_id' => $maat->id, 'name' => ['nl' => 'M']]);
    $kleur = ProductFilter::create(['name' => ['nl' => 'Kleur']]);
    $rood = ProductFilterOption::create(['product_filter_id' => $kleur->id, 'name' => ['nl' => 'Rood']]);
    $blauw = ProductFilterOption::create(['product_filter_id' => $kleur->id, 'name' => ['nl' => 'Blauw']]);

    DB::table('dashed__active_product_filter')->insert([
        ['product_group_id' => $group->id, 'product_filter_id' => $maat->id, 'use_for_variations' => 1],
        ['product_group_id' => $group->id, 'product_filter_id' => $kleur->id, 'use_for_variations' => 1],
    ]);
    DB::table('dashed__product_enabled_filter_options')->insert([
        ['product_group_id' => $group->id, 'product_filter_id' => $maat->id, 'product_filter_option_id' => $s->id],
        ['product_group_id' => $group->id, 'product_filter_id' => $maat->id, 'product_filter_option_id' => $m->id],
        ['product_group_id' => $group->id, 'product_filter_id' => $kleur->id, 'product_filter_option_id' => $rood->id],
        ['product_group_id' => $group->id, 'product_filter_id' => $kleur->id, 'product_filter_option_id' => $blauw->id],
    ]);

    app()->setLocale('nl');

    $options = \Dashed\DashedEcommerceCore\Filament\Resources\ProductGroupResource::missingVariationOptions($group->fresh());

    // 2 maten x 2 kleuren = 4 mogelijke, geen bestaande producten -> 4 ontbrekend.
    expect($options)->toHaveCount(4);
    // labels bevatten de filter- en optienamen.
    $joined = implode(' | ', $options);
    expect($joined)->toContain('Maat: S')->toContain('Kleur: Rood')->toContain('Maat: M')->toContain('Kleur: Blauw');
    // keys zijn de option-id-combinaties (decodeerbaar door de actie).
    expect(array_keys($options)[0])->toMatch('/^\d+-\d+$/');
});

/**
 * Een variatie die je uitsluit wordt niet meer voorgesteld: niet in de
 * vinklijst, niet in de badge, en niet door de job die zonder selectie draait.
 * Terugzetten haalt de uitsluiting weer weg.
 */
function makeGroupWithSizes(): array
{
    $group = ProductGroup::create([
        'name' => ['nl' => 'Shirt'], 'slug' => ['nl' => 'shirt-' . uniqid()],
        'short_description' => ['nl' => ''], 'description' => ['nl' => ''],
        'content' => ['nl' => ''], 'search_terms' => ['nl' => ''], 'site_ids' => ['site'],
    ]);

    $maat = ProductFilter::create(['name' => ['nl' => 'Maat']]);
    $s = ProductFilterOption::create(['product_filter_id' => $maat->id, 'name' => ['nl' => 'S']]);
    $m = ProductFilterOption::create(['product_filter_id' => $maat->id, 'name' => ['nl' => 'M']]);
    $l = ProductFilterOption::create(['product_filter_id' => $maat->id, 'name' => ['nl' => 'L']]);

    DB::table('dashed__active_product_filter')->insert([
        ['product_group_id' => $group->id, 'product_filter_id' => $maat->id, 'use_for_variations' => 1],
    ]);
    DB::table('dashed__product_enabled_filter_options')->insert([
        ['product_group_id' => $group->id, 'product_filter_id' => $maat->id, 'product_filter_option_id' => $s->id],
        ['product_group_id' => $group->id, 'product_filter_id' => $maat->id, 'product_filter_option_id' => $m->id],
        ['product_group_id' => $group->id, 'product_filter_id' => $maat->id, 'product_filter_option_id' => $l->id],
    ]);

    return [$group->fresh(), $s, $m, $l];
}

it('leaves an excluded variation out of the missing variations', function () {
    [$group, $s, $m, $l] = makeGroupWithSizes();

    expect($group->missingVariations())->toHaveCount(3);

    $group->excludeVariations([[$l->id]]);

    $missing = collect($group->fresh()->missingVariations())->map(fn ($v) => array_values($v))->all();
    expect($missing)->toHaveCount(2)
        ->and($missing)->toContain([$s->id])
        ->and($missing)->toContain([$m->id])
        ->and($missing)->not->toContain([$l->id]);
});

it('does not create an excluded variation when the job runs without a selection', function () {
    Queue::fake();
    [$group, $s, $m, $l] = makeGroupWithSizes();

    $group->excludeVariations([[$l->id]]);

    (new CreateMissingProductVariationsJob($group->fresh()))->handle();

    $createdOptionIds = DB::table('dashed__product_filter')
        ->whereIn('product_id', $group->products()->pluck('id'))
        ->pluck('product_filter_option_id')
        ->all();

    expect($group->products()->count())->toBe(2)
        ->and($createdOptionIds)->not->toContain($l->id);
});

it('proposes a restored variation again', function () {
    [$group, $s, $m, $l] = makeGroupWithSizes();

    $group->excludeVariations([[$l->id]]);
    expect($group->fresh()->missingVariations())->toHaveCount(2);

    $group->fresh()->restoreVariations([[$l->id]]);

    expect($group->fresh()->missingVariations())->toHaveCount(3)
        ->and($group->fresh()->excluded_variations)->toBe([]);
});

it('matches an exclusion regardless of the option order', function () {
    [$group, $s, $m, $l] = makeGroupWithSizes();
    $kleur = ProductFilter::create(['name' => ['nl' => 'Kleur']]);
    $rood = ProductFilterOption::create(['product_filter_id' => $kleur->id, 'name' => ['nl' => 'Rood']]);
    DB::table('dashed__active_product_filter')->insert([
        ['product_group_id' => $group->id, 'product_filter_id' => $kleur->id, 'use_for_variations' => 1],
    ]);
    DB::table('dashed__product_enabled_filter_options')->insert([
        ['product_group_id' => $group->id, 'product_filter_id' => $kleur->id, 'product_filter_option_id' => $rood->id],
    ]);

    // Omgekeerde volgorde t.o.v. hoe possibleVariations() de combinatie oplevert.
    $group->fresh()->excludeVariations([[$rood->id, $l->id]]);

    expect($group->fresh()->missingVariations())->toHaveCount(2);
});

it('builds a readable checklist of the excluded variations for the restore modal', function () {
    [$group, $s, $m, $l] = makeGroupWithSizes();
    app()->setLocale('nl');

    $group->excludeVariations([[$l->id], [$s->id]]);

    $options = \Dashed\DashedEcommerceCore\Filament\Resources\ProductGroupResource::excludedVariationOptions($group->fresh());

    expect($options)->toHaveCount(2)
        ->and($options)->toHaveKey((string) $l->id)
        ->and($options[(string) $l->id])->toBe('Maat: L');
});
