<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employee_event_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50); // Férias, Licença, Falta, Atestado Médico
            $table->string('description')->nullable();
            $table->boolean('requires_document')->default(false); // Se requer anexo de documento
            $table->boolean('requires_date_range')->default(false); // Se requer período (início e fim)
            $table->boolean('requires_single_date')->default(false); // Se requer apenas uma data
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_event_types');
    }
};
