<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eventos de retorno parcial/total de uma consignação.
 *
 * Relação N:1 com `consignments` — uma consignação pode ter múltiplos
 * retornos (cliente devolve parte hoje, resto na próxima visita).
 * Cada evento referencia uma NF de retorno do CIGAM (movement_code=21)
 * via snapshot composite (store_code + invoice_number + date).
 *
 * Os itens devolvidos neste evento ficam em consignment_return_items,
 * que por sua vez apontam para consignment_items (pivô). A regra M1
 * (itens de retorno ⊆ itens de saída com quantidade ≤ pendente) é
 * validada em ConsignmentReturnService::register.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignment_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_id')->constrained('consignments')->cascadeOnDelete();

            // NF de retorno (pode vir do CIGAM ou ser informada manualmente)
            $table->string('return_invoice_number', 20)->nullable();
            $table->date('return_date');
            $table->string('return_store_code', 10);

            // Totais agregados
            $table->unsignedInteger('returned_quantity')->default(0);
            $table->decimal('returned_value', 12, 2)->default(0);

            // Link opcional para um movement específico (code=21) se houver
            // conciliação automática via command consignments:cigam-match
            $table->foreignId('movement_id')->nullable()->constrained('movements')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();

            $table->text('notes')->nullable();

            // Audit
            $table->foreignId('registered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['consignment_id', 'return_date']);
            $table->index(['return_store_code', 'return_invoice_number'], 'idx_cr_store_invoice');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_returns');
    }
};
