<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuração anual dos thresholds mínimos de faturamento para cada tier VIP.
 *
 * Preenchida manualmente por Marketing antes de rodar a geração automática de
 * sugestões. Sem config para um ano, o service ainda consegue gerar um ranking
 * (top N por valor) mas não consegue sugerir tier concreto; a curadoria manual
 * continua funcionando em qualquer caso.
 *
 * Unicidade (year, tier) garante um único valor de threshold por combinação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_vip_tier_configs', function (Blueprint $table) {
            $table->id();

            $table->unsignedSmallInteger('year');
            $table->enum('tier', ['black', 'gold']);

            // Faturamento mínimo líquido (vendas code=2 menos devoluções code=6+E)
            // para o cliente ser sugerido nesse tier durante o ano.
            $table->decimal('min_revenue', 14, 2);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['year', 'tier']);
            $table->index('year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_vip_tier_configs');
    }
};
