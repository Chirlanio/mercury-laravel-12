<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Cost Centers (Centros de Custo)
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20);
            $table->string('name');
            $table->unsignedBigInteger('area_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('area_id');
        });

        // Banks (Bancos)
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name', 150);
            $table->integer('cod_bank')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Brands (Marcas)
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Payment Types (Tipos de Pagamento)
        Schema::create('payment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // PIX Key Types (Tipos de Chave PIX)
        Schema::create('pix_key_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Management Reasons (Motivos Gerenciais)
        Schema::create('management_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed default data
        $now = now();

        DB::table('banks')->insert([
            ['bank_name' => 'Banco do Brasil', 'cod_bank' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['bank_name' => 'Bradesco', 'cod_bank' => 237, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['bank_name' => 'Itaú', 'cod_bank' => 341, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['bank_name' => 'Santander', 'cod_bank' => 33, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['bank_name' => 'Caixa Econômica', 'cod_bank' => 104, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['bank_name' => 'Nubank', 'cod_bank' => 260, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['bank_name' => 'Banco Inter', 'cod_bank' => 77, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['bank_name' => 'PagBank', 'cod_bank' => 290, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['bank_name' => 'C6 Bank', 'cod_bank' => 336, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['bank_name' => 'Stone Pagamentos', 'cod_bank' => 197, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['bank_name' => 'Sicredi', 'cod_bank' => 748, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['bank_name' => 'Banco Safra', 'cod_bank' => 422, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('brands')->insert([
            ['name' => 'Arezzo', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Schutz', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Anacapri', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Meia Sola', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Ms Off', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Geral', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('payment_types')->insert([
            ['name' => 'PIX', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Transferência Bancária', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Dinheiro', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Cartão', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Boleto', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'QR Code', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Depósito', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('pix_key_types')->insert([
            ['name' => 'CPF/CNPJ', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'E-mail', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Telefone', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Chave Aleatória', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('management_reasons');
        Schema::dropIfExists('pix_key_types');
        Schema::dropIfExists('payment_types');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('banks');
        Schema::dropIfExists('cost_centers');
    }
};
