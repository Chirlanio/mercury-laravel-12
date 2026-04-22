<?php

namespace Tests\Feature\DRE;

use App\Enums\Permission;
use App\Models\DrePeriodClosing;
use App\Models\User;
use App\Notifications\DrePeriodReopenedNotification;
use App\Services\DRE\DrePeriodClosingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Cobre os endpoints HTTP de `DrePeriodClosingController` com foco em
 * autorização, validação de reason e dispatch de notification.
 */
class DrePeriodClosingControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        app(\App\Services\CentralRoleResolver::class)->clearCache();
    }

    public function test_user_without_permission_gets_403_on_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dre.periods.index'))
            ->assertStatus(403);
    }

    public function test_admin_sees_index(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('dre.periods.index'))
            ->assertInertia(fn ($page) => $page->component('DRE/Periods/Index'));
    }

    public function test_store_closes_period(): void
    {
        $this->actingAs($this->adminUser)
            ->post(route('dre.periods.store'), [
                'closed_up_to_date' => '2026-03-31',
                'notes' => null,
            ])
            ->assertRedirect(route('dre.periods.index'));

        $this->assertDatabaseHas('dre_period_closings', [
            'closed_up_to_date' => '2026-03-31',
            'closed_by_user_id' => $this->adminUser->id,
        ]);
    }

    public function test_reopen_requires_reason(): void
    {
        $closing = app(DrePeriodClosingService::class)->close(
            closedUpToDate: Carbon::parse('2026-03-31'),
            closedBy: $this->adminUser,
        );

        $this->actingAs($this->adminUser)
            ->patch(route('dre.periods.reopen', $closing), ['reason' => ''])
            ->assertStatus(302)
            ->assertSessionHasErrors('reason');
    }

    public function test_reopen_with_valid_reason_dispatches_notification(): void
    {
        Notification::fake();

        $closing = app(DrePeriodClosingService::class)->close(
            closedUpToDate: Carbon::parse('2026-03-31'),
            closedBy: $this->adminUser,
        );

        // Outro admin para ser destinatário.
        $otherAdmin = User::factory()->create([
            'role' => \App\Enums\Role::ADMIN->value,
            'access_level_id' => 1,
        ]);

        $this->actingAs($this->adminUser)
            ->patch(route('dre.periods.reopen', $closing), [
                'reason' => 'Ajuste contábil identificado na conciliação mensal',
            ])
            ->assertRedirect(route('dre.periods.index'));

        $closing->refresh();
        $this->assertNotNull($closing->reopened_at);

        // Destinatário recebeu a notification (quem reabriu não recebe).
        Notification::assertSentTo($otherAdmin, DrePeriodReopenedNotification::class);
        Notification::assertNotSentTo($this->adminUser, DrePeriodReopenedNotification::class);
    }

    public function test_preview_endpoint_returns_json_diffs(): void
    {
        $closing = app(DrePeriodClosingService::class)->close(
            closedUpToDate: Carbon::parse('2026-03-31'),
            closedBy: $this->adminUser,
        );

        $this->actingAs($this->adminUser)
            ->getJson(route('dre.periods.preview', $closing))
            ->assertOk()
            ->assertJsonStructure(['diffs', 'diffs_count', 'snapshots_deleted']);
    }

    public function test_user_without_permission_cannot_reopen(): void
    {
        $closing = app(DrePeriodClosingService::class)->close(
            closedUpToDate: Carbon::parse('2026-03-31'),
            closedBy: $this->adminUser,
        );

        $user = User::factory()->create();
        $this->actingAs($user)
            ->patch(route('dre.periods.reopen', $closing), ['reason' => str_repeat('x', 20)])
            ->assertStatus(403);
    }
}
