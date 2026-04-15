<?php

namespace App\Services;

use App\Models\OrderPayment;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gera ordens de pagamento (order_payments) automaticamente a partir de
 * uma ordem de compra quando ela transita para INVOICED com flag
 * auto_generate_payments=true.
 *
 * Como funciona:
 *  1. Lê purchase_order.payment_terms_raw (ex: "30/60/90")
 *  2. PaymentTermsParser converte em array de dias
 *  3. Calcula total da ordem (sum unit_cost * quantity_ordered)
 *  4. Divide igualmente em parcelas (última absorve resto)
 *  5. Cria 1 OrderPayment por parcela com:
 *      - date_payment = now() + N dias (data base é a transição para INVOICED)
 *      - description = "Compra #{order_number} - parcela X/N"
 *      - status = 'backlog'
 *      - purchase_order_id, supplier_id, store_id herdados
 *
 * Idempotente: se já existem order_payments vinculados a esta ordem, não
 * regera. Útil para evitar duplicação se a ordem voltar para PENDING e
 * for refaturada.
 */
class PurchaseOrderPaymentGenerator
{
    public function __construct(
        protected PaymentTermsParser $parser,
    ) {}

    /**
     * @return array{generated: int, skipped_reason: ?string}
     */
    public function generateForOrder(PurchaseOrder $order, ?User $actor = null): array
    {
        if (! $order->auto_generate_payments) {
            return ['generated' => 0, 'skipped_reason' => 'auto_generate_payments=false'];
        }

        // Idempotência: se já há pagamentos vinculados, não regera
        $existing = OrderPayment::where('purchase_order_id', $order->id)->count();
        if ($existing > 0) {
            return ['generated' => 0, 'skipped_reason' => "já existem {$existing} order_payment(s) vinculadas"];
        }

        $days = $this->parser->parse($order->payment_terms_raw);
        if (empty($days)) {
            return ['generated' => 0, 'skipped_reason' => 'payment_terms_raw vazio ou inválido'];
        }

        $order->loadMissing('items');
        $totalCost = $order->total_cost;
        if ($totalCost <= 0) {
            return ['generated' => 0, 'skipped_reason' => 'ordem sem itens ou custo zero'];
        }

        $count = count($days);
        $amounts = $this->parser->splitAmount($totalCost, $count);

        $now = Carbon::now();
        $generated = 0;

        // order_payments.created_by_user_id é NOT NULL no schema. Fallback
        // para o criador da ordem quando o generator é chamado sem actor
        // (caminho background/job).
        $actorId = $actor?->id ?? $order->created_by_user_id;
        if (! $actorId) {
            return ['generated' => 0, 'skipped_reason' => 'sem actor nem created_by_user_id na ordem'];
        }

        DB::transaction(function () use ($order, $days, $amounts, $now, $count, $actorId, &$generated) {
            foreach ($days as $idx => $daysOffset) {
                try {
                    OrderPayment::create([
                        'purchase_order_id' => $order->id,
                        'supplier_id' => $order->supplier_id,
                        'store_id' => null, // store é code/string em PO; OP usa unsignedBigInteger — deixar nulo
                        'description' => "Compra #{$order->order_number} - parcela " . ($idx + 1) . "/{$count}",
                        'total_value' => $amounts[$idx],
                        'date_payment' => $now->copy()->addDays($daysOffset)->toDateString(),
                        'payment_type' => null,
                        'installments' => 0,
                        'status' => OrderPayment::STATUS_BACKLOG,
                        'created_by_user_id' => $actorId,
                        'updated_by_user_id' => $actorId,
                    ]);
                    $generated++;
                } catch (\Throwable $e) {
                    // Se falhar uma parcela, log mas continua.
                    Log::warning('Failed to generate order_payment from purchase order', [
                        'purchase_order_id' => $order->id,
                        'parcela' => $idx + 1,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        return ['generated' => $generated, 'skipped_reason' => null];
    }
}
