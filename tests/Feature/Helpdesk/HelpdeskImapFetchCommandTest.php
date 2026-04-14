<?php

namespace Tests\Feature\Helpdesk;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Smoke coverage for the helpdesk:imap-fetch artisan command.
 *
 * Deep testing of the driver interaction is already covered by the
 * other test suites:
 *
 *   - ImapAccountServiceTest: CRUD + encryption of account storage
 *   - ImapMessageNormalizerTest: raw → driver payload transformation
 *   - EmailIntakeTest: EmailIntakeDriver behavior (the consumer)
 *
 * So this test class is intentionally thin: it just verifies that the
 * command boots, iterates tenants, and exits cleanly in the "no tenant /
 * no accounts" path. End-to-end tests against a real IMAP server are
 * performed manually (the Webklex Client cannot be stubbed without a
 * connection because Message's constructor requires one).
 */
class HelpdeskImapFetchCommandTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    public function test_command_exits_cleanly_when_no_tenants(): void
    {
        // The test SQLite DB has no rows in tenants by default — the
        // command should warn and return success.
        Tenant::query()->delete();

        $this->artisan('helpdesk:imap-fetch')
            ->expectsOutputToContain('Nenhum tenant encontrado.')
            ->assertExitCode(0);
    }

    public function test_command_accepts_dry_run_and_tenant_flags(): void
    {
        // Just make sure the option signature doesn't explode — the
        // command should accept both flags even when no work exists.
        Tenant::query()->delete();

        $this->artisan('helpdesk:imap-fetch', ['--dry-run' => true, '--tenant' => 'not-a-real-tenant'])
            ->assertExitCode(0);
    }
}
