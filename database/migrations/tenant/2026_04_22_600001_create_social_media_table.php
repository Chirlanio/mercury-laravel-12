<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('social_media')) {
            return;
        }

        Schema::create('social_media', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('icon', 60)->nullable();
            // Configuração de validação do link (ver Model SocialMedia::validateLink)
            $table->string('link_type', 20)->default('url');
            $table->string('link_placeholder', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_active');
        });

        $now = now();
        DB::table('social_media')->insert([
            ['name' => 'Instagram', 'icon' => 'fa-brands fa-instagram', 'link_type' => 'username', 'link_placeholder' => '@usuario ou instagram.com/usuario',       'is_active' => true, 'sort_order' => 10,  'created_at' => $now, 'updated_at' => $now],
            ['name' => 'TikTok',    'icon' => 'fa-brands fa-tiktok',    'link_type' => 'username', 'link_placeholder' => '@usuario ou tiktok.com/@usuario',          'is_active' => true, 'sort_order' => 20,  'created_at' => $now, 'updated_at' => $now],
            ['name' => 'YouTube',   'icon' => 'fa-brands fa-youtube',   'link_type' => 'url',      'link_placeholder' => 'https://youtube.com/@canal',               'is_active' => true, 'sort_order' => 30,  'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Facebook',  'icon' => 'fa-brands fa-facebook',  'link_type' => 'url',      'link_placeholder' => 'https://facebook.com/pagina',              'is_active' => true, 'sort_order' => 40,  'created_at' => $now, 'updated_at' => $now],
            ['name' => 'X',         'icon' => 'fa-brands fa-x-twitter', 'link_type' => 'username', 'link_placeholder' => '@usuario ou x.com/usuario',                'is_active' => true, 'sort_order' => 50,  'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Outra',     'icon' => 'fa-solid fa-globe',      'link_type' => 'url',      'link_placeholder' => 'https://...',                              'is_active' => true, 'sort_order' => 999, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('social_media');
    }
};
