<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'product_categories',
            'product_collections',
            'product_subcollections',
            'product_colors',
            'product_brands',
            'product_materials',
            'product_sizes',
            'product_article_complements',
        ];

        foreach ($tables as $table) {
            Schema::create($table, function (Blueprint $blueprint) {
                $blueprint->id();
                $blueprint->string('cigam_code')->unique();
                $blueprint->string('name');
                $blueprint->boolean('is_active')->default(true);
                $blueprint->timestamps();
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'product_article_complements',
            'product_sizes',
            'product_materials',
            'product_brands',
            'product_colors',
            'product_subcollections',
            'product_collections',
            'product_categories',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};
