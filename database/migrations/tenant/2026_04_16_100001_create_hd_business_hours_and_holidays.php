<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hd_business_hours')) {
            Schema::create('hd_business_hours', function (Blueprint $table) {
                $table->id();
                // Nullable department → fallback/global schedule when no department-specific row exists.
                $table->foreignId('department_id')->nullable()->constrained('hd_departments')->cascadeOnDelete();
                $table->unsignedTinyInteger('weekday')->comment('1=Mon .. 7=Sun (ISO)');
                $table->time('start_time');
                $table->time('end_time');
                $table->timestamps();

                $table->index(['department_id', 'weekday']);
            });
        }

        if (! Schema::hasTable('hd_holidays')) {
            Schema::create('hd_holidays', function (Blueprint $table) {
                $table->id();
                // Nullable department → applies to all departments.
                $table->foreignId('department_id')->nullable()->constrained('hd_departments')->cascadeOnDelete();
                $table->date('date');
                $table->string('description', 120)->nullable();
                $table->timestamps();

                $table->index(['department_id', 'date']);
                $table->unique(['department_id', 'date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hd_holidays');
        Schema::dropIfExists('hd_business_hours');
    }
};
