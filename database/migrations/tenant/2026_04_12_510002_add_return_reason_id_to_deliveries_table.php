<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('deliveries')) {
            return;
        }

        Schema::table('deliveries', function (Blueprint $table) {
            if (! Schema::hasColumn('deliveries', 'return_reason_id')) {
                $table->foreignId('return_reason_id')->nullable()->after('status')
                    ->constrained('delivery_return_reasons')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('deliveries')) {
            return;
        }

        Schema::table('deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('deliveries', 'return_reason_id')) {
                $table->dropForeign(['return_reason_id']);
                $table->dropColumn('return_reason_id');
            }
        });
    }
};
