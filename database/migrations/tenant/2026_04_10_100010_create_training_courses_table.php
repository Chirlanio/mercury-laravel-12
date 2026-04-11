<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_courses', function (Blueprint $table) {
            $table->id();
            $table->uuid('hash_id')->unique();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('thumbnail_path', 255)->nullable();
            $table->foreignId('subject_id')->nullable()->constrained('training_subjects')->nullOnDelete();
            $table->foreignId('facilitator_id')->nullable()->constrained('training_facilitators')->nullOnDelete();
            $table->string('visibility', 10)->default('private'); // public, private
            $table->string('status', 20)->default('draft'); // draft, published, archived
            $table->boolean('requires_sequential')->default(false);
            $table->boolean('certificate_on_completion')->default(false);
            $table->foreignId('certificate_template_id')->nullable()->constrained('certificate_templates')->nullOnDelete();
            $table->unsignedInteger('estimated_duration_minutes')->nullable();
            $table->dateTime('published_at')->nullable();

            // Audit
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('deleted_reason')->nullable();

            // Indexes
            $table->index('status');
            $table->index('visibility');
            $table->index(['status', 'visibility']);
            $table->index('subject_id');
            $table->index('facilitator_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_courses');
    }
};
