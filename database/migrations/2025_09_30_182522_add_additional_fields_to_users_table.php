<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Apelido/Nome abreviado
            $table->string('nickname', 220)->nullable()->after('name');

            // Username (usuário) - único para login
            $table->string('username', 220)->unique()->nullable()->after('email');

            // Loja do usuário (relacionamento com stores)
            $table->string('store_id', 4)->nullable()->after('role');
            $table->foreign('store_id')->references('code')->on('stores')->onDelete('set null');

            // Área do usuário
            $table->integer('area_id')->nullable()->after('store_id');

            // Chave para descadastro/desativação
            $table->string('unsubscribe_key', 220)->nullable()->after('remember_token');

            // Chave de confirmação de email
            $table->string('email_confirmation_key', 120)->nullable()->after('email_verified_at');

            // Status do usuário
            $table->integer('status_id')->default(1)->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropColumn([
                'nickname',
                'username',
                'store_id',
                'area_id',
                'unsubscribe_key',
                'email_confirmation_key',
                'status_id'
            ]);
        });
    }
};
