<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__carts', function (Blueprint $table) {
            $table->decimal('total', 10, 2)
                ->default(0)
                ->after('user_id');
        });

        foreach(\Dashed\DashedEcommerceCore\Models\Cart::get() as $cart){
            $cart->updateTotal();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
