<?php

use App\Enums\ReturnReasonCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('return_reasons')) {
            return;
        }

        Schema::create('return_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            // Categoria fixa via enum (ReturnReasonCategory) — cada motivo
            // pertence a uma das 6 categorias. Permite análise agregada
            // no dashboard e filtro em cascata no frontend.
            $table->string('category', 30);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_active');
            $table->index('category');
        });

        // Seed inicial — paridade com adms_return_reasons (v1) enriquecido
        // com categorização. O v1 tem 6 motivos simples; ampliamos para 14
        // motivos categorizados que cobrem os casos reais do e-commerce.
        $now = now();
        DB::table('return_reasons')->insert([
            // Arrependimento
            ['code' => 'ARREPEND_GERAL', 'name' => 'Mudou de ideia', 'category' => ReturnReasonCategory::ARREPENDIMENTO->value, 'description' => 'Cliente desistiu da compra sem motivo específico (dentro do prazo legal do CDC).', 'is_active' => true, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'ARREPEND_VALOR', 'name' => 'Encontrou mais barato', 'category' => ReturnReasonCategory::ARREPENDIMENTO->value, 'description' => 'Cliente desistiu porque encontrou o produto por preço menor.', 'is_active' => true, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],

            // Defeito
            ['code' => 'DEFEITO_COSTURA', 'name' => 'Defeito de costura', 'category' => ReturnReasonCategory::DEFEITO->value, 'description' => 'Problema de fabricação na costura do produto.', 'is_active' => true, 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'DEFEITO_TECIDO', 'name' => 'Defeito no tecido', 'category' => ReturnReasonCategory::DEFEITO->value, 'description' => 'Furo, mancha ou outro problema no tecido.', 'is_active' => true, 'sort_order' => 40, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'DEFEITO_GERAL', 'name' => 'Outro defeito', 'category' => ReturnReasonCategory::DEFEITO->value, 'description' => 'Defeito de qualidade não especificado.', 'is_active' => true, 'sort_order' => 50, 'created_at' => $now, 'updated_at' => $now],

            // Divergência
            ['code' => 'DIV_COR', 'name' => 'Cor diferente do anúncio', 'category' => ReturnReasonCategory::DIVERGENCIA->value, 'description' => 'Cor do produto entregue diverge da cor mostrada nas fotos do e-commerce.', 'is_active' => true, 'sort_order' => 60, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'DIV_MODELO', 'name' => 'Modelo diferente do anúncio', 'category' => ReturnReasonCategory::DIVERGENCIA->value, 'description' => 'Modelo entregue diverge do produto anunciado.', 'is_active' => true, 'sort_order' => 70, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'DIV_QTD', 'name' => 'Quantidade divergente', 'category' => ReturnReasonCategory::DIVERGENCIA->value, 'description' => 'Quantidade recebida menor que a comprada.', 'is_active' => true, 'sort_order' => 80, 'created_at' => $now, 'updated_at' => $now],

            // Tamanho / Cor errados (erro do cliente, não da loja)
            ['code' => 'TAM_PEQUENO', 'name' => 'Tamanho ficou pequeno', 'category' => ReturnReasonCategory::TAMANHO_COR->value, 'description' => 'Produto não serviu — tamanho menor que o esperado.', 'is_active' => true, 'sort_order' => 90, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'TAM_GRANDE', 'name' => 'Tamanho ficou grande', 'category' => ReturnReasonCategory::TAMANHO_COR->value, 'description' => 'Produto não serviu — tamanho maior que o esperado.', 'is_active' => true, 'sort_order' => 100, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'COR_ERRADA', 'name' => 'Escolheu cor errada', 'category' => ReturnReasonCategory::TAMANHO_COR->value, 'description' => 'Cliente escolheu cor diferente da que queria.', 'is_active' => true, 'sort_order' => 110, 'created_at' => $now, 'updated_at' => $now],

            // Não recebido
            ['code' => 'EXTRAVIO', 'name' => 'Extravio na entrega', 'category' => ReturnReasonCategory::NAO_RECEBIDO->value, 'description' => 'Pedido não chegou ao destinatário — extraviado pela transportadora.', 'is_active' => true, 'sort_order' => 120, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'ENTREGA_INCORRETA', 'name' => 'Entregue em endereço errado', 'category' => ReturnReasonCategory::NAO_RECEBIDO->value, 'description' => 'Transportadora entregou em local diferente do cadastrado.', 'is_active' => true, 'sort_order' => 130, 'created_at' => $now, 'updated_at' => $now],

            // Outro (fallback)
            ['code' => 'OUTRO', 'name' => 'Outro motivo', 'category' => ReturnReasonCategory::OUTRO->value, 'description' => 'Motivo não enquadrado nas categorias acima — detalhar na observação.', 'is_active' => true, 'sort_order' => 999, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('return_reasons');
    }
};
