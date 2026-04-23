<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela de clientes sincronizada do CIGAM (view msl_dcliente_).
 *
 * Fonte de verdade: CIGAM. Mercury mantém cópia local para:
 *  - lookup rápido ao cadastrar consignação (autocomplete)
 *  - histórico relacional (Consignment.customer_id FK)
 *  - filtros/relatórios locais sem round-trip ao CIGAM
 *
 * Sanitização aplicada antes de persistir (CustomerSyncService):
 *  - name, neighborhood, city, address: uppercase + trim
 *  - email: lowercase + trim + validação formato
 *  - cpf, phone, mobile, cep: só dígitos
 *  - birth_date, registered_at: parse flexível (CIGAM pode retornar
 *    formatos diferentes), null se inválido
 *
 * Campo chave: cigam_code (codigo_cliente + digito_cliente concatenados
 * da view) — usado em upsert. CPF pode duplicar entre códigos CIGAM
 * (ex: cliente com cadastros duplicados); por isso cigam_code é o PK
 * natural, não o CPF.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            // Chave natural do CIGAM (codigo_cliente + digito_cliente)
            $table->string('cigam_code', 20)->unique();

            // Identificação
            $table->string('name', 200);
            $table->string('cpf', 14)->nullable();
            $table->char('person_type', 1)->nullable(); // F=física, J=jurídica (tip_pessoa)
            $table->char('gender', 1)->nullable();      // M/F — conforme CIGAM, pode ser null

            // Contato
            $table->string('email', 255)->nullable();
            $table->string('phone', 15)->nullable();  // DDD + número, só dígitos
            $table->string('mobile', 15)->nullable(); // DDD + número, só dígitos

            // Endereço
            $table->string('address', 250)->nullable();
            $table->string('number', 20)->nullable();
            $table->string('complement', 100)->nullable();
            $table->string('neighborhood', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 2)->nullable(); // UF
            $table->string('zipcode', 10)->nullable(); // CEP só dígitos

            // Datas
            $table->date('birth_date')->nullable();    // data_aniversario
            $table->date('registered_at')->nullable(); // data_cadastramento

            // Controle
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable();

            $table->timestamps();

            $table->index(['cpf']);
            $table->index(['name']);
            $table->index(['mobile']);
            $table->index(['city', 'state']);
            $table->index(['synced_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
