<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try{
            Schema::table('dashed__order_logs', function (Blueprint $table) {
                // Huidige FK eerst droppen
                $table->dropForeign('qcommerce__order_logs_user_id_foreign');
            });

            Schema::table('dashed__order_logs', function (Blueprint $table) {
                // Nieuwe FK met ON DELETE CASCADE
                $table->foreign('user_id', 'qcommerce__order_logs_user_id_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }catch (Exception $e){

        }
        try{
            Schema::table('dashed__order_logs', function (Blueprint $table) {
                // Huidige FK eerst droppen
                $table->dropForeign('dashed__order_logs_user_id_foreign');
            });

            Schema::table('dashed__order_logs', function (Blueprint $table) {
                // Nieuwe FK met ON DELETE CASCADE
                $table->foreign('user_id', 'dashed__order_logs_user_id_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }catch (Exception $e){

        }
        try{
            Schema::table('dashed__orders', function (Blueprint $table) {
                // Huidige FK eerst droppen
                $table->dropForeign('qcommerce__orders_user_id_foreign');
            });

            Schema::table('dashed__orders', function (Blueprint $table) {
                // Nieuwe FK met ON DELETE CASCADE
                $table->foreign('user_id', 'qcommerce__orders_user_id_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }catch (Exception $e){

        }
        try{
            Schema::table('dashed__orders', function (Blueprint $table) {
                // Huidige FK eerst droppen
                $table->dropForeign('dashed__orders_user_id_foreign');
            });

            Schema::table('dashed__orders', function (Blueprint $table) {
                // Nieuwe FK met ON DELETE CASCADE
                $table->foreign('user_id', 'dashed__orders_user_id_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }catch (Exception $e){

        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
