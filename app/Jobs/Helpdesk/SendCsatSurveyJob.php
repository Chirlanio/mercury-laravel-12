<?php

namespace App\Jobs\Helpdesk;

use App\Models\HdSatisfactionSurvey;
use App\Models\HdTicket;
use App\Services\Channels\EvolutionApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Creates a satisfaction survey row for a resolved ticket and sends the
 * signed invitation link to the requester via their preferred channel.
 *
 * Delivery channel:
 *   - ticket.source === 'whatsapp' → Evolution sendText with the link
 *   - otherwise → email to the requester's email address
 *
 * Failure modes all degrade gracefully:
 *   - Missing requester → log + skip
 *   - Missing requester email AND no WhatsApp channel → log + keep the
 *     survey row (so ops can resend manually) but don't crash
 *   - Evolution down → survey still exists, technician can resend
 *
 * TTL: survey expires in 7 days (config('helpdesk.csat.ttl_days')).
 */
class SendCsatSurveyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(public readonly int $ticketId) {}

    public function handle(): void
    {
        $ticket = HdTicket::with(['requester', 'department', 'category', 'ticketChannels'])->find($this->ticketId);

        if (! $ticket) {
            Log::info('SendCsatSurveyJob: ticket not found', ['id' => $this->ticketId]);

            return;
        }

        // Idempotency check — another listener fire may have raced us.
        if (HdSatisfactionSurvey::where('ticket_id', $ticket->id)->exists()) {
            return;
        }

        $ttlDays = (int) config('helpdesk.csat.ttl_days', 7);

        $survey = HdSatisfactionSurvey::create([
            'ticket_id' => $ticket->id,
            'requester_id' => $ticket->requester_id,
            'resolved_by_user_id' => $ticket->assigned_technician_id ?? $ticket->updated_by_user_id,
            'department_id' => $ticket->department_id,
            'category_id' => $ticket->category_id,
            'signed_token' => HdSatisfactionSurvey::generateToken(),
            'expires_at' => now()->addDays($ttlDays),
        ]);

        $url = URL::temporarySignedRoute(
            'helpdesk.csat.show',
            $survey->expires_at,
            ['token' => $survey->signed_token],
        );

        $sent = $this->deliver($ticket, $survey, $url);

        if ($sent) {
            $survey->update([
                'sent_via' => $sent,
                'sent_at' => now(),
            ]);
        }
    }

    /**
     * Deliver the invitation via the appropriate channel. Returns the
     * channel name on success or null on failure (failure is NOT a job
     * error — the survey row still exists for manual resend).
     */
    protected function deliver(HdTicket $ticket, HdSatisfactionSurvey $survey, string $url): ?string
    {
        // Prefer WhatsApp when the ticket originated there.
        if ($ticket->source === 'whatsapp') {
            $channelRow = $ticket->ticketChannels->first();
            if ($channelRow && $channelRow->external_contact) {
                $result = EvolutionApiClient::fromConfig()->sendText(
                    $channelRow->external_contact,
                    $this->whatsappMessage($ticket, $url),
                );
                if ($result['success']) {
                    return 'whatsapp';
                }
                Log::warning('SendCsatSurveyJob: WhatsApp delivery failed', ['ticket_id' => $ticket->id]);
            }
        }

        // Email fallback.
        $email = $ticket->requester?->email;
        if ($email && $email !== 'whatsapp-bot@system.local') {
            try {
                Mail::raw($this->emailBody($ticket, $url), function ($message) use ($email, $ticket) {
                    $message->to($email)
                        ->subject("Como foi seu atendimento? — Chamado #{$ticket->id}");
                });

                return 'email';
            } catch (\Throwable $e) {
                Log::warning('SendCsatSurveyJob: email delivery failed', [
                    'ticket_id' => $ticket->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('SendCsatSurveyJob: no delivery channel available — survey created for manual resend', [
            'ticket_id' => $ticket->id,
            'survey_id' => $survey->id,
        ]);

        return null;
    }

    protected function whatsappMessage(HdTicket $ticket, string $url): string
    {
        return "Olá! Seu chamado *#{$ticket->id}* foi resolvido.\n\n"
            ."Poderia avaliar o atendimento? Leva 10 segundos:\n{$url}\n\n"
            .'Obrigado!';
    }

    protected function emailBody(HdTicket $ticket, string $url): string
    {
        return "Olá,\n\n"
            ."Seu chamado #{$ticket->id} ({$ticket->title}) foi resolvido.\n\n"
            ."Gostaríamos de saber como foi seu atendimento. Clique no link abaixo para avaliar (leva 10 segundos):\n\n"
            ."{$url}\n\n"
            ."O link é válido por 7 dias.\n\n"
            ."Obrigado!\n"
            .'Equipe do Helpdesk';
    }
}
