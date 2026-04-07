<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_level_pages', function (Blueprint $table) {
            // Add unique constraint (keeps the existing index for FK support)
            $table->unique(['access_level_id', 'page_id'], 'alp_level_page_unique');
        });
    }

    public function down(): void
    {
        Schema::table('access_level_pages', function (Blueprint $table) {
            $table->dropUnique('alp_level_page_unique');
        });
    }
};
