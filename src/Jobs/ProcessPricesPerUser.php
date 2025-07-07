<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use App\Models\User;
use Dashed\DashedEcommerceCore\Imports\PricePerProductForUserImport;
use Dashed\DashedEcommerceCore\Imports\ProductsToEditImport;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Exports\ProductListExport;
use Dashed\DashedEcommerceCore\Mail\ProductListExportMail;

class ProcessPricesPerUser implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 5;
    public $timeout = 1200;
    public array $data;
    public User $user;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, array $data)
    {
        $this->user = $user;
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $data = $this->data;
        $user = $this->user;

//        $products = Product::all();
        $productCategories = ProductCategory::all();

        $productGroupIds = [];

//        foreach ($products as $product) {
//            if (in_array($product->id, $data['product_ids'])) {
//                $price = $data[$product->id . '_discount_price'];
//                $discountPercentage = $data[$product->id . '_discount_percentage'];
//
//                DB::table('dashed__product_user')
//                    ->updateOrInsert(
//                        ['product_id' => $product->id, 'user_id' => $user->id],
//                        ['discount_price' => $price, 'discount_percentage' => $discountPercentage]
//                    );
//
//                $productGroupIds[] = $product->product_group_id;
//            }
//        }
//
//        DB::table('dashed__product_user')
//            ->where('user_id', $user->id)
//            ->whereNotIn('product_id', $data['product_ids'])
//            ->delete();

        foreach ($productCategories as $productCategory) {
            if (in_array($productCategory->id, $data['product_category_ids'])) {
                $price = $data[$productCategory->id . '_category_discount_price'];
                $discountPercentage = $data[$productCategory->id . '_category_discount_percentage'];

                DB::table('dashed__product_category_user')
                    ->updateOrInsert(
                        ['product_category_id' => $productCategory->id, 'user_id' => $user->id],
                        ['discount_price' => $price, 'discount_percentage' => $discountPercentage]
                    );

                foreach ($productCategory->products as $product) {
                    DB::table('dashed__product_user')->updateOrInsert(
                        [
                            'product_id' => $product->id,
                            'user_id' => $user->id,
                        ],
                        [
                            'discount_price' => $price,
                            'discount_percentage' => $discountPercentage,
                        ]
                    );

                    $productGroupIds[] = $product->product_group_id;
                }
            } else {
                DB::table('dashed__product_category_user')
                    ->where('product_category_id', $productCategory->id)
                    ->where('user_id', $user->id)
                    ->delete();

                DB::table('dashed__product_user')
                    ->whereIn('product_id', $productCategory->products->pluck('id'))
                    ->where('user_id', $user->id)
                    ->where('activated_by_category', true)
                    ->delete();
            }
        }

        foreach (ProductGroup::whereIn('id', $productGroupIds)->get() as $productGroup) {
            UpdateProductInformationJob::dispatch($productGroup, false)->onQueue('ecommerce');
        }
    }
}
