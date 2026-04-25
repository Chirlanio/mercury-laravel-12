<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('type_expenses')) {
            return;
        }

        Schema::create('type_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('icon', 60)->nullable();
            $table->string('color', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_active');
        });

        $now = now();
        DB::table('type_expenses')->insert([
            ['name' => 'Alimentação',  'icon' => 'fa-solid fa-utensils',     'color' => 'orange', 'is_active' => true, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Transporte',   'icon' => 'fa-solid fa-car',          'color' => 'blue',   'is_active' => true, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Hospedagem',   'icon' => 'fa-solid fa-bed',          'color' => 'purple', 'is_active' => true, 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Combustível',  'icon' => 'fa-solid fa-gas-pump',     'color' => 'red',    'is_active' => true, 'sort_order' => 40, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Estacionamento', 'icon' => 'fa-solid fa-square-parking', 'color' => 'gray', 'is_active' => true, 'sort_order' => 50, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Pedágio',      'icon' => 'fa-solid fa-road',         'color' => 'gray',   'is_active' => true, 'sort_order' => 60, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Outros',       'icon' => 'fa-solid fa-receipt',      'color' => 'gray',   'is_active' => true, 'sort_order' => 999, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('type_expenses');
    }
};
