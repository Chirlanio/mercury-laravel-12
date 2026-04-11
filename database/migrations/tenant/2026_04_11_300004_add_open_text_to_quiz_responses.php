<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('training_quiz_responses') && ! Schema::hasColumn('training_quiz_responses', 'response_text')) {
            Schema::table('training_quiz_responses', function (Blueprint $table) {
                $table->text('response_text')->nullable()->after('selected_options');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('training_quiz_responses') && Schema::hasColumn('training_quiz_responses', 'response_text')) {
            Schema::table('training_quiz_responses', function (Blueprint $table) {
                $table->dropColumn('response_text');
            });
        }
    }
};
