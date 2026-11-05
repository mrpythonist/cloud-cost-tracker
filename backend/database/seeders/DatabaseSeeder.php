<?php

namespace Database\Seeders;

use App\Models\BillingRecord;
use App\Models\BillingUpload;
use App\Models\Budget;
use App\Services\CsvParser;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds billing data from sample-data/sample-aws-billing.csv (same file
     * used by the verify script) plus example budgets for 2026-10.
     * Run: php artisan db:seed
     */
    public function run(): void
    {
        $csv = base_path('../sample-data/sample-aws-billing.csv');
        if (!is_file($csv)) {
            $this->command?->warn("Sample CSV not found at {$csv}; skipping billing seed.");
        } else {
            $parsed = (new CsvParser())->parse($csv);
            $upload = BillingUpload::create([
                'original_filename' => 'sample-aws-billing.csv',
                'stored_path' => 'billing-csv/sample-aws-billing.csv',
                'storage_disk' => 'local',
                'file_size_bytes' => filesize($csv),
                'format' => $parsed['detected_format'],
                'status' => 'completed',
                'rows_imported' => count($parsed['rows']),
                'rows_skipped' => count($parsed['skipped']),
                'total_cost' => round(array_sum(array_column($parsed['rows'], 'cost')), 2),
                'currency' => 'USD',
                'months_covered' => array_values(array_unique(array_column($parsed['rows'], 'month'))),
                'parse_report' => ['skipped_rows' => $parsed['skipped']],
            ]);
            foreach (array_chunk($parsed['rows'], 500) as $chunk) {
                BillingRecord::insert(array_map(fn ($r) => array_merge($r, [
                    'billing_upload_id' => $upload->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]), $chunk));
            }
        }

        // Example budgets for the latest sample month (2026-10, ~$1,124 total).
        // The overall budget is deliberately set so the sample data triggers
        // a warning, demonstrating the alert flow on first seed.
        Budget::firstOrCreate(
            ['period' => '2026-10', 'service' => null],
            ['amount' => 1200.00, 'currency' => 'USD']
        );
        Budget::firstOrCreate(
            ['period' => '2026-10', 'service' => 'EC2'],
            ['amount' => 600.00, 'currency' => 'USD']
        );
    }
}
