<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taneia_messages', function (Blueprint $table) {
            // Lista de documentos citados pelo RAG: [{"filename": "...", "page": N}, ...]
            // Nullable porque apenas respostas do assistente com contexto tem fontes.
            $table->json('sources')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('taneia_messages', function (Blueprint $table) {
            $table->dropColumn('sources');
        });
    }
};
