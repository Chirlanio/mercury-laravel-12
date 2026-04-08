<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_sync_logs', function (Blueprint $table) {
            $table->date('date_range_start')->nullable()->after('error_details');
            $table->date('date_range_end')->nullable()->after('date_range_start');
        });
    }

    public function down(): void
    {
        Schema::table('product_sync_logs', function (Blueprint $table) {
            $table->dropColumn(['date_range_start', 'date_range_end']);
        });
    }
};
