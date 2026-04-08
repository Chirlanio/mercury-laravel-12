<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected array $tables = [
        'product_brands',
        'product_categories',
        'product_collections',
        'product_subcollections',
        'product_colors',
        'product_materials',
        'product_sizes',
        'product_article_complements',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'merged_into')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('merged_into');
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'merged_into')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->string('merged_into')->nullable()->after('is_active');
                });
            }
        }
    }
};
