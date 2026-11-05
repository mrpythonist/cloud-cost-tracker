<?php

namespace Tests\Unit;

use App\Services\BudgetChecker;
use PHPUnit\Framework\TestCase;

class BudgetCheckerTest extends TestCase
{
    private BudgetChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new BudgetChecker();
    }

    public function test_no_alert_below_warning_threshold(): void
    {
        $alerts = $this->checker->check(
            [['id' => 1, 'period' => '2026-10', 'service' => null, 'amount' => 1000.0]],
            ['period' => '2026-10', 'overall' => 500.0, 'by_service' => []]
        );
        $this->assertSame([], $alerts);
    }

    public function test_warning_at_80_percent(): void
    {
        $alerts = $this->checker->check(
            [['id' => 1, 'period' => '2026-10', 'service' => null, 'amount' => 1000.0]],
            ['period' => '2026-10', 'overall' => 850.0, 'by_service' => []]
        );
        $this->assertCount(1, $alerts);
        $this->assertSame('warning', $alerts[0]['level']);
        $this->assertSame(85.0, $alerts[0]['usage_percent']);
    }

    public function test_exceeded_at_100_percent_and_per_service_budget(): void
    {
        $alerts = $this->checker->check(
            [
                ['id' => 1, 'period' => '2026-10', 'service' => null, 'amount' => 1000.0],
                ['id' => 2, 'period' => '2026-10', 'service' => 'EC2', 'amount' => 600.0],
            ],
            ['period' => '2026-10', 'overall' => 1124.0, 'by_service' => ['EC2' => 690.0]]
        );
        $this->assertCount(2, $alerts);
        $levels = array_column($alerts, 'level', 'service');
        $this->assertSame('exceeded', $levels['']); // overall: key '' is null service
        $this->assertSame('exceeded', $levels['EC2']);
    }

    public function test_budgets_for_other_periods_are_ignored(): void
    {
        $alerts = $this->checker->check(
            [['id' => 1, 'period' => '2026-09', 'service' => null, 'amount' => 10.0]],
            ['period' => '2026-10', 'overall' => 9999.0, 'by_service' => []]
        );
        $this->assertSame([], $alerts);
    }

    public function test_status_helper(): void
    {
        $this->assertSame('ok', BudgetChecker::status(100, 1000));
        $this->assertSame('warning', BudgetChecker::status(800, 1000));
        $this->assertSame('exceeded', BudgetChecker::status(1000, 1000));
    }
}
