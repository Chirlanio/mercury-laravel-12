<?php

namespace App\Services\Helpdesk;

use App\Models\HdChannel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;

/**
 * Manages IMAP mailbox accounts stored inside `hd_channels.config.imap_accounts`.
 *
 * Storage model
 * -------------
 * Accounts live as an array under the email channel's JSON config, each with
 * a generated UUID so the UI can reference individual rows stably without
 * relying on array index. Passwords are encrypted at rest via Laravel Crypt
 * before persisting — `getDecryptedPassword()` is the only place they come
 * back as plain text, and that is called from the fetch command itself, not
 * from any controller or view.
 *
 * Shape of each account (after toArray, before decryption):
 *
 *   [
 *     'id' => 'uuid-v4',
 *     'label' => 'TI',
 *     'email_address' => 'ti@meiasola.com.br',
 *     'department_id' => 1,
 *     'host' => 'imap.hostinger.com',
 *     'port' => 993,
 *     'encryption' => 'ssl',    // ssl | tls | starttls | null
 *     'username' => 'ti@meiasola.com.br',
 *     'password_encrypted' => 'eyJ...',   // never returned in lists
 *     'processed_folder' => 'INBOX.Processados',
 *     'validate_cert' => true,
 *     'active' => true,
 *   ]
 *
 * The service does not expose `password_encrypted` to callers — it strips
 * that field out of `list()`. Callers that need the plain-text password
 * (fetch command, test connection) call `getDecryptedPassword($id)` after
 * authenticating themselves at the HTTP layer.
 */
class ImapAccountService
{
    /** Channel slug that holds the IMAP config. */
    public const CHANNEL_SLUG = 'email';

    /** Default processed folder when the operator doesn't set one. */
    public const DEFAULT_PROCESSED_FOLDER = 'INBOX.Processados';

    /**
     * Fetch the email channel, throwing a clear error when the seed
     * migration hasn't run yet.
     */
    public function channel(): HdChannel
    {
        $channel = HdChannel::findBySlug(self::CHANNEL_SLUG);
        if (! $channel) {
            throw new \RuntimeException(
                'Canal de e-mail não existe em hd_channels. Execute as migrations de tenant.'
            );
        }

        return $channel;
    }

    /**
     * Return all accounts with password_encrypted stripped. Safe to send
     * down to the admin UI.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        $accounts = (array) ($this->channel()->config['imap_accounts'] ?? []);

        return array_values(array_map(function ($account) {
            unset($account['password_encrypted']);
            // Add a has_password flag so the UI can show "senha definida"
            // without ever seeing the actual value.
            $account['has_password'] = true;

            return $account;
        }, $accounts));
    }

    /**
     * Look up a single account by id. Returns the raw stored shape
     * (password_encrypted included) — use `getDecryptedPassword()` when
     * you only need the plain-text password.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        $accounts = (array) ($this->channel()->config['imap_accounts'] ?? []);
        foreach ($accounts as $account) {
            if (($account['id'] ?? null) === $id) {
                return $account;
            }
        }

        return null;
    }

    /**
     * Create a new account. Generates the uuid, validates required fields,
     * encrypts the password, and persists.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>  the stored account (password stripped)
     */
    public function create(array $data): array
    {
        $normalized = $this->normalizeForStorage($data);
        if (empty($normalized['password_encrypted'])) {
            throw new \InvalidArgumentException('A senha é obrigatória ao criar uma conta IMAP.');
        }

        $normalized['id'] = (string) Str::uuid();

        $channel = $this->channel();
        $config = $channel->config ?? [];
        $config['imap_accounts'] = array_values(array_merge(
            (array) ($config['imap_accounts'] ?? []),
            [$normalized],
        ));

        $channel->update(['config' => $config]);

        unset($normalized['password_encrypted']);
        $normalized['has_password'] = true;

        return $normalized;
    }

    /**
     * Update an existing account. Password is only re-encrypted when the
     * caller provides a non-empty `password` field — blanks mean "keep
     * whatever is already stored".
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>  the updated account (password stripped)
     */
    public function update(string $id, array $data): array
    {
        $channel = $this->channel();
        $config = $channel->config ?? [];
        $accounts = (array) ($config['imap_accounts'] ?? []);

        $found = false;
        foreach ($accounts as &$account) {
            if (($account['id'] ?? null) !== $id) {
                continue;
            }

            $updated = $this->normalizeForStorage(array_merge(
                // Keep the existing password when the new data omits it.
                ['password_encrypted' => $account['password_encrypted'] ?? null],
                $data,
            ));
            $updated['id'] = $id;

            $account = $updated;
            $found = true;
            break;
        }
        unset($account);

        if (! $found) {
            throw new \RuntimeException("Conta IMAP {$id} não encontrada.");
        }

        $config['imap_accounts'] = array_values($accounts);
        $channel->update(['config' => $config]);

        $result = $this->find($id);
        if ($result) {
            unset($result['password_encrypted']);
            $result['has_password'] = true;
        }

        return $result ?? [];
    }

    /**
     * Remove an account entirely. Returns true when something was deleted.
     */
    public function delete(string $id): bool
    {
        $channel = $this->channel();
        $config = $channel->config ?? [];
        $accounts = (array) ($config['imap_accounts'] ?? []);

        $filtered = array_values(array_filter(
            $accounts,
            fn ($a) => ($a['id'] ?? null) !== $id,
        ));

        if (count($filtered) === count($accounts)) {
            return false;
        }

        $config['imap_accounts'] = $filtered;
        $channel->update(['config' => $config]);

        return true;
    }

    /**
     * Decrypt the stored password. Exclusively used by the fetch command
     * and the connection test — never by controller responses.
     */
    public function getDecryptedPassword(string $id): ?string
    {
        $account = $this->find($id);
        if (! $account) {
            return null;
        }

        $encrypted = $account['password_encrypted'] ?? null;
        if (! $encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * List only accounts that are flagged active, with decrypted passwords
     * attached. Used by the fetch command — never surfaced to HTTP.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listActiveWithPasswords(): array
    {
        $accounts = (array) ($this->channel()->config['imap_accounts'] ?? []);
        $out = [];

        foreach ($accounts as $account) {
            if (empty($account['active'])) {
                continue;
            }
            $encrypted = $account['password_encrypted'] ?? null;
            if (! $encrypted) {
                continue;
            }
            try {
                $account['password'] = Crypt::decryptString($encrypted);
            } catch (\Throwable) {
                continue;
            }
            unset($account['password_encrypted']);
            $out[] = $account;
        }

        return $out;
    }

    /**
     * Attempt a connect + disconnect against the given account. Returns a
     * tuple so the UI can show both a status flag and an error string.
     *
     * @return array{ok:bool, message:string}
     */
    public function testConnection(string $id): array
    {
        $account = $this->find($id);
        if (! $account) {
            return ['ok' => false, 'message' => 'Conta não encontrada.'];
        }

        $password = $this->getDecryptedPassword($id);
        if (! $password) {
            return ['ok' => false, 'message' => 'Senha inválida ou não configurada.'];
        }

        $client = (new ClientManager())->make([
            'host' => $account['host'] ?? '',
            'port' => (int) ($account['port'] ?? 993),
            'encryption' => $account['encryption'] ?? 'ssl',
            'validate_cert' => (bool) ($account['validate_cert'] ?? true),
            'username' => $account['username'] ?? $account['email_address'] ?? '',
            'password' => $password,
            'protocol' => 'imap',
        ]);

        try {
            $client->connect();
            // Quick sanity check: open INBOX so servers that defer auth until
            // a SELECT (some cyrus variants) actually fail here if credentials
            // are wrong.
            $client->getFolder('INBOX')?->select();
            $client->disconnect();

            return ['ok' => true, 'message' => 'Conexão bem-sucedida.'];
        } catch (ConnectionFailedException $e) {
            return ['ok' => false, 'message' => 'Falha de conexão: '.$e->getMessage()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Erro: '.$e->getMessage()];
        }
    }

    /**
     * Normalize a raw input array into the shape we store on the channel
     * config. Encrypts the password when provided, preserves the existing
     * `password_encrypted` when not.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeForStorage(array $data): array
    {
        $passwordEncrypted = $data['password_encrypted'] ?? null;
        if (! empty($data['password'])) {
            $passwordEncrypted = Crypt::encryptString((string) $data['password']);
        }

        return [
            'id' => $data['id'] ?? null,
            'label' => trim((string) ($data['label'] ?? '')),
            'email_address' => mb_strtolower(trim((string) ($data['email_address'] ?? ''))),
            'department_id' => (int) ($data['department_id'] ?? 0),
            'host' => trim((string) ($data['host'] ?? '')),
            'port' => (int) ($data['port'] ?? 993),
            'encryption' => $data['encryption'] ?? 'ssl',
            'username' => trim((string) ($data['username'] ?? ($data['email_address'] ?? ''))),
            'password_encrypted' => $passwordEncrypted,
            'processed_folder' => trim((string) ($data['processed_folder'] ?? self::DEFAULT_PROCESSED_FOLDER)),
            'validate_cert' => (bool) ($data['validate_cert'] ?? true),
            'active' => (bool) ($data['active'] ?? true),
        ];
    }
}
