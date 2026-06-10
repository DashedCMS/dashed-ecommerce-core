<?php

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Classes\CartHelper;
use Dashed\DashedEcommerceCore\Livewire\Frontend\ProductFinder\ProductFinderQuiz;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductFinder;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Services\ProductFinder\ProductFinderMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function quizProduct(string $name, float $price = 10.0): Product
{
    $site = Sites::getActive() ?: 'default';
    $group = ProductGroup::create([
        'name' => ['en' => $name.' G'], 'slug' => ['en' => Str::slug($name).'-g'],
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

// Een ingelogde gebruiker zorgt dat CartHelper de cart op user_id resolvet
// (niet op de cart-cookie). De cookie propageert niet vanzelf in Livewire::test,
// waardoor elke request anders een nieuwe gast-cart zou aanmaken; via user_id
// vindt zowel de Livewire-request als de assertie-scope dezelfde cart.
function loginQuizUser(): User
{
    $user = User::factory()->create();
    test()->actingAs($user);

    return $user;
}

beforeEach(function () {
    CartHelper::$cart = null;
    CartHelper::$cartItemsInitialized = false;
    CartHelper::$cartItems = [];
    CartHelper::$cartProductsById = [];

    // Vervang de matcher door een fake die vaste resultaten geeft, zodat de
    // quiz-flow getest wordt zonder AI.
    $this->fakeResults = [];
    app()->bind(ProductFinderMatcher::class, function () {
        return new class($this->fakeResults) extends ProductFinderMatcher
        {
            public function __construct(private array $results) {}

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
            'id' => $p->id, 'name' => 'Alpha', 'price' => 10.0, 'reason' => 'Past goed', 'url' => $p->getUrl(),
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

it('legt een aanbevolen product in de winkelwagen', function () {
    loginQuizUser();
    $finder = quizFinder();
    $p = quizProduct('Alpha');
    $this->fakeResults = [['product' => $p, 'reason' => 'Past goed']];

    Livewire::test(ProductFinderQuiz::class, ['blockData' => ['finder_id' => $finder->id]])
        ->call('selectAnswer', 'Voor wie?', 'Cadeau')
        ->call('selectAnswer', 'Budget?', 'Hoog')
        ->assertSet('finished', true)
        ->call('addToCart', $p->id);

    CartHelper::$cartItemsInitialized = false;
    $productIds = collect(cartHelper()->getCartItems())->pluck('id');
    expect($productIds)->toContain($p->id);
});

it('legt alle aanbevelingen in de winkelwagen', function () {
    loginQuizUser();
    $finder = quizFinder();
    $a = quizProduct('Alpha');
    $b = quizProduct('Beta');
    $this->fakeResults = [
        ['product' => $a, 'reason' => 'A'],
        ['product' => $b, 'reason' => 'B'],
    ];

    Livewire::test(ProductFinderQuiz::class, ['blockData' => ['finder_id' => $finder->id]])
        ->call('selectAnswer', 'Voor wie?', 'Cadeau')
        ->call('selectAnswer', 'Budget?', 'Hoog')
        ->call('addAll');

    CartHelper::$cartItemsInitialized = false;
    $productIds = collect(cartHelper()->getCartItems())->pluck('id');
    expect($productIds)->toContain($a->id);
    expect($productIds)->toContain($b->id);
});
