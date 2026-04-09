<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_sync_logs', function (Blueprint $table) {
            $table->unsignedInteger('price_total')->default(0)->after('lookup_current');
            $table->unsignedInteger('price_processed')->default(0)->after('price_total');
        });
    }

    public function down(): void
    {
        Schema::table('product_sync_logs', function (Blueprint $table) {
            $table->dropColumn(['price_total', 'price_processed']);
        });
    }
};
