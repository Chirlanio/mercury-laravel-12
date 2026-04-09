<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_audit_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained('stock_audits')->cascadeOnDelete();
            $table->foreignId('signer_user_id')->constrained('users');
            $table->string('signer_role', 30); // gerente, auditor, supervisor
            $table->mediumText('signature_data'); // base64 canvas data
            $table->string('ip_address', 45);
            $table->string('user_agent', 500);
            $table->timestamp('signed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_audit_signatures');
    }
};
