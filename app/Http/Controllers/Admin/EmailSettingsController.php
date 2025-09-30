<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EmailSettingsController extends Controller
{
    /**
     * Display email server settings
     */
    public function index(): Response
    {
        // Prioridade 1: Tentar obter configurações da tabela email_configurations
        $emailConfig = DB::table('email_configurations')->first();

        if ($emailConfig) {
            // Usar configurações da tabela email_configurations
            $settings = [
                'id' => $emailConfig->id,
                'driver' => 'smtp',
                'host' => $emailConfig->host,
                'port' => $emailConfig->port,
                'encryption' => $emailConfig->smtp_security ?? 'tls',
                'username' => $emailConfig->username,
                'password' => !empty($emailConfig->password), // Apenas indica se tem senha
                'timeout' => 60,
                'from_address' => $emailConfig->email,
                'from_name' => $emailConfig->name,
                'notes' => 'Configuração carregada da tabela email_configurations',
                'source' => 'email_configurations',
            ];
        } else {
            // Prioridade 2: Tentar obter configurações da tabela email_settings
            $dbSettings = EmailSetting::getActive();

            if ($dbSettings) {
                // Usar configurações do banco de dados
                $settings = [
                    'id' => $dbSettings->id,
                    'driver' => $dbSettings->driver,
                    'host' => $dbSettings->host,
                    'port' => $dbSettings->port,
                    'encryption' => $dbSettings->encryption,
                    'username' => $dbSettings->username,
                    'password' => $dbSettings->hasPassword(), // Apenas indica se tem senha
                    'timeout' => $dbSettings->timeout,
                    'from_address' => $dbSettings->from_address,
                    'from_name' => $dbSettings->from_name,
                    'notes' => $dbSettings->notes,
                    'source' => 'email_settings',
                ];
            } else {
                // Prioridade 3: Fallback para configurações do arquivo .env
                $settings = [
                    'driver' => config('mail.default'),
                    'host' => config('mail.mailers.smtp.host'),
                    'port' => config('mail.mailers.smtp.port'),
                    'encryption' => config('mail.mailers.smtp.encryption'),
                    'username' => config('mail.mailers.smtp.username'),
                    'password' => config('mail.mailers.smtp.password') ? true : false,
                    'timeout' => config('mail.mailers.smtp.timeout', 60),
                    'from_address' => config('mail.from.address'),
                    'from_name' => config('mail.from.name'),
                    'notes' => null,
                    'source' => 'env',
                ];
            }
        }

        return Inertia::render('Admin/EmailSettings', [
            'settings' => $settings,
        ]);
    }
}
