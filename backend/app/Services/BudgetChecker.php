<?php

namespace App\Services;

/**
 * Evaluates spending budgets against actual monthly spend and produces
 * budget alerts.
 *
 * Thresholds: 80% of budget = "warning", 100% = "exceeded".
 * Framework-free: works on plain arrays so it can be unit-tested with
 * plain PHP. A "budget" is:
 *   ['id' => int, 'period' => 'YYYY-MM', 'service' => string|null,
 *    'amount' => float]  (service null = overall monthly budget)
 * Actuals are keyed the same way the analyzer produces them:
 *   ['period' => 'YYYY-MM', 'overall' => float, 'by_service' => [service => float]]
 */
class BudgetChecker
{
    public const WARNING_THRESHOLD = 0.80;
    public const EXCEEDED_THRESHOLD = 1.00;

    /**
     * @param array<int, array{id: int, period: string, service: string|null, amount: float}> $budgets
     * @param array{period: string, overall: float, by_service: array<string, float>} $actuals
     * @return array<int, array{budget_id: int, period: string, service: string|null, level: string, threshold_percent: int, actual: float, budget_amount: float, usage_percent: float, message: string}>
     */
    public function check(array $budgets, array $actuals): array
    {
        $alerts = [];
        foreach ($budgets as $budget) {
            if ($budget['period'] !== $actuals['period']) {
                continue;
            }
            $amount = (float) $budget['amount'];
            if ($amount <= 0) {
                continue;
            }
            $actual = $budget['service'] === null
                ? (float) $actuals['overall']
                : (float) ($actuals['by_service'][$budget['service']] ?? 0.0);

            $usage = $actual / $amount;
            $level = null;
            if ($usage >= self::EXCEEDED_THRESHOLD) {
                $level = 'exceeded';
            } elseif ($usage >= self::WARNING_THRESHOLD) {
                $level = 'warning';
            }
            if ($level === null) {
                continue;
            }

            $scope = $budget['service'] === null
                ? "Overall spend for {$budget['period']}"
                : "{$budget['service']} spend for {$budget['period']}";
            $alerts[] = [
                'budget_id' => (int) $budget['id'],
                'period' => $budget['period'],
                'service' => $budget['service'],
                'level' => $level,
                'threshold_percent' => $level === 'exceeded' ? 100 : 80,
                'actual' => round($actual, 2),
                'budget_amount' => round($amount, 2),
                'usage_percent' => round($usage * 100, 2),
                'message' => $level === 'exceeded'
                    ? "{$scope} is \${$actual} — over the \${$amount} budget (" . round($usage * 100, 1) . "% used)."
                    : "{$scope} is \${$actual} — " . round($usage * 100, 1) . "% of the \${$amount} budget.",
            ];
        }
        return $alerts;
    }

    /** Usage percent for display (0 when the budget amount is invalid). */
    public static function usagePercent(float $actual, float $budgetAmount): float
    {
        return $budgetAmount > 0 ? round($actual / $budgetAmount * 100, 2) : 0.0;
    }

    /** 'ok' | 'warning' | 'exceeded' for a single actual vs budget pair. */
    public static function status(float $actual, float $budgetAmount): string
    {
        if ($budgetAmount <= 0) {
            return 'ok';
        }
        $usage = $actual / $budgetAmount;
        if ($usage >= self::EXCEEDED_THRESHOLD) {
            return 'exceeded';
        }
        if ($usage >= self::WARNING_THRESHOLD) {
            return 'warning';
        }
        return 'ok';
    }
}
