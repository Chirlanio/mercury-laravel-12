<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerVipActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * CRUD de atividades de relacionamento com clientes (feed CRM-light).
 *
 * Não limita a clientes VIP — qualquer cliente pode ter atividades, mas a
 * UI expõe o fluxo dentro do módulo VIP. Soft delete para auditoria.
 */
class CustomerVipActivityService
{
    public function create(Customer $customer, array $data, User $by): CustomerVipActivity
    {
        return DB::transaction(function () use ($customer, $data, $by) {
            return CustomerVipActivity::create([
                'customer_id' => $customer->id,
                'type' => $data['type'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'occurred_at' => $data['occurred_at'],
                'created_by_user_id' => $by->id,
                'metadata' => $data['metadata'] ?? null,
            ]);
        });
    }

    public function update(CustomerVipActivity $activity, array $data): CustomerVipActivity
    {
        return DB::transaction(function () use ($activity, $data) {
            $activity->update(array_filter([
                'type' => $data['type'] ?? null,
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'occurred_at' => $data['occurred_at'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ], fn ($v) => $v !== null));

            return $activity->fresh();
        });
    }

    public function delete(CustomerVipActivity $activity): void
    {
        $activity->delete();
    }

    public function restore(CustomerVipActivity $activity): void
    {
        $activity->restore();
    }
}
