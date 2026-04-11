<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dismissal_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed default dismissal reasons
        $reasons = [
            'Desalinhamento cultural e comportamental',
            'Baixo fit com a vaga e escopo de trabalho',
            'Nova proposta de trabalho / Remuneração',
            'Motivos pessoais (saúde, mudança de localidade, etc)',
            'Mudança de carreira para outras áreas',
            'Performance e produtividade abaixo do esperado',
            'Ausência frequente no trabalho',
            'Contrato temporário',
        ];

        $now = now();
        foreach ($reasons as $reason) {
            DB::table('dismissal_reasons')->insert([
                'name' => $reason,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Update pivot table to reference dismissal_reasons
        // SQLite doesn't support dropColumn with unique indexes, so recreate the table
        if (DB::getDriverName() === 'sqlite') {
            Schema::dropIfExists('personnel_movement_reasons');
            Schema::create('personnel_movement_reasons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('personnel_movement_id')->constrained('personnel_movements')->cascadeOnDelete();
                $table->foreignId('dismissal_reason_id')->constrained('dismissal_reasons')->cascadeOnDelete();
                $table->unique(['personnel_movement_id', 'dismissal_reason_id'], 'pm_reason_unique');
            });
        } else {
            Schema::table('personnel_movement_reasons', function (Blueprint $table) {
                $table->dropForeign(['management_reason_id']);
                $table->dropColumn('management_reason_id');
                $table->foreignId('dismissal_reason_id')->after('personnel_movement_id')->constrained('dismissal_reasons')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('personnel_movement_reasons', function (Blueprint $table) {
            $table->dropForeign(['dismissal_reason_id']);
            $table->dropColumn('dismissal_reason_id');
            $table->foreignId('management_reason_id')->after('personnel_movement_id')->constrained('management_reasons')->cascadeOnDelete();
        });

        Schema::dropIfExists('dismissal_reasons');
    }
};
