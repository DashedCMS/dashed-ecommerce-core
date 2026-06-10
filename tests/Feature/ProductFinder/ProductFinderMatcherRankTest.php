<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Str;
use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductFinder;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Services\ProductFinder\ProductFinderMatcher;

function rankProduct(string $name, float $price = 10.0): Product
{
    $group = ProductGroup::create([
        'name' => ['en' => $name . ' G'], 'slug' => ['en' => Str::slug($name) . '-g'],
        'short_description' => ['en' => ''], 'description' => ['en' => ''],
        'content' => ['en' => ''], 'search_terms' => ['en' => ''], 'site_ids' => ['default'],
    ]);

    return Product::withoutEvents(fn () => Product::create([
        'name' => ['en' => $name], 'slug' => ['en' => Str::slug($name)],
        'site_ids' => ['default'], 'product_group_id' => $group->id,
        'public' => 1, 'use_stock' => false, 'in_stock' => true, 'stock_status' => 'in_stock',
        'price' => $price, 'current_price' => $price,
    ]));
}

function rankFinder(int $resultCount = 2): ProductFinder
{
    return ProductFinder::create(['site_id' => 'default', 'name' => 'F', 'is_active' => true,
        'questions' => [], 'result_count' => $resultCount]);
}

it('rankt de door de AI gekozen producten met redenen, gecapt op result_count', function () {
    $a = rankProduct('Alpha'); $b = rankProduct('Beta'); $c = rankProduct('Gamma');
    $candidates = collect([$a, $b, $c]);

    Ai::shouldReceive('hasProvider')->andReturn(true);
    Ai::shouldReceive('json')->once()->andReturn(['results' => [
        ['id' => $c->id, 'reason' => 'Past het best'],
        ['id' => $a->id, 'reason' => 'Goede tweede'],
        ['id' => $b->id, 'reason' => 'Derde'],
    ]]);

    $result = (new ProductFinderMatcher())->rank(rankFinder(2), ['Voor wie?' => 'Cadeau'], $candidates);

    expect($result)->toHaveCount(2);
    expect($result[0]['product']->id)->toBe($c->id);
    expect($result[0]['reason'])->toBe('Past het best');
    expect($result[1]['product']->id)->toBe($a->id);
});

it('filtert onbekende ids uit de AI-output', function () {
    $a = rankProduct('Alpha');
    Ai::shouldReceive('hasProvider')->andReturn(true);
    Ai::shouldReceive('json')->once()->andReturn(['results' => [
        ['id' => 999999, 'reason' => 'bestaat niet'],
        ['id' => $a->id, 'reason' => 'wel'],
    ]]);

    $result = (new ProductFinderMatcher())->rank(rankFinder(4), [], collect([$a]));

    expect($result)->toHaveCount(1);
    expect($result[0]['product']->id)->toBe($a->id);
});

it('valt terug op de eerste N kandidaten als de AI null/leeg teruggeeft', function () {
    $a = rankProduct('Alpha'); $b = rankProduct('Beta'); $c = rankProduct('Gamma');
    Ai::shouldReceive('hasProvider')->andReturn(true);
    Ai::shouldReceive('json')->once()->andReturn(null);

    $result = (new ProductFinderMatcher())->rank(rankFinder(2), [], collect([$a, $b, $c]));

    expect($result)->toHaveCount(2);
    expect($result[0]['product']->id)->toBe($a->id);
    expect($result[0]['reason'])->toBe('');
});

it('valt terug op kandidaten zonder AI-provider', function () {
    $a = rankProduct('Alpha');
    Ai::shouldReceive('hasProvider')->andReturn(false);

    $result = (new ProductFinderMatcher())->rank(rankFinder(4), [], collect([$a]));

    expect($result)->toHaveCount(1);
    expect($result[0]['product']->id)->toBe($a->id);
});

it('geeft een lege lijst zonder kandidaten', function () {
    Ai::shouldReceive('hasProvider')->andReturn(true);
    expect((new ProductFinderMatcher())->rank(rankFinder(4), [], collect()))->toBe([]);
});

it('valt terug bij niet-array rijen in de AI-output (geen crash)', function () {
    $a = rankProduct('Alpha'); $b = rankProduct('Beta');
    Ai::shouldReceive('hasProvider')->andReturn(true);
    Ai::shouldReceive('json')->once()->andReturn(['results' => ['Alpha', 'Beta']]); // scalars, ongeldig

    $result = (new ProductFinderMatcher())->rank(rankFinder(2), [], collect([$a, $b]));

    expect($result)->toHaveCount(2); // valt terug op de kandidaten
    expect($result[0]['product']->id)->toBe($a->id);
});
