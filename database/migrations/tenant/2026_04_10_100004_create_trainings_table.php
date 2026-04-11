<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->uuid('hash_id')->unique();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('image_path', 255)->nullable();
            $table->date('event_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->string('location', 255)->nullable();
            $table->unsignedInteger('max_participants')->nullable();
            $table->foreignId('facilitator_id')->constrained('training_facilitators');
            $table->foreignId('subject_id')->constrained('training_subjects');
            $table->string('status', 20)->default('draft'); // draft, published, in_progress, completed, cancelled
            $table->string('attendance_qrcode_token', 64)->unique();
            $table->string('evaluation_qrcode_token', 64)->unique();
            $table->boolean('allow_late_attendance')->default(false);
            $table->unsignedInteger('attendance_grace_minutes')->default(15);
            $table->foreignId('certificate_template_id')->nullable()->constrained('certificate_templates')->nullOnDelete();
            $table->boolean('evaluation_enabled')->default(true);

            // Audit
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('deleted_reason')->nullable();

            // Indexes
            $table->index('event_date');
            $table->index('status');
            $table->index('facilitator_id');
            $table->index('subject_id');
            $table->index(['status', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainings');
    }
};
