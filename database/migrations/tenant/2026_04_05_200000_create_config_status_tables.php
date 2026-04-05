<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Situação de Ajuste de Estoque
        Schema::create('stock_adjustment_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('color_theme_id')->nullable()->constrained('color_themes')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Situação de Transferência
        Schema::create('transfer_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('color_theme_id')->nullable()->constrained('color_themes')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Situação de Ordem de Pagamento
        Schema::create('order_payment_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('color_theme_id')->nullable()->constrained('color_themes')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed default data based on existing hardcoded constants
        $now = now();

        DB::table('stock_adjustment_statuses')->insert([
            ['name' => 'Pendente', 'color_theme_id' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Em Análise', 'color_theme_id' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Aguardando Resposta', 'color_theme_id' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Transferência de Saldo', 'color_theme_id' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Ajustado', 'color_theme_id' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sem Ajuste', 'color_theme_id' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Cancelado', 'color_theme_id' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('transfer_statuses')->insert([
            ['name' => 'Pendente', 'color_theme_id' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Em Rota', 'color_theme_id' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Entregue', 'color_theme_id' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Confirmado', 'color_theme_id' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Cancelado', 'color_theme_id' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('order_payment_statuses')->insert([
            ['name' => 'Solicitação', 'color_theme_id' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Reg. Fiscal', 'color_theme_id' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Lançado', 'color_theme_id' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Pago', 'color_theme_id' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payment_statuses');
        Schema::dropIfExists('transfer_statuses');
        Schema::dropIfExists('stock_adjustment_statuses');
    }
};
