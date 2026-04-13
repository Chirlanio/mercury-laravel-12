<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hd_channels')) {
            return;
        }

        $exists = DB::table('hd_channels')->where('slug', 'whatsapp')->exists();
        if ($exists) {
            return;
        }

        // Seeded as INACTIVE by default. Admin flips is_active=true once
        // EVOLUTION_* env vars are configured on the tenant host.
        DB::table('hd_channels')->insert([
            'slug' => 'whatsapp',
            'name' => 'WhatsApp',
            'driver' => 'whatsapp',
            'config' => json_encode([
                'greeting' => 'Olá! Sou o atendimento virtual do Grupo Meia Sola. Como posso ajudar?',
                'fallback_department_id' => null,
            ]),
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('hd_channels')) {
            return;
        }

        DB::table('hd_channels')->where('slug', 'whatsapp')->delete();
    }
};
