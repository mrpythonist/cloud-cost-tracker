<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadCsvRequest;
use App\Models\BillingRecord;
use App\Models\BillingUpload;
use App\Services\BudgetChecker;
use App\Services\CostAnalyzer;
use App\Services\CsvParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function __construct(
        protected CsvParser $parser,
        protected CostAnalyzer $analyzer,
        protected BudgetChecker $checker,
    ) {
    }

    /** GET /api/uploads — upload history, newest first. */
    public function index(): JsonResponse
    {
        $uploads = BillingUpload::orderByDesc('created_at')->paginate(20);
        return response()->json($uploads);
    }

    /**
     * POST /api/uploads — multipart `csv_file`.
     * Validates, stores the file (local disk by default, S3 when
     * FILESYSTEM_DISK=s3 — see config/filesystems.php), parses it, stores
     * one BillingRecord per valid row, then re-checks budgets for every
     * month the file covers.
     */
    public function store(UploadCsvRequest $request): JsonResponse
    {
        $file = $request->file('csv_file');
        $disk = config('filesystems.default');
        $storedPath = $file->store('billing-csv', $disk);

        $localPath = $disk === 's3'
            ? Storage::disk('local')->putFile('billing-csv', $file)
            : Storage::disk($disk)->path($storedPath);

        try {
            $parsed = $this->parser->parse($localPath);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            Storage::disk($disk)->delete($storedPath);
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $rows = $parsed['rows'];
        $months = array_values(array_unique(array_column($rows, 'month')));
        sort($months);
        $totalCost = round(array_sum(array_column($rows, 'cost')), 2);

        $upload = BillingUpload::create([
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'storage_disk' => $disk,
            'file_size_bytes' => $file->getSize(),
            'format' => $parsed['detected_format'],
            'status' => 'completed',
            'rows_imported' => count($rows),
            'rows_skipped' => count($parsed['skipped']),
            'total_cost' => $totalCost,
            'currency' => $rows[0]['currency'] ?? 'USD',
            'months_covered' => $months,
            'parse_report' => ['skipped_rows' => $parsed['skipped']],
        ]);

        foreach (array_chunk($rows, 500) as $chunk) {
            $insert = array_map(fn ($r) => array_merge($r, [
                'billing_upload_id' => $upload->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]), $chunk);
            BillingRecord::insert($insert);
        }

        $alertsCreated = $this->recheckBudgets($months);

        return response()->json([
            'upload' => $upload,
            'rows_imported' => count($rows),
            'rows_skipped' => count($parsed['skipped']),
            'skipped_rows' => $parsed['skipped'],
            'total_cost' => $totalCost,
            'months' => $months,
            'alerts_created' => $alertsCreated,
        ], 201);
    }

    /**
     * Re-evaluate budgets for the given months. An alert is created only
     * when no unacknowledged alert at the same (or higher) level already
     * exists for that budget + period, so re-uploads do not spam duplicates.
     *
     * @return int number of alerts created
     */
    public function recheckBudgets(array $months): int
    {
        $created = 0;
        foreach ($months as $month) {
            $records = BillingRecord::where('month', $month)->get();
            $rows = $records->map(fn ($r) => [
                'month' => $r->month, 'service' => $r->service, 'cost' => (float) $r->cost,
            ])->all();
            $byService = $this->analyzer->byService($rows, $month);
            $actuals = [
                'period' => $month,
                'overall' => round(array_sum(array_column($byService, 'total')), 2),
                'by_service' => array_column($byService, 'total', 'service'),
            ];
            $budgets = \App\Models\Budget::where('period', $month)->get()->map(fn ($b) => [
                'id' => $b->id, 'period' => $b->period, 'service' => $b->service, 'amount' => (float) $b->amount,
            ])->all();

            foreach ($this->checker->check($budgets, $actuals) as $alert) {
                $exists = \App\Models\BudgetAlert::where('budget_id', $alert['budget_id'])
                    ->where('period', $month)
                    ->whereNull('acknowledged_at')
                    ->where('level', $alert['level'])
                    ->exists();
                if ($exists) {
                    continue;
                }
                \App\Models\BudgetAlert::create($alert);
                $created++;
            }
        }
        return $created;
    }
}
