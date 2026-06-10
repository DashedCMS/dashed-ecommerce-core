<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Str;
use Livewire\Livewire;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ProductFinder;
use Dashed\DashedEcommerceCore\Services\ProductFinder\ProductFinderMatcher;
use Dashed\DashedEcommerceCore\Livewire\Frontend\ProductFinder\ProductFinderQuiz;

function quizProduct(string $name, float $price = 10.0): Product
{
    $site = Sites::getActive() ?: 'default';
    $group = ProductGroup::create([
        'name' => ['en' => $name . ' G'], 'slug' => ['en' => Str::slug($name) . '-g'],
        'short_description' => ['en' => ''], 'description' => ['en' => ''],
        'content' => ['en' => ''], 'search_terms' => ['en' => ''], 'site_ids' => [$site],
    ]);

    return Product::withoutEvents(fn () => Product::create([
        'name' => ['en' => $name], 'slug' => ['en' => Str::slug($name)],
        'site_ids' => [$site], 'product_group_id' => $group->id,
        'public' => 1, 'use_stock' => false, 'in_stock' => true, 'stock_status' => 'in_stock',
        'price' => $price, 'current_price' => $price,
    ]));
}

function quizFinder(): ProductFinder
{
    return ProductFinder::create([
        'site_id' => Sites::getActive() ?: 'default', 'name' => 'Quiz', 'is_active' => true,
        'result_count' => 2,
        'questions' => [
            ['label' => 'Voor wie?', 'options' => [['label' => 'Mezelf'], ['label' => 'Cadeau']]],
            ['label' => 'Budget?', 'options' => [['label' => 'Laag'], ['label' => 'Hoog']]],
        ],
    ]);
}

beforeEach(function () {
    // Vervang de matcher door een fake die vaste resultaten geeft, zodat de
    // quiz-flow getest wordt zonder AI.
    $this->fakeResults = [];
    app()->bind(ProductFinderMatcher::class, function () {
        return new class ($this->fakeResults) extends ProductFinderMatcher {
            public function __construct(private array $results)
            {
            }

            public function match(ProductFinder $finder, array $answers): array
            {
                return $this->results;
            }
        };
    });
});

it('loopt de stappen door en levert resultaten op het einde', function () {
    $finder = quizFinder();
    $p = quizProduct('Alpha');
    $this->fakeResults = [['product' => $p, 'reason' => 'Past goed']];

    Livewire::test(ProductFinderQuiz::class, ['blockData' => ['finder_id' => $finder->id]])
        ->assertSet('step', 0)
        ->assertSet('finished', false)
        ->call('selectAnswer', 'Voor wie?', 'Cadeau')
        ->assertSet('step', 1)
        ->assertSet('finished', false)
        ->call('selectAnswer', 'Budget?', 'Hoog')
        ->assertSet('finished', true)
        ->assertSet('results', [[
            'id' => $p->id, 'name' => 'Alpha', 'price' => 10.0, 'reason' => 'Past goed', 'url' => $p->url ?? null,
        ]]);
});

it('herstart naar stap 0 zonder resultaten', function () {
    $finder = quizFinder();

    Livewire::test(ProductFinderQuiz::class, ['blockData' => ['finder_id' => $finder->id]])
        ->call('selectAnswer', 'Voor wie?', 'Cadeau')
        ->call('restart')
        ->assertSet('step', 0)
        ->assertSet('finished', false)
        ->assertSet('answers', []);
});
