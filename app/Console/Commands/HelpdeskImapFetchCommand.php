<?php

namespace App\Console\Commands;

use App\Models\HdInteraction;
use App\Models\Tenant;
use App\Services\Helpdesk\ImapAccountService;
use App\Services\Helpdesk\ImapMessageNormalizer;
use App\Services\HelpdeskIntakeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\FolderFetchingException;
use Webklex\PHPIMAP\Message;

/**
 * Fetch new email messages from every active IMAP account and convert them
 * into helpdesk tickets (or thread replies) through the existing
 * EmailIntakeDriver pipeline.
 *
 * Runs per-tenant. For each tenant it reads hd_channels.config.imap_accounts
 * via ImapAccountService, connects to each active account, and for each
 * UNSEEN message in INBOX:
 *
 *   1. Normalize headers/body/attachments into the driver payload shape.
 *   2. Dedup by Message-ID against hd_interactions.external_id — skip
 *      messages we've already processed (defensive: protects against flag
 *      corruption or folder moves that failed).
 *   3. Dispatch synchronously to HelpdeskIntakeService::handle('email', ...)
 *      — no queue job layer here because we're already inside a scheduled
 *      command running per-tenant. Running sync keeps the code path
 *      simpler and ensures errors surface immediately.
 *   4. On success, move the message to the configured processed folder
 *      (auto-creating it if missing). On move failure, mark as \\Seen so
 *      the next run won't see it as UNSEEN.
 *
 * Per-account error isolation: one misconfigured mailbox never prevents the
 * others from being polled.
 *
 * Idempotency: Message-ID dedup covers the case where the move fails but
 * the ticket was already created. Re-running the command for the same
 * message is a no-op.
 *
 * Flags:
 *   --tenant=meia-sola   limit to a single tenant
 *   --account=uuid       limit to a single account within that tenant
 *   --dry-run            connect, list new messages, do not create tickets
 */
class HelpdeskImapFetchCommand extends Command
{
    protected $signature = 'helpdesk:imap-fetch
        {--tenant= : Only fetch for a specific tenant id}
        {--account= : Only fetch for a specific account UUID}
        {--dry-run : Connect and count without creating tickets}';

    protected $description = 'Busca e-mails via IMAP e cria chamados (Hostinger / servidores próprios).';

    public function handle(
        ImapAccountService $accounts,
        ImapMessageNormalizer $normalizer,
        HelpdeskIntakeService $intake,
    ): int {
        $this->info('Iniciando fetch IMAP de helpdesk...');

        $tenantFilter = $this->option('tenant');
        $tenants = $tenantFilter
            ? Tenant::query()->where('id', $tenantFilter)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return self::SUCCESS;
        }

        $totalProcessed = 0;
        $totalSkipped = 0;
        $totalFailed = 0;

        foreach ($tenants as $tenant) {
            $this->line("<fg=cyan>Tenant:</> {$tenant->id}");

            try {
                $tenant->run(function () use ($accounts, $normalizer, $intake, &$totalProcessed, &$totalSkipped, &$totalFailed) {
                    if (! Schema::hasTable('hd_channels')) {
                        $this->warn('  Tabelas de helpdesk não encontradas (execute migrations).');

                        return;
                    }

                    $list = $accounts->listActiveWithPasswords();
                    if (empty($list)) {
                        $this->line('  Nenhuma conta IMAP ativa.');

                        return;
                    }

                    $accountFilter = $this->option('account');

                    foreach ($list as $account) {
                        if ($accountFilter && ($account['id'] ?? null) !== $accountFilter) {
                            continue;
                        }

                        $label = $account['label'] ?? $account['email_address'] ?? '?';
                        $this->line("  <fg=yellow>Conta:</> {$label} <{$account['email_address']}>");

                        try {
                            $stats = $this->fetchAccount($account, $normalizer, $intake);
                            $totalProcessed += $stats['processed'];
                            $totalSkipped += $stats['skipped'];
                            $totalFailed += $stats['failed'];

                            $this->line(sprintf(
                                '    → %d novos, %d já processados, %d falharam',
                                $stats['processed'], $stats['skipped'], $stats['failed'],
                            ));
                        } catch (\Throwable $e) {
                            $totalFailed++;
                            $this->error('    ✗ '.$e->getMessage());
                            Log::error('HelpdeskImapFetchCommand: account error', [
                                'account_id' => $account['id'] ?? null,
                                'email' => $account['email_address'] ?? null,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                });
            } catch (\Throwable $e) {
                $this->error("  ✗ Tenant error: {$e->getMessage()}");
                Log::error('HelpdeskImapFetchCommand: tenant error', [
                    'tenant' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Concluído: %d processados · %d já conhecidos · %d falhas',
            $totalProcessed, $totalSkipped, $totalFailed,
        ));

        Log::info('HelpdeskImapFetchCommand completed', [
            'processed' => $totalProcessed,
            'skipped' => $totalSkipped,
            'failed' => $totalFailed,
        ]);

        return self::SUCCESS;
    }

    /**
     * Connect to a single account, fetch UNSEEN, process each message.
     *
     * @param  array<string, mixed>  $account  already decrypted by ImapAccountService::listActiveWithPasswords
     * @return array{processed:int, skipped:int, failed:int}
     */
    protected function fetchAccount(
        array $account,
        ImapMessageNormalizer $normalizer,
        HelpdeskIntakeService $intake,
    ): array {
        $stats = ['processed' => 0, 'skipped' => 0, 'failed' => 0];

        $client = $this->resolveClient($account);
        $client->connect();

        try {
            $inbox = $client->getFolder('INBOX');
            if (! $inbox) {
                throw new \RuntimeException('INBOX não encontrado.');
            }

            /** @var \Webklex\PHPIMAP\Support\MessageCollection $messages */
            $messages = $inbox->messages()->unseen()->get();

            foreach ($messages as $message) {
                try {
                    $result = $this->processMessage(
                        $message,
                        $account,
                        $normalizer,
                        $intake,
                        $client,
                    );

                    $stats[$result]++;
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    Log::error('HelpdeskImapFetchCommand: message error', [
                        'account_id' => $account['id'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                    // Mark the message as seen so we don't loop forever
                    // on a single poisonous message.
                    $this->safelyMarkSeen($message);
                }
            }
        } catch (FolderFetchingException $e) {
            throw new \RuntimeException('Falha ao ler INBOX: '.$e->getMessage(), 0, $e);
        } finally {
            try {
                $client->disconnect();
            } catch (\Throwable) {
                // best-effort
            }
        }

        return $stats;
    }

    /**
     * Process one inbound message. Returns 'processed' | 'skipped' to
     * increment the right counter in the caller.
     */
    protected function processMessage(
        Message $message,
        array $account,
        ImapMessageNormalizer $normalizer,
        HelpdeskIntakeService $intake,
        \Webklex\PHPIMAP\Client $client,
    ): string {
        $payload = $normalizer->normalize($message, (string) ($account['email_address'] ?? ''));
        $messageId = $payload['message_id'] ?? null;

        // Dedup by Message-ID: if we've previously imported a message with
        // this id into any interaction, skip. This is our safety net for
        // the case where move-to-Processed failed but the ticket was
        // already created.
        if ($messageId && HdInteraction::where('external_id', $messageId)->exists()) {
            $this->moveOrMarkSeen($message, $account);

            return 'skipped';
        }

        if ($this->option('dry-run')) {
            $this->line(sprintf(
                '    [dry-run] %s — %s',
                $payload['from_email'] ?? '?',
                mb_substr($payload['subject'] ?? '', 0, 60),
            ));

            return 'processed';
        }

        $intake->handle('email', $payload, [
            'external_contact' => $payload['from_email'] ?? null,
            'external_id' => $messageId,
        ]);

        $this->moveOrMarkSeen($message, $account);

        return 'processed';
    }

    /**
     * Best-effort message cleanup after successful processing. Tries to
     * move to the configured processed folder; on any failure, falls
     * back to the \\Seen flag so the next run won't see it again.
     */
    protected function moveOrMarkSeen(Message $message, array $account): void
    {
        $folder = $account['processed_folder'] ?? ImapAccountService::DEFAULT_PROCESSED_FOLDER;

        try {
            // Make sure the target folder exists. createFolder is idempotent
            // on most servers; catch any failure and fall through to seen.
            $client = $message->getClient();
            if ($client && ! $client->getFolderByPath($folder)) {
                try {
                    $client->createFolder($folder, false);
                } catch (\Throwable) {
                    // folder creation can fail for naming/encoding reasons —
                    // we'll fall back to seen below.
                }
            }

            $moved = $message->move($folder);
            if ($moved) {
                return;
            }
        } catch (\Throwable $e) {
            Log::warning('HelpdeskImapFetchCommand: move failed, falling back to seen', [
                'folder' => $folder,
                'error' => $e->getMessage(),
            ]);
        }

        $this->safelyMarkSeen($message);
    }

    protected function safelyMarkSeen(Message $message): void
    {
        try {
            $message->setFlag('Seen');
        } catch (\Throwable) {
            // If we can't even set \\Seen, there's nothing more to do —
            // the dedup by Message-ID will still protect us from dupes.
        }
    }

    /**
     * Build a webklex Client for this account. Extracted so tests can
     * override this method with a fake client.
     *
     * @param  array<string, mixed>  $account
     */
    protected function resolveClient(array $account): \Webklex\PHPIMAP\Client
    {
        return (new ClientManager())->make([
            'host' => $account['host'] ?? '',
            'port' => (int) ($account['port'] ?? 993),
            'encryption' => $account['encryption'] ?? 'ssl',
            'validate_cert' => (bool) ($account['validate_cert'] ?? true),
            'username' => $account['username'] ?? $account['email_address'] ?? '',
            'password' => $account['password'] ?? '',
            'protocol' => 'imap',
        ]);
    }
}
