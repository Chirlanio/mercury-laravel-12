<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hd_attachments')) {
            return;
        }

        Schema::create('hd_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('hd_tickets')->cascadeOnDelete();
            $table->foreignId('interaction_id')->nullable()->constrained('hd_interactions')->nullOnDelete();
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('file_path');
            $table->string('mime_type');
            $table->unsignedInteger('size_bytes');
            $table->foreignId('uploaded_by_user_id')->constrained('users');
            $table->timestamps();

            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hd_attachments');
    }
};
