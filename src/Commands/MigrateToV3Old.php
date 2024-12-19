<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductVariant;

class MigrateToV3Old extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dashed:migrate-to-v3';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate to v3';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {



        dd('test');
        $productIdsToDelete = [];

        //        ProductVariant::where('id', '!=', 0)->forceDelete();

        foreach (Product::all() as $product) {
            $this->info('Migrating product ' . $product->id);

            $shouldMigrateProductExtras = false;

            $productVariant = new ProductVariant();

            $productVariant->setRawAttributes([
                'name' => $product->getRawOriginal('name'),
                'images' => $product->getRawOriginal('images'),
            ]);

            $productVariant->old_product_id = $product->id;

            if ($product->type == 'variable' && $product->parent_id) {
                $productVariant->product_id = $product->parent_id;
                $productIdsToDelete[] = $product->id;
                $shouldMigrateProductExtras = true;
            } elseif ($product->type == 'variable' && ! $product->parent_id) {
                continue;
            } elseif ($product->type == 'simple') {
                $productVariant->product_id = $product->id;
            }

            $productVariant->price = $product->price ?: 0;
            $productVariant->current_price = $product->current_price ?: 0;
            $productVariant->old_price = $product->old_price ?: 0;
            $productVariant->discount_price = $product->discount_price ?: 0;
            $productVariant->purchase_price = $product->purchase_price ?: 0;
            $productVariant->vat_rate = $product->vat_rate;
            $productVariant->use_stock = $product->use_stock;
            $productVariant->stock_status = $product->stock_status;
            $productVariant->stock = $product->stock;
            $productVariant->in_stock = $product->in_stock;
            $productVariant->total_stock = $product->total_stock;
            $productVariant->purchases = $product->purchases;
            $productVariant->total_purchases = $product->total_purchases;
            $productVariant->low_stock_notification = $product->low_stock_notification;
            $productVariant->low_stock_notification_limit = $product->low_stock_notification_limit;
            $productVariant->limit_purchases_per_customer = $product->limit_purchases_per_customer;
            $productVariant->limit_purchases_per_customer_limit = $product->limit_purchases_per_customer_limit;
            $productVariant->is_bundle = $product->is_bundle;
            $productVariant->use_bundle_product_price = $product->use_bundle_product_price;
            $productVariant->use_bundle_product_tax = $product->use_bundle_product_tax;
            $productVariant->sku = $product->sku;
            $productVariant->barcode = $product->barcode;
            $productVariant->out_of_stock_sellable = $product->out_of_stock_sellable;
            $productVariant->expected_in_stock_date = $product->expected_in_stock_date;
            $productVariant->expected_delivery_in_days = $product->expected_delivery_in_days;
            $productVariant->order = $product->order;
            $productVariant->weight = $product->weight;
            $productVariant->length = $product->length;
            $productVariant->width = $product->width;
            $productVariant->height = $product->height;
            $productVariant->save();

            if ($shouldMigrateProductExtras) {
                foreach ($product->productExtras as $productExtra) {
                    $productExtra->product_id = null;
                    $productExtra->product_variant_id = $productVariant->id;
                    $productExtra->save();
                }
            }
        }
        dd('Done');

        Product::whereIn('id', $productIdsToDelete)->delete();

        foreach (Product::all() as $product) {
            $product->variants()->first()->update([
                'default' => 1,
            ]);
        }



        //Todo: drop bundle products columns
        //Todo: put thing below in a migration but add a check if the migration command is already done

        //Todo: clean up products table
        Schema::table('dashed__products', function (Blueprint $table) {
            $table->dropColumn('price');
            $table->dropColumn('current_price');
            $table->dropColumn('old_price');
            $table->dropColumn('discount_price');
            $table->dropColumn('purchase_price');
            $table->dropColumn('vat_rate');
            $table->dropColumn('use_stock');
            $table->dropColumn('stock_status');
            $table->dropColumn('stock');
            $table->dropColumn('in_stock');
            $table->dropColumn('total_stock');
            $table->dropColumn('purchases');
            $table->dropColumn('total_purchases');
            $table->dropColumn('low_stock_notification');
            $table->dropColumn('low_stock_notification_limit');
            $table->dropColumn('limit_purchases_per_customer');
            $table->dropColumn('limit_purchases_per_customer_limit');
            $table->dropColumn('use_bundle_product_price');
            $table->dropColumn('use_bundle_product_tax');
            $table->dropColumn('sku');
            $table->dropColumn('barcode');
            $table->dropColumn('article_code');
            $table->dropColumn('out_of_stock_sellable');
            $table->dropColumn('expected_in_stock_date');
            $table->dropColumn('expected_delivery_in_days');
            $table->dropColumn('order');
            $table->dropColumn('weight');
            $table->dropColumn('length');
            $table->dropColumn('width');
            $table->dropColumn('height');
            $table->dropColumn('start_date');
            $table->dropColumn('end_date');
            $table->dropColumn('efulfillment_shop_id');
            $table->dropColumn('efulfillment_shop_error');
            $table->dropColumn('type');

            $table->decimal('price', 2)
                ->nullable();
            $table->decimal('max_price', 2)
                ->nullable();
            $table->decimal('discount_price', 2)
                ->nullable();
        });

        Schema::table('dashed__product_variations', function (Blueprint $table) {
            $table->dropColumn('old_product_id');
        });
    }
}
