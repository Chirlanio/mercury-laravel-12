<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Captures technician corrections to AI-classified tickets. Used as a
 * feedback loop to measure AI accuracy per department/category over time
 * and, eventually, to curate a fine-tuning dataset.
 *
 * Immutable rows — no updated_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hd_ai_classification_corrections')) {
            return;
        }

        Schema::create('hd_ai_classification_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('hd_tickets')->cascadeOnDelete();
            // What the AI originally suggested — stored as snapshot so later
            // category renames don't corrupt the history.
            $table->unsignedBigInteger('original_ai_category_id')->nullable();
            $table->unsignedTinyInteger('original_ai_priority')->nullable();
            $table->decimal('original_ai_confidence', 3, 2)->nullable();
            $table->string('original_ai_model', 80)->nullable();
            // What the technician changed it to.
            $table->unsignedBigInteger('corrected_category_id')->nullable();
            $table->unsignedTinyInteger('corrected_priority')->nullable();
            // Who made the correction — nullable because the technician may
            // later be deleted but the audit record should survive.
            $table->foreignId('corrected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index('ticket_id');
            $table->index('corrected_by_user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hd_ai_classification_corrections');
    }
};
