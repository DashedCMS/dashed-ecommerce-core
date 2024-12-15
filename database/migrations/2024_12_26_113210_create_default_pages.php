<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!\Dashed\DashedCore\Models\Customsetting::get('product_overview_page_id')) {
            $page = new \Dashed\DashedPages\Models\Page();
            $page->setTranslation('name', 'nl', 'Producten');
            $page->setTranslation('slug', 'nl', 'producten');
            $page->setTranslation('content', 'nl', [
                [
                    'data' => [],
                    'type' => 'all-products',
                ]
            ]);
            $page->save();

            \Dashed\DashedCore\Models\Customsetting::set('product_overview_page_id', $page->id);
        }

        $page = new \Dashed\DashedPages\Models\Page();
        $page->setTranslation('name', 'nl', 'Bestellingen');
        $page->setTranslation('slug', 'nl', 'bestellingen');
        $page->setTranslation('content', 'nl', [
            [
                'data' => [],
                'type' => 'orders-block',
            ]
        ]);
        $page->save();

        \Dashed\DashedCore\Models\Customsetting::set('orders_page_id', $page->id);

        $page = new \Dashed\DashedPages\Models\Page();
        $page->setTranslation('name', 'nl', 'Bestelling');
        $page->setTranslation('slug', 'nl', 'bestelling');
        $page->setTranslation('content', 'nl', [
            [
                'data' => [],
                'type' => 'view-order-block',
            ]
        ]);
        $page->save();

        \Dashed\DashedCore\Models\Customsetting::set('order_page_id', $page->id);

        $page = new \Dashed\DashedPages\Models\Page();
        $page->setTranslation('name', 'nl', 'Winkelwagen');
        $page->setTranslation('slug', 'nl', 'winkelwagen');
        $page->setTranslation('content', 'nl', [
            [
                'data' => [],
                'type' => 'cart-block',
            ]
        ]);
        $page->save();

        \Dashed\DashedCore\Models\Customsetting::set('cart_page_id', $page->id);

        $page = new \Dashed\DashedPages\Models\Page();
        $page->setTranslation('name', 'nl', 'Afrekenen');
        $page->setTranslation('slug', 'nl', 'afrekenen');
        $page->setTranslation('content', 'nl', [
            [
                'data' => [],
                'type' => 'checkout-block',
            ]
        ]);
        $page->save();

        \Dashed\DashedCore\Models\Customsetting::set('checkout_page_id', $page->id);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('metadata');
    }
};
