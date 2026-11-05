<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillingRecord;
use App\Services\CostAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(protected CostAnalyzer $analyzer)
    {
    }

    /** GET /api/reports/monthly?month=YYYY-MM */
    public function monthly(Request $request): JsonResponse
    {
        $month = $request->query('month') ?? BillingRecord::max('month');
        if ($month === null || !preg_match('/^\d{4}-\d{2}$/', $month)) {
            return response()->json(['message' => 'No billing data yet, or month must be YYYY-MM.'], 404);
        }
        $rows = $this->rowsForMonths([$month]);
        $byService = $this->analyzer->byService($rows, $month);
        return response()->json([
            'month' => $month,
            'total' => round(array_sum(array_column($byService, 'total')), 2),
            'currency' => BillingRecord::where('month', $month)->value('currency') ?? 'USD',
            'by_service' => $byService,
            'top_services' => $this->analyzer->topServices($rows, $month),
        ]);
    }

    /** GET /api/reports/compare?from=YYYY-MM&to=YYYY-MM */
    public function compare(Request $request): JsonResponse
    {
        $from = $request->query('from');
        $to = $request->query('to');
        if (!preg_match('/^\d{4}-\d{2}$/', (string) $from) || !preg_match('/^\d{4}-\d{2}$/', (string) $to)) {
            return response()->json(['message' => 'Query params from and to are required as YYYY-MM.'], 422);
        }
        $rows = $this->rowsForMonths([$from, $to]);
        return response()->json($this->analyzer->compareMonths($rows, $from, $to));
    }

    /** GET /api/reports/trends?months=3&threshold=20 */
    public function trends(Request $request): JsonResponse
    {
        $months = max(2, min(12, (int) $request->query('months', 3)));
        $threshold = (float) $request->query('threshold', CostAnalyzer::DEFAULT_INCREASE_THRESHOLD_PERCENT);
        $rows = $this->allRows();
        return response()->json($this->analyzer->trends($rows, $months, $threshold));
    }

    /** @return array<int, array{month: string, service: string, cost: float}> */
    private function rowsForMonths(array $months): array
    {
        return BillingRecord::whereIn('month', $months)
            ->get(['month', 'service', 'cost'])
            ->map(fn ($r) => ['month' => $r->month, 'service' => $r->service, 'cost' => (float) $r->cost])
            ->all();
    }

    /** @return array<int, array{month: string, service: string, cost: float}> */
    private function allRows(): array
    {
        return BillingRecord::get(['month', 'service', 'cost'])
            ->map(fn ($r) => ['month' => $r->month, 'service' => $r->service, 'cost' => (float) $r->cost])
            ->all();
    }
}
