<?php

namespace App\Services;

/**
 * Aggregates parsed billing rows into monthly totals, per-service
 * breakdowns, month-over-month comparisons and cost-increase detection.
 *
 * Framework-free: works on plain arrays (the normalised rows produced by
 * CsvParser), so it can be unit-tested with plain PHP.
 *
 * A "row" is: ['month' => 'YYYY-MM', 'service' => string, 'cost' => float, ...]
 */
class CostAnalyzer
{
    public const DEFAULT_INCREASE_THRESHOLD_PERCENT = 20.0;

    /** @param array<int, array{month: string, service: string, cost: float}> $rows */
    public function monthlyTotals(array $rows): array
    {
        $totals = [];
        foreach ($rows as $row) {
            $totals[$row['month']] = ($totals[$row['month']] ?? 0.0) + (float) $row['cost'];
        }
        ksort($totals);
        return array_map(fn ($v) => round($v, 2), $totals);
    }

    /**
     * Per-service totals for one month, sorted highest first.
     * @return array<int, array{service: string, total: float, share_percent: float, row_count: int}>
     */
    public function byService(array $rows, string $month): array
    {
        $byService = [];
        $total = 0.0;
        foreach ($rows as $row) {
            if ($row['month'] !== $month) {
                continue;
            }
            $byService[$row['service']]['total'] = ($byService[$row['service']]['total'] ?? 0.0) + (float) $row['cost'];
            $byService[$row['service']]['row_count'] = ($byService[$row['service']]['row_count'] ?? 0) + 1;
            $total += (float) $row['cost'];
        }
        $out = [];
        foreach ($byService as $service => $data) {
            $out[] = [
                'service' => $service,
                'total' => round($data['total'], 2),
                'share_percent' => $total > 0 ? round($data['total'] / $total * 100, 2) : 0.0,
                'row_count' => $data['row_count'],
            ];
        }
        usort($out, fn ($a, $b) => $b['total'] <=> $a['total']);
        return $out;
    }

    /** @return array<int, array{service: string, total: float}> Top N services for a month. */
    public function topServices(array $rows, string $month, int $limit = 5): array
    {
        return array_slice(array_map(
            fn ($s) => ['service' => $s['service'], 'total' => $s['total']],
            $this->byService($rows, $month)
        ), 0, $limit);
    }

    /**
     * Month-over-month comparison between two YYYY-MM months.
     * @return array{from: string, to: string, from_total: float, to_total: float, delta: float, delta_percent: float|null, per_service: array<int, array{service: string, from_total: float, to_total: float, delta: float, delta_percent: float|null}>}
     */
    public function compareMonths(array $rows, string $from, string $to): array
    {
        $totals = $this->monthlyTotals($rows);
        $fromTotal = $totals[$from] ?? 0.0;
        $toTotal = $totals[$to] ?? 0.0;
        $delta = round($toTotal - $fromTotal, 2);

        $fromBy = $this->indexByService($this->byService($rows, $from));
        $toBy = $this->indexByService($this->byService($rows, $to));
        $services = array_unique(array_merge(array_keys($fromBy), array_keys($toBy)));
        sort($services);
        $perService = [];
        foreach ($services as $service) {
            $f = $fromBy[$service] ?? 0.0;
            $t = $toBy[$service] ?? 0.0;
            $perService[] = [
                'service' => $service,
                'from_total' => $f,
                'to_total' => $t,
                'delta' => round($t - $f, 2),
                'delta_percent' => $f > 0 ? round(($t - $f) / $f * 100, 2) : ($t > 0 ? null : 0.0),
            ];
        }
        usort($perService, fn ($a, $b) => abs($b['delta']) <=> abs($a['delta']));

        return [
            'from' => $from,
            'to' => $to,
            'from_total' => $fromTotal,
            'to_total' => $toTotal,
            'delta' => $delta,
            'delta_percent' => $fromTotal > 0 ? round($delta / $fromTotal * 100, 2) : ($toTotal > 0 ? null : 0.0),
            'per_service' => $perService,
        ];
    }

    /**
     * Monthly series per service, plus the services whose cost is rising.
     * A service is flagged when EITHER:
     *  - its latest month is up by more than $thresholdPercent vs the
     *    previous month, OR
     *  - it rose in each of the last 2 consecutive month-over-month steps
     *    (a sustained upward trend, even below the threshold).
     *
     * @return array{months: string[], series: array<string, array<string, float>>, increasing_cost_services: array<int, array{service: string, reason: string, latest_month: string, latest_total: float, previous_total: float, delta_percent: float|null, consecutive_increases: int}>}
     */
    public function trends(array $rows, int $months = 3, float $thresholdPercent = self::DEFAULT_INCREASE_THRESHOLD_PERCENT): array
    {
        $totals = $this->monthlyTotals($rows);
        $allMonths = array_keys($totals);
        sort($allMonths);
        $monthsList = array_slice($allMonths, -$months);
        if (count($monthsList) < 2) {
            return ['months' => $monthsList, 'series' => $this->buildSeries($rows, $monthsList), 'increasing_cost_services' => []];
        }

        $series = $this->buildSeries($rows, $monthsList);
        $latest = end($monthsList);
        $previous = $monthsList[count($monthsList) - 2];

        $flagged = [];
        foreach ($series as $service => $perMonth) {
            $latestTotal = $perMonth[$latest] ?? 0.0;
            $prevTotal = $perMonth[$previous] ?? 0.0;
            if ($latestTotal <= 0) {
                continue;
            }
            $deltaPercent = $prevTotal > 0 ? round(($latestTotal - $prevTotal) / $prevTotal * 100, 2) : null;
            $consecutive = $this->countConsecutiveIncreases($perMonth, $monthsList);

            $reasons = [];
            if ($deltaPercent !== null && $deltaPercent > $thresholdPercent) {
                $reasons[] = "up {$deltaPercent}% vs {$previous} (threshold {$thresholdPercent}%)";
            }
            if ($consecutive >= 2) {
                $reasons[] = "rising for {$consecutive} consecutive months";
            }
            if ($reasons !== []) {
                $flagged[] = [
                    'service' => $service,
                    'reason' => implode('; ', $reasons),
                    'latest_month' => $latest,
                    'latest_total' => $latestTotal,
                    'previous_total' => $prevTotal,
                    'delta_percent' => $deltaPercent,
                    'consecutive_increases' => $consecutive,
                ];
            }
        }
        usort($flagged, fn ($a, $b) => ($b['delta_percent'] ?? 0) <=> ($a['delta_percent'] ?? 0));

        return ['months' => $monthsList, 'series' => $series, 'increasing_cost_services' => $flagged];
    }

    /** @return array<string, array<string, float>> service => [month => total] */
    private function buildSeries(array $rows, array $monthsList): array
    {
        $series = [];
        foreach ($rows as $row) {
            if (!in_array($row['month'], $monthsList, true)) {
                continue;
            }
            $series[$row['service']][$row['month']] = ($series[$row['service']][$row['month']] ?? 0.0) + (float) $row['cost'];
        }
        foreach ($series as $service => $perMonth) {
            foreach ($monthsList as $m) {
                $series[$service][$m] = round($perMonth[$m] ?? 0.0, 2);
            }
            ksort($series[$service]);
        }
        ksort($series);
        return $series;
    }

    /** Count trailing consecutive month-over-month increases in a series. */
    private function countConsecutiveIncreases(array $perMonth, array $monthsList): int
    {
        $count = 0;
        for ($i = count($monthsList) - 1; $i >= 1; $i--) {
            $cur = $perMonth[$monthsList[$i]] ?? 0.0;
            $prev = $perMonth[$monthsList[$i - 1]] ?? 0.0;
            if ($prev > 0 && $cur > $prev) {
                $count++;
            } else {
                break;
            }
        }
        return $count;
    }

    /** @param array<int, array{service: string, total: float}> $byService */
    private function indexByService(array $byService): array
    {
        $out = [];
        foreach ($byService as $s) {
            $out[$s['service']] = $s['total'];
        }
        return $out;
    }
}
