<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Classes\ProductCompleteness;

function completeProduct(array $overrides = []): array
{
    return array_merge([
        'name' => json_encode(['nl' => 'Vaas Mint', 'de' => 'Vase Mint']),
        'slug' => json_encode(['nl' => 'vaas-mint', 'de' => 'vase-mint']),
        'description' => null,
        'short_description' => null,
        'images' => json_encode(['12']),
        'price' => 29.95,
        'purchase_price' => 6.5,
        'sku' => 'SKU1',
        'ean' => '8721517700000',
        'weight' => 0.2,
        'length' => 20,
        'width' => 12,
        'height' => 12,
        'is_bundle' => 0,
    ], $overrides);
}

function completeGroup(array $overrides = []): array
{
    $doc = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Mooie vaas']]]]];

    return array_merge([
        'name' => json_encode(['nl' => 'Vaas', 'de' => 'Vase']),
        'slug' => json_encode(['nl' => 'vaas', 'de' => 'vase']),
        'description' => json_encode(['nl' => $doc, 'de' => $doc]),
        'short_description' => json_encode(['nl' => 'Kort', 'de' => 'Kurz']),
        'images' => json_encode(['1', '2']),
    ], $overrides);
}

function completeContext(array $overrides = []): array
{
    $meta = ['title' => json_encode(['nl' => 'Titel']), 'description' => json_encode(['nl' => 'Omschrijving'])];

    return array_merge([
        'product_meta' => null,
        'group_meta' => $meta,
        'has_category' => true,
        'missing_filter' => false,
        'group_product_count' => 3,
    ], $overrides);
}

it('finds nothing missing when the group supplies texts, images and seo', function () {
    expect(ProductCompleteness::evaluateProduct(completeProduct(), completeGroup(), completeContext(), 'nl', ['de']))->toBe([]);
});

it('treats an empty tiptap document and empty html as no text', function () {
    expect(ProductCompleteness::hasText(['type' => 'doc', 'content' => [['type' => 'paragraph']]]))->toBeFalse()
        ->and(ProductCompleteness::hasText('<p>&nbsp;</p>'))->toBeFalse()
        ->and(ProductCompleteness::hasText('{"type": "doc", "content": [{"type": "paragraph"}]}'))->toBeFalse()
        ->and(ProductCompleteness::hasText('<p>Hallo</p>'))->toBeTrue();
});

it('reports missing images only when product and group both have none', function () {
    $noImages = completeProduct(['images' => '[]']);

    expect(ProductCompleteness::evaluateProduct($noImages, completeGroup(), completeContext(), 'nl', []))->toContain('no_own_images')
        ->not->toContain('no_images')
        ->and(ProductCompleteness::evaluateProduct($noImages, completeGroup(['images' => '[]']), completeContext(), 'nl', []))->toContain('no_images')
        ->and(ProductCompleteness::evaluateProduct($noImages, completeGroup(), completeContext(['group_product_count' => 1]), 'nl', []))->not->toContain('no_own_images');
});

it('reports missing translations and feed data', function () {
    $product = completeProduct([
        'name' => json_encode(['nl' => 'Vaas Mint']),
        'ean' => '',
        'weight' => 0,
        'height' => null,
        'purchase_price' => null,
    ]);
    $group = completeGroup(['description' => json_encode(['nl' => '<p>Tekst</p>'])]);

    expect(ProductCompleteness::evaluateProduct($product, $group, completeContext(['has_category' => false, 'missing_filter' => true]), 'nl', ['de']))
        ->toEqualCanonicalizing(['name_de', 'description_de', 'no_ean', 'no_weight', 'no_dimensions', 'no_purchase_price', 'no_category', 'missing_filter']);
});

it('does not ask to translate a description that is missing in the main locale', function () {
    $group = completeGroup(['description' => null]);

    expect(ProductCompleteness::evaluateProduct(completeProduct(), $group, completeContext(), 'nl', ['de']))
        ->toContain('no_description')
        ->not->toContain('description_de');
});

it('evaluates a group on its own fields', function () {
    expect(ProductCompleteness::evaluateGroup(completeGroup(['images' => null]), ['group_meta' => null, 'has_category' => false], 'nl', ['de']))
        ->toEqualCanonicalizing(['no_images', 'no_seo_title', 'no_seo_description', 'no_category']);
});
