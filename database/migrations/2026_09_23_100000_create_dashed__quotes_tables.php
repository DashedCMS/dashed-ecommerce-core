<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__quotes')) {
            Schema::create('dashed__quotes', function (Blueprint $table) {
                $table->id();
                $table->string('site_id')->nullable()->index();
                $table->string('hash', 32)->unique();
                $table->string('locale', 10)->default('nl');

                $table->string('quote_number')->nullable()->index();
                $table->unsignedSmallInteger('version')->default(1);
                $table->unsignedBigInteger('parent_quote_id')->nullable()->index();
                $table->string('status')->default('concept')->index();

                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->unsignedBigInteger('order_id')->nullable()->index();

                $table->string('company_name')->nullable();
                $table->string('btw_id')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone_number')->nullable();
                $table->string('street')->nullable();
                $table->string('house_nr')->nullable();
                $table->string('zip_code')->nullable();
                $table->string('city')->nullable();
                $table->string('country')->nullable();
                $table->string('invoice_street')->nullable();
                $table->string('invoice_house_nr')->nullable();
                $table->string('invoice_zip_code')->nullable();
                $table->string('invoice_city')->nullable();
                $table->string('invoice_country')->nullable();

                $table->string('title')->nullable();
                $table->string('reference')->nullable();
                $table->date('valid_until')->nullable()->index();
                $table->boolean('prices_ex_vat')->default(true);
                $table->string('payment_route')->default('prepay');

                $table->text('intro')->nullable();
                $table->text('terms')->nullable();
                $table->text('acceptance_text')->nullable();
                $table->text('notes')->nullable();

                $table->decimal('total', 12, 2)->default(0);

                $table->timestamp('sent_at')->nullable();
                $table->timestamp('viewed_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('reminder_sent_at')->nullable();

                $table->string('accepted_name')->nullable();
                $table->string('accepted_ip')->nullable();
                $table->text('rejection_reason')->nullable();

                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dashed__quote_lines')) {
            Schema::create('dashed__quote_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quote_id')->constrained('dashed__quotes')->cascadeOnDelete();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('sku')->nullable();
                $table->unsignedInteger('quantity')->default(1);
                // Prijs per stuk inclusief btw, zoals Product::$current_price en
                // OrderProduct::$price. Het bedrag ex btw wordt getoond met
                // VatDisplay::exFromIncl(), niet opgeslagen.
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->decimal('vat_rate', 5, 2)->default(21);
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_optional')->default(false);
                $table->boolean('is_selected')->default(true);
                $table->string('choice_group')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__quote_lines');
        Schema::dropIfExists('dashed__quotes');
    }
};
