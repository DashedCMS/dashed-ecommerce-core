<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MigrateShippingMethodVariablesToOtherArray extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach (\Qubiqx\QcommerceEcommerceCore\Models\ShippingMethod::get() as $shippingMethod) {
            if ($shippingMethod->variables) {
                $newVariables = [];
                foreach ($shippingMethod->variables as $variable) {
                    $newVariables[Str::orderedUuid()->toString()] = [
                        'costs' => $variable['costs'],
                        'amount_of_items' => $variable['amount_of_items'],
                    ];
                }
                $shippingMethod->variables = $newVariables;
                $shippingMethod->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('other_array', function (Blueprint $table) {
            //
        });
    }
}
