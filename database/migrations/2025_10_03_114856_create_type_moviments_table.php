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
        Schema::create('type_moviments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100); // Nome do tipo de movimentação
            $table->string('description', 255)->nullable(); // Descrição
            $table->boolean('is_active')->default(true); // Status ativo/inativo
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('type_moviments');
    }
};
