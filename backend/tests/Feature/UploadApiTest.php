<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Requires a database (RefreshDatabase) and the Laravel test bootstrap.
 * NOT run by scripts/verify-sample.php — run with `vendor/bin/phpunit`
 * after `composer install` and migrations.
 */
class UploadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_parses_csv_and_returns_summary(): void
    {
        $csv = <<<'CSV'
            date,service,usage_type,cost,currency,region
            2026-10-01,EC2,EC2-Instance-Hours,100.00,USD,us-east-1
            2026-10-02,S3,S3-Storage-GB-Mo,20.00,USD,us-east-1
            CSV;
        $file = UploadedFile::fake()->createWithContent('billing.csv', $csv);

        $response = $this->postJson('/api/uploads', ['csv_file' => $file]);

        $response->assertCreated()
            ->assertJsonPath('rows_imported', 2)
            ->assertJsonPath('rows_skipped', 0)
            ->assertJsonPath('total_cost', 120.0)
            ->assertJsonPath('months', ['2026-10']);
        $this->assertDatabaseCount('billing_records', 2);
        $this->assertDatabaseCount('billing_uploads', 1);
    }

    public function test_upload_rejects_non_csv(): void
    {
        $file = UploadedFile::fake()->create('billing.pdf', 100, 'application/pdf');
        $this->postJson('/api/uploads', ['csv_file' => $file])->assertUnprocessable();
    }

    public function test_monthly_report_after_upload(): void
    {
        $csv = "date,service,cost\n2026-10-01,EC2,100.00\n2026-10-02,EC2,50.00\n";
        $file = UploadedFile::fake()->createWithContent('billing.csv', $csv);
        $this->postJson('/api/uploads', ['csv_file' => $file])->assertCreated();

        $this->getJson('/api/reports/monthly?month=2026-10')
            ->assertOk()
            ->assertJsonPath('total', 150.0)
            ->assertJsonPath('by_service.0.service', 'EC2');
    }

    public function test_budget_alert_created_when_upload_exceeds_budget(): void
    {
        $this->postJson('/api/budgets', [
            'period' => '2026-10', 'service' => null, 'amount' => 100.00,
        ])->assertCreated();

        $csv = "date,service,cost\n2026-10-01,EC2,150.00\n";
        $file = UploadedFile::fake()->createWithContent('billing.csv', $csv);
        $this->postJson('/api/uploads', ['csv_file' => $file])->assertCreated();

        $this->getJson('/api/alerts')->assertOk()
            ->assertJsonPath('data.0.level', 'exceeded');
    }
}
