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
            // AI suggestion columns — populated asynchronously by ClassifyTicketJob.
            // These hold the AI's opinion; the USER's choice stays in category_id
            // and priority. Dashboard surfaces disagreement for technician review.
            if (! Schema::hasColumn('hd_tickets', 'ai_category_id')) {
                $table->foreignId('ai_category_id')
                    ->nullable()
                    ->after('ai_classified_at')
                    ->constrained('hd_categories')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('hd_tickets', 'ai_priority')) {
                $table->unsignedTinyInteger('ai_priority')->nullable()->after('ai_category_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hd_tickets')) {
            return;
        }

        Schema::table('hd_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('hd_tickets', 'ai_category_id')) {
                $table->dropForeign(['ai_category_id']);
                $table->dropColumn('ai_category_id');
            }
            if (Schema::hasColumn('hd_tickets', 'ai_priority')) {
                $table->dropColumn('ai_priority');
            }
        });
    }
};
