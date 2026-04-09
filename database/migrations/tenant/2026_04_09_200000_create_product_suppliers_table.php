<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates product_suppliers table for product catalog suppliers (from CIGAM).
 * Separate from the general suppliers table used in order payments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_for')->unique();
            $table->string('cnpj')->nullable();
            $table->string('razao_social');
            $table->string('nome_fantasia')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Migrate existing data from suppliers that are referenced by products
        if (Schema::hasTable('suppliers') && Schema::hasTable('products')) {
            DB::statement("
                INSERT INTO product_suppliers (codigo_for, cnpj, razao_social, nome_fantasia, is_active, created_at, updated_at)
                SELECT DISTINCT s.codigo_for, s.cnpj, s.razao_social, s.nome_fantasia, s.is_active, s.created_at, s.updated_at
                FROM suppliers s
                INNER JOIN products p ON p.supplier_codigo_for = s.codigo_for
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_suppliers');
    }
};
