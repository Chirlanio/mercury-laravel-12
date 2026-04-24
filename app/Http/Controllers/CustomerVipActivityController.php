<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerVipActivity;
use App\Services\CustomerVipActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * CRUD de atividades de relacionamento (feed CRM-light por cliente).
 *
 * GET    /customers/{customer}/vip/activities    index (JSON)
 * POST   /customers/{customer}/vip/activities    store
 * PATCH  /customers/vip/activities/{activity}    update
 * DELETE /customers/vip/activities/{activity}    destroy
 */
class CustomerVipActivityController extends Controller
{
    public function __construct(private readonly CustomerVipActivityService $activities) {}

    public function index(Customer $customer): JsonResponse
    {
        $items = $customer->vipActivities()
            ->with('createdBy:id,name')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json([
            'activities' => $items->map(fn (CustomerVipActivity $a) => $this->format($a)),
        ]);
    }

    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $data = $this->validated($request);

        $this->activities->create($customer, $data, $request->user());

        return back()->with('success', 'Atividade registrada.');
    }

    public function update(Request $request, CustomerVipActivity $activity): RedirectResponse
    {
        $data = $this->validated($request, partial: true);

        $this->activities->update($activity, $data);

        return back()->with('success', 'Atividade atualizada.');
    }

    public function destroy(CustomerVipActivity $activity): RedirectResponse
    {
        $this->activities->delete($activity);

        return back()->with('success', 'Atividade excluída.');
    }

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    private function validated(Request $request, bool $partial = false): array
    {
        $rules = [
            'type' => ['required', 'in:gift,event,contact,note,other'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'occurred_at' => ['required', 'date'],
            'metadata' => ['nullable', 'array'],
        ];

        if ($partial) {
            foreach ($rules as $field => $fieldRules) {
                $rules[$field] = array_map(
                    fn ($r) => $r === 'required' ? 'sometimes' : $r,
                    $fieldRules,
                );
            }
        }

        return $request->validate($rules);
    }

    private function format(CustomerVipActivity $a): array
    {
        return [
            'id' => $a->id,
            'type' => $a->type,
            'title' => $a->title,
            'description' => $a->description,
            'occurred_at' => $a->occurred_at?->format('Y-m-d'),
            'created_by' => $a->createdBy?->name,
            'created_at' => $a->created_at?->toIso8601String(),
            'metadata' => $a->metadata,
        ];
    }
}
