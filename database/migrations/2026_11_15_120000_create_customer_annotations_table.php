<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__customer_annotations')) {
            return;
        }

        // Notities + vrije-tekst tags per klant. Klanten zijn in DashedCMS een
        // aggregatie van bestellingen op e-mailadres (geen los klant-model), dus
        // de annotatie hangt aan (site_id, email) en niet aan een user-id.
        Schema::create('dashed__customer_annotations', function (Blueprint $table) {
            $table->id();
            $table->string('site_id')->nullable();
            $table->string('email');
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__customer_annotations');
    }
};
