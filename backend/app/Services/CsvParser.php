<?php

namespace App\Services;

/**
 * Parses AWS Cost and Usage Report (CUR) style billing CSV files, plus a
 * simple fallback header set for hand-made exports.
 *
 * CUR headers (subset): UsageStartDate, ProductCode, UsageType,
 * UnblendedCost, CurrencyCode, Region (also accepts `lineItem/ProductCode`,
 * `lineItem/UnblendedCost`, `bill/BillingPeriodStartDate` variants).
 * Simple headers: date, service, usage_type, cost, currency, region.
 *
 * Framework-free: no Laravel dependencies, so it can be unit-tested with
 * plain PHP. Returns normalised rows plus a count/reason for every skipped
 * row instead of failing the whole upload on one bad line.
 */
class CsvParser
{
    /** Map of known AWS ProductCode values to short display names. */
    public const SERVICE_MAP = [
        'AmazonEC2' => 'EC2',
        'AmazonS3' => 'S3',
        'AmazonRDS' => 'RDS',
        'AWSLambda' => 'Lambda',
        'Lambda' => 'Lambda',
        'CloudFront' => 'CloudFront',
        'AmazonCloudFront' => 'CloudFront',
        'AmazonDynamoDB' => 'DynamoDB',
        'AmazonElastiCache' => 'ElastiCache',
        'AmazonEKS' => 'EKS',
        'AmazonECS' => 'ECS',
        'AmazonVPC' => 'VPC',
        'AmazonRoute53' => 'Route 53',
        'AmazonSNS' => 'SNS',
        'AmazonSQS' => 'SQS',
        'AmazonCloudWatch' => 'CloudWatch',
        'AWSSecretsManager' => 'Secrets Manager',
        'AmazonKinesis' => 'Kinesis',
        'AWSELB' => 'ELB',
        'AmazonSES' => 'SES',
    ];

    public const MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB
    public const MAX_ROWS = 100_000;

    /**
     * @return array{rows: array<int, array{month: string, date: string, service: string, usage_type: string|null, cost: float, currency: string, region: string|null}>, skipped: array<int, array{line: int, reason: string}>, detected_format: string}
     */
    public function parse(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \InvalidArgumentException("CSV file not found or not readable: {$path}");
        }
        if (filesize($path) > self::MAX_FILE_SIZE_BYTES) {
            throw new \InvalidArgumentException('CSV file exceeds the 10 MB limit.');
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Could not open CSV file: {$path}");
        }

        $header = fgetcsv($handle);
        if ($header === false || $header === [null]) {
            fclose($handle);
            throw new \InvalidArgumentException('CSV file is empty or has no header row.');
        }
        $header = array_map(fn ($h) => trim((string) $h), $header);
        $map = $this->resolveColumns($header);
        if ($map === null) {
            fclose($handle);
            throw new \InvalidArgumentException(
                'Unrecognised CSV headers. Expected AWS CUR headers (UsageStartDate, ProductCode, UnblendedCost, ...) ' .
                'or simple headers (date, service, cost, ...). Got: ' . implode(', ', $header)
            );
        }

        $rows = [];
        $skipped = [];
        $line = 1; // header is line 1
        while (($data = fgetcsv($handle)) !== false) {
            $line++;
            if ($data === [null] || count(array_filter($data, fn ($v) => $v !== null && trim((string) $v) !== '')) === 0) {
                continue; // blank line
            }
            if (count($rows) + count($skipped) >= self::MAX_ROWS) {
                $skipped[] = ['line' => $line, 'reason' => 'Row limit (100,000) reached; remaining rows ignored.'];
                break;
            }
            $parsed = $this->parseRow($data, $map, $line);
            if (isset($parsed['error'])) {
                $skipped[] = ['line' => $line, 'reason' => $parsed['error']];
            } else {
                $rows[] = $parsed['row'];
            }
        }
        fclose($handle);

        return [
            'rows' => $rows,
            'skipped' => $skipped,
            'detected_format' => $map['format'],
        ];
    }

    /** @return array{format: string, date: int, service: int, usage_type: int|null, cost: int, currency: int|null, region: int|null}|null */
    private function resolveColumns(array $header): ?array
    {
        $idx = [];
        foreach ($header as $i => $name) {
            $idx[strtolower($name)] = $i;
        }
        $find = function (array $candidates) use ($idx): ?int {
            foreach ($candidates as $c) {
                if (array_key_exists(strtolower($c), $idx)) {
                    return $idx[strtolower($c)];
                }
            }
            return null;
        };

        // CUR format: needs at least a date, a product/service and a cost.
        $date = $find(['UsageStartDate', 'lineItem/UsageStartDate', 'bill/BillingPeriodStartDate']);
        $service = $find(['ProductCode', 'lineItem/ProductCode', 'product/ProductName']);
        $cost = $find(['UnblendedCost', 'lineItem/UnblendedCost', 'BlendedCost', 'lineItem/BlendedCost']);
        if ($date !== null && $service !== null && $cost !== null) {
            return [
                'format' => 'aws-cur',
                'date' => $date,
                'service' => $service,
                'usage_type' => $find(['UsageType', 'lineItem/UsageType']),
                'cost' => $cost,
                'currency' => $find(['CurrencyCode', 'lineItem/CurrencyCode', 'bill/CurrencyCode']),
                'region' => $find(['Region', 'product/Region', 'lineItem/AvailabilityZone']),
            ];
        }

        // Simple fallback format.
        $date = $find(['date', 'usage_date', 'billing_date']);
        $service = $find(['service', 'product_code']);
        $cost = $find(['cost', 'amount', 'unblended_cost']);
        if ($date !== null && $service !== null && $cost !== null) {
            return [
                'format' => 'simple',
                'date' => $date,
                'service' => $service,
                'usage_type' => $find(['usage_type']),
                'cost' => $cost,
                'currency' => $find(['currency', 'currency_code']),
                'region' => $find(['region']),
            ];
        }

        return null;
    }

    /** @return array{row: array}|array{error: string} */
    private function parseRow(array $data, array $map, int $line): array
    {
        $get = fn (?int $i) => $i === null ? null : (isset($data[$i]) ? trim((string) $data[$i]) : null);

        $rawDate = $get($map['date']);
        $rawService = $get($map['service']);
        $rawCost = $get($map['cost']);

        if ($rawDate === null || $rawDate === '') {
            return ['error' => 'Missing date.'];
        }
        if ($rawService === null || $rawService === '') {
            return ['error' => 'Missing service / ProductCode.'];
        }
        if ($rawCost === null || $rawCost === '' || !is_numeric($rawCost)) {
            return ['error' => "Cost is missing or not a number: '{$rawCost}'."];
        }
        $cost = (float) $rawCost;
        if ($cost < 0) {
            return ['error' => "Negative cost: {$rawCost}."];
        }

        $month = $this->extractMonth($rawDate);
        if ($month === null) {
            return ['error' => "Unparseable date: '{$rawDate}'."];
        }

        $currency = $get($map['currency']) ?: 'USD';
        if (!preg_match('/^[A-Z]{3}$/i', $currency)) {
            return ['error' => "Invalid currency code: '{$currency}'."];
        }

        return ['row' => [
            'month' => $month,
            'date' => $this->normaliseDate($rawDate),
            'service' => self::normaliseService($rawService),
            'usage_type' => $get($map['usage_type']),
            'cost' => round($cost, 4),
            'currency' => strtoupper($currency),
            'region' => $get($map['region']),
        ]];
    }

    /** Extract YYYY-MM from ISO dates, CUR timestamps, or YYYY-MM-DD strings. */
    private function extractMonth(string $raw): ?string
    {
        if (preg_match('/^(\d{4})-(\d{2})/', $raw, $m)) {
            $month = (int) $m[2];
            return ($month >= 1 && $month <= 12) ? "{$m[1]}-" . str_pad((string) $month, 2, '0', STR_PAD_LEFT) : null;
        }
        $ts = strtotime($raw);
        return $ts === false ? null : date('Y-m', $ts);
    }

    private function normaliseDate(string $raw): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw, $m)) {
            return $m[0];
        }
        $ts = strtotime($raw);
        return $ts === false ? $raw : date('Y-m-d', $ts);
    }

    public static function normaliseService(string $productCode): string
    {
        $code = trim($productCode);
        if (isset(self::SERVICE_MAP[$code])) {
            return self::SERVICE_MAP[$code];
        }
        // e.g. "Amazon Elastic Compute Cloud" -> keep as-is, trimmed.
        return $code;
    }
}
