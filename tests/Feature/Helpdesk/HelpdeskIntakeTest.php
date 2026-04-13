<?php

namespace Tests\Feature\Helpdesk;

use App\Enums\Role;
use App\Models\HdCategory;
use App\Models\HdChannel;
use App\Models\HdDepartment;
use App\Models\HdIntakeTemplate;
use App\Models\HdTicket;
use App\Models\HdTicketChannel;
use App\Models\HdTicketIntakeData;
use App\Models\User;
use App\Services\HelpdeskIntakeService;
use App\Services\HelpdeskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class HelpdeskIntakeTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected HdDepartment $department;
    protected HdCategory $category;
    protected HdChannel $webChannel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $base = $this->createHelpdeskBaseData();
        $this->department = $base['department'];
        $this->category = $base['categories'][0];

        // The migration seeds a web channel in MySQL; in the SQLite test DB the
        // seed runs too because it's a plain DB::insert. Guard for idempotency.
        $this->webChannel = HdChannel::findBySlug('web') ?? HdChannel::create([
            'slug' => 'web',
            'name' => 'Web',
            'driver' => 'web',
            'config' => [],
            'is_active' => true,
        ]);
    }

    public function test_tickets_created_via_helpdesk_service_default_to_web_source(): void
    {
        $ticket = app(HelpdeskService::class)->createTicket([
            'department_id' => $this->department->id,
            'category_id' => $this->category->id,
            'title' => 'Bug no ERP',
            'description' => 'Erro ao salvar venda.',
            'priority' => HdTicket::PRIORITY_MEDIUM,
        ], $this->regularUser->id);

        $this->assertSame('web', $ticket->source);
    }

    public function test_web_intake_driver_creates_ticket_and_records_channel(): void
    {
        $intake = app(HelpdeskIntakeService::class);

        $step = $intake->handle('web', [
            'department_id' => $this->department->id,
            'category_id' => $this->category->id,
            'title' => 'Tela travada',
            'description' => 'Tela de caixa não responde.',
            'priority' => HdTicket::PRIORITY_HIGH,
            'ip' => '127.0.0.1',
            'user_agent' => 'phpunit/1.0',
        ], [
            'user_id' => $this->regularUser->id,
        ]);

        $this->assertTrue($step->isComplete);
        $this->assertNotNull($step->ticketId);

        $ticket = HdTicket::findOrFail($step->ticketId);
        $this->assertSame('web', $ticket->source);
        $this->assertSame($this->regularUser->id, $ticket->requester_id);

        $channelRow = HdTicketChannel::where('ticket_id', $ticket->id)->first();
        $this->assertNotNull($channelRow);
        $this->assertSame($this->webChannel->id, $channelRow->channel_id);
        $this->assertSame('127.0.0.1', $channelRow->metadata['ip']);
    }

    public function test_unknown_channel_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Canal desconhecido/');

        app(HelpdeskIntakeService::class)->handle('tiktok', [], ['user_id' => $this->regularUser->id]);
    }

    public function test_inactive_channel_is_rejected(): void
    {
        HdChannel::create([
            'slug' => 'email',
            'name' => 'E-mail',
            'driver' => 'email',
            'is_active' => false,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/inativo/');

        app(HelpdeskIntakeService::class)->handle('email', [], ['user_id' => $this->regularUser->id]);
    }

    public function test_intake_template_resolves_most_specific_match(): void
    {
        $deptWide = HdIntakeTemplate::create([
            'department_id' => $this->department->id,
            'category_id' => null,
            'name' => 'Padrão do departamento',
            'fields' => [['name' => 'foo', 'type' => 'text', 'required' => true]],
            'active' => true,
            'sort_order' => 0,
        ]);

        $categorySpecific = HdIntakeTemplate::create([
            'department_id' => $this->department->id,
            'category_id' => $this->category->id,
            'name' => 'Específico de hardware',
            'fields' => [['name' => 'asset_tag', 'type' => 'text', 'required' => true]],
            'active' => true,
            'sort_order' => 0,
        ]);

        $resolved = HdIntakeTemplate::resolveFor($this->department->id, $this->category->id);
        $this->assertSame($categorySpecific->id, $resolved?->id);

        $fallback = HdIntakeTemplate::resolveFor($this->department->id, null);
        $this->assertSame($deptWide->id, $fallback?->id);
    }

    public function test_create_ticket_persists_intake_template_data(): void
    {
        $template = HdIntakeTemplate::create([
            'department_id' => $this->department->id,
            'name' => 'Férias',
            'fields' => [
                ['name' => 'start_date', 'type' => 'date', 'required' => true],
                ['name' => 'days', 'type' => 'text', 'required' => true],
            ],
            'active' => true,
        ]);

        $ticket = app(HelpdeskService::class)->createTicket([
            'department_id' => $this->department->id,
            'category_id' => $this->category->id,
            'title' => 'Solicitação de férias',
            'description' => 'Pedido formal.',
            'priority' => HdTicket::PRIORITY_LOW,
            'intake_template_id' => $template->id,
            'intake_data' => ['start_date' => '2026-05-01', 'days' => '10'],
        ], $this->regularUser->id);

        $row = HdTicketIntakeData::where('ticket_id', $ticket->id)->first();
        $this->assertNotNull($row);
        $this->assertSame($template->id, $row->template_id);
        $this->assertSame('2026-05-01', $row->data['start_date']);
        $this->assertSame('10', $row->data['days']);
    }
}
