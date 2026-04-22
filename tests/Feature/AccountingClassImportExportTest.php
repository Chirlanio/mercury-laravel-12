<?php

namespace Tests\Feature;

use App\Enums\AccountingNature;
use App\Enums\DreGroup;
use App\Models\AccountingClass;
use App\Services\AccountingClassImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class AccountingClassImportExportTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    protected function makeExcel(array $rows, array $headings = ['codigo', 'nome', 'descricao', 'codigo_pai', 'natureza', 'grupo_dre', 'aceita_lancamento', 'ativo']): string
    {
        $export = new class($rows, $headings) implements FromArray, WithHeadings
        {
            public function __construct(public array $rows, public array $headings) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return $this->headings;
            }
        };

        $path = tempnam(sys_get_temp_dir(), 'ac-test').'.xlsx';
        Excel::store($export, basename($path), 'local');

        $stored = storage_path('app/private/'.basename($path));
        if (! file_exists($stored)) {
            $stored = storage_path('app/'.basename($path));
        }

        return $stored;
    }

    public function test_export_returns_xlsx(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('accounting-classes.export'));

        $response->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
    }

    public function test_preview_with_valid_and_invalid_rows(): void
    {
        $service = app(AccountingClassImportService::class);

        $path = $this->makeExcel([
            ['AC-100', 'Conta OK', '', '', 'debit', DreGroup::DESPESAS_ADMINISTRATIVAS->value, 'sim', 'sim'],
            ['AC-101', 'Outra OK', '', '', 'credit', DreGroup::RECEITA_BRUTA->value, 'sim', 'sim'],
            ['', 'Sem código', '', '', 'debit', DreGroup::DESPESAS_GERAIS->value, 'sim', 'sim'],
            ['AC-102', '', '', '', 'debit', DreGroup::DESPESAS_GERAIS->value, 'sim', 'sim'],
            ['AC-103', 'Natureza inválida', '', '', 'xyz', DreGroup::DESPESAS_GERAIS->value, 'sim', 'sim'],
            ['AC-104', 'Grupo inválido', '', '', 'debit', 'grupo_que_nao_existe', 'sim', 'sim'],
            ['AC-100', 'Duplicado', '', '', 'debit', DreGroup::DESPESAS_GERAIS->value, 'sim', 'sim'],
        ]);

        $result = $service->preview($path);

        $this->assertEquals(2, $result['valid_count']);
        $this->assertEquals(5, $result['invalid_count']);
    }

    public function test_import_creates_and_updates_by_code(): void
    {
        // Conta pré-existente (não é do seed para evitar dependência)
        AccountingClass::create([
            'code' => 'IMP-UPD',
            'name' => 'Nome Antigo',
            'nature' => AccountingNature::DEBIT->value,
            'dre_group' => DreGroup::DESPESAS_GERAIS->value,
            'accepts_entries' => true,
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $service = app(AccountingClassImportService::class);

        $path = $this->makeExcel([
            ['IMP-NEW', 'Nova', '', '', 'debit', DreGroup::DESPESAS_GERAIS->value, 'sim', 'sim'],
            ['IMP-UPD', 'Nome Atualizado', '', '', 'debit', DreGroup::DESPESAS_GERAIS->value, 'sim', 'sim'],
        ]);

        $result = $service->import($path, $this->adminUser);

        $this->assertEquals(1, $result['created']);
        $this->assertEquals(1, $result['updated']);
        $this->assertDatabaseHas('chart_of_accounts', [
            'code' => 'IMP-NEW',
            'name' => 'Nova',
        ]);
        $this->assertDatabaseHas('chart_of_accounts', [
            'code' => 'IMP-UPD',
            'name' => 'Nome Atualizado',
        ]);
    }

    public function test_import_resolves_parent_by_code(): void
    {
        AccountingClass::create([
            'code' => 'IMP-PARENT',
            'name' => 'Pai Sintético',
            'nature' => AccountingNature::DEBIT->value,
            'dre_group' => DreGroup::DESPESAS_GERAIS->value,
            'accepts_entries' => false,
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $service = app(AccountingClassImportService::class);

        $path = $this->makeExcel([
            ['IMP-CHILD', 'Filha', '', 'IMP-PARENT', 'debit', DreGroup::DESPESAS_GERAIS->value, 'sim', 'sim'],
        ]);

        $result = $service->import($path, $this->adminUser);

        $this->assertEquals(1, $result['created']);
        $child = AccountingClass::where('code', 'IMP-CHILD')->first();
        $this->assertNotNull($child->parent_id);
        $this->assertEquals('IMP-PARENT', $child->parent->code);
    }

    public function test_import_rejects_when_parent_is_leaf(): void
    {
        AccountingClass::create([
            'code' => 'IMP-LEAF',
            'name' => 'Folha Analítica',
            'nature' => AccountingNature::DEBIT->value,
            'dre_group' => DreGroup::DESPESAS_GERAIS->value,
            'accepts_entries' => true, // folha — não pode ser pai
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $service = app(AccountingClassImportService::class);

        $path = $this->makeExcel([
            ['IMP-CHILD-BAD', 'Tentando filhar folha', '', 'IMP-LEAF', 'debit', DreGroup::DESPESAS_GERAIS->value, 'sim', 'sim'],
        ]);

        $result = $service->import($path, $this->adminUser);

        $this->assertEquals(0, $result['created']);
        $this->assertEquals(1, $result['skipped']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_import_accepts_nature_aliases(): void
    {
        $service = app(AccountingClassImportService::class);

        $path = $this->makeExcel([
            ['IMP-D', 'Débito PT', '', '', 'devedora', DreGroup::DESPESAS_GERAIS->value, 'sim', 'sim'],
            ['IMP-C', 'Crédito PT', '', '', 'credora', DreGroup::RECEITA_BRUTA->value, 'sim', 'sim'],
        ]);

        $result = $service->import($path, $this->adminUser);

        $this->assertEquals(2, $result['created']);
        $this->assertDatabaseHas('chart_of_accounts', [
            'code' => 'IMP-D',
            'nature' => 'debit',
        ]);
        $this->assertDatabaseHas('chart_of_accounts', [
            'code' => 'IMP-C',
            'nature' => 'credit',
        ]);
    }

    public function test_import_rejects_file_wrong_extension(): void
    {
        $fake = UploadedFile::fake()->create('dados.txt', 100);

        $response = $this->actingAs($this->adminUser)
            ->post(route('accounting-classes.import.store'), ['file' => $fake]);

        $response->assertSessionHasErrors('file');
    }
}
