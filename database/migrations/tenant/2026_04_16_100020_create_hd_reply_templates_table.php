<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reply templates (macros) that technicians can insert into a ticket
 * comment with a single click. Supports simple {{placeholder}}
 * substitution (ticket.id, ticket.title, requester.name, etc.)
 * resolved at paste time on the frontend so the technician still sees
 * the text before sending.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hd_reply_templates')) {
            return;
        }

        Schema::create('hd_reply_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            // Short label / folder for grouping in the dropdown.
            $table->string('category', 60)->nullable();
            $table->text('body');
            // Optional scope — when set, only technicians of that
            // department see the template. When null, it's shared across
            // all departments.
            $table->foreignId('department_id')->nullable()->constrained('hd_departments')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            // is_shared=true → any technician with MANAGE_TICKETS sees it
            // is_shared=false → only the author sees it (personal macro)
            $table->boolean('is_shared')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'is_shared']);
            $table->index(['department_id', 'is_active']);
            $table->index('author_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hd_reply_templates');
    }
};
