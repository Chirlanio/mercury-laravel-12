<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client wrapper for the Asaas payment gateway API.
 * Handles customers, payments (PIX/boleto/card), subscriptions, and refunds.
 */
class AsaasService
{
    protected function client(): PendingRequest
    {
        return Http::withHeaders([
            'access_token' => config('services.asaas.api_key'),
            'Content-Type' => 'application/json',
        ])->baseUrl(config('services.asaas.base_url'));
    }

    /**
     * Check if Asaas is configured (API key present).
     */
    public function isConfigured(): bool
    {
        return ! empty(config('services.asaas.api_key'));
    }

    // =================== CUSTOMERS ===================

    public function createCustomer(array $data): array
    {
        $response = $this->client()->post('/customers', $data);

        if ($response->failed()) {
            Log::error('Asaas createCustomer failed', ['status' => $response->status(), 'body' => $response->json()]);
            throw new \RuntimeException('Erro ao criar cliente no Asaas: ' . ($response->json('errors.0.description') ?? $response->body()));
        }

        return $response->json();
    }

    public function findCustomerByExternalReference(string $ref): ?array
    {
        $response = $this->client()->get('/customers', ['externalReference' => $ref]);

        $data = $response->json('data', []);

        return count($data) > 0 ? $data[0] : null;
    }

    public function getCustomer(string $customerId): array
    {
        return $this->client()->get("/customers/{$customerId}")->json();
    }

    // =================== PAYMENTS ===================

    /**
     * Create a payment (charge) on Asaas.
     *
     * @param  array  $data  Must include: customer, billingType, value, dueDate
     *                       billingType: BOLETO, PIX, CREDIT_CARD, UNDEFINED
     */
    public function createPayment(array $data): array
    {
        $response = $this->client()->post('/payments', $data);

        if ($response->failed()) {
            Log::error('Asaas createPayment failed', ['status' => $response->status(), 'body' => $response->json()]);
            throw new \RuntimeException('Erro ao criar cobrança no Asaas: ' . ($response->json('errors.0.description') ?? $response->body()));
        }

        return $response->json();
    }

    public function getPayment(string $paymentId): array
    {
        return $this->client()->get("/payments/{$paymentId}")->json();
    }

    public function cancelPayment(string $paymentId): array
    {
        $response = $this->client()->delete("/payments/{$paymentId}");

        if ($response->failed()) {
            Log::error('Asaas cancelPayment failed', ['status' => $response->status(), 'body' => $response->json()]);
            throw new \RuntimeException('Erro ao cancelar cobrança no Asaas: ' . ($response->json('errors.0.description') ?? $response->body()));
        }

        return $response->json();
    }

    // =================== PIX ===================

    /**
     * Get PIX QR Code for a payment.
     * Returns: encodedImage (base64 PNG), payload (copia e cola), expirationDate
     */
    public function getPixQrCode(string $paymentId): array
    {
        $response = $this->client()->get("/payments/{$paymentId}/pixQrCode");

        if ($response->failed()) {
            Log::error('Asaas getPixQrCode failed', ['status' => $response->status(), 'body' => $response->json()]);
            throw new \RuntimeException('Erro ao obter QR Code PIX: ' . ($response->json('errors.0.description') ?? $response->body()));
        }

        return $response->json();
    }

    // =================== SUBSCRIPTIONS ===================

    /**
     * Create a recurring subscription.
     * Cycle: MONTHLY, QUARTERLY, SEMIANNUALLY, YEARLY
     */
    public function createSubscription(array $data): array
    {
        $response = $this->client()->post('/subscriptions', $data);

        if ($response->failed()) {
            Log::error('Asaas createSubscription failed', ['status' => $response->status(), 'body' => $response->json()]);
            throw new \RuntimeException('Erro ao criar assinatura no Asaas: ' . ($response->json('errors.0.description') ?? $response->body()));
        }

        return $response->json();
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        $response = $this->client()->delete("/subscriptions/{$subscriptionId}");

        if ($response->failed()) {
            Log::error('Asaas cancelSubscription failed', ['status' => $response->status(), 'body' => $response->json()]);
            throw new \RuntimeException('Erro ao cancelar assinatura: ' . ($response->json('errors.0.description') ?? $response->body()));
        }

        return $response->json();
    }

    // =================== REFUND ===================

    public function refundPayment(string $paymentId, ?float $value = null, ?string $description = null): array
    {
        $data = [];
        if ($value) {
            $data['value'] = $value;
        }
        if ($description) {
            $data['description'] = $description;
        }

        $response = $this->client()->post("/payments/{$paymentId}/refund", $data);

        if ($response->failed()) {
            Log::error('Asaas refundPayment failed', ['status' => $response->status(), 'body' => $response->json()]);
            throw new \RuntimeException('Erro ao estornar pagamento: ' . ($response->json('errors.0.description') ?? $response->body()));
        }

        return $response->json();
    }

    // =================== STATUS MAPPING ===================

    /**
     * Map Asaas payment status to our internal invoice status.
     */
    public static function mapStatus(string $asaasStatus): string
    {
        return match ($asaasStatus) {
            'PENDING', 'AWAITING_RISK_ANALYSIS' => 'pending',
            'RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH' => 'paid',
            'OVERDUE', 'DUNNING_REQUESTED' => 'overdue',
            'REFUNDED', 'REFUND_REQUESTED', 'REFUND_IN_PROGRESS' => 'cancelled',
            'DELETED' => 'cancelled',
            default => 'pending',
        };
    }
}
