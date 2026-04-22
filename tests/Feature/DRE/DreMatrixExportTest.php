<?php

namespace Tests\Feature\DRE;

use App\Models\ChartOfAccount;
use App\Models\DreActual;
use App\Models\DreMapping;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Cobre os endpoints de export XLSX/PDF da matriz DRE (playbook prompt 13).
 *
 * Foco: autorização (`EXPORT_DRE`), content-type e tamanho mínimo do payload —
 * o conteúdo fino (5 sheets, 3 gráficos etc.) é testado de forma manual na UI.
 */
class DreMatrixExportTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        app(\App\Services\CentralRoleResolver::class)->clearCache();

        // Scaffold mínimo para ter algo na matriz.
        $store = Store::factory()->create();
        $account = ChartOfAccount::factory()->analytical()->create([
            'code' => 'EXP.TEST.01',
            'account_group' => 4,
        ]);
        DreActual::create([
            'entry_date' => '2026-03-15',
            'chart_of_account_id' => $account->id,
            'store_id' => $store->id,
            'amount' => -250.00,
            'source' => DreActual::SOURCE_MANUAL_IMPORT,
        ]);
    }

    public function test_xlsx_export_returns_spreadsheet(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('dre.matrix.export.xlsx', [
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ]));

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('Content-Type') ?? '',
        );
        $this->assertStringContainsString(
            'dre-matriz',
            $response->headers->get('Content-Disposition') ?? '',
        );
    }

    public function test_pdf_export_returns_pdf(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('dre.matrix.export.pdf', [
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ]));

        $response->assertStatus(200);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_user_without_export_permission_gets_403(): void
    {
        $user = User::factory()->create(); // Role::USER default — sem EXPORT_DRE

        $this->actingAs($user)
            ->get(route('dre.matrix.export.xlsx', [
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ]))
            ->assertStatus(403);

        $this->actingAs($user)
            ->get(route('dre.matrix.export.pdf', [
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ]))
            ->assertStatus(403);
    }

    public function test_export_respects_matrix_request_validation(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('dre.matrix.export.xlsx', [
                'start_date' => '2026-12-31',
                'end_date' => '2026-01-01', // fim antes do início
            ]))
            ->assertStatus(302)
            ->assertSessionHasErrors('end_date');
    }
}
