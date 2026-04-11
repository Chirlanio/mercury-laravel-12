<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnel_movement_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_movement_id')->constrained('personnel_movements')->cascadeOnDelete();
            $table->foreignId('management_reason_id')->constrained('management_reasons')->cascadeOnDelete();
            $table->unique(['personnel_movement_id', 'management_reason_id'], 'pm_reason_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_movement_reasons');
    }
};
