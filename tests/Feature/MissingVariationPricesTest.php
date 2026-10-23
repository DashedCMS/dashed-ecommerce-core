<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ProductFilter;
use Dashed\DashedEcommerceCore\Models\ProductFilterOption;
use Dashed\DashedEcommerceCore\Classes\VariationPriceSuggester;
use Dashed\DashedEcommerceCore\Jobs\CreateMissingProductVariationsJob;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductGroupResource;

/**
 * Bij "Ontbrekende variaties aanmaken" kan de prijs komen van bestaande
 * producten met dezelfde optie op een gekozen filter (bv. Maat).
 */
function priceGroup(): array
{
    Queue::fake();

    $group = ProductGroup::create([
        'name' => ['nl' => 'Vaas'], 'slug' => ['nl' => 'vaas-' . uniqid()],
        'short_description' => ['nl' => ''], 'description' => ['nl' => ''],
        'content' => ['nl' => ''], 'search_terms' => ['nl' => ''], 'site_ids' => ['site'],
    ]);

    $maat = ProductFilter::create(['name' => ['nl' => 'Maat']]);
    $klein = ProductFilterOption::create(['product_filter_id' => $maat->id, 'name' => ['nl' => '20CM']]);
    $groot = ProductFilterOption::create(['product_filter_id' => $maat->id, 'name' => ['nl' => '30CM']]);
    $kleur = ProductFilter::create(['name' => ['nl' => 'Kleur']]);
    $rood = ProductFilterOption::create(['product_filter_id' => $kleur->id, 'name' => ['nl' => 'Rood']]);
    $blauw = ProductFilterOption::create(['product_filter_id' => $kleur->id, 'name' => ['nl' => 'Blauw']]);
    $groen = ProductFilterOption::create(['product_filter_id' => $kleur->id, 'name' => ['nl' => 'Groen']]);

    DB::table('dashed__active_product_filter')->insert([
        ['product_group_id' => $group->id, 'product_filter_id' => $maat->id, 'use_for_variations' => 1],
        ['product_group_id' => $group->id, 'product_filter_id' => $kleur->id, 'use_for_variations' => 1],
    ]);
    foreach ([[$maat, $klein], [$maat, $groot], [$kleur, $rood], [$kleur, $blauw], [$kleur, $groen]] as [$filter, $option]) {
        DB::table('dashed__product_enabled_filter_options')->insert([
            'product_group_id' => $group->id, 'product_filter_id' => $filter->id, 'product_filter_option_id' => $option->id,
        ]);
    }

    $maak = function (array $options, float $price, ?float $newPrice = null) use ($group) {
        $product = new Product();
        $product->setTranslation('name', 'nl', 'Vaas ' . uniqid());
        $product->setTranslation('slug', 'nl', 'vaas-' . uniqid());
        $product->site_ids = ['site'];
        $product->product_group_id = $group->id;
        $product->price = $price;
        $product->new_price = $newPrice;
        $product->save();
        foreach ($options as $option) {
            DB::table('dashed__product_filter')->insert([
                'product_id' => $product->id,
                'product_filter_id' => $option->product_filter_id,
                'product_filter_option_id' => $option->id,
            ]);
        }
    };

    // 20CM: twee keer 24,95 en een keer 29,95; 30CM: 39,95 met van-prijs 44,95.
    $maak([$klein, $rood], 24.95);
    $maak([$klein, $blauw], 24.95);
    $maak([$klein, $groen], 29.95);
    $maak([$groot, $rood], 39.95, 44.95);

    return compact('group', 'maat', 'kleur', 'klein', 'groot', 'rood', 'blauw', 'groen');
}

it('takes the most common price of products with the same option on the chosen filter', function () {
    $d = priceGroup();

    $prices = VariationPriceSuggester::forVariations(
        $d['group'],
        [[$d['groot']->id, $d['blauw']->id], [$d['klein']->id, $d['blauw']->id]],
        [$d['maat']->id]
    );

    expect($prices[$d['groot']->id . '-' . $d['blauw']->id])->toMatchArray(['price' => 39.95, 'new_price' => 44.95, 'matches' => 1])
        ->and($prices[$d['klein']->id . '-' . $d['blauw']->id])->toMatchArray(['price' => 24.95, 'new_price' => null, 'matches' => 3]);
});

it('returns no price when no product shares all chosen options', function () {
    $d = priceGroup();

    $prices = VariationPriceSuggester::forVariations(
        $d['group'],
        [[$d['groot']->id, $d['groen']->id]],
        [$d['maat']->id, $d['kleur']->id]
    );

    expect($prices[$d['groot']->id . '-' . $d['groen']->id])->toBeNull();
});

it('shows the price per variation in the checklist', function () {
    $d = priceGroup();
    app()->setLocale('nl');

    $options = ProductGroupResource::missingVariationOptions($d['group']->fresh(), [$d['maat']->id]);

    // De sleutelvolgorde volgt de filtervolgorde van de groep, dus zoek op label.
    $label = collect($options)->first(fn ($label) => str_contains($label, 'Maat: 30CM') && str_contains($label, 'Kleur: Blauw'));
    $zonderPrijs = collect($options)->first(fn ($label) => str_contains($label, 'Maat: 30CM') && str_contains($label, 'Kleur: Groen'));

    expect($options)->toHaveCount(2)
        ->and($label)->toContain('39,95')->toContain('44,95')
        ->and($zonderPrijs)->toContain('39,95')
        ->and(ProductGroupResource::missingVariationOptions($d['group']->fresh()))->each->not->toContain('→');
});

it('creates the variation with the suggested price', function () {
    $d = priceGroup();
    $variation = [$d['groot']->id, $d['blauw']->id];
    $prices = VariationPriceSuggester::forVariations($d['group'], [$variation], [$d['maat']->id]);

    (new CreateMissingProductVariationsJob($d['group'], [$variation], $prices))->handle();

    $created = Product::where('product_group_id', $d['group']->id)->latest('id')->first();

    expect((float) $created->price)->toBe(39.95)
        ->and((float) $created->new_price)->toBe(44.95);
});

it('does not mark a combination as existing when its options are spread over different products', function () {
    $d = priceGroup();

    // 30CM bestaat (met Rood) en Blauw bestaat (met 20CM), maar 30CM + Blauw niet.
    expect($d['group']->fresh()->variationExists([$d['groot']->id, $d['blauw']->id]))->toBeFalse()
        ->and($d['group']->fresh()->variationExists([$d['groot']->id, $d['rood']->id]))->toBeTrue();
});
