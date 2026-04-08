<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates product_lookup_groups table and adds group_id to all lookup tables.
 *
 * Groups allow business-level aggregation of lookup records for reporting.
 * Example: brands "MS ANDINE" and "MS BOLSAS BOZZ" can be grouped as "M|S".
 */
return new class extends Migration
{
    protected array $lookupTables = [
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
        Schema::create('product_lookup_groups', function (Blueprint $table) {
            $table->id();
            $table->enum('lookup_type', [
                'brands', 'categories', 'collections', 'subcollections',
                'colors', 'materials', 'sizes', 'article_complements',
            ]);
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['lookup_type', 'name']);
            $table->index('lookup_type');
        });

        // Add group_id FK to each lookup table
        foreach ($this->lookupTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('group_id')->nullable()->after('merged_into');
                $table->index('group_id');
            });
        }

        // Seed predefined groups
        $this->seedGroups();
    }

    public function down(): void
    {
        foreach ($this->lookupTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['group_id']);
                $table->dropColumn('group_id');
            });
        }

        Schema::dropIfExists('product_lookup_groups');
    }

    protected function seedGroups(): void
    {
        $now = now();
        $groups = [
            // Cores
            ['lookup_type' => 'colors', 'name' => 'Neutros'],
            ['lookup_type' => 'colors', 'name' => 'Quentes'],
            ['lookup_type' => 'colors', 'name' => 'Frios'],
            ['lookup_type' => 'colors', 'name' => 'Terrosos'],
            ['lookup_type' => 'colors', 'name' => 'Metalicos'],
            ['lookup_type' => 'colors', 'name' => 'Estampados'],

            // Categorias
            ['lookup_type' => 'categories', 'name' => 'Calcados'],
            ['lookup_type' => 'categories', 'name' => 'Bolsas'],
            ['lookup_type' => 'categories', 'name' => 'Acessorios'],

            // Colecoes
            ['lookup_type' => 'collections', 'name' => 'Verao'],
            ['lookup_type' => 'collections', 'name' => 'Inverno'],
            ['lookup_type' => 'collections', 'name' => 'Meia-Estacao'],
            ['lookup_type' => 'collections', 'name' => 'Permanente'],

            // Tamanhos
            ['lookup_type' => 'sizes', 'name' => 'Adulto'],
            ['lookup_type' => 'sizes', 'name' => 'Infantil'],
            ['lookup_type' => 'sizes', 'name' => 'Letra'],
            ['lookup_type' => 'sizes', 'name' => 'Unico'],

            // Materiais
            ['lookup_type' => 'materials', 'name' => 'Couro Natural'],
            ['lookup_type' => 'materials', 'name' => 'Couro Tratado'],
            ['lookup_type' => 'materials', 'name' => 'Sintetico'],
            ['lookup_type' => 'materials', 'name' => 'Textil'],
            ['lookup_type' => 'materials', 'name' => 'Borracha'],

            // Complementos de Artigo
            ['lookup_type' => 'article_complements', 'name' => 'Masculino'],
            ['lookup_type' => 'article_complements', 'name' => 'Feminino'],
            ['lookup_type' => 'article_complements', 'name' => 'Infantil'],
            ['lookup_type' => 'article_complements', 'name' => 'Unissex'],
        ];

        foreach ($groups as $group) {
            DB::table('product_lookup_groups')->insert(array_merge($group, [
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }
};
