<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela principal de avariados.
 *
 * Cada row registra UMA ocorrência (par trocado OU avaria) numa loja específica.
 * Engine de matching (DamagedProductMatchingService) cruza pares quando ambos
 * estão em status=open, mesma `product_reference`, lojas distintas e regras de
 * marca/rede compatíveis.
 *
 * Decisão de design: `product_reference` é STRING (não FK) pra acomodar
 * registros cuja referência ainda não existe no catálogo `products` (raro mas
 * possível em loja com produto não-sincronizado). FK `product_id` (nullable)
 * é populada quando há match no catálogo — permite auto-fill de brand/color.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damaged_products', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();

            // Contexto da loja
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();

            // Produto (FK opcional + reference sempre presente)
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_reference', 100); // SKU/referência (sempre preenchida)
            $table->string('product_name', 255)->nullable();
            $table->string('product_color', 80)->nullable();
            $table->string('brand_cigam_code', 30)->nullable(); // pra matching brand-rule
            $table->string('product_size', 20)->nullable(); // tamanho do par "intacto" esperado

            // Tipo de problema (booleans, podem ser ambos)
            $table->boolean('is_mismatched')->default(false); // par com pé trocado
            $table->boolean('is_damaged')->default(false);    // par com avaria

            // Detalhes mismatched (preenchido quando is_mismatched=true)
            $table->enum('mismatched_foot', ['left', 'right'])->nullable();
            $table->string('mismatched_actual_size', 20)->nullable();
            $table->string('mismatched_expected_size', 20)->nullable();

            // Detalhes damaged (preenchido quando is_damaged=true)
            $table->foreignId('damage_type_id')->nullable()->constrained('damage_types')->nullOnDelete();
            $table->enum('damaged_foot', ['left', 'right', 'both', 'na'])->nullable();
            $table->text('damage_description')->nullable();
            $table->boolean('is_repairable')->default(false); // melhoria v2 — distinguir reparo de combinação
            $table->decimal('estimated_repair_cost', 10, 2)->nullable();

            // Status (state machine via DamagedProductStatus enum)
            $table->string('status', 30)->default('open');
            $table->text('notes')->nullable();

            // Auditoria
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            // TTL opcional (melhoria v2 — alerta antes de expirar)
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('status');
            $table->index('product_reference');
            $table->index('brand_cigam_code');
            $table->index(['store_id', 'status']);
            $table->index(['product_reference', 'status']);
            $table->index(['is_mismatched', 'is_damaged']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damaged_products');
    }
};
