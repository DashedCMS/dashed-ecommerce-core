<?php

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedFiles\Classes\MediaHelper;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;

/**
 * De mobile-api product-write-endpoints uploaden foto's via de echte
 * MediaHelper (spatie media-library). Dat is in een package-test lastig te
 * fingeren, dus binden we een lichte fake die deterministische media-ids
 * teruggeeft en de bijbehorende getSingleMedia()-lookups beantwoordt — zodat
 * removeInvalidImages() de zojuist toegevoegde foto niet wegstript.
 */
function bindFakeMediaHelper(): void
{
    Storage::fake('dashed');

    // UpdateProductInformationJob draait normaal synchroon in tests en gebruikt
    // MySQL-only SQL (GREATEST) in calculatePrices(). We faken de queue zodat de
    // dispatch (de side-effect die we wíllen) gebeurt zonder die SQL op SQLite.
    Queue::fake();

    $fake = new class extends MediaHelper
    {
        public int $nextId = 9000;

        public function __construct()
        {
        }

        public function uploadFromPath($path, $folder, bool $isExternalImage = false): ?int
        {
            return $this->nextId++;
        }

        public function getSingleMedia($mediaId, $manipulations = [])
        {
            if (! $mediaId) {
                return null;
            }

            return (object) ['url' => "https://cdn.test/media/{$mediaId}.jpg"];
        }
    };

    app()->instance(MediaHelper::class, $fake);
}

it('creates a product with name, price and an uploaded image', function () {
    bindFakeMediaHelper();
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $response = $this->postJson('/api/v1/products', [
        'name' => 'Test product',
        'price' => 19.95,
        'vat_rate' => 21,
        'images' => [UploadedFile::fake()->image('photo.jpg')],
    ], ['X-Site-Id' => 'site']);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Test product')
        ->assertJsonPath('data.price', 19.95);

    $product = Product::first();
    expect($product)->not->toBeNull()
        ->and($product->name)->toBe('Test product')
        ->and((float) $product->price)->toBe(19.95)
        ->and($product->images)->toBeArray()
        ->and(count($product->images))->toBe(1)
        ->and($product->product_group_id)->not->toBeNull();

    // Canonieke side-effect: de product-info-job is via de saved-hook gedispatcht.
    Queue::assertPushed(UpdateProductInformationJob::class);
});

it('updates a product price and appends an image', function () {
    bindFakeMediaHelper();
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $group = ProductGroup::create([
        'name' => ['en' => 'Group'], 'slug' => ['en' => 'group-' . uniqid()],
        'short_description' => ['en' => ''], 'description' => ['en' => ''],
        'content' => ['en' => ''], 'search_terms' => ['en' => ''],
        'site_ids' => ['site'],
    ]);
    $product = Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $group->id,
        'name' => ['en' => 'Old name'],
        'slug' => ['en' => 'old-' . uniqid()],
        'site_ids' => ['site'],
        'price' => 10, 'current_price' => 10, 'vat_rate' => 21,
        'images' => [],
    ]));

    $response = $this->putJson("/api/v1/products/{$product->id}", [
        'price' => 25,
        'images' => [UploadedFile::fake()->image('new.jpg')],
    ], ['X-Site-Id' => 'site']);

    $response->assertStatus(200);
    expect((float) $response->json('data.price'))->toBe(25.0);

    $product->refresh();
    expect((float) $product->price)->toBe(25.0)
        ->and(count($product->images))->toBe(1);
});

it('rejects product writes without the products.write ability', function () {
    bindFakeMediaHelper();
    $user = User::factory()->create(['role' => 'customer']);
    $this->actingAs($user, 'sanctum');

    $this->postJson('/api/v1/products', [
        'name' => 'Nope',
        'price' => 1,
    ], ['X-Site-Id' => 'site'])->assertStatus(403);
});

it('validates required fields on create', function () {
    bindFakeMediaHelper();
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $this->postJson('/api/v1/products', [
        // name + price ontbreken
        'vat_rate' => 21,
    ], ['X-Site-Id' => 'site'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'price']);
});
