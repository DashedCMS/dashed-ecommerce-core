<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__order_returns')) {
            return;
        }

        Schema::table('dashed__order_returns', function (Blueprint $table) {
            if (! Schema::hasColumn('dashed__order_returns', 'auto_accepted')) {
                $table->boolean('auto_accepted')->default(false);
            }
            if (! Schema::hasColumn('dashed__order_returns', 'return_label_provider')) {
                $table->string('return_label_provider')->nullable();
            }
            if (! Schema::hasColumn('dashed__order_returns', 'return_label_path')) {
                $table->string('return_label_path')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('dashed__order_returns', function (Blueprint $table) {
            $table->dropColumn(['auto_accepted', 'return_label_provider', 'return_label_path']);
        });
    }
};
