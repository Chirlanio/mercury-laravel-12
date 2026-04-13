<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CSAT survey records. One row per ticket, created when a ticket is
 * marked RESOLVED. The requester gets a signed URL by email or WhatsApp
 * and has until expires_at (default 7 days) to submit a rating.
 *
 * Signed URLs are stateless — we still store a token in the DB so
 * submissions can be traced and prevented from double-rating.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hd_satisfaction_surveys')) {
            return;
        }

        Schema::create('hd_satisfaction_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->unique()->constrained('hd_tickets')->cascadeOnDelete();
            // The person who will rate. Snapshotted at creation time so
            // deleting the user later doesn't orphan the survey.
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            // Who resolved the ticket at the time the survey was sent.
            // Used in reporting ("average CSAT per technician").
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('hd_departments')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('hd_categories')->nullOnDelete();
            // 1-5 star rating. Null until the user submits.
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('comment', 1000)->nullable();
            // URL-safe random token embedded in the signed URL payload.
            // 40 chars of Str::random() gives ~240 bits of entropy.
            $table->string('signed_token', 64)->unique();
            // 'email' | 'whatsapp' — channel the invitation was sent through.
            $table->string('sent_via', 20)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['submitted_at', 'rating']);
            $table->index('resolved_by_user_id');
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hd_satisfaction_surveys');
    }
};
