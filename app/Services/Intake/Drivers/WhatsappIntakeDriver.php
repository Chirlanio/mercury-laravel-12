<?php

namespace App\Services\Intake\Drivers;

use App\Models\HdChannel;
use App\Models\HdChatSession;
use App\Models\HdDepartment;
use App\Models\HdInteraction;
use App\Models\HdTicket;
use App\Models\HdTicketChannel;
use App\Models\User;
use App\Services\HelpdeskService;
use App\Services\Helpdesk\EmployeeIdentityResolver;
use App\Services\Intake\IntakeDriverInterface;
use App\Services\Intake\IntakeStep;
use Illuminate\Support\Facades\DB;

/**
 * Conversational intake driver for WhatsApp via Evolution API.
 *
 * State machine (stored as step in hd_chat_sessions):
 *
 *   [new]                 → try phone match + list departments  → awaiting_department
 *   awaiting_department   → pick department N                   → awaiting_cpf | awaiting_category | awaiting_description
 *   awaiting_cpf          → user types CPF                      → awaiting_category | awaiting_description | (retry)
 *   awaiting_category     → pick category N                     → awaiting_description
 *   awaiting_description  → free text becomes ticket body       → [complete → ticket created]
 *
 * Identity resolution:
 *   1. First message triggers silent phone match on employees.phone_primary.
 *      If matched, the session stores employee_id and first_name, and no
 *      CPF is ever requested regardless of department.
 *   2. If not matched via phone, the driver continues to the department menu.
 *      When a department is picked, the driver checks `requires_identification`:
 *         - If true AND no employee_id yet → transition to awaiting_cpf.
 *         - Otherwise → proceed as normal (non-identified ticket allowed).
 *   3. In awaiting_cpf, the user gets up to 2 retries on an invalid/unknown CPF.
 *      After the 2nd failure, the driver offers a non-identified path:
 *      ticket is still created but with employee_id=null and a system note
 *      flagging it for manual identification by the operator.
 *
 * Re-entry rules (unchanged from earlier):
 *   - Contact with an open non-terminal ticket → new messages append as
 *     interactions, no new flow starts.
 *
 * Session TTL is 30 minutes.
 */
class WhatsappIntakeDriver implements IntakeDriverInterface
{
    protected const SESSION_TTL_MINUTES = 30;

    protected const STEP_AWAITING_DEPARTMENT = 'awaiting_department';

    protected const STEP_AWAITING_CPF = 'awaiting_cpf';

    protected const STEP_AWAITING_CATEGORY = 'awaiting_category';

    protected const STEP_AWAITING_DESCRIPTION = 'awaiting_description';

    protected const MAX_CPF_ATTEMPTS = 2;

    public function __construct(
        private HelpdeskService $helpdeskService,
        private EmployeeIdentityResolver $identityResolver,
    ) {}

    public function handle(HdChannel $channel, ?HdChatSession $session, array $payload, array $context = []): IntakeStep
    {
        $externalContact = $this->requireContact($context);
        $message = trim((string) ($payload['message'] ?? ''));

        // 1) If this contact already has an active ticket, route message into it
        //    as a new interaction rather than opening a fresh flow.
        $openTicketChannel = $this->findOpenTicketForContact($channel->id, $externalContact);
        if ($openTicketChannel) {
            return $this->appendToExistingTicket($openTicketChannel, $message, $context);
        }

        // 2) If we have an expired session, treat it as a new contact.
        if ($session && $session->isExpired()) {
            $session->delete();
            $session = null;
        }

        // 3) Dispatch on session state.
        if (! $session) {
            return $this->startFlow($channel, $externalContact);
        }

        return match ($session->step) {
            self::STEP_AWAITING_DEPARTMENT => $this->handleDepartmentChoice($channel, $session, $message),
            self::STEP_AWAITING_CPF => $this->handleCpfInput($channel, $session, $message),
            self::STEP_AWAITING_CATEGORY => $this->handleCategoryChoice($channel, $session, $message),
            self::STEP_AWAITING_DESCRIPTION => $this->handleDescription($channel, $session, $message, $context),
            default => $this->startFlow($channel, $externalContact, reset: $session),
        };
    }

    // ---------------------------------------------------------------------
    // Flow entry — department menu, possibly preceded by silent phone match
    // ---------------------------------------------------------------------

    protected function startFlow(HdChannel $channel, string $externalContact, ?HdChatSession $reset = null): IntakeStep
    {
        $departments = HdDepartment::active()->ordered()->get(['id', 'name', 'requires_identification']);

        if ($departments->isEmpty()) {
            return IntakeStep::ask(
                'Desculpe, no momento não há departamentos disponíveis para atendimento. Tente mais tarde.',
            );
        }

        // Silent phone match — UX best when the employee is already cadastrado.
        $employee = $this->identityResolver->byPhone($externalContact, [
            'channel_id' => $channel->id,
        ]);

        $contextPayload = [
            'department_ids' => $departments->pluck('id')->all(),
        ];

        if ($employee) {
            $contextPayload = array_merge(
                $contextPayload,
                $this->identityResolver->enrichContext($employee),
            );
        }

        $greeting = $channel->config['greeting'] ?? 'Olá! Como posso ajudar?';
        if ($employee) {
            $greeting = "Olá, *{$employee->first_name}*! 👋\n\n".$greeting;
        }

        $menu = $greeting."\n\n".$this->renderMenu(
            'Escolha um departamento respondendo com o número correspondente:',
            $departments->pluck('name')->all(),
        );

        $sessionAttributes = [
            'step' => self::STEP_AWAITING_DEPARTMENT,
            'context' => $contextPayload,
            'expires_at' => now()->addMinutes(self::SESSION_TTL_MINUTES),
        ];

        if ($reset) {
            $reset->update($sessionAttributes);
        } else {
            HdChatSession::create(array_merge($sessionAttributes, [
                'channel_id' => $channel->id,
                'external_contact' => $externalContact,
            ]));
        }

        return IntakeStep::ask(
            prompt: $menu,
            options: $departments->map(fn ($d) => ['id' => $d->id, 'label' => $d->name])->all(),
        );
    }

    // ---------------------------------------------------------------------
    // Department selection — decides whether CPF is required next
    // ---------------------------------------------------------------------

    protected function handleDepartmentChoice(HdChannel $channel, HdChatSession $session, string $message): IntakeStep
    {
        $departmentIds = $session->context['department_ids'] ?? [];
        $choice = $this->parseMenuChoice($message, count($departmentIds));

        if ($choice === null) {
            $departments = HdDepartment::whereIn('id', $departmentIds)->ordered()->get();

            return IntakeStep::ask(
                'Não entendi. '.$this->renderMenu(
                    'Responda com o número do departamento:',
                    $departments->pluck('name')->all(),
                ),
            );
        }

        $departmentId = $departmentIds[$choice - 1];
        $department = HdDepartment::with(['activeCategories' => fn ($q) => $q->orderBy('name')])->find($departmentId);

        if (! $department) {
            return $this->startFlow($channel, $session->external_contact, reset: $session);
        }

        // Does this department require identification AND do we still not
        // have an employee_id in the session context?
        $needsIdentification = $department->requires_identification
            && empty($session->context['employee_id']);

        if ($needsIdentification) {
            $session->update([
                'step' => self::STEP_AWAITING_CPF,
                'context' => array_merge($session->context ?? [], [
                    'department_id' => $departmentId,
                    'cpf_attempts' => 0,
                ]),
                'expires_at' => now()->addMinutes(self::SESSION_TTL_MINUTES),
            ]);

            return IntakeStep::ask(
                "Departamento *{$department->name}* selecionado.\n\n".
                "Para te atender preciso identificar seu cadastro. Por favor, informe seu *CPF* (apenas os 11 dígitos):",
            );
        }

        return $this->enterCategoryOrDescription($session, $department);
    }

    // ---------------------------------------------------------------------
    // CPF handling
    // ---------------------------------------------------------------------

    protected function handleCpfInput(HdChannel $channel, HdChatSession $session, string $message): IntakeStep
    {
        $attempts = (int) ($session->context['cpf_attempts'] ?? 0) + 1;
        $departmentId = (int) ($session->context['department_id'] ?? 0);
        $department = HdDepartment::with(['activeCategories' => fn ($q) => $q->orderBy('name')])->find($departmentId);

        if (! $department) {
            return $this->startFlow($channel, $session->external_contact, reset: $session);
        }

        $employee = $this->identityResolver->byCpf($message, $attempts, [
            'channel_id' => $channel->id,
            'external_contact' => $session->external_contact,
        ]);

        if ($employee) {
            $session->update([
                'context' => array_merge(
                    $session->context ?? [],
                    $this->identityResolver->enrichContext($employee),
                    ['cpf_attempts' => $attempts],
                ),
                'expires_at' => now()->addMinutes(self::SESSION_TTL_MINUTES),
            ]);

            $firstName = $employee->first_name;
            $storeCode = $employee->store_id;

            $prompt = "✅ Identificado! Olá, *{$firstName}*";
            if ($storeCode) {
                $prompt .= " — loja *{$storeCode}*";
            }
            $prompt .= ".\n\n";

            // Move on to category or description now that the user is known.
            $next = $this->enterCategoryOrDescription($session, $department);

            // Prepend the greeting to the next prompt. Keep options intact.
            return IntakeStep::ask(
                prompt: $prompt.$next->prompt,
                options: $next->options,
                collected: $next->collected,
            );
        }

        // No match.
        if ($attempts >= self::MAX_CPF_ATTEMPTS) {
            // Give up on identification, fall through to category/description
            // with employee_id=null. The ticket will be flagged for manual
            // identification in handleDescription.
            $session->update([
                'context' => array_merge($session->context ?? [], [
                    'cpf_attempts' => $attempts,
                    'identification_failed' => true,
                ]),
                'expires_at' => now()->addMinutes(self::SESSION_TTL_MINUTES),
            ]);

            $next = $this->enterCategoryOrDescription($session, $department);

            return IntakeStep::ask(
                prompt: "Não consegui encontrar seu cadastro. Vou abrir o chamado assim mesmo — o atendente do {$department->name} vai precisar te identificar manualmente.\n\n".$next->prompt,
                options: $next->options,
                collected: $next->collected,
            );
        }

        // Retry.
        $session->update([
            'context' => array_merge($session->context ?? [], ['cpf_attempts' => $attempts]),
            'expires_at' => now()->addMinutes(self::SESSION_TTL_MINUTES),
        ]);

        return IntakeStep::ask(
            'CPF não encontrado ou inválido. Verifique os 11 dígitos e tente novamente:',
        );
    }

    /**
     * Transition the session into either awaiting_category (if the department
     * has categories) or awaiting_description (if it does not). Returns the
     * prompt the user should see.
     */
    protected function enterCategoryOrDescription(HdChatSession $session, HdDepartment $department): IntakeStep
    {
        $categories = $department->activeCategories;

        if ($categories->isEmpty()) {
            $session->update([
                'step' => self::STEP_AWAITING_DESCRIPTION,
                'context' => array_merge($session->context ?? [], [
                    'department_id' => $department->id,
                    'category_id' => null,
                ]),
                'expires_at' => now()->addMinutes(self::SESSION_TTL_MINUTES),
            ]);

            return IntakeStep::ask(
                "Departamento *{$department->name}* selecionado.\n\nDescreva em detalhes o que você precisa:",
            );
        }

        $session->update([
            'step' => self::STEP_AWAITING_CATEGORY,
            'context' => array_merge($session->context ?? [], [
                'department_id' => $department->id,
                'category_ids' => $categories->pluck('id')->all(),
            ]),
            'expires_at' => now()->addMinutes(self::SESSION_TTL_MINUTES),
        ]);

        return IntakeStep::ask(
            prompt: $this->renderMenu(
                'Agora escolha o tipo de solicitação:',
                $categories->pluck('name')->all(),
            ),
            options: $categories->map(fn ($c) => ['id' => $c->id, 'label' => $c->name])->all(),
        );
    }

    // ---------------------------------------------------------------------
    // Category selection
    // ---------------------------------------------------------------------

    protected function handleCategoryChoice(HdChannel $channel, HdChatSession $session, string $message): IntakeStep
    {
        $categoryIds = $session->context['category_ids'] ?? [];
        $choice = $this->parseMenuChoice($message, count($categoryIds));

        if ($choice === null) {
            $categories = \App\Models\HdCategory::whereIn('id', $categoryIds)->orderBy('name')->get();

            return IntakeStep::ask(
                'Não entendi. '.$this->renderMenu(
                    'Responda com o número da categoria:',
                    $categories->pluck('name')->all(),
                ),
            );
        }

        $categoryId = $categoryIds[$choice - 1];

        $session->update([
            'step' => self::STEP_AWAITING_DESCRIPTION,
            'context' => array_merge($session->context ?? [], ['category_id' => $categoryId]),
            'expires_at' => now()->addMinutes(self::SESSION_TTL_MINUTES),
        ]);

        return IntakeStep::ask('Ótimo! Descreva em detalhes o que você precisa:');
    }

    // ---------------------------------------------------------------------
    // Description → ticket
    // ---------------------------------------------------------------------

    protected function handleDescription(HdChannel $channel, HdChatSession $session, string $message, array $context): IntakeStep
    {
        if (mb_strlen($message) < 5) {
            return IntakeStep::ask('A descrição parece muito curta. Conte um pouco mais sobre o que você precisa.');
        }

        $departmentId = (int) ($session->context['department_id'] ?? 0);
        $categoryId = $session->context['category_id'] ?? null;
        $employeeId = $session->context['employee_id'] ?? null;
        $identificationFailed = (bool) ($session->context['identification_failed'] ?? false);

        $data = [
            'department_id' => $departmentId,
            'category_id' => $categoryId,
            'title' => mb_substr($message, 0, 80),
            'description' => $message,
            'priority' => HdTicket::PRIORITY_MEDIUM,
            'source' => 'whatsapp',
            'employee_id' => $employeeId,
            'channel_id' => $channel->id,
            'external_contact' => $session->external_contact,
            'external_id' => $context['external_id'] ?? null,
            'channel_metadata' => [
                'pushName' => $context['push_name'] ?? null,
                'instance' => $context['instance'] ?? null,
                'identification_failed' => $identificationFailed,
            ],
        ];

        $ticket = DB::transaction(function () use ($data, $session, $identificationFailed) {
            $userForCreation = $this->systemBotUserId();
            $ticket = $this->helpdeskService->createTicket($data, $userForCreation);

            // If identification failed, leave an internal note so the operator
            // sees the flag immediately when opening the ticket.
            if ($identificationFailed) {
                HdInteraction::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $userForCreation,
                    'comment' => '⚠ CPF não foi informado ou não corresponde a um cadastro ativo. Identifique manualmente antes de prosseguir.',
                    'type' => 'comment',
                    'is_internal' => true,
                ]);
            }

            // Clear the session now that the ticket exists.
            $session->delete();

            return $ticket;
        });

        return IntakeStep::done(
            ticketId: $ticket->id,
            prompt: "Pronto! Seu chamado *#{$ticket->id}* foi aberto. Assim que um atendente responder, você receberá aqui. Se precisar enviar mais detalhes, basta responder nesta conversa.",
            collected: [
                'department_id' => $departmentId,
                'category_id' => $categoryId,
                'employee_id' => $employeeId,
            ],
        );
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    protected function findOpenTicketForContact(int $channelId, string $externalContact): ?HdTicketChannel
    {
        return HdTicketChannel::query()
            ->where('channel_id', $channelId)
            ->where('external_contact', $externalContact)
            ->whereHas('ticket', fn ($q) => $q->whereNotIn('status', HdTicket::TERMINAL_STATUSES))
            ->latest('id')
            ->first();
    }

    protected function appendToExistingTicket(HdTicketChannel $channelRow, string $message, array $context): IntakeStep
    {
        $ticket = $channelRow->ticket;

        if (! $ticket) {
            throw new \RuntimeException('Ticket vinculado ao canal não encontrado.');
        }

        HdInteraction::create([
            'ticket_id' => $ticket->id,
            'user_id' => $ticket->requester_id ?? $this->systemBotUserId(),
            'comment' => $message,
            'type' => 'comment',
            'is_internal' => false,
        ]);

        return IntakeStep::ask(
            "Recebemos sua mensagem no chamado *#{$ticket->id}*. Retornaremos em breve.",
        );
    }

    /**
     * Resolve (or create) a system bot user used as a stand-in for the
     * `requester_id` FK when the contact isn't a Mercury user. The bot is
     * only about WHO opened the ticket technically — WHO the ticket is
     * ABOUT lives in hd_tickets.employee_id (when CPF resolves).
     */
    protected function systemBotUserId(): int
    {
        $bot = User::where('email', 'whatsapp-bot@system.local')->first();

        if ($bot) {
            return $bot->id;
        }

        $bot = User::create([
            'name' => 'WhatsApp Bot',
            'email' => 'whatsapp-bot@system.local',
            'password' => bcrypt(bin2hex(random_bytes(16))),
            'role' => 'user',
        ]);

        return $bot->id;
    }

    protected function requireContact(array $context): string
    {
        $contact = $context['external_contact'] ?? null;
        if (! $contact) {
            throw new \InvalidArgumentException('WhatsappIntakeDriver requires external_contact in context.');
        }

        return (string) $contact;
    }

    /**
     * @param  array<int, string>  $items
     */
    protected function renderMenu(string $header, array $items): string
    {
        $lines = [$header, ''];
        foreach ($items as $idx => $item) {
            $lines[] = sprintf('%d) %s', $idx + 1, $item);
        }

        return implode("\n", $lines);
    }

    /**
     * Parse a numeric menu choice. Accepts "1", "2)", "1 - Hardware", etc.
     * Returns null on invalid input.
     */
    protected function parseMenuChoice(string $message, int $max): ?int
    {
        if ($max === 0) {
            return null;
        }

        if (! preg_match('/\d+/', $message, $matches)) {
            return null;
        }

        $choice = (int) $matches[0];

        if ($choice < 1 || $choice > $max) {
            return null;
        }

        return $choice;
    }
}
