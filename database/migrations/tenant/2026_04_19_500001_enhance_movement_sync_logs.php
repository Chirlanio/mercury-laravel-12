<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movement_sync_logs', function (Blueprint $table) {
            $table->json('deletion_summary')->nullable()->after('error_details');
        });
    }

    public function down(): void
    {
        Schema::table('movement_sync_logs', function (Blueprint $table) {
            $table->dropColumn('deletion_summary');
        });
    }
};
