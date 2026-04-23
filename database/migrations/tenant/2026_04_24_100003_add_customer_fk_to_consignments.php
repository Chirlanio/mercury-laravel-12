<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liga consignments.customer_id ao registro real em customers.
 *
 * Na Fase 1 o campo foi criado como unsignedBigInteger sem constraint
 * porque o módulo Customers ainda não existia. Agora que foi criado
 * (Fase 5), adicionamos o foreign key com nullOnDelete — consignações
 * antigas/não vinculadas continuam válidas, mas deletar um cliente
 * apenas desanexa sem excluir a consignação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consignments', function (Blueprint $table) {
            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('consignments', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });
    }
};
