<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taneia_messages', function (Blueprint $table) {
            // Feedback do usuario sobre a resposta do assistente:
            //   NULL = sem avaliacao, 1 = thumbs up, -1 = thumbs down.
            // So faz sentido em mensagens role=assistant; nao aplicamos
            // constraint pra manter flexibilidade.
            $table->tinyInteger('rating')->nullable()->after('sources');
            $table->index('rating');
        });
    }

    public function down(): void
    {
        Schema::table('taneia_messages', function (Blueprint $table) {
            $table->dropIndex(['rating']);
            $table->dropColumn('rating');
        });
    }
};
