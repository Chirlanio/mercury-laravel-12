<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('turn_list_attendance_outcomes')) {
            return;
        }

        Schema::create('turn_list_attendance_outcomes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('description', 255)->nullable();
            $table->string('color', 20)->default('gray');
            $table->string('icon', 60)->nullable();
            // Flag de conversão — entra nas métricas de % de conversão.
            $table->boolean('is_conversion')->default(false);
            // Quando true, a consultora volta à posição original na fila
            // ao finalizar atendimento com este outcome — em vez de ir pro
            // fim. Usado em "Retorna vez" (cliente pediu por essa pessoa
            // específica ou foi troca convertida).
            $table->boolean('restore_queue_position')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
            $table->index('is_conversion');
        });

        $now = now();
        // Paridade com seed v1 (10 outcomes)
        DB::table('turn_list_attendance_outcomes')->insert([
            ['name' => 'Venda Realizada',             'description' => 'Cliente realizou compra',                     'color' => 'success',   'icon' => 'fa-solid fa-cart-shopping',     'is_conversion' => true,  'restore_queue_position' => false, 'sort_order' => 10,  'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Pesquisa',                    'description' => 'Cliente apenas pesquisando preços/produtos',  'color' => 'info',      'icon' => 'fa-solid fa-magnifying-glass',  'is_conversion' => false, 'restore_queue_position' => false, 'sort_order' => 20,  'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Produto Indisponível',        'description' => 'Loja não trabalha com o produto procurado',   'color' => 'warning',   'icon' => 'fa-solid fa-box-open',          'is_conversion' => false, 'restore_queue_position' => false, 'sort_order' => 30,  'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Entrou e Saiu',               'description' => 'Cliente entrou e saiu rapidamente',           'color' => 'gray',      'icon' => 'fa-solid fa-door-open',         'is_conversion' => false, 'restore_queue_position' => false, 'sort_order' => 40,  'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Preço',                       'description' => 'Cliente desistiu pelo preço',                 'color' => 'warning',   'icon' => 'fa-solid fa-tag',               'is_conversion' => false, 'restore_queue_position' => false, 'sort_order' => 50,  'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Tamanho/Modelo',              'description' => 'Não tinha tamanho ou modelo desejado',        'color' => 'warning',   'icon' => 'fa-solid fa-ruler',             'is_conversion' => false, 'restore_queue_position' => false, 'sort_order' => 60,  'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Troca/Devolução',             'description' => 'Atendimento para troca ou devolução',         'color' => 'info',      'icon' => 'fa-solid fa-right-left',        'is_conversion' => false, 'restore_queue_position' => false, 'sort_order' => 70,  'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Troca convertida/Retorna vez','description' => 'Troca convertida em venda — preserva posição','color' => 'success',   'icon' => 'fa-solid fa-rotate',            'is_conversion' => true,  'restore_queue_position' => true,  'sort_order' => 80,  'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Preferência/Retorna vez',     'description' => 'Cliente preferiu outra consultora — preserva posição', 'color' => 'purple', 'icon' => 'fa-solid fa-user-tag', 'is_conversion' => false, 'restore_queue_position' => true, 'sort_order' => 90, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Outro',                       'description' => 'Outros motivos não listados',                 'color' => 'gray',      'icon' => 'fa-solid fa-circle-question',   'is_conversion' => false, 'restore_queue_position' => false, 'sort_order' => 999, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('turn_list_attendance_outcomes');
    }
};
