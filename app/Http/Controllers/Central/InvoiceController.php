<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\CentralActivityLog;
use App\Models\Tenant;
use App\Models\TenantInvoice;
use App\Models\TenantPlan;
use App\Services\AsaasService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function __construct(
        protected AsaasService $asaas,
    ) {}

    public function index(Request $request)
    {
        $query = TenantInvoice::with('tenant', 'plan');

        if ($search = $request->input('search')) {
            $query->whereHas('tenant', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($cycle = $request->input('billing_cycle')) {
            $query->where('billing_cycle', $cycle);
        }

        if ($planId = $request->input('plan_id')) {
            $query->where('plan_id', $planId);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->where('billing_period_start', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->where('billing_period_end', '<=', $dateTo);
        }

        $invoices = $query->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn ($inv) => [
                'id' => $inv->id,
                'tenant_id' => $inv->tenant_id,
                'tenant_name' => $inv->tenant?->name ?? '—',
                'plan_name' => $inv->plan?->name ?? '—',
                'plan_id' => $inv->plan_id,
                'amount' => (float) $inv->amount,
                'currency' => $inv->currency,
                'billing_cycle' => $inv->billing_cycle,
                'billing_period_start' => $inv->billing_period_start?->format('d/m/Y'),
                'billing_period_end' => $inv->billing_period_end?->format('d/m/Y'),
                'status' => $inv->status,
                'due_at' => $inv->due_at?->format('d/m/Y'),
                'paid_at' => $inv->paid_at?->format('d/m/Y'),
                'payment_method' => $inv->payment_method,
                'transaction_id' => $inv->transaction_id,
                'payment_url' => $inv->payment_url,
                'auto_generated' => $inv->auto_generated,
                'notes' => $inv->notes,
                'created_at' => $inv->created_at->format('d/m/Y'),
            ]);

        return Inertia::render('Central/Invoices/Index', [
            'invoices' => $invoices,
            'stats' => $this->getStatistics(),
            'tenants' => Tenant::orderBy('name')->get(['id', 'name']),
            'plans' => TenantPlan::where('is_active', true)->get(['id', 'name']),
            'filters' => $request->only(['search', 'status', 'billing_cycle', 'plan_id', 'date_from', 'date_to']),
            'asaasConfigured' => $this->asaas->isConfigured(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tenant_id' => 'required|string|exists:tenants,id',
            'plan_id' => 'nullable|exists:tenant_plans,id',
            'amount' => 'required|numeric|min:0.01',
            'billing_cycle' => 'required|in:monthly,yearly',
            'billing_period_start' => 'required|date',
            'billing_period_end' => 'required|date|after_or_equal:billing_period_start',
            'due_at' => 'required|date',
            'notes' => 'nullable|string',
            'payment_url' => 'nullable|string',
        ]);

        $invoice = TenantInvoice::create([
            ...$validated,
            'currency' => 'BRL',
            'status' => 'pending',
            'auto_generated' => false,
        ]);

        $tenantName = Tenant::find($validated['tenant_id'])?->name;
        CentralActivityLog::log('invoice.created', "Fatura R$ {$validated['amount']} criada para '{$tenantName}'", $validated['tenant_id']);

        return redirect('/admin/invoices')->with('success', 'Fatura criada com sucesso.');
    }

    public function update(Request $request, TenantInvoice $invoice)
    {
        if ($invoice->isPaid() || $invoice->isCancelled()) {
            return back()->with('error', 'Faturas pagas ou canceladas não podem ser editadas.');
        }

        $validated = $request->validate([
            'amount' => 'sometimes|numeric|min:0.01',
            'billing_cycle' => 'sometimes|in:monthly,yearly',
            'billing_period_start' => 'sometimes|date',
            'billing_period_end' => 'sometimes|date',
            'due_at' => 'sometimes|date',
            'notes' => 'nullable|string',
            'payment_url' => 'nullable|string',
        ]);

        $invoice->update($validated);

        CentralActivityLog::log('invoice.updated', "Fatura #{$invoice->id} atualizada", $invoice->tenant_id);

        return back()->with('success', 'Fatura atualizada.');
    }

    public function markAsPaid(Request $request, TenantInvoice $invoice)
    {
        if ($invoice->isPaid()) {
            return back()->with('error', 'Fatura já está paga.');
        }

        $validated = $request->validate([
            'payment_method' => 'required|string',
            'paid_at' => 'nullable|date',
            'transaction_id' => 'nullable|string',
        ]);

        $invoice->markAsPaid(
            $validated['payment_method'],
            $validated['transaction_id'] ?? null,
            $validated['paid_at'] ?? null,
        );

        CentralActivityLog::log('invoice.paid', "Fatura #{$invoice->id} marcada como paga ({$validated['payment_method']})", $invoice->tenant_id);

        return back()->with('success', 'Fatura marcada como paga.');
    }

    public function markAsOverdue(TenantInvoice $invoice)
    {
        if (! $invoice->isPending()) {
            return back()->with('error', 'Apenas faturas pendentes podem ser marcadas como vencidas.');
        }

        $invoice->markAsOverdue();

        CentralActivityLog::log('invoice.overdue', "Fatura #{$invoice->id} marcada como vencida", $invoice->tenant_id);

        return back()->with('success', 'Fatura marcada como vencida.');
    }

    public function cancel(TenantInvoice $invoice)
    {
        if ($invoice->isPaid()) {
            return back()->with('error', 'Faturas pagas não podem ser canceladas.');
        }

        $invoice->cancel();

        CentralActivityLog::log('invoice.cancelled', "Fatura #{$invoice->id} cancelada", $invoice->tenant_id);

        return back()->with('success', 'Fatura cancelada.');
    }

    public function generateForTenant(Request $request)
    {
        $validated = $request->validate([
            'tenant_id' => 'required|string|exists:tenants,id',
            'billing_cycle' => 'required|in:monthly,yearly',
        ]);

        $tenant = Tenant::with('plan')->findOrFail($validated['tenant_id']);

        if (! $tenant->plan) {
            return back()->with('error', "Tenant '{$tenant->name}' não possui plano associado.");
        }

        $cycle = $validated['billing_cycle'];
        $amount = $cycle === 'monthly' ? $tenant->plan->price_monthly : $tenant->plan->price_yearly;

        if (! $amount || $amount <= 0) {
            return back()->with('error', "O plano '{$tenant->plan->name}' não possui preço definido para o ciclo {$cycle}.");
        }

        [$periodStart, $periodEnd] = $this->calculatePeriod($cycle);

        // Check duplicate
        $exists = TenantInvoice::where('tenant_id', $tenant->id)
            ->where('billing_cycle', $cycle)
            ->where('billing_period_start', $periodStart)
            ->whereNotIn('status', ['cancelled'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'Já existe uma fatura para este tenant e período.');
        }

        TenantInvoice::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $tenant->plan->id,
            'amount' => $amount,
            'currency' => 'BRL',
            'billing_cycle' => $cycle,
            'billing_period_start' => $periodStart,
            'billing_period_end' => $periodEnd,
            'status' => 'pending',
            'due_at' => $periodStart->copy()->addDays(10),
            'auto_generated' => false,
        ]);

        CentralActivityLog::log('invoice.generated', "Fatura gerada para '{$tenant->name}' - R$ {$amount} ({$cycle})", $tenant->id);

        return back()->with('success', "Fatura de R$ " . number_format($amount, 2, ',', '.') . " gerada para '{$tenant->name}'.");
    }

    public function generateBulk(Request $request)
    {
        $validated = $request->validate([
            'billing_cycle' => 'required|in:monthly,yearly',
        ]);

        $cycle = $validated['billing_cycle'];
        [$periodStart, $periodEnd] = $this->calculatePeriod($cycle);

        $tenants = Tenant::with('plan')
            ->where('is_active', true)
            ->whereNotNull('plan_id')
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($tenants as $tenant) {
            $amount = $cycle === 'monthly' ? $tenant->plan->price_monthly : $tenant->plan->price_yearly;

            if (! $amount || $amount <= 0) {
                $skipped++;
                continue;
            }

            $exists = TenantInvoice::where('tenant_id', $tenant->id)
                ->where('billing_cycle', $cycle)
                ->where('billing_period_start', $periodStart)
                ->whereNotIn('status', ['cancelled'])
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            TenantInvoice::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $tenant->plan->id,
                'amount' => $amount,
                'currency' => 'BRL',
                'billing_cycle' => $cycle,
                'billing_period_start' => $periodStart,
                'billing_period_end' => $periodEnd,
                'status' => 'pending',
                'due_at' => $periodStart->copy()->addDays(10),
                'auto_generated' => false,
            ]);

            $created++;
        }

        CentralActivityLog::log('invoice.bulk_generated', "{$created} faturas geradas em lote ({$cycle}), {$skipped} ignoradas");

        return back()->with('success', "{$created} faturas geradas, {$skipped} ignoradas (já existiam ou sem preço).");
    }

    /**
     * Create a charge on Asaas for an existing invoice.
     */
    public function chargeOnAsaas(Request $request, TenantInvoice $invoice)
    {
        if (! $this->asaas->isConfigured()) {
            return back()->with('error', 'Asaas não está configurado. Defina ASAAS_API_KEY no .env.');
        }

        if ($invoice->isPaid() || $invoice->isCancelled()) {
            return back()->with('error', 'Fatura já está paga ou cancelada.');
        }

        $validated = $request->validate([
            'billing_type' => 'required|in:PIX,BOLETO,UNDEFINED',
        ]);

        $tenant = Tenant::findOrFail($invoice->tenant_id);

        try {
            // Ensure tenant has an Asaas customer
            $customerId = $this->ensureAsaasCustomer($tenant);

            // Create payment on Asaas
            $payment = $this->asaas->createPayment([
                'customer' => $customerId,
                'billingType' => $validated['billing_type'],
                'value' => (float) $invoice->amount,
                'dueDate' => $invoice->due_at->format('Y-m-d'),
                'description' => "Fatura #{$invoice->id} - {$tenant->name}",
                'externalReference' => "invoice_{$invoice->id}",
            ]);

            // Update invoice with Asaas data
            $invoice->update([
                'gateway_provider' => 'asaas',
                'gateway_id' => $payment['id'],
                'payment_url' => $payment['invoiceUrl'] ?? null,
            ]);

            CentralActivityLog::log(
                'invoice.asaas_charged',
                "Cobrança Asaas criada para fatura #{$invoice->id} ({$validated['billing_type']})",
                $invoice->tenant_id
            );

            return back()->with('success', "Cobrança {$validated['billing_type']} criada no Asaas.");
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao criar cobrança: ' . $e->getMessage());
        }
    }

    /**
     * Get PIX QR Code for an invoice charged on Asaas.
     */
    public function getPixQrCode(TenantInvoice $invoice)
    {
        if (! $invoice->gateway_id || $invoice->gateway_provider !== 'asaas') {
            return response()->json(['error' => 'Fatura não possui cobrança Asaas.'], 400);
        }

        try {
            $qrCode = $this->asaas->getPixQrCode($invoice->gateway_id);

            return response()->json($qrCode);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Ensure the tenant has a customer record on Asaas.
     * Creates one if it doesn't exist, stores the ID in tenant settings.
     */
    protected function ensureAsaasCustomer(Tenant $tenant): string
    {
        $settings = $tenant->settings ?? [];

        // Already has Asaas customer ID
        if (! empty($settings['asaas_customer_id'])) {
            return $settings['asaas_customer_id'];
        }

        // Try to find by external reference
        $existing = $this->asaas->findCustomerByExternalReference($tenant->id);
        if ($existing) {
            $settings['asaas_customer_id'] = $existing['id'];
            $tenant->update(['settings' => $settings]);

            return $existing['id'];
        }

        // Create new customer
        $customer = $this->asaas->createCustomer([
            'name' => $tenant->name,
            'email' => $tenant->owner_email,
            'cpfCnpj' => preg_replace('/\D/', '', $tenant->cnpj ?? ''),
            'externalReference' => $tenant->id,
            'notificationDisabled' => false,
        ]);

        $settings['asaas_customer_id'] = $customer['id'];
        $tenant->update(['settings' => $settings]);

        return $customer['id'];
    }

    protected function calculatePeriod(string $cycle): array
    {
        $now = Carbon::now();

        if ($cycle === 'monthly') {
            return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
        }

        return [$now->copy()->startOfYear(), $now->copy()->endOfYear()];
    }

    protected function getStatistics(): array
    {
        $now = Carbon::now();

        $paidThisMonth = TenantInvoice::paid()
            ->whereMonth('paid_at', $now->month)
            ->whereYear('paid_at', $now->year)
            ->sum('amount');

        $monthlyPaid = TenantInvoice::paid()
            ->where('billing_cycle', 'monthly')
            ->whereMonth('paid_at', $now->month)
            ->whereYear('paid_at', $now->year)
            ->sum('amount');

        $yearlyPaidThisMonth = TenantInvoice::paid()
            ->where('billing_cycle', 'yearly')
            ->whereMonth('paid_at', $now->month)
            ->whereYear('paid_at', $now->year)
            ->sum('amount');

        return [
            'mrr' => round((float) $monthlyPaid + ((float) $yearlyPaidThisMonth / 12), 2),
            'total_pending' => round((float) TenantInvoice::pending()->sum('amount'), 2),
            'total_overdue' => round((float) TenantInvoice::where(function ($q) {
                $q->where('status', 'overdue')
                  ->orWhere(fn ($q2) => $q2->where('status', 'pending')->where('due_at', '<', now()));
            })->sum('amount'), 2),
            'paid_this_month' => round((float) $paidThisMonth, 2),
        ];
    }
}
