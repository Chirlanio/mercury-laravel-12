<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Itens de prestação de contas de uma verba de viagem (substitui
 * adms_travel_expense_reimbursements da v1).
 *
 * Cada item representa um gasto durante a viagem: tipo (Alimentação,
 * Transporte, etc), valor, data, NF/recibo opcional, observação e
 * comprovante anexado.
 *
 * Uploads:
 *  - attachment_path: caminho relativo no disk 'tenant_assets', sob
 *    travel-expenses/{ulid}/{filename}
 *  - attachment_original_name + attachment_mime + attachment_size pra
 *    auditoria e download (Storage::download com Content-Disposition)
 *
 * Soft delete manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_expense_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_expense_id')->constrained('travel_expenses')->cascadeOnDelete();
            $table->foreignId('type_expense_id')->constrained('type_expenses')->restrictOnDelete();

            $table->date('expense_date');
            $table->decimal('value', 10, 2);
            $table->string('invoice_number', 30)->nullable();
            $table->string('description', 250);

            // === COMPROVANTE ===
            $table->string('attachment_path', 500)->nullable();
            $table->string('attachment_original_name', 200)->nullable();
            $table->string('attachment_mime', 100)->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable(); // bytes

            // === AUDIT ===
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Soft delete manual
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // === ÍNDICES ===
            $table->index(['travel_expense_id', 'expense_date']);
            $table->index('type_expense_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_expense_items');
    }
};
