<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hd_intake_templates')) {
            Schema::create('hd_intake_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('department_id')->nullable()->constrained('hd_departments')->cascadeOnDelete();
                $table->foreignId('category_id')->nullable()->constrained('hd_categories')->cascadeOnDelete();
                $table->string('name', 120);
                // Field schema as JSON: [{name, label, type, required, options}]
                // Types: text, textarea, date, select, multiselect, boolean, file
                $table->json('fields');
                $table->boolean('active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['department_id', 'active']);
                $table->index(['category_id', 'active']);
            });
        }

        if (! Schema::hasTable('hd_ticket_intake_data')) {
            Schema::create('hd_ticket_intake_data', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_id')->constrained('hd_tickets')->cascadeOnDelete();
                $table->foreignId('template_id')->nullable()->constrained('hd_intake_templates')->nullOnDelete();
                $table->json('data');
                $table->timestamps();

                $table->index('ticket_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hd_ticket_intake_data');
        Schema::dropIfExists('hd_intake_templates');
    }
};
