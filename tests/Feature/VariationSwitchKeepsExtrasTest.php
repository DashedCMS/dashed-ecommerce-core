<?php

declare(strict_types=1);

use Livewire\Livewire;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ProductExtraOption;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Cart\AddToCart;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Een extra op een product of een groep (global, gekoppeld aan de groep).
 *
 * @return array{0: ProductExtra, 1: array<string, ProductExtraOption>}
 */
function vskExtra(Product|ProductGroup $owner, string $name, string $type, array $optionValues = []): array
{
    $extra = ProductExtra::create(['name' => ['nl' => $name], 'type' => $type]);

    if ($owner instanceof ProductGroup) {
        $extra->forceFill(['global' => 1])->save();
        DB::table('dashed__product_extra_product_groups')->insert([
            'product_group_id' => $owner->id,
            'product_extra_id' => $extra->id,
        ]);
    } else {
        $extra->forceFill(['product_id' => $owner->id])->save();
    }

    $options = [];
    foreach ($optionValues as $value) {
        $options[$value] = ProductExtraOption::create(['product_extra_id' => $extra->id, 'value' => $value, 'price' => 1]);
    }

    return [$extra, $options];
}

function vskGroup(): array
{
    [$group, [$s, $m]] = feedGroup('Maat', ['S', 'M']);

    $sizeOption = fn (Product $p) => DB::table('dashed__product_filter')->where('product_id', $p->id)->value('product_filter_option_id');

    return [$group, $s, $m, $sizeOption($s), $sizeOption($m)];
}

it('houdt de gekozen opties van gedeelde extras vast bij het wisselen van variatie', function () {
    [$group, $s, $m, $sOption, $mOption] = vskGroup();
    [, $kleur] = vskExtra($group, 'Kleur', 'single', ['Rood', 'Blauw']);
    vskExtra($group, 'Gravure', 'input');
    [, $boxen] = vskExtra($group, 'Boxen', 'multiple', ['Doos', 'Zak']);
    [, $cadeau] = vskExtra($group, 'Cadeauverpakking', 'checkbox', ['Ja']);

    $component = Livewire::test(AddToCart::class, ['product' => $s, 'view' => 'add-to-cart-small'])
        ->set('extras.0.value', (string) $kleur['Blauw']->id)
        ->set('extras.1.value', 'Voor Robin')
        ->set('extras.2.value', [(string) $boxen['Zak']->id])
        ->set('extras.3.value', (string) $cadeau['Ja']->id)
        ->call('setFilterValue', 0, $mOption);

    expect($component->get('product')->id)->toBe($m->id)
        ->and((int) $component->get('extras.0.value'))->toBe($kleur['Blauw']->id)
        ->and($component->get('extras.1.value'))->toBe('Voor Robin')
        ->and(array_map('intval', $component->get('extras.2.value')))->toBe([$boxen['Zak']->id])
        ->and((int) $component->get('extras.3.value'))->toBe($cadeau['Ja']->id);
});

it('neemt een eigen extra met dezelfde naam over op de optiewaarde, en laat een optie die niet meer bestaat leeg', function () {
    [$group, $s, $m, $sOption, $mOption] = vskGroup();
    [, $opS] = vskExtra($s, 'Afwerking', 'single', ['Mat', 'Glans']);
    [, $opM] = vskExtra($m, 'Afwerking', 'single', ['Mat']);
    vskExtra($s, 'Alleen op S', 'input');

    $component = Livewire::test(AddToCart::class, ['product' => $s, 'view' => 'add-to-cart-small'])
        ->set('extras.0.value', (string) $opS['Mat']->id)
        ->set('extras.1.value', 'verdwijnt')
        ->call('setFilterValue', 0, $mOption);

    expect($component->get('product')->id)->toBe($m->id)
        ->and((int) $component->get('extras.0.value'))->toBe($opM['Mat']->id)
        ->and($component->get('extras'))->toHaveCount(1);

    $component = Livewire::test(AddToCart::class, ['product' => $s, 'view' => 'add-to-cart-small'])
        ->set('extras.0.value', (string) $opS['Glans']->id)
        ->call('setFilterValue', 0, $mOption);

    expect($component->get('extras.0.value'))->toBeNull();
});

it('houdt bij meerdere opties alleen de opties die nog bestaan en eigen tekstwaarden', function () {
    [$group, $s, $m, $sOption, $mOption] = vskGroup();
    [, $opS] = vskExtra($s, 'Toppings', 'multiple', ['Kaas', 'Ui']);
    [, $opM] = vskExtra($m, 'Toppings', 'multiple', ['Kaas']);

    $component = Livewire::test(AddToCart::class, ['product' => $s, 'view' => 'add-to-cart-small'])
        ->set('extras.0.value', [(string) $opS['Kaas']->id, (string) $opS['Ui']->id, 'extra pittig'])
        ->call('setFilterValue', 0, $mOption);

    expect($component->get('extras.0.value'))->toBe([(string) $opM['Kaas']->id, 'extra pittig']);
});

it('houdt de opties ook vast als de site na een variatiewissel doorstuurt naar de nieuwe productpagina', function () {
    [$group, $s, $m, $sOption, $mOption] = vskGroup();
    [, $kleur] = vskExtra($group, 'Kleur', 'single', ['Rood', 'Blauw']);
    Customsetting::set('product_redirect_after_new_variation_selected', true);

    Livewire::test(AddToCart::class, ['product' => $s, 'view' => 'add-to-cart-small'])
        ->set('extras.0.value', (string) $kleur['Rood']->id)
        ->call('setFilterValue', 0, $mOption)
        ->assertRedirect($m->getUrl(forceOwnUrl: true));

    $component = Livewire::test(AddToCart::class, ['product' => $m, 'view' => 'add-to-cart-small']);

    expect((int) $component->get('extras.0.value'))->toBe($kleur['Rood']->id);

    // De meegegeven keuze geldt één keer: een volgende paginalading begint schoon.
    expect(Livewire::test(AddToCart::class, ['product' => $m, 'view' => 'add-to-cart-small'])->get('extras.0.value'))->toBeNull();
});
