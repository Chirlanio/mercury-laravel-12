<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atividades de relacionamento com clientes VIP.
 *
 * Feed CRM-light operado pela equipe de Marketing: envio de brinde, convite
 * para evento, contato telefônico, nota interna, etc. Não é exclusivo de
 * clientes VIP (customer_id referencia customers genericamente), mas na UI
 * fica dentro do fluxo VIP — outros módulos podem vir a consumir mais tarde.
 *
 * Isolada do sync CIGAM. Soft deletes para auditoria — Marketing pode
 * restaurar uma atividade deletada por engano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_vip_activities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

            $table->enum('type', ['gift', 'event', 'contact', 'note', 'other']);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->date('occurred_at');

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Metadata livre para extensões futuras (ex: nome do evento, valor
            // estimado do brinde, link de campanha).
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_vip_activities');
    }
};
