<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cupons de desconto — paridade com v1 (adms_coupons) adaptado ao modelo v2:
 *
 *  - Tipos de beneficiário (enum CouponType): consultor, influencer, ms_indica.
 *  - CPF armazenado encriptado (cast `encrypted` no Model) + cpf_hash
 *    (HMAC-SHA256 determinístico) pra busca e validação de unicidade.
 *  - Store via código (FK string pra stores.code, igual Reversals/Returns).
 *  - State machine de 6 estados em CouponStatus (draft → requested → issued
 *    → active → expired|cancelled). Mutação exclusivamente via
 *    CouponTransitionService.
 *
 * Unicidade (1 cupom ativo por beneficiário) validada no CouponService,
 * NÃO via unique constraint. Motivo: chave composta varia por tipo:
 *   - consultor/ms_indica: (cpf_hash, type, store_code)
 *   - influencer: (cpf_hash, type) — sem store
 * MySQL não trata NULL como igual a NULL em unique composite, então um
 * índice único permitiria duplicatas influencer. Segue o padrão Reversals.
 *
 * Soft delete manual (convenção do projeto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            // === TIPO E ESTADO ===
            // consultor | influencer | ms_indica (CouponType enum)
            $table->string('type', 20);
            // draft | requested | issued | active | expired | cancelled (CouponStatus)
            $table->string('status', 30)->default('draft');

            // === BENEFICIÁRIO ===
            // Employee é opcional pra influencer (que não é colaborador)
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();

            // Store é obrigatória pra consultor/ms_indica, nula pra influencer
            $table->string('store_code', 10)->nullable();

            // Nome do influencer (quando não há employee vinculado)
            $table->string('influencer_name', 120)->nullable();

            // CPF armazenado encriptado (cast `encrypted` no Model)
            $table->text('cpf');

            // Hash determinístico do CPF (HMAC-SHA256 com app.key como secret)
            // Permite busca exata + validação de unicidade sem expor o CPF
            // em claro. Tamanho 64 = sha256 em hex.
            $table->string('cpf_hash', 64);

            // === INFLUENCER-SPECIFIC ===
            $table->foreignId('social_media_id')->nullable()->constrained('social_media')->nullOnDelete();
            $table->string('social_media_link', 250)->nullable();
            $table->string('city', 60)->nullable();

            // === CÓDIGO DO CUPOM ===
            // Sugestão do solicitante (ex: "MARIA25")
            $table->string('suggested_coupon', 30)->nullable();
            // Código efetivo emitido na plataforma (preenchido pelo
            // e-commerce na transição requested → issued)
            $table->string('coupon_site', 30)->nullable();

            // === CAMPANHA E VALIDADE ===
            $table->string('campaign_name', 80)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();

            // === USO (contador manual — integração externa fora do escopo) ===
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedInteger('max_uses')->nullable();
            $table->timestamp('last_used_at')->nullable();

            // === TIMESTAMPS DO WORKFLOW ===
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();

            // === OBSERVAÇÕES ===
            $table->text('notes')->nullable();

            // === AUDIT ===
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Soft delete manual
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('deleted_reason')->nullable();

            // === ÍNDICES ===
            $table->index(['store_code', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('type');
            $table->index('employee_id');
            // Dedup lookup — checagem via service (MySQL null-aware)
            $table->index(['cpf_hash', 'type', 'store_code'], 'idx_coupon_dedup_lookup');
            // Code lookup — evita duplicar coupon_site entre cupons ativos
            $table->index('coupon_site');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
