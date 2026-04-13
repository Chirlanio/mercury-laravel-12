<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hd_ticket_channels')) {
            return;
        }

        Schema::create('hd_ticket_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('hd_tickets')->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained('hd_channels')->cascadeOnDelete();
            // External contact — phone number, email address, etc. Nullable for web.
            $table->string('external_contact', 191)->nullable();
            // Provider-specific id — whatsapp message id, email message-id, etc.
            $table->string('external_id', 191)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['channel_id', 'external_contact']);
            $table->index(['ticket_id']);
            $table->index(['external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hd_ticket_channels');
    }
};
