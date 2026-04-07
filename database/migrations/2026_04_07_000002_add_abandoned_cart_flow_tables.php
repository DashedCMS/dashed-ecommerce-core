<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dashed__abandoned_cart_flows')) {
            Schema::create('dashed__abandoned_cart_flows', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->boolean('is_active')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dashed__abandoned_cart_flow_steps')) {
            Schema::create('dashed__abandoned_cart_flow_steps', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('flow_id');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->unsignedSmallInteger('delay_value')->default(1);
                $table->string('delay_unit', 10)->default('hours'); // hours, days
                $table->string('subject');
                $table->text('intro_text')->nullable();
                $table->string('button_label')->default('Bestel nu');
                $table->boolean('show_products')->default(true);
                $table->boolean('show_review')->default(false);
                $table->boolean('incentive_enabled')->default(false);
                $table->string('incentive_type', 20)->default('amount'); // amount, percentage
                $table->decimal('incentive_value', 8, 2)->default(0);
                $table->unsignedSmallInteger('incentive_valid_days')->default(7);
                $table->boolean('enabled')->default(true);
                $table->timestamps();

                $table->foreign('flow_id')->references('id')->on('dashed__abandoned_cart_flows')->cascadeOnDelete();
                $table->index(['flow_id', 'sort_order']);
            });
        }

        if (Schema::hasTable('dashed__abandoned_cart_emails') && ! Schema::hasColumn('dashed__abandoned_cart_emails', 'flow_step_id')) {
            Schema::table('dashed__abandoned_cart_emails', function (Blueprint $table) {
                $table->unsignedBigInteger('flow_step_id')->nullable()->after('email_number');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__abandoned_cart_flow_steps');
        Schema::dropIfExists('dashed__abandoned_cart_flows');

        if (Schema::hasColumn('dashed__abandoned_cart_emails', 'flow_step_id')) {
            Schema::table('dashed__abandoned_cart_emails', function (Blueprint $table) {
                $table->dropColumn('flow_step_id');
            });
        }
    }
};
