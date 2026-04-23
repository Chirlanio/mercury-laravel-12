<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona configuração de validação do link por rede social:
 *  - link_type: 'url' (YouTube/Facebook/genéricas) ou 'username'
 *    (Instagram/TikTok/X — aceita @ do perfil).
 *  - link_placeholder: hint exibida no input do modal.
 *
 * Permite que o backend e frontend validem o link de forma contextual
 * à rede escolhida (YouTube exige URL do canal; Instagram aceita @perfil).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('social_media', 'link_type')) {
            Schema::table('social_media', function (Blueprint $table) {
                $table->string('link_type', 20)->default('url')->after('icon');
                $table->string('link_placeholder', 100)->nullable()->after('link_type');
            });
        }

        // Atualiza as redes seedadas pra configuração correta
        $presets = [
            'Instagram' => ['username', '@usuario ou instagram.com/usuario'],
            'TikTok' => ['username', '@usuario ou tiktok.com/@usuario'],
            'YouTube' => ['url', 'https://youtube.com/@canal'],
            'Facebook' => ['url', 'https://facebook.com/pagina'],
            'X' => ['username', '@usuario ou x.com/usuario'],
            'Outra' => ['url', 'https://...'],
        ];

        foreach ($presets as $name => [$type, $placeholder]) {
            DB::table('social_media')
                ->where('name', $name)
                ->update([
                    'link_type' => $type,
                    'link_placeholder' => $placeholder,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('social_media', 'link_type')) {
            Schema::table('social_media', function (Blueprint $table) {
                $table->dropColumn(['link_type', 'link_placeholder']);
            });
        }
    }
};
