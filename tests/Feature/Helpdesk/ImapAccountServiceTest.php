<?php

namespace Tests\Feature\Helpdesk;

use App\Models\HdChannel;
use App\Models\HdDepartment;
use App\Services\Helpdesk\ImapAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Coverage for the IMAP account CRUD service. We don't test real IMAP
 * connections here — testConnection() is exercised as "can it fail
 * gracefully when the host is unreachable" only.
 */
class ImapAccountServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected HdDepartment $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        HdDepartment::query()->delete();
        $this->department = HdDepartment::factory()->create(['name' => 'TI']);

        // The email channel is created by the seed migration; ensure it exists
        // for the test DB and reset its imap_accounts list.
        HdChannel::updateOrCreate(
            ['slug' => 'email'],
            [
                'name' => 'E-mail',
                'driver' => 'email',
                'is_active' => true,
                'config' => ['imap_accounts' => []],
            ],
        );
    }

    public function test_list_returns_empty_when_no_accounts(): void
    {
        $service = app(ImapAccountService::class);
        $this->assertSame([], $service->list());
    }

    public function test_create_persists_account_and_encrypts_password(): void
    {
        $service = app(ImapAccountService::class);

        $account = $service->create([
            'label' => 'TI',
            'email_address' => 'TI@Empresa.Com',
            'department_id' => $this->department->id,
            'host' => 'imap.hostinger.com',
            'port' => 993,
            'encryption' => 'ssl',
            'password' => 'minhaSenha123',
        ]);

        $this->assertNotEmpty($account['id']);
        // Email normalized to lowercase on storage
        $this->assertSame('ti@empresa.com', $account['email_address']);
        // Plain password never in the returned payload
        $this->assertArrayNotHasKey('password_encrypted', $account);
        $this->assertArrayNotHasKey('password', $account);
        $this->assertTrue($account['has_password']);

        // Stored encrypted value is decryptable
        $raw = $service->getDecryptedPassword($account['id']);
        $this->assertSame('minhaSenha123', $raw);

        // Stored on the channel config
        $channel = HdChannel::findBySlug('email');
        $this->assertCount(1, $channel->config['imap_accounts']);
        $stored = $channel->config['imap_accounts'][0];
        $this->assertNotSame('minhaSenha123', $stored['password_encrypted']);
        $this->assertSame('minhaSenha123', Crypt::decryptString($stored['password_encrypted']));
    }

    public function test_create_rejects_missing_password(): void
    {
        $service = app(ImapAccountService::class);

        $this->expectException(\InvalidArgumentException::class);

        $service->create([
            'label' => 'X',
            'email_address' => 'x@y.com',
            'department_id' => $this->department->id,
            'host' => 'imap.y.com',
        ]);
    }

    public function test_update_keeps_existing_password_when_not_provided(): void
    {
        $service = app(ImapAccountService::class);

        $created = $service->create([
            'label' => 'TI',
            'email_address' => 'ti@empresa.com',
            'department_id' => $this->department->id,
            'host' => 'imap.hostinger.com',
            'password' => 'original',
        ]);

        $service->update($created['id'], [
            'label' => 'TI Atualizado',
            'email_address' => 'ti@empresa.com',
            'department_id' => $this->department->id,
            'host' => 'imap.hostinger.com',
            // no password key at all
        ]);

        $this->assertSame('original', $service->getDecryptedPassword($created['id']));
        $updated = $service->find($created['id']);
        $this->assertSame('TI Atualizado', $updated['label']);
    }

    public function test_update_replaces_password_when_provided(): void
    {
        $service = app(ImapAccountService::class);

        $created = $service->create([
            'label' => 'TI',
            'email_address' => 'ti@empresa.com',
            'department_id' => $this->department->id,
            'host' => 'imap.hostinger.com',
            'password' => 'original',
        ]);

        $service->update($created['id'], [
            'label' => 'TI',
            'email_address' => 'ti@empresa.com',
            'department_id' => $this->department->id,
            'host' => 'imap.hostinger.com',
            'password' => 'nova-senha',
        ]);

        $this->assertSame('nova-senha', $service->getDecryptedPassword($created['id']));
    }

    public function test_delete_removes_account(): void
    {
        $service = app(ImapAccountService::class);

        $created = $service->create([
            'label' => 'TI',
            'email_address' => 'ti@empresa.com',
            'department_id' => $this->department->id,
            'host' => 'imap.hostinger.com',
            'password' => 'x',
        ]);

        $this->assertTrue($service->delete($created['id']));
        $this->assertCount(0, $service->list());
        $this->assertFalse($service->delete($created['id'])); // idempotent
    }

    public function test_list_active_with_passwords_excludes_inactive_and_decrypts(): void
    {
        $service = app(ImapAccountService::class);

        $active = $service->create([
            'label' => 'Ativa',
            'email_address' => 'a@empresa.com',
            'department_id' => $this->department->id,
            'host' => 'imap.hostinger.com',
            'password' => 'senha-ativa',
            'active' => true,
        ]);

        $inactive = $service->create([
            'label' => 'Inativa',
            'email_address' => 'b@empresa.com',
            'department_id' => $this->department->id,
            'host' => 'imap.hostinger.com',
            'password' => 'senha-inativa',
            'active' => false,
        ]);

        $list = $service->listActiveWithPasswords();

        $this->assertCount(1, $list);
        $this->assertSame($active['id'], $list[0]['id']);
        $this->assertSame('senha-ativa', $list[0]['password']);
        $this->assertArrayNotHasKey('password_encrypted', $list[0]);
    }

    public function test_test_connection_returns_error_for_unreachable_host(): void
    {
        $service = app(ImapAccountService::class);

        $created = $service->create([
            'label' => 'Fake',
            'email_address' => 'fake@example.com',
            'department_id' => $this->department->id,
            'host' => '127.0.0.1',
            'port' => 1,                 // nothing listening
            'encryption' => 'ssl',
            'password' => 'x',
            'validate_cert' => false,
        ]);

        $result = $service->testConnection($created['id']);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['message']);
    }

    public function test_test_connection_returns_error_for_unknown_account(): void
    {
        $service = app(ImapAccountService::class);

        $result = $service->testConnection('not-a-real-id');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('não encontrada', $result['message']);
    }
}
