<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\EmailSetting;

class EmailSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Criar configuração de e-mail a partir das variáveis de ambiente
        EmailSetting::create([
            'driver' => config('mail.default', 'smtp'),
            'host' => config('mail.mailers.smtp.host', 'smtp.gmail.com'),
            'port' => config('mail.mailers.smtp.port', 587),
            'encryption' => config('mail.mailers.smtp.encryption', 'tls'),
            'username' => config('mail.mailers.smtp.username', 'usuario@exemplo.com'),
            'password' => config('mail.mailers.smtp.password', 'senha_exemplo'),
            'timeout' => (int) config('mail.mailers.smtp.timeout', 60),
            'from_address' => config('mail.from.address', 'noreply@example.com'),
            'from_name' => config('mail.from.name', 'Mercury System'),
            'is_active' => true,
            'notes' => 'Configuração inicial importada do arquivo .env. Atualize com as credenciais reais do servidor SMTP.',
        ]);
    }
}
