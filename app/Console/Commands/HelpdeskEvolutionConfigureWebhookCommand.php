<?php

namespace App\Console\Commands;

use App\Services\Channels\EvolutionApiClient;
use Illuminate\Console\Command;

/**
 * Idempotent configurator for the Evolution API webhook. Treats the Laravel
 * app as the source of truth — running this command resets the webhook on the
 * Evolution instance to exactly what Mercury expects:
 *
 *   - enabled: true
 *   - url: {public_url}/api/webhooks/whatsapp/{tenant}
 *   - headers: { x-mercury-webhook-token: <EVOLUTION_WEBHOOK_TOKEN> }
 *   - byEvents: false      (so Evolution POSTs to the exact URL above, not {url}/messages-upsert)
 *   - events: ["MESSAGES_UPSERT"]
 *
 * The Evolution Manager UI in v2.3.7 does NOT expose the `headers` field, so
 * editing the webhook there silently drops our verification token. Always use
 * this command after any Manager change.
 *
 *   php artisan helpdesk:evolution:configure-webhook --tenant=meia-sola
 *   php artisan helpdesk:evolution:configure-webhook --tenant=meia-sola --dry-run
 *   php artisan helpdesk:evolution:configure-webhook --tenant=meia-sola --instance=mercury-dp --public-url=http://host.docker.internal:8000
 */
class HelpdeskEvolutionConfigureWebhookCommand extends Command
{
    protected $signature = 'helpdesk:evolution:configure-webhook
        {--tenant= : Tenant id the webhook should target (path segment in the public URL)}
        {--instance= : Evolution instance name (default: config services.evolution.instance)}
        {--public-url= : Override the public URL base (default: config services.evolution.webhook_public_url)}
        {--dry-run : Show the payload without calling Evolution}';

    protected $description = 'Configures the Evolution API webhook to point at this Mercury tenant.';

    public function handle(): int
    {
        $tenant = $this->option('tenant');
        if (! $tenant) {
            $this->error('Informe --tenant=<id>. Ex.: --tenant=meia-sola');

            return self::INVALID;
        }

        $instance = $this->option('instance') ?: config('services.evolution.instance');
        $publicUrl = rtrim((string) ($this->option('public-url') ?: config('services.evolution.webhook_public_url')), '/');
        $webhookToken = config('services.evolution.webhook_token');
        $baseUrl = config('services.evolution.base_url');
        $apiKey = config('services.evolution.api_key');

        if (! $instance || ! $baseUrl || ! $apiKey) {
            $this->error('Evolution não configurada. Defina EVOLUTION_API_URL, EVOLUTION_API_KEY e EVOLUTION_INSTANCE no .env.');

            return self::FAILURE;
        }

        if (! $webhookToken) {
            $this->warn('EVOLUTION_WEBHOOK_TOKEN está vazio — o webhook será configurado SEM header de verificação.');
            $this->warn('Gere um com: openssl rand -hex 32');
            if (! $this->confirm('Continuar assim mesmo?', false)) {
                return self::FAILURE;
            }
        }

        $webhookUrl = "{$publicUrl}/api/webhooks/whatsapp/{$tenant}";

        $this->info('Configuração que será enviada para Evolution:');
        $this->table(['Campo', 'Valor'], [
            ['Evolution base_url', $baseUrl],
            ['Evolution instance', $instance],
            ['Webhook URL', $webhookUrl],
            ['byEvents', 'false'],
            ['events', 'MESSAGES_UPSERT'],
            ['Header verificação', $webhookToken ? 'x-mercury-webhook-token: '.substr($webhookToken, 0, 8).'…' : '(nenhum)'],
        ]);

        if ($this->option('dry-run')) {
            $this->warn('[DRY-RUN] Nada enviado à Evolution.');

            return self::SUCCESS;
        }

        $client = new EvolutionApiClient(
            baseUrl: $baseUrl,
            apiKey: $apiKey,
            instance: $instance,
            fake: false, // Admin commands must hit the real API even when app is in fake mode.
        );

        $headers = $webhookToken ? ['x-mercury-webhook-token' => $webhookToken] : [];

        $result = $client->setWebhook(
            url: $webhookUrl,
            events: ['MESSAGES_UPSERT'],
            headers: $headers,
            byEvents: false,
        );

        if (! $result['success']) {
            $this->error('Falha ao configurar webhook na Evolution.');
            $this->line('Status HTTP: '.($result['status'] ?? 'n/a'));
            $this->line('Resposta: '.json_encode($result['raw'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::FAILURE;
        }

        $this->info('Webhook configurado. Verificando…');

        $verification = $client->getWebhook();
        if (! $verification['success']) {
            $this->warn('Webhook gravado, mas não consegui verificar via GET /webhook/find.');

            return self::SUCCESS;
        }

        $remote = $verification['raw'];
        $remoteUrl = trim((string) ($remote['url'] ?? ''));
        $remoteHeaders = $remote['headers'] ?? null;
        $remoteByEvents = $remote['webhookByEvents'] ?? $remote['byEvents'] ?? null;

        $this->line('');
        $this->line('Estado atual na Evolution:');
        $this->table(['Campo', 'Valor'], [
            ['url', $remoteUrl],
            ['byEvents', var_export($remoteByEvents, true)],
            ['events', implode(', ', (array) ($remote['events'] ?? []))],
            ['headers', $remoteHeaders ? json_encode($remoteHeaders, JSON_UNESCAPED_SLASHES) : '(vazio)'],
        ]);

        // Sanity checks — surface the issues that caused the user's original bug.
        $hasIssue = false;

        if ($remoteUrl !== $webhookUrl) {
            $this->error("URL gravada difere da esperada (nota: espaço em branco costuma ser a causa).\nesperado: {$webhookUrl}\ngravado : {$remoteUrl}");
            $hasIssue = true;
        }

        if ($remoteByEvents === true) {
            $this->error('byEvents está true — Evolution vai postar em {url}/{evento} em vez de {url}. Nossa rota não aceita esse formato.');
            $hasIssue = true;
        }

        if ($webhookToken && empty($remoteHeaders)) {
            $this->warn('Headers vazios na resposta — pode ser limitação do GET /webhook/find em v2.3.x; teste o envio de uma mensagem para confirmar.');
        }

        return $hasIssue ? self::FAILURE : self::SUCCESS;
    }
}
