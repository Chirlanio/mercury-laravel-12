<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_audit_store_justifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained('stock_audits')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('stock_audit_items')->cascadeOnDelete();
            $table->text('justification_text');
            $table->decimal('found_quantity', 10, 2)->nullable();
            $table->foreignId('submitted_by_user_id')->constrained('users');
            $table->timestamp('submitted_at');
            $table->string('review_status', 20)->default('pending'); // pending, accepted, rejected
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_audit_justification_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('justification_id')->constrained('stock_audit_store_justifications')->cascadeOnDelete();
            $table->string('file_path', 500);
            $table->string('file_name', 255);
            $table->foreignId('uploaded_by_user_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_audit_justification_images');
        Schema::dropIfExists('stock_audit_store_justifications');
    }
};
