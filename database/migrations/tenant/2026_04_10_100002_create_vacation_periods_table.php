<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacation_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date_start_acq'); // Início do período aquisitivo
            $table->date('date_end_acq'); // Fim do período aquisitivo (12 meses)
            $table->date('date_limit_concessive'); // Limite para gozo (12 meses após fim aquisitivo)
            $table->unsignedSmallInteger('days_entitled')->default(30); // Dias de direito (Art. 130)
            $table->unsignedSmallInteger('days_taken')->default(0); // Dias já usufruídos
            $table->unsignedSmallInteger('sell_days')->default(0); // Dias vendidos (abono)
            $table->unsignedSmallInteger('absences_count')->default(0); // Faltas injustificadas no período
            $table->string('status', 30)->default('acquiring'); // acquiring, available, partially_taken, settled, expired, lost
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'date_start_acq']);
            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacation_periods');
    }
};
