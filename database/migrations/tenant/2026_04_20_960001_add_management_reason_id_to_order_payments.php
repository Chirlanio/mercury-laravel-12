<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona management_reason_id em order_payments — o campo "Motivo Gerencial"
 * já existia no form do CreateModal mas não era persistido (validator aceitava,
 * mass assignment descartava). Agora passa a ter coluna + FK nullable para
 * management_reasons.
 *
 * Nullable para não quebrar OPs existentes. Idempotente via hasColumn.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('order_payments', 'management_reason_id')) {
            return;
        }

        Schema::table('order_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('management_reason_id')
                ->nullable()
                ->after('manager_id');

            $table->foreign('management_reason_id')
                ->references('id')
                ->on('management_reasons')
                ->nullOnDelete();

            $table->index('management_reason_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('order_payments', 'management_reason_id')) {
            return;
        }

        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropForeign(['management_reason_id']);
            $table->dropIndex(['management_reason_id']);
            $table->dropColumn('management_reason_id');
        });
    }
};
