<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_adjustment_attachments')) {
            return;
        }

        Schema::create('stock_adjustment_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')
                ->constrained('stock_adjustments')
                ->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('file_path');
            $table->string('mime_type');
            $table->unsignedInteger('size_bytes');
            $table->foreignId('uploaded_by_user_id')
                ->constrained('users');
            $table->timestamps();

            $table->index('stock_adjustment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_attachments');
    }
};
