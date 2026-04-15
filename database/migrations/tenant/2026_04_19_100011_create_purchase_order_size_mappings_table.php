<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela de/para para tamanhos de planilhas importadas vs. product_sizes
 * oficiais (sincronizadas do CIGAM).
 *
 * A planilha v1 usa labels como "PP", "33", "33/34", "33.5", "70" etc.
 * A maioria casa direto com `product_sizes.name`, mas alguns (tamanhos
 * duplos "33/34", "35/36") não têm equivalente oficial — o usuário
 * precisa configurar o mapeamento manualmente.
 *
 * Resolução durante import:
 *  1. PurchaseOrderImportService detecta colunas de tamanho na planilha
 *  2. Pra cada label, consulta esta tabela por `source_label`
 *  3. Se existe mapping ativo com product_size_id → usa
 *  4. Se não existe ou product_size_id é null → rejeita o item com
 *     mensagem "Configure mapeamento em Configurações → Tamanhos"
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_size_mappings', function (Blueprint $table) {
            $table->id();

            // Label como aparece na coluna da planilha (ex: "PP", "33", "33/34")
            // Armazenado normalizado (trim + upper) pra lookup case-insensitive
            $table->string('source_label', 50);

            // Tamanho oficial do catálogo CIGAM (nullable quando ainda não
            // foi mapeado — permite existir como registro pendente de resolução)
            $table->foreignId('product_size_id')
                ->nullable()
                ->constrained('product_sizes')
                ->nullOnDelete();

            $table->boolean('is_active')->default(true);

            // Flag pra distinguir mappings criados pelo seeder (match automático
            // por name) de mappings criados manualmente pelo usuário
            $table->boolean('auto_detected')->default(false);

            $table->text('notes')->nullable();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique('source_label', 'idx_po_size_mappings_label_unique');
            $table->index(['is_active', 'product_size_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_size_mappings');
    }
};
