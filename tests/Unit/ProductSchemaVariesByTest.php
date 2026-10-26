<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Tests\Unit;

use Spatie\SchemaOrg\Schema;
use PHPUnit\Framework\TestCase;
use Dashed\DashedEcommerceCore\Classes\Seo\ProductSchema;

/**
 * Google verwacht schema.org-eigenschappen in variesBy, alleen voor wat echt
 * verschilt; "Kleuren" en "Hoogte" letterlijk gaven fouten in Search Console.
 * Zuiver: zonder app, dus als PHPUnit-klasse in plaats van een Pest-test met harnas.
 */
class ProductSchemaVariesByTest extends TestCase
{
    public function test_filter_names_in_any_language_map_to_schema_org_properties(): void
    {
        $this->assertSame('color', ProductSchema::variationProperty('Kleuren'));
        $this->assertSame('size', ProductSchema::variationProperty(' Hoogte '));
        $this->assertSame('color', ProductSchema::variationProperty('Farbe'));
        $this->assertSame('size', ProductSchema::variationProperty('Größe'));
        $this->assertNull(ProductSchema::variationProperty('Gravure'));
    }

    public function test_only_properties_that_differ_between_variants_are_listed(): void
    {
        $variants = [
            Schema::product()->setProperty('color', 'Zwart')->setProperty('size', '17.5 CM'),
            Schema::product()->setProperty('color', 'Wit')->setProperty('size', '17.5 CM'),
        ];

        $this->assertSame(['https://schema.org/color'], ProductSchema::variesBy($variants));
    }

    public function test_variesby_is_empty_when_nothing_differs(): void
    {
        $variants = [Schema::product()->setProperty('color', 'Zwart'), Schema::product()->setProperty('color', 'Zwart')];

        $this->assertSame([], ProductSchema::variesBy($variants));
    }
}
