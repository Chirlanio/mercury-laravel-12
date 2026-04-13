<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hd_chat_sessions')) {
            return;
        }

        Schema::create('hd_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('hd_channels')->cascadeOnDelete();
            // External contact identifier — e.g. WhatsApp phone number, email address.
            $table->string('external_contact', 191);
            // Current step in the conversational state machine. Free-form string so
            // drivers can define their own flows (e.g. 'awaiting_cpf', 'awaiting_demand',
            // 'template_collecting', 'complete').
            $table->string('step', 60);
            $table->json('context')->nullable();
            // Ticket becomes available once intake produces one. Nullable until then.
            $table->foreignId('ticket_id')->nullable()->constrained('hd_tickets')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['channel_id', 'external_contact']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hd_chat_sessions');
    }
};
