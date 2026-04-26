<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('turn_list_break_types')) {
            return;
        }

        Schema::create('turn_list_break_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique();
            $table->unsignedInteger('max_duration_minutes')->comment('Tempo máximo antes de gerar alerta');
            $table->string('color', 20)->default('gray');
            $table->string('icon', 60)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        $now = now();
        DB::table('turn_list_break_types')->insert([
            ['name' => 'Intervalo', 'max_duration_minutes' => 15, 'color' => 'info',    'icon' => 'fa-solid fa-mug-hot',  'sort_order' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Almoço',    'max_duration_minutes' => 60, 'color' => 'warning', 'icon' => 'fa-solid fa-utensils', 'sort_order' => 20, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('turn_list_break_types');
    }
};
