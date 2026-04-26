<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('damage_types')) {
            return;
        }

        Schema::create('damage_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('slug', 80)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_active');
        });

        // Seed dos 8 tipos de dano padrão (paridade com adms_damage_types da v1).
        $now = now();
        DB::table('damage_types')->insert([
            ['name' => 'Rasgo',         'slug' => 'rasgo',         'is_active' => true, 'sort_order' => 10,  'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Mancha',        'slug' => 'mancha',        'is_active' => true, 'sort_order' => 20,  'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Descostura',    'slug' => 'descostura',    'is_active' => true, 'sort_order' => 30,  'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Deformação',    'slug' => 'deformacao',    'is_active' => true, 'sort_order' => 40,  'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Risco',         'slug' => 'risco',         'is_active' => true, 'sort_order' => 50,  'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Mofo',          'slug' => 'mofo',          'is_active' => true, 'sort_order' => 60,  'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Descolamento',  'slug' => 'descolamento',  'is_active' => true, 'sort_order' => 70,  'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Outro',         'slug' => 'outro',         'is_active' => true, 'sort_order' => 999, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_types');
    }
};
