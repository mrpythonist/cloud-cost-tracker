<?php

namespace Tests\Unit;

use App\Services\CostAnalyzer;
use PHPUnit\Framework\TestCase;

class CostAnalyzerTest extends TestCase
{
    private CostAnalyzer $analyzer;

    /** Rows: EC2 rises 100 -> 130 -> 170; S3 flat; RDS falls. */
    private array $rows = [
        ['month' => '2026-08', 'service' => 'EC2', 'cost' => 100.0],
        ['month' => '2026-08', 'service' => 'S3', 'cost' => 50.0],
        ['month' => '2026-08', 'service' => 'RDS', 'cost' => 80.0],
        ['month' => '2026-09', 'service' => 'EC2', 'cost' => 130.0],
        ['month' => '2026-09', 'service' => 'S3', 'cost' => 50.0],
        ['month' => '2026-09', 'service' => 'RDS', 'cost' => 70.0],
        ['month' => '2026-10', 'service' => 'EC2', 'cost' => 170.0],
        ['month' => '2026-10', 'service' => 'S3', 'cost' => 50.0],
        ['month' => '2026-10', 'service' => 'RDS', 'cost' => 60.0],
    ];

    protected function setUp(): void
    {
        $this->analyzer = new CostAnalyzer();
    }

    public function test_monthly_totals(): void
    {
        $this->assertSame(
            ['2026-08' => 230.0, '2026-09' => 250.0, '2026-10' => 280.0],
            $this->analyzer->monthlyTotals($this->rows)
        );
    }

    public function test_by_service_sorted_highest_first_with_share(): void
    {
        $by = $this->analyzer->byService($this->rows, '2026-10');
        $this->assertSame('EC2', $by[0]['service']);
        $this->assertSame(170.0, $by[0]['total']);
        $this->assertEqualsWithDelta(60.71, $by[0]['share_percent'], 0.01);
    }

    public function test_compare_months_delta_and_per_service(): void
    {
        $cmp = $this->analyzer->compareMonths($this->rows, '2026-09', '2026-10');
        $this->assertSame(250.0, $cmp['from_total']);
        $this->assertSame(280.0, $cmp['to_total']);
        $this->assertSame(30.0, $cmp['delta']);
        $this->assertSame(12.0, $cmp['delta_percent']);
        $ec2 = array_values(array_filter($cmp['per_service'], fn ($s) => $s['service'] === 'EC2'))[0];
        $this->assertSame(40.0, $ec2['delta']);
        $this->assertEqualsWithDelta(30.77, $ec2['delta_percent'], 0.01);
    }

    public function test_trends_flags_rising_service(): void
    {
        $trends = $this->analyzer->trends($this->rows, 3, 20.0);
        $flagged = array_column($trends['increasing_cost_services'], 'service');
        $this->assertContains('EC2', $flagged); // +30.8% then +30.8%: over threshold AND 2 consecutive rises
        $this->assertNotContains('S3', $flagged);
        $this->assertNotContains('RDS', $flagged);
        $ec2 = array_values(array_filter(
            $trends['increasing_cost_services'],
            fn ($s) => $s['service'] === 'EC2'
        ))[0];
        $this->assertSame(2, $ec2['consecutive_increases']);
    }
}
