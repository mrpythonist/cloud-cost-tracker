<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Services\BudgetChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    /** GET /api/budgets?period=YYYY-MM — with current usage attached. */
    public function index(Request $request): JsonResponse
    {
        $query = Budget::query()->orderBy('period')->orderByRaw('service IS NULL DESC')->orderBy('service');
        if ($request->query('period')) {
            $query->where('period', $request->query('period'));
        }
        $budgets = $query->get();
        $out = $budgets->map(function (Budget $b) {
            $actual = $b->service === null
                ? (float) \App\Models\BillingRecord::where('month', $b->period)->sum('cost')
                : (float) \App\Models\BillingRecord::where('month', $b->period)->where('service', $b->service)->sum('cost');
            return array_merge($b->toArray(), [
                'actual' => round($actual, 2),
                'usage_percent' => BudgetChecker::usagePercent($actual, (float) $b->amount),
                'status' => BudgetChecker::status($actual, (float) $b->amount),
            ]);
        });
        return response()->json($out);
    }

    /** POST /api/budgets — {period: YYYY-MM, service?: string|null, amount: number, currency?: string} */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'service' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);
        $exists = Budget::where('period', $data['period'])->where('service', $data['service'] ?? null)->exists();
        if ($exists) {
            return response()->json(['message' => 'A budget already exists for this period and service. Update it instead.'], 422);
        }
        return response()->json(Budget::create($data), 201);
    }

    /** PUT /api/budgets/{id} */
    public function update(Request $request, Budget $budget): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ]);
        $budget->update($data);
        return response()->json($budget);
    }

    /** DELETE /api/budgets/{id} */
    public function destroy(Budget $budget): JsonResponse
    {
        $budget->delete();
        return response()->json(['message' => 'Budget deleted.'], 200);
    }
}
