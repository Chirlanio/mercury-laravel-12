<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela de/para para nomes de marca da planilha de importação vs.
 * product_brands oficiais (sincronizadas do CIGAM).
 *
 * Motivação: planilhas históricas trazem nomes como "DIAN PATRIS" sem
 * o prefixo "MS " que o CIGAM usa pra marcas próprias da Meia Sola.
 * Também podem trazer grafias diferentes (ex: "HITS" vs "HITZ" no CIGAM).
 *
 * Resolução durante import:
 *  1. ImportService tenta match direto em product_brands.name
 *  2. Se falhar, consulta purchase_order_brand_aliases.source_name
 *  3. Se ainda falhar, rejeita a ordem (marca obrigatória)
 *
 * Mantém rastreabilidade — diferente de criar marcas automaticamente, o
 * alias preserva a intenção do usuário (qual marca real estamos usando
 * pra representar aquele nome histórico).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_brand_aliases', function (Blueprint $table) {
            $table->id();

            // Nome como aparece na planilha (ex: "DIAN PATRIS")
            // Normalizado (upper+trim) pra lookup case-insensitive
            $table->string('source_name', 100);

            // Marca oficial do catálogo pra qual o alias aponta (nullable
            // quando o usuário ainda não resolveu o mapeamento)
            $table->foreignId('product_brand_id')
                ->nullable()
                ->constrained('product_brands')
                ->nullOnDelete();

            $table->boolean('is_active')->default(true);

            // Flag distinguindo aliases criados pelo auto-detect MS prefix
            // vs aliases criados manualmente pelo usuário no CRUD
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

            $table->unique('source_name', 'idx_po_brand_aliases_name_unique');
            $table->index(['is_active', 'product_brand_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_brand_aliases');
    }
};
