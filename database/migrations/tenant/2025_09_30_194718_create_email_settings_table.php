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
        Schema::create('email_settings', function (Blueprint $table) {
            $table->id();
            $table->string('driver', 50)->default('smtp')->comment('Driver de e-mail (smtp, sendmail, mailgun, etc.)');
            $table->string('host')->nullable()->comment('Servidor SMTP');
            $table->integer('port')->nullable()->comment('Porta do servidor SMTP');
            $table->string('encryption', 20)->nullable()->comment('Tipo de criptografia (tls, ssl)');
            $table->string('username')->nullable()->comment('Usuário para autenticação SMTP');
            $table->text('password')->nullable()->comment('Senha para autenticação SMTP (criptografada)');
            $table->integer('timeout')->default(60)->comment('Timeout em segundos');
            $table->string('from_address')->nullable()->comment('E-mail do remetente padrão');
            $table->string('from_name')->nullable()->comment('Nome do remetente padrão');
            $table->boolean('is_active')->default(true)->comment('Se esta configuração está ativa');
            $table->text('notes')->nullable()->comment('Observações sobre a configuração');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_settings');
    }
};
