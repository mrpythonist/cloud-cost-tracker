<?php

namespace Tests\Unit;

use App\Services\CsvParser;
use PHPUnit\Framework\TestCase;

class CsvParserTest extends TestCase
{
    private CsvParser $parser;

    protected function setUp(): void
    {
        $this->parser = new CsvParser();
    }

    private function tmpCsv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'billing') . '.csv';
        file_put_contents($path, $content);
        return $path;
    }

    public function test_parses_aws_cur_headers_and_normalises_service_names(): void
    {
        $path = $this->tmpCsv(<<<'CSV'
            UsageStartDate,UsageEndDate,ProductCode,UsageType,UnblendedCost,CurrencyCode,Region
            2026-10-01T00:00:00Z,2026-10-02T00:00:00Z,AmazonEC2,EC2-Instance-Hours,12.50,USD,us-east-1
            2026-10-01T00:00:00Z,2026-10-02T00:00:00Z,AmazonS3,S3-Storage-GB-Mo,3.20,USD,us-east-1
            CSV);
        $result = $this->parser->parse($path);
        $this->assertSame('aws-cur', $result['detected_format']);
        $this->assertCount(2, $result['rows']);
        $this->assertSame('EC2', $result['rows'][0]['service']);
        $this->assertSame('S3', $result['rows'][1]['service']);
        $this->assertSame('2026-10', $result['rows'][0]['month']);
        $this->assertSame(12.50, $result['rows'][0]['cost']);
        $this->assertSame([], $result['skipped']);
    }

    public function test_parses_simple_fallback_headers(): void
    {
        $path = $this->tmpCsv(<<<'CSV'
            date,service,usage_type,cost,currency,region
            2026-09-15,EC2,EC2-Instance-Hours,40.00,USD,us-east-1
            CSV);
        $result = $this->parser->parse($path);
        $this->assertSame('simple', $result['detected_format']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame('EC2', $result['rows'][0]['service']);
    }

    public function test_skips_malformed_rows_and_reports_them(): void
    {
        $path = $this->tmpCsv(<<<'CSV'
            date,service,cost
            2026-09-15,EC2,10.00
            not-a-date,EC2,10.00
            2026-09-15,EC2,not-a-number
            2026-09-15,,10.00
            2026-09-15,EC2,-5.00
            CSV);
        $result = $this->parser->parse($path);
        $this->assertCount(1, $result['rows']);
        $this->assertCount(4, $result['skipped']);
        $this->assertSame(3, $result['skipped'][0]['line']); // bad date
    }

    public function test_rejects_unrecognised_headers(): void
    {
        $path = $this->tmpCsv("foo,bar,baz\n1,2,3\n");
        $this->expectException(\InvalidArgumentException::class);
        $this->parser->parse($path);
    }

    public function test_normalise_service_maps_known_product_codes(): void
    {
        $this->assertSame('EC2', CsvParser::normaliseService('AmazonEC2'));
        $this->assertSame('RDS', CsvParser::normaliseService('AmazonRDS'));
        $this->assertSame('Lambda', CsvParser::normaliseService('AWSLambda'));
        $this->assertSame('UnknownService', CsvParser::normaliseService('UnknownService'));
    }
}
