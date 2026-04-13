<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for every explicit identity-resolution attempt made by the
 * helpdesk intake pipeline. Used for LGPD compliance — answers "who was
 * looked up, by what method, did it succeed, who triggered it".
 *
 * Immutable records — no updated_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hd_identity_lookups')) {
            return;
        }

        Schema::create('hd_identity_lookups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->nullable()->constrained('hd_channels')->nullOnDelete();
            // Whoever initiated the lookup — WhatsApp number, email address, or a
            // user_id stringified for web-authenticated flows. Free-form for audit.
            $table->string('external_contact', 191);
            // 'phone' = silent phone match; 'cpf' = user typed CPF; future: 'email'
            $table->string('method', 20);
            $table->boolean('matched')->default(false);
            // Populated only when matched=true. Nullable FK so the row survives
            // an employee deletion (soft audit trail).
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            // Attempt counter within the same session — helps analyze where
            // users get stuck on CPF entry.
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['channel_id', 'external_contact']);
            $table->index(['method', 'matched']);
            $table->index('employee_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hd_identity_lookups');
    }
};
