<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_quiz_responses')) {
            return;
        }

        Schema::table('training_quiz_responses', function (Blueprint $table) {
            if (! Schema::hasColumn('training_quiz_responses', 'feedback')) {
                $table->text('feedback')->nullable()->after('response_text');
            }
            if (! Schema::hasColumn('training_quiz_responses', 'graded_by_user_id')) {
                $table->unsignedBigInteger('graded_by_user_id')->nullable()->after('feedback');
            }
            if (! Schema::hasColumn('training_quiz_responses', 'graded_at')) {
                $table->timestamp('graded_at')->nullable()->after('graded_by_user_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('training_quiz_responses')) {
            return;
        }

        Schema::table('training_quiz_responses', function (Blueprint $table) {
            $table->dropColumn(['feedback', 'graded_by_user_id', 'graded_at']);
        });
    }
};
