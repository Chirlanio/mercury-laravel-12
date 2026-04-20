<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona management_class_id em order_payments — peça central da cascata
 * Área → Gerencial → CC → AC do form da OP.
 *
 * A classe gerencial (padrão 8.1.DD.UU) codifica num único lookup:
 *   - DD = departamento/área (Marketing/Operações/Fiscal/...)
 *   - UU = unidade de negócio (loja ou transversal)
 *
 * Escolher a MC no form fixa o CC automaticamente (derivado do
 * management_classes.cost_center_id pré-vinculado no seed 500002) e
 * restringe a lista de ACs disponíveis. Zero ambiguidade de qual
 * departamento a despesa pertence.
 *
 * Nullable aqui para não quebrar OPs antigas; obrigatoriedade fica para
 * o commit de backfill junto com cost_center_id/accounting_class_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('management_class_id')
                ->nullable()
                ->after('accounting_class_id');

            $table->foreign('management_class_id')
                ->references('id')
                ->on('management_classes')
                ->nullOnDelete();

            $table->index('management_class_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropForeign(['management_class_id']);
            $table->dropIndex(['management_class_id']);
            $table->dropColumn('management_class_id');
        });
    }
};
