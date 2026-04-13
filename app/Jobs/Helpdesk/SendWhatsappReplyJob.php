<?php

namespace App\Jobs\Helpdesk;

use App\Models\HdInteraction;
use App\Services\Channels\EvolutionApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sends a technician's comment back to the original WhatsApp contact via
 * Evolution API. Queued so the HTTP call doesn't block the web request
 * that created the interaction.
 *
 * Retries up to 3 times with exponential backoff. On final failure, the
 * failed() hook writes an internal warning note on the ticket so the
 * technician knows the reply didn't reach the user.
 *
 * The Evolution message_id returned on successful send is persisted into
 * hd_interactions.external_id for audit + future dedup.
 */
class SendWhatsappReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 15;

    public function __construct(public readonly int $interactionId) {}

    public function handle(): void
    {
        $interaction = HdInteraction::with(['ticket.ticketChannels', 'user'])->find($this->interactionId);

        if (! $interaction) {
            Log::info('SendWhatsappReplyJob: interaction not found (already deleted?)', [
                'interaction_id' => $this->interactionId,
            ]);

            return;
        }

        $ticket = $interaction->ticket;
        $channelRow = $ticket?->ticketChannels->first();

        if (! $ticket || ! $channelRow || ! $channelRow->external_contact) {
            Log::info('SendWhatsappReplyJob: missing ticket/channel/contact', [
                'interaction_id' => $this->interactionId,
                'ticket_id' => $ticket?->id,
            ]);

            return;
        }

        $message = $this->formatMessage($ticket->id, $interaction->user?->name, (string) $interaction->comment);

        $result = EvolutionApiClient::fromConfig()->sendText(
            $channelRow->external_contact,
            $message,
        );

        if (! $result['success']) {
            // Force a retry — Laravel will re-run handle() up to $tries times.
            throw new \RuntimeException('Evolution sendText returned non-success for interaction '.$this->interactionId);
        }

        // Persist the Evolution message id for audit/dedup. Ignore failures
        // here — the message was delivered; a missing external_id is
        // cosmetic, not transactional.
        if (! empty($result['message_id'])) {
            $interaction->forceFill(['external_id' => $result['message_id']])->save();
        }
    }

    /**
     * Called by the framework after $tries retries exhaust. Writes an internal
     * note on the ticket so the attending technician sees the failure.
     */
    public function failed(\Throwable $e): void
    {
        $interaction = HdInteraction::with('ticket')->find($this->interactionId);

        if (! $interaction || ! $interaction->ticket) {
            Log::error('SendWhatsappReplyJob permanently failed AND interaction/ticket missing', [
                'interaction_id' => $this->interactionId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        HdInteraction::create([
            'ticket_id' => $interaction->ticket_id,
            'user_id' => $interaction->user_id,
            'comment' => "⚠ Falha ao enviar a resposta via WhatsApp para o usuário. Verifique o envio manualmente.\nErro: {$e->getMessage()}",
            'type' => 'comment',
            'is_internal' => true,
        ]);

        Log::error('SendWhatsappReplyJob permanently failed', [
            'interaction_id' => $this->interactionId,
            'ticket_id' => $interaction->ticket_id,
            'error' => $e->getMessage(),
        ]);
    }

    protected function formatMessage(int $ticketId, ?string $authorName, string $comment): string
    {
        $author = $authorName ? "*{$authorName}*" : '*Atendente*';

        return "[#{$ticketId}] {$author}:\n{$comment}";
    }
}
