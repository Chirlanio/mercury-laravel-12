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
        Schema::create('email_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 220);
            $table->string('email', 220);
            $table->string('host', 220);
            $table->string('username', 220);
            $table->string('password', 120);
            $table->string('smtp_security', 10)->default('tls');
            $table->integer('port')->default(587);
            $table->timestamps();

            $table->index('email');
            $table->index('host');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_configurations');
    }
};
