<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->enum('type', ['main', 'hr', 'utility', 'system'])->default('main')->after('is_active');
        });

        // Atualizar menus existentes baseado no nome
        $hrMenus = ['Pessoas & Cultura', 'Departamento Pessoal', 'Funcionários', 'Controle de Jornada'];
        $utilityMenus = ["FAQ's", 'Movidesk', 'Biblioteca de Processos', 'Escola Digital'];
        $systemMenus = ["Dashboard's", 'Qualidade', 'Sair', 'Configurações', 'Gerenciar Níveis', 'Gerenciar Menus', 'Gerenciar Páginas', 'Logger', 'Configurações de Email', 'Temas de Cores'];

        \DB::table('menus')->whereIn('name', $hrMenus)->update(['type' => 'hr']);
        \DB::table('menus')->whereIn('name', $utilityMenus)->update(['type' => 'utility']);
        \DB::table('menus')->whereIn('name', $systemMenus)->update(['type' => 'system']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
