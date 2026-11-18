<?php

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Classes\ParcelType;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ShippingZone;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;
use Dashed\DashedEcommerceCore\Support\Automation\LabelCreator;
use Dashed\DashedEcommerceCore\Http\Controllers\Api\V1\OrderController;
use Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile\OrderResource;
use Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile\OrderSummaryResource;

/**
 * Stand-in for a carrier label row (VeloydOrder / MyParcelOrder): the carrier
 * packages are not loaded in this harness, so the stored-label step is tested
 * through a test-only dynamic relation on a temporary table.
 */
class ParcelTypeTestLabel extends Model
{
    protected $table = 'parcel_type_test_labels';

    protected $guarded = [];
}

/** A carrier label table from before the return-label migrations (no is_return). */
class ParcelTypeOldLabel extends Model
{
    protected $table = 'parcel_type_old_labels';

    protected $guarded = [];
}

if (! function_exists('parcelTypeOrder')) {
    function parcelTypeOrder(array $attrs = []): Order
    {
        return Order::create(array_merge([
            'site_id' => 'default',
            'email' => 'klant@example.com',
            'invoice_id' => 'INV-PT-' . strtoupper(Str::random(6)),
            'status' => 'paid',
            'country' => 'NL',
            'hash' => Str::random(32),
        ], $attrs));
    }

    function parcelTypeShippingMethod(string $sort, string $name = 'Gratis verzending'): ShippingMethod
    {
        $zone = ShippingZone::create(['site_id' => 'default', 'name' => ['nl' => 'Nederland']]);

        return ShippingMethod::create(['shipping_zone_id' => $zone->id, 'name' => ['nl' => $name], 'sort' => $sort]);
    }

    function parcelTypeProductLine(Order $order, bool $digital): OrderProduct
    {
        $group = ProductGroup::withoutEvents(fn () => ProductGroup::create([
            'name' => ['en' => 'Groep'], 'slug' => ['en' => 'groep-' . Str::random(6)], 'site_ids' => ['default'],
        ]));
        $product = Product::withoutEvents(fn () => Product::create([
            'name' => ['en' => 'Product'], 'slug' => ['en' => 'product-' . Str::random(6)], 'site_ids' => ['default'],
            'product_group_id' => $group->id, 'price' => 10, 'current_price' => 10, 'is_digital' => $digital,
        ]));

        return OrderProduct::withoutEvents(fn () => OrderProduct::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'name' => 'Product', 'quantity' => 1, 'price' => 10,
        ]));
    }

    function parcelTypeStoredLabel(Order $order, ?int $packageType, bool $isReturn = false): ParcelTypeTestLabel
    {
        return ParcelTypeTestLabel::create(['order_id' => $order->id, 'package_type' => $packageType, 'is_return' => $isReturn]);
    }
}

beforeEach(function () {
    ParcelType::reset();

    // CREATE TEMPORARY TABLE does not commit the RefreshDatabase transaction.
    Schema::create('parcel_type_test_labels', function (Blueprint $table) {
        $table->temporary();
        $table->id();
        $table->unsignedBigInteger('order_id');
        $table->string('package_type')->nullable();
        $table->boolean('is_return')->default(false);
        $table->timestamps();
    });

    Order::addDynamicRelation('parcelTypeTestLabels', fn (Order $order) => $order->hasMany(ParcelTypeTestLabel::class));
    ParcelType::useLabelRelations(['parcelTypeTestLabels']);
});

afterEach(function () {
    ParcelType::reset();
});

it('resolves pickup, digital, override, stored label, computation and unknown in that order', function () {
    // (f) nothing known
    $order = parcelTypeOrder();
    expect(ParcelType::forOrder($order))->toBe(['key' => 'unknown', 'code' => null, 'label' => 'ONBEKEND', 'color' => 'red']);

    // (e) computation
    ParcelType::computeUsing(fn (Order $o) => null);
    ParcelType::computeUsing(fn (Order $o) => 1);
    expect(ParcelType::forOrder($order->fresh())['key'])->toBe('package');

    // (d) stored label beats the computation; return labels and empty rows are ignored
    parcelTypeStoredLabel($order, 3);
    parcelTypeStoredLabel($order, null);
    parcelTypeStoredLabel($order, 6, isReturn: true);
    expect(ParcelType::forOrder($order->fresh())['key'])->toBe('letter');

    // eager-loaded relation gives the same answer
    expect(ParcelType::forOrder(Order::with(ParcelType::eagerLoads())->find($order->id))['key'])->toBe('letter');

    // (c) override beats the stored label; accepts keys as well as codes
    ParcelType::overrideUsing(fn (Order $o) => null);
    ParcelType::overrideUsing(fn (Order $o) => 'mailbox');
    expect(ParcelType::forOrder($order->fresh()))->toBe(['key' => 'mailbox', 'code' => 2, 'label' => 'BRIEVENBUS', 'color' => 'orange'])
        ->and(ParcelType::carrierCodeFor($order->fresh()))->toBe(2);

    // (b) digital beats the override; lines without product are ignored
    $digital = parcelTypeOrder();
    parcelTypeProductLine($digital, true);
    OrderProduct::withoutEvents(fn () => OrderProduct::create(['order_id' => $digital->id, 'name' => 'Verzendkosten', 'sku' => 'shipping_costs', 'quantity' => 1, 'price' => 5]));
    expect(ParcelType::forOrder($digital->fresh())['key'])->toBe('digital')
        ->and(ParcelType::carrierCodeFor($digital->fresh()))->toBeNull();

    // a mixed order is not digital
    parcelTypeProductLine($digital, false);
    expect(ParcelType::forOrder($digital->fresh())['key'])->toBe('mailbox');

    // (a) pickup beats everything
    $pickup = parcelTypeOrder(['shipping_method_id' => parcelTypeShippingMethod('take_away', 'Ophalen')->id]);
    expect(ParcelType::forOrder($pickup)['key'])->toBe('pickup')
        ->and(ParcelType::carrierCodeFor($pickup))->toBeNull();
});

it('memoises per order instance and forgets after flush', function () {
    $order = parcelTypeOrder();
    $calls = 0;
    ParcelType::computeUsing(function () use (&$calls) {
        $calls++;

        return 2;
    });

    ParcelType::forOrder($order);
    ParcelType::forOrder($order);
    expect($calls)->toBe(1);

    ParcelType::flush();
    ParcelType::forOrder($order);
    expect($calls)->toBe(2)
        ->and(ParcelType::keyFor('9'))->toBeNull()
        ->and(ParcelType::keyFor('2'))->toBe('mailbox')
        ->and(ParcelType::keyFor('nonsense'))->toBeNull();
});

it('uses the verzendvorm as label default and only when no package_type was chosen', function () {
    $order = parcelTypeOrder();

    // unknown: label overrides untouched, form falls back to the country default
    expect(LabelCreator::withParcelTypeDefault($order, []))->toBe([])
        ->and(OrderController::defaultPackageType($order, 'veloyd'))->toBe('1');

    ParcelType::overrideUsing(fn () => 2);
    $order = $order->fresh();

    expect(LabelCreator::withParcelTypeDefault($order, ['carrier' => 'PostNL']))->toBe(['carrier' => 'PostNL', 'package_type' => 2])
        ->and(LabelCreator::withParcelTypeDefault($order, ['package_type' => '1']))->toBe(['package_type' => '1'])
        ->and(OrderController::defaultPackageType($order, 'veloyd'))->toBe('2')
        ->and(OrderController::defaultPackageType($order, 'myparcel'))->toBe('2');
});

it('adds parcel_type to the mobile resources and prefixes the shipping method', function () {
    ParcelType::overrideUsing(fn () => 2);
    $order = parcelTypeOrder(['shipping_method_id' => parcelTypeShippingMethod('free_delivery')->id])->fresh();

    $detail = (new OrderResource($order))->toArray(Request::create('/', 'GET', ['detail' => 1]));
    expect($detail['parcel_type'])->toBe(['key' => 'mailbox', 'code' => 2, 'label' => 'BRIEVENBUS'])
        ->and($detail['shipping_method'])->toBe('BRIEVENBUS · Gratis verzending')
        ->and($detail['shipping_method_name'])->toBe('Gratis verzending');

    // unknown: bare method name, no "ONBEKEND ·"
    ParcelType::reset();
    ParcelType::useLabelRelations(['parcelTypeTestLabels']);
    $unknown = (new OrderResource($order->fresh()))->toArray(Request::create('/', 'GET', ['detail' => 1]));
    expect($unknown['shipping_method'])->toBe('Gratis verzending')
        ->and($unknown['parcel_type']['key'])->toBe('unknown');
    ParcelType::overrideUsing(fn () => 2);

    $list = (new OrderResource($order))->toArray(Request::create('/'));
    expect($list['parcel_type']['key'])->toBe('mailbox');

    $summary = (new OrderSummaryResource($order->load('orderProducts')))->toArray(Request::create('/'));
    expect($summary['parcel_type'])->toBe(['key' => 'mailbox', 'code' => 2, 'label' => 'BRIEVENBUS']);
});

it('never throws: failing or odd callables fall through, and tables without is_return work', function () {
    $order = parcelTypeOrder();

    ParcelType::overrideUsing(fn () => throw new RuntimeException('boom'));
    ParcelType::overrideUsing(fn () => false);
    ParcelType::overrideUsing(fn () => 1.5);
    ParcelType::computeUsing(fn () => throw new RuntimeException('boom'));
    expect(ParcelType::forOrder($order)['key'])->toBe('unknown');

    ParcelType::computeUsing(fn () => '2');
    expect(ParcelType::forOrder($order->fresh())['key'])->toBe('mailbox');

    // a stored-label relation that throws (missing table) is skipped too
    Order::addDynamicRelation('parcelTypeMissingLabels', fn (Order $o) => $o->hasMany(ParcelTypeTestLabel::class)->from('parcel_type_does_not_exist'));
    ParcelType::useLabelRelations(['parcelTypeMissingLabels']);
    expect(ParcelType::forOrder($order->fresh())['key'])->toBe('mailbox');

    // a carrier table without is_return
    Schema::create('parcel_type_old_labels', function (Blueprint $table) {
        $table->temporary();
        $table->id();
        $table->unsignedBigInteger('order_id');
        $table->string('package_type')->nullable();
        $table->timestamps();
    });
    Order::addDynamicRelation('parcelTypeOldLabels', fn (Order $o) => $o->hasMany(ParcelTypeOldLabel::class));
    ParcelType::useLabelRelations(['parcelTypeOldLabels']);
    ParcelTypeOldLabel::create(['order_id' => $order->id, 'package_type' => '3']);
    expect(ParcelType::forOrder($order->fresh())['key'])->toBe('letter');
});

it('treats a line whose product was deleted as physical and forgets one order on request', function () {
    $order = parcelTypeOrder();
    parcelTypeProductLine($order, true);
    Schema::disableForeignKeyConstraints();
    OrderProduct::withoutEvents(fn () => OrderProduct::create(['order_id' => $order->id, 'product_id' => 999999, 'name' => 'Weg', 'quantity' => 1, 'price' => 5]));
    Schema::enableForeignKeyConstraints();
    expect(ParcelType::forOrder($order->fresh())['key'])->toBe('unknown');

    $order = parcelTypeOrder();
    expect(ParcelType::forOrder($order)['key'])->toBe('unknown');
    parcelTypeStoredLabel($order, 2);
    expect(ParcelType::forOrder($order)['key'])->toBe('unknown'); // memoised
    ParcelType::forget($order);
    expect(ParcelType::forOrder($order)['key'])->toBe('mailbox');
});
