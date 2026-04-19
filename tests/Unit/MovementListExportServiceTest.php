<?php

namespace Tests\Unit;

use App\Models\Movement;
use App\Services\MovementListExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MovementListExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private MovementListExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MovementListExportService;
    }

    public function test_export_xlsx_returns_download_response_for_small_dataset(): void
    {
        Movement::factory()->sale()->count(3)->create();

        $response = $this->service->exportXlsx(Movement::query(), []);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('movimentacoes-', $response->headers->get('Content-Disposition') ?? '');
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition') ?? '');
    }

    public function test_export_pdf_returns_download_response_for_small_dataset(): void
    {
        Movement::factory()->sale()->count(2)->create();

        $response = $this->service->exportPdf(Movement::query(), [
            'date_start' => '2026-04-01', 'date_end' => '2026-04-30',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_export_xlsx_aborts_when_row_limit_exceeded(): void
    {
        $service = new class extends MovementListExportService {
            const ROW_LIMIT = 2;
        };

        Movement::factory()->sale()->count(3)->create();

        try {
            $service->exportXlsx(Movement::query(), []);
            $this->fail('Expected HttpException was not thrown.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('Refine os filtros', $e->getMessage());
        }
    }

    public function test_export_pdf_aborts_when_pdf_row_limit_exceeded(): void
    {
        $service = new class extends MovementListExportService {
            const PDF_ROW_LIMIT = 1;
        };

        Movement::factory()->sale()->count(2)->create();

        try {
            $service->exportPdf(Movement::query(), []);
            $this->fail('Expected HttpException was not thrown.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('XLSX', $e->getMessage());
        }
    }
}
