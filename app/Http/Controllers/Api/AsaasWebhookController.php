<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CentralActivityLog;
use App\Models\TenantInvoice;
use App\Services\AsaasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AsaasWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Verify webhook auth token
        $expectedToken = config('services.asaas.webhook_token');
        if ($expectedToken && $request->header('asaas-access-token') !== $expectedToken) {
            Log::warning('Asaas webhook: invalid auth token', ['ip' => $request->ip()]);
            return response('Unauthorized', 401);
        }

        $event = $request->input('event');
        $payment = $request->input('payment');

        if (! $event || ! $payment) {
            return response('Bad Request', 400);
        }

        Log::info("Asaas webhook received: {$event}", [
            'payment_id' => $payment['id'] ?? null,
            'external_ref' => $payment['externalReference'] ?? null,
        ]);

        // Find the invoice by gateway_id or externalReference
        $invoice = $this->findInvoice($payment);

        if (! $invoice) {
            Log::warning("Asaas webhook: invoice not found", [
                'event' => $event,
                'payment_id' => $payment['id'] ?? null,
                'external_ref' => $payment['externalReference'] ?? null,
            ]);
            return response('OK', 200); // Acknowledge even if we can't process
        }

        match ($event) {
            'PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED' => $this->handlePaymentReceived($invoice, $payment),
            'PAYMENT_OVERDUE' => $this->handlePaymentOverdue($invoice, $payment),
            'PAYMENT_DELETED' => $this->handlePaymentDeleted($invoice, $payment),
            'PAYMENT_REFUNDED' => $this->handlePaymentRefunded($invoice, $payment),
            'PAYMENT_CREATED', 'PAYMENT_UPDATED' => $this->handlePaymentUpdated($invoice, $payment),
            default => Log::info("Asaas webhook: unhandled event {$event}"),
        };

        return response('OK', 200);
    }

    protected function findInvoice(array $payment): ?TenantInvoice
    {
        // Try by gateway_id first
        if (! empty($payment['id'])) {
            $invoice = TenantInvoice::where('gateway_id', $payment['id'])->first();
            if ($invoice) {
                return $invoice;
            }
        }

        // Try by externalReference (we store invoice ID there)
        if (! empty($payment['externalReference'])) {
            $ref = $payment['externalReference'];
            // Format: "invoice_{id}"
            if (str_starts_with($ref, 'invoice_')) {
                $invoiceId = (int) str_replace('invoice_', '', $ref);
                return TenantInvoice::find($invoiceId);
            }
        }

        return null;
    }

    protected function handlePaymentReceived(TenantInvoice $invoice, array $payment): void
    {
        if ($invoice->isPaid()) {
            return;
        }

        $paymentMethod = match ($payment['billingType'] ?? '') {
            'PIX' => 'pix',
            'BOLETO' => 'boleto',
            'CREDIT_CARD' => 'cartao',
            default => 'outro',
        };

        $invoice->markAsPaid(
            $paymentMethod,
            $payment['id'] ?? null,
            $payment['paymentDate'] ?? $payment['confirmedDate'] ?? null,
        );

        // Update payment URL and gateway info
        $invoice->update([
            'payment_url' => $payment['invoiceUrl'] ?? $invoice->payment_url,
        ]);

        CentralActivityLog::log(
            'invoice.asaas_paid',
            "Fatura #{$invoice->id} paga via Asaas ({$paymentMethod})",
            $invoice->tenant_id
        );
    }

    protected function handlePaymentOverdue(TenantInvoice $invoice, array $payment): void
    {
        if ($invoice->isPaid() || $invoice->isCancelled()) {
            return;
        }

        $invoice->markAsOverdue();

        CentralActivityLog::log(
            'invoice.asaas_overdue',
            "Fatura #{$invoice->id} vencida (Asaas)",
            $invoice->tenant_id
        );
    }

    protected function handlePaymentDeleted(TenantInvoice $invoice, array $payment): void
    {
        if ($invoice->isPaid()) {
            return;
        }

        $invoice->cancel();

        CentralActivityLog::log(
            'invoice.asaas_cancelled',
            "Fatura #{$invoice->id} cancelada no Asaas",
            $invoice->tenant_id
        );
    }

    protected function handlePaymentRefunded(TenantInvoice $invoice, array $payment): void
    {
        $invoice->update(['status' => 'cancelled']);

        CentralActivityLog::log(
            'invoice.asaas_refunded',
            "Fatura #{$invoice->id} estornada no Asaas",
            $invoice->tenant_id
        );
    }

    protected function handlePaymentUpdated(TenantInvoice $invoice, array $payment): void
    {
        $updates = [];

        if (! empty($payment['invoiceUrl'])) {
            $updates['payment_url'] = $payment['invoiceUrl'];
        }

        if (! empty($payment['status'])) {
            $newStatus = AsaasService::mapStatus($payment['status']);
            if ($newStatus !== $invoice->status && ! $invoice->isPaid()) {
                $updates['status'] = $newStatus;
            }
        }

        if (! empty($updates)) {
            $invoice->update($updates);
        }
    }
}
