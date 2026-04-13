<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hd_departments')) {
            return;
        }

        Schema::table('hd_departments', function (Blueprint $table) {
            // Per-department opt-in to AI classification. Defaults to false so
            // the feature is inert until an admin explicitly turns it on.
            if (! Schema::hasColumn('hd_departments', 'ai_classification_enabled')) {
                $table->boolean('ai_classification_enabled')
                    ->default(false)
                    ->after('requires_identification');
            }
            // Optional custom prompt for this department. When null, the
            // classifier falls back to config('helpdesk.ai.default_prompt').
            // Admins can override per-department to bake in domain-specific
            // hints, e.g. "Para DP, chamados sobre 'atestado médico' têm
            // prioridade 3 quando urgentes e 2 caso contrário".
            if (! Schema::hasColumn('hd_departments', 'ai_classification_prompt')) {
                $table->text('ai_classification_prompt')->nullable()->after('ai_classification_enabled');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hd_departments')) {
            return;
        }

        Schema::table('hd_departments', function (Blueprint $table) {
            if (Schema::hasColumn('hd_departments', 'ai_classification_prompt')) {
                $table->dropColumn('ai_classification_prompt');
            }
            if (Schema::hasColumn('hd_departments', 'ai_classification_enabled')) {
                $table->dropColumn('ai_classification_enabled');
            }
        });
    }
};
