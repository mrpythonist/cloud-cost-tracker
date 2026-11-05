<?php
/**
 * Standalone verification — no Laravel bootstrap, no database, no vendor/.
 * Loads the framework-free service classes directly and:
 *   1. Parses sample-data/sample-aws-billing.csv
 *   2. Computes monthly totals, per-service breakdown, MoM comparison
 *   3. Detects increasing-cost services
 *   4. Evaluates budgets (seed budgets: $1,200 overall, $600 EC2 for 2026-10)
 *   5. Runs assertion checks mirroring the PHPUnit tests in tests/Unit/
 *
 * Usage: php scripts/verify-sample.php
 * Exit code 0 = all checks passed.
 */

declare(strict_types=1);

require __DIR__ . '/../app/Services/CsvParser.php';
require __DIR__ . '/../app/Services/CostAnalyzer.php';
require __DIR__ . '/../app/Services/BudgetChecker.php';

use App\Services\BudgetChecker;
use App\Services\CostAnalyzer;
use App\Services\CsvParser;

$passed = 0;
$failed = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  PASS  {$name}\n";
    } else {
        $failed++;
        echo "  FAIL  {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

$csv = dirname(__DIR__, 2) . '/sample-data/sample-aws-billing.csv';
if (!is_file($csv)) {
    $csv = __DIR__ . '/../../sample-data/sample-aws-billing.csv';
}

echo "== 1. Parse sample CSV ==\n";
$parser = new CsvParser();
$parsed = $parser->parse($csv);
$rows = $parsed['rows'];
echo "  format: {$parsed['detected_format']}, imported: " . count($rows) . ", skipped: " . count($parsed['skipped']) . "\n";
check('CUR format detected', $parsed['detected_format'] === 'aws-cur');
check('76 rows imported', count($rows) === 76, 'got ' . count($rows));
check('0 rows skipped', count($parsed['skipped']) === 0);
check('AmazonEC2 normalised to EC2', in_array('EC2', array_column($rows, 'service'), true));
check('months covered are 2026-08..2026-10', array_values(array_unique(array_column($rows, 'month'))) === ['2026-08', '2026-09', '2026-10']);

// Malformed-row handling (mirrors CsvParserTest)
$tmp = tempnam(sys_get_temp_dir(), 'billing') . '.csv';
file_put_contents($tmp, "date,service,cost\n2026-09-15,EC2,10.00\nnot-a-date,EC2,10.00\n2026-09-15,EC2,not-a-number\n2026-09-15,,10.00\n");
$bad = $parser->parse($tmp);
check('malformed rows skipped + reported (1 good, 3 skipped)', count($bad['rows']) === 1 && count($bad['skipped']) === 3);
unlink($tmp);

echo "== 2. Monthly totals + per-service breakdown ==\n";
$analyzer = new CostAnalyzer();
$totals = $analyzer->monthlyTotals($rows);
foreach ($totals as $month => $total) {
    echo "  {$month}: \${$total}\n";
}
check('Aug 2026 total is $794.97', abs(($totals['2026-08'] ?? 0) - 794.97) < 0.01);
check('Sep 2026 total is $949.99', abs(($totals['2026-09'] ?? 0) - 949.99) < 0.01);
check('Oct 2026 total is $1,124.02', abs(($totals['2026-10'] ?? 0) - 1124.02) < 0.01);
$oct = $analyzer->byService($rows, '2026-10');
echo "  Oct by service: " . implode(', ', array_map(fn ($s) => "{$s['service']} \${$s['total']} ({$s['share_percent']}%)", $oct)) . "\n";
check('EC2 is top service in Oct', $oct[0]['service'] === 'EC2' && abs($oct[0]['total'] - 690.01) < 0.01);

echo "== 3. Month-over-month comparison (Sep -> Oct) ==\n";
$cmp = $analyzer->compareMonths($rows, '2026-09', '2026-10');
echo "  {$cmp['from_total']} -> {$cmp['to_total']} (delta \${$cmp['delta']}, {$cmp['delta_percent']}%)\n";
check('MoM delta is +$174.03', abs($cmp['delta'] - 174.03) < 0.01);
check('MoM delta percent is +18.32%', abs(($cmp['delta_percent'] ?? 0) - 18.32) < 0.05);
$ec2Cmp = array_values(array_filter($cmp['per_service'], fn ($s) => $s['service'] === 'EC2'))[0];
check('EC2 MoM delta is +$150.02', abs($ec2Cmp['delta'] - 150.02) < 0.01);

echo "== 4. Increasing-cost detection (threshold 20%) ==\n";
$trends = $analyzer->trends($rows, 3, 20.0);
foreach ($trends['increasing_cost_services'] as $f) {
    echo "  FLAGGED {$f['service']}: {$f['reason']} ({$f['previous_total']} -> {$f['latest_total']})\n";
}
$flagged = array_column($trends['increasing_cost_services'], 'service');
check('EC2 flagged as increasing', in_array('EC2', $flagged, true));
check('S3 not flagged', !in_array('S3', $flagged, true));
$ec2Flag = array_values(array_filter($trends['increasing_cost_services'], fn ($s) => $s['service'] === 'EC2'))[0] ?? null;
check('EC2 has 2 consecutive increases', ($ec2Flag['consecutive_increases'] ?? 0) === 2);

echo "== 5. Budget alerts (seed budgets: overall \$1,200, EC2 \$600, period 2026-10) ==\n";
$checker = new BudgetChecker();
$byServiceOct = array_column($oct, 'total', 'service');
$alerts = $checker->check(
    [
        ['id' => 1, 'period' => '2026-10', 'service' => null, 'amount' => 1200.00],
        ['id' => 2, 'period' => '2026-10', 'service' => 'EC2', 'amount' => 600.00],
    ],
    ['period' => '2026-10', 'overall' => $totals['2026-10'], 'by_service' => $byServiceOct]
);
foreach ($alerts as $a) {
    echo "  {$a['level']}: {$a['message']}\n";
}
$levels = array_column($alerts, 'level', 'service');
check('overall budget triggers warning at 93.67% usage', ($levels[''] ?? null) === 'warning');
check('EC2 budget triggers exceeded at 115% usage', ($levels['EC2'] ?? null) === 'exceeded');
check('no alert below 80% threshold', $checker->check(
    [['id' => 1, 'period' => '2026-10', 'service' => null, 'amount' => 1000.0]],
    ['period' => '2026-10', 'overall' => 500.0, 'by_service' => []]
) === []);

echo "\n{$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
