# Cloud Infrastructure Cost Tracker

A dashboard that helps developers understand and monitor their cloud
infrastructure expenses. Upload an AWS billing CSV (Cost and Usage Report
export), see spend broken down by service and month, compare months,
spot services whose costs are rising, set budgets, and get alerts when
spend approaches or exceeds them.

**Stack:** Laravel 11 (PHP 8.2+) API, PostgreSQL, Next.js 14 + TypeScript +
Tailwind dashboard, Docker Compose. S3 storage for uploaded CSVs is
optional — local disk is the default and no AWS credentials are needed to
run locally.

> This starts from **manually uploaded AWS billing CSV files**. There is no
> live AWS Cost Explorer / CUR S3 integration in this version — that is a
> documented next step, not a claim.

## Features

- Upload AWS billing CSV (CUR headers or a simple `date,service,cost`
  fallback). File type/size validated, upload recorded, rows parsed,
  malformed rows skipped and reported with line numbers and reasons.
- Expenses categorised by service — `AmazonEC2` → `EC2`, `AmazonS3` → `S3`,
  `AmazonRDS` → `RDS`, `AWSLambda` → `Lambda`, `CloudFront`, and more
  (see `CsvParser::SERVICE_MAP`).
- Monthly spending: total, per-service breakdown with share %, top services.
- Month-over-month comparison: change in $ and %, per service.
- Increasing-cost detection: flags a service when its latest month is up
  more than a configurable threshold (default 20%) vs the previous month,
  or when it rose in 2+ consecutive months.
- Spending budgets: monthly overall budget plus per-service budgets, by
  period (`YYYY-MM`).
- Budget alerts: 80% of budget → `warning`, 100% → `exceeded`. Alerts are
  created after every upload (and on demand via `POST /api/alerts/check`),
  listed in the dashboard, and can be acknowledged. Re-uploads do not
  create duplicate unacknowledged alerts at the same level.

## Architecture

```
 CSV upload ──▶ Laravel API :8000 ──▶ PostgreSQL
   (CUR or        │ parse (CsvParser)     billing_uploads
    simple)       │ aggregate (CostAnalyzer) billing_records
                  │ budgets (BudgetChecker)  budgets, budget_alerts
                  ▼
             Next.js dashboard :3000
             / (overview) /upload /compare /budgets
```

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for the data flow, the
API table, the expected CSV format, and how the optional S3 disk works.

## Quickstart

### With Docker Compose (recommended)

```bash
cp .env.example .env
# Edit .env: set DB_PASSWORD and (optionally) the AWS_* vars for S3 uploads.
docker compose up --build
# In another terminal, run migrations + seed the sample data once:
docker compose exec backend php artisan migrate --seed
```

- API: http://localhost:8000 (`/api/health` to check)
- Dashboard: http://localhost:3000

The seed loads `sample-data/sample-aws-billing.csv` (Aug–Oct 2026, EC2
rising month over month) and creates example budgets for 2026-10
(overall $1,200, EC2 $600) so the warning/exceeded alerts are visible
immediately. Run `POST /api/alerts/check` (or the "Re-check budgets now"
button) after seeding to generate them.

### Local development

```bash
# Start only PostgreSQL:
docker compose up -d postgres

# Backend (new terminal):
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve   # http://localhost:8000

# Dashboard (new terminal):
cd frontend
cp .env.example .env.local
npm install
npm run dev         # http://localhost:3000
```

## API reference

Base URL: `http://localhost:8000/api`

### Uploads

```bash
# Upload a billing CSV (multipart, field name: csv_file)
curl -s -X POST http://localhost:8000/api/uploads \
  -F "csv_file=@../sample-data/sample-aws-billing.csv" | jq .

# Upload history
curl -s http://localhost:8000/api/uploads | jq .
```

Response for an upload: `{upload, rows_imported, rows_skipped,
skipped_rows[], total_cost, months[], alerts_created}`.

### Reports

```bash
# Monthly spend
curl -s 'http://localhost:8000/api/reports/monthly?month=2026-10' | jq .

# Compare two months
curl -s 'http://localhost:8000/api/reports/compare?from=2026-09&to=2026-10' | jq .

# Trends (months=3, threshold=20% by default)
curl -s 'http://localhost:8000/api/reports/trends?months=3' | jq .
```

### Budgets & alerts

```bash
# List budgets (with current actual + usage % + status)
curl -s http://localhost:8000/api/budgets | jq .

# Overall monthly budget
curl -s -X POST http://localhost:8000/api/budgets \
  -H 'Content-Type: application/json' \
  -d '{"period":"2026-11","service":null,"amount":1200}'

# Per-service budget
curl -s -X POST http://localhost:8000/api/budgets \
  -H 'Content-Type: application/json' \
  -d '{"period":"2026-11","service":"EC2","amount":600}'

# Update / delete
curl -s -X PUT http://localhost:8000/api/budgets/1 \
  -H 'Content-Type: application/json' -d '{"amount":1500}'
curl -s -X DELETE http://localhost:8000/api/budgets/1

# Alerts
curl -s http://localhost:8000/api/alerts | jq .
curl -s -X POST http://localhost:8000/api/alerts/check | jq .
curl -s -X PATCH http://localhost:8000/api/alerts/1/acknowledge | jq .
```

## Configuration

| Variable | Used by | Default | Purpose |
|---|---|---|---|
| `DB_*` | backend | pgsql on 127.0.0.1:5432 | PostgreSQL connection |
| `FILESYSTEM_DISK` | backend | `local` | Where uploaded CSVs are stored; set `s3` for S3 |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_DEFAULT_REGION` / `AWS_BUCKET` | backend | — | Only needed when `FILESYSTEM_DISK=s3` |
| `COST_INCREASE_THRESHOLD_PERCENT` | backend | `20` | MoM % rise that flags a service |
| `BUDGET_WARNING_THRESHOLD` / `BUDGET_EXCEEDED_THRESHOLD` | backend | `0.80` / `1.00` | Alert thresholds as fractions |
| `NEXT_PUBLIC_API_URL` | frontend | `http://localhost:8000/api` | How the dashboard reaches the API |

## Verification status

VM-verified (no Docker daemon and no Composer in this environment, so
containers were not run and `composer install` was not run):

- `php -l` clean on every PHP file in `backend/`
- `php backend/scripts/verify-sample.php` — 19 checks pass: parses the
  76-row sample CSV (CUR format, 0 skipped), monthly totals
  Aug $794.97 / Sep $949.99 / Oct $1,124.02, Sep→Oct MoM +$174.03 (+18.32%),
  EC2 flagged as increasing (2 consecutive rises, +27.8%), budget alerts
  (overall $1,200 → warning at 93.67%, EC2 $600 → exceeded at 115%)
- `npm run build` clean for the frontend (Next.js 14, 4 routes: `/`,
  `/upload`, `/compare`, `/budgets`), and `npx tsc --noEmit` clean

Not verified here (flagged honestly, needs a machine with Docker and
Composer):

- `docker compose up` end-to-end, the migrations against a live
  PostgreSQL, and the seed against the live database
- `composer install` / `vendor/bin/phpunit` (the PHPUnit tests in
  `backend/tests/` — the same logic is covered by the standalone
  verify script above)
- An upload against a real S3 bucket (local disk is the verified path)

## Resume bullets

- Built a cloud cost tracker (Laravel 11, PostgreSQL, Next.js 14,
  TypeScript, Tailwind, Docker Compose) that ingests AWS billing CSVs,
  normalises ProductCodes to services, and reports monthly spend with
  per-service breakdowns and month-over-month comparisons
- Implemented cost-trend detection that flags services rising more than a
  configurable threshold (default 20%) or for 2+ consecutive months, plus
  monthly/per-service budgets with 80% warning and 100% exceeded alerts
  re-evaluated after every upload
- Shipped a dashboard with a monthly spend overview, SVG trend chart,
  CSV upload with parse reporting for malformed rows, and a budget/alert
  workspace; S3 upload storage is env-driven and optional, local disk is
  the default
