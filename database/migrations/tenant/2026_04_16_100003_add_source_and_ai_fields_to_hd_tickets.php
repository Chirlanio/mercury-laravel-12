<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hd_tickets')) {
            return;
        }

        Schema::table('hd_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('hd_tickets', 'source')) {
                $table->string('source', 20)->default('web')->after('priority');
                $table->index('source');
            }
            if (! Schema::hasColumn('hd_tickets', 'ai_confidence')) {
                $table->decimal('ai_confidence', 3, 2)->nullable()->after('source');
            }
            if (! Schema::hasColumn('hd_tickets', 'ai_model')) {
                $table->string('ai_model', 80)->nullable()->after('ai_confidence');
            }
            if (! Schema::hasColumn('hd_tickets', 'ai_classified_at')) {
                $table->timestamp('ai_classified_at')->nullable()->after('ai_model');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hd_tickets')) {
            return;
        }

        Schema::table('hd_tickets', function (Blueprint $table) {
            foreach (['ai_classified_at', 'ai_model', 'ai_confidence', 'source'] as $col) {
                if (Schema::hasColumn('hd_tickets', $col)) {
                    if ($col === 'source') {
                        $table->dropIndex(['source']);
                    }
                    $table->dropColumn($col);
                }
            }
        });
    }
};
