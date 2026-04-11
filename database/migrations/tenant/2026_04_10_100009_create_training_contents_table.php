<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_contents', function (Blueprint $table) {
            $table->id();
            $table->uuid('hash_id')->unique();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('content_type', 20); // video, audio, document, link, text
            $table->string('file_path', 500)->nullable();
            $table->string('file_name', 255)->nullable();
            $table->unsignedBigInteger('file_size')->nullable(); // bytes
            $table->string('file_mime_type', 100)->nullable();
            $table->string('external_url', 500)->nullable();
            $table->longText('text_content')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('thumbnail_path', 255)->nullable();
            $table->foreignId('category_id')->nullable()->constrained('training_content_categories')->nullOnDelete();
            $table->boolean('is_active')->default(true);

            // Audit
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Indexes
            $table->index('content_type');
            $table->index('category_id');
            $table->index('is_active');
            $table->index(['is_active', 'content_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_contents');
    }
};
