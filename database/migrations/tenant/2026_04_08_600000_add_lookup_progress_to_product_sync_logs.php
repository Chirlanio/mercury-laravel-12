<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_sync_logs', function (Blueprint $table) {
            $table->unsignedInteger('lookup_total')->default(0)->after('current_phase');
            $table->unsignedInteger('lookup_processed')->default(0)->after('lookup_total');
            $table->string('lookup_current', 50)->nullable()->after('lookup_processed');
        });
    }

    public function down(): void
    {
        Schema::table('product_sync_logs', function (Blueprint $table) {
            $table->dropColumn(['lookup_total', 'lookup_processed', 'lookup_current']);
        });
    }
};
