# Cloud Cost Tracker

Upload an AWS billing CSV, see where the money goes, and get warned before
a budget is blown.

Cloud Cost Tracker ingests AWS Cost and Usage Report (CUR) exports, breaks
spend down by service and month, compares months, flags services whose
costs keep rising, and alerts you when spend approaches or passes a budget.

## The problem

AWS bills are hard to read. A CUR export has one row per usage line, with
ProductCodes like `AmazonEC2` and costs scattered across hundreds of lines.
Answering simple questions takes real work: how much did we spend last
month, which service grew, and are we about to exceed the budget?

## Why this exists

This project answers those three questions from a file you already have:
the billing CSV. No AWS account access, no Cost Explorer setup, no
credentials. Upload the export, and the dashboard shows monthly totals,
per-service breakdowns, month-over-month change, rising-cost flags, and
budget alerts.

It starts from manually uploaded CSV files. There is no live AWS Cost
Explorer or CUR S3 integration in this version. That is a documented next
step, not a claim. See Roadmap.

## Key features

- **CSV upload.** AWS CUR headers or a simple `date,service,cost`
  fallback. Files are validated (`.csv`, max 10 MB, max 100,000 rows).
  Malformed rows are skipped and reported with line numbers and reasons
  instead of failing the whole upload.
- **Service categorisation.** ProductCodes are normalised to short names:
  `AmazonEC2` to `EC2`, `AmazonS3` to `S3`, `AmazonRDS` to `RDS`,
  `AWSLambda` to `Lambda`, and more (see `CsvParser::SERVICE_MAP`).
  Unknown codes are kept as-is.
- **Monthly spend.** Totals, per-service breakdown with share percent,
  and top services for any month.
- **Month-over-month comparison.** Change in dollars and percent, overall
  and per service.
- **Increasing-cost detection.** A service is flagged when its latest
  month is up more than a configurable threshold (default 20%) against
  the previous month, or when it rose in 2 or more consecutive months.
- **Budgets.** Monthly budgets, overall or per service, by period
  (`YYYY-MM`).
- **Budget alerts.** 80% of a budget triggers a `warning`, 100% triggers
  `exceeded`. Alerts are created after every upload and on demand, listed
  in the dashboard, and can be acknowledged. Re-uploads do not create
  duplicate unacknowledged alerts at the same level.
- **Dashboard.** Four pages: overview, upload, month comparison, and
  budgets with alerts.
- **Optional S3 storage.** Uploaded CSVs are stored on local disk by
  default. Set `FILESYSTEM_DISK=s3` to store them in S3 instead. No AWS
  credentials are needed to run locally.

## Screenshots

There is no headless browser on the machine used to prepare this release,
and the API cannot fully run there (no PostgreSQL), so there are no GUI
screenshots. What exists instead, in `docs/screenshots/`, are genuine
terminal captures from 2026-11-06: `php -l` on all 30 PHP files,
`php backend/scripts/verify-sample.php` (19 checks pass), `npx tsc
--noEmit` (clean), `npm run build` (clean, 4 routes), and a `curl` smoke
test of the production dashboard build (all four pages return HTTP 200).
See `docs/screenshots/README.md` for what each file shows.

## Architecture

```
CSV upload --> Laravel API :8000 --> PostgreSQL
  (CUR or       parse (CsvParser)     billing_uploads
   simple)      aggregate (CostAnalyzer) billing_records
                budgets (BudgetChecker)  budgets, budget_alerts
                    |
                    v
               Next.js dashboard :3000
               / (overview) /upload /compare /budgets
```

The three service classes (`CsvParser`, `CostAnalyzer`, `BudgetChecker`)
are framework-free: they take plain arrays and return plain arrays. That
is why `backend/scripts/verify-sample.php` can exercise the core logic
without Composer or a database.

See `docs/architecture.md` for the upload flow, the data model, and the
deliberate limits. See `docs/ARCHITECTURE.md` for the original short
data-flow notes.

## Tech stack

| Layer | Technology |
| --- | --- |
| API | Laravel 11, PHP 8.2+ |
| Database | PostgreSQL 16 |
| Dashboard | Next.js 14, React 18, TypeScript 5, Tailwind CSS 3 |
| Tests | PHPUnit 11 (backend), standalone verify script |
| Deployment | Docker Compose (PostgreSQL, API, dashboard) |
| Storage | Local disk by default, S3 optional |

## Prerequisites

- Docker and Docker Compose (full stack), or PHP 8.2+ with Composer,
  PostgreSQL 16, and Node 20+ with npm for local development.
- No AWS account is needed. The sample data in `sample-data/` is
  synthetic.

## Installation

### With Docker Compose (recommended)

```bash
git clone https://github.com/mrpythonist/cloud-cost-tracker.git
cd cloud-cost-tracker
cp .env.example .env
# Edit .env: set DB_PASSWORD to something other than the placeholder.
docker compose up --build
```

In another terminal, run migrations and seed the sample data once:

```bash
docker compose exec backend php artisan migrate --seed
```

### Local development

Start only PostgreSQL:

```bash
docker compose up -d postgres
```

Backend (new terminal):

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve   # http://localhost:8000
```

Dashboard (new terminal):

```bash
cd frontend
cp .env.example .env.local
npm install
npm run dev         # http://localhost:3000
```

## Configuration

| Variable | Used by | Default | Purpose |
| --- | --- | --- | --- |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_PORT` | root `.env`, Compose | `cloud_cost_tracker`, `postgres`, `change-me`, `5432` | PostgreSQL connection |
| `APP_URL` | backend | `http://localhost:8000` | API base URL |
| `FILESYSTEM_DISK` | backend | `local` | Where uploaded CSVs are stored; set `s3` for S3 |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET` | backend | - | Only needed when `FILESYSTEM_DISK=s3` |
| `COST_INCREASE_THRESHOLD_PERCENT` | backend | `20` | Month-over-month rise percent that flags a service |
| `BUDGET_WARNING_THRESHOLD`, `BUDGET_EXCEEDED_THRESHOLD` | backend | `0.80`, `1.00` | Alert thresholds as fractions of the budget |
| `NEXT_PUBLIC_API_URL` | frontend | `http://localhost:8000/api` | How the dashboard reaches the API |

The backend and frontend each have their own `.env.example` with the full
variable lists. Never commit a real `.env` file.

## Quick start

1. Start the stack and seed it (see Installation).
2. Open http://localhost:3000. The overview shows Oct 2026: $1,124.02
   total, EC2 $690.01 (61.39%), with EC2 flagged as increasing.
3. Go to `/upload` and upload `sample-data/sample-aws-billing.csv` again
   to see the parse report, or upload your own CUR export.
4. Go to `/budgets` to see the seed budgets (overall $1,200, EC2 $600
   for 2026-10) and their alerts. Run `POST /api/alerts/check`, or use
   the "Re-check budgets now" button, to generate the alerts if they are
   not there yet.

## Usage

### Dashboard

- `/` - Overview for a month: total spend, month-over-month change,
  per-service breakdown, trend chart, and increasing-cost flags.
- `/upload` - Upload a billing CSV and see the parse report (rows
  imported, rows skipped with reasons) and the upload history.
- `/compare` - Pick two months and compare totals and per-service change.
- `/budgets` - Create and manage budgets, see usage percent and status,
  list alerts, acknowledge them, and re-check budgets on demand.

### API reference

Base URL: `http://localhost:8000/api`. Full reference with request and
response examples is in `docs/api.md`. Summary:

| Method | Path | Description |
| --- | --- | --- |
| GET | `/api/health` | Liveness check |
| POST | `/api/uploads` | Upload billing CSV (multipart `csv_file`) |
| GET | `/api/uploads` | Upload history (paginated) |
| GET | `/api/reports/monthly?month=YYYY-MM` | Total, currency, by-service, top services |
| GET | `/api/reports/compare?from=YYYY-MM&to=YYYY-MM` | Totals, delta $/%, per-service deltas |
| GET | `/api/reports/trends?months=3&threshold=20` | Per-service monthly series, increasing services |
| GET | `/api/budgets` | Budgets with actual spend, usage %, status |
| POST | `/api/budgets` | Create (period, service null = overall, amount) |
| PUT | `/api/budgets/{id}` | Update amount/currency |
| DELETE | `/api/budgets/{id}` | Delete |
| GET | `/api/alerts` | Alerts (filter by level, acknowledged) |
| POST | `/api/alerts/check` | Re-evaluate all budgets for months with data |
| PATCH | `/api/alerts/{id}/acknowledge` | Acknowledge an alert |

Example:

```bash
# Upload the sample billing CSV
curl -s -X POST http://localhost:8000/api/uploads \
  -F "csv_file=@sample-data/sample-aws-billing.csv"

# Monthly spend
curl -s 'http://localhost:8000/api/reports/monthly?month=2026-10'

# Compare two months
curl -s 'http://localhost:8000/api/reports/compare?from=2026-09&to=2026-10'

# Create an overall monthly budget
curl -s -X POST http://localhost:8000/api/budgets \
  -H 'Content-Type: application/json' \
  -d '{"period":"2026-11","service":null,"amount":1200}'
```

### CSV format

**AWS CUR (preferred).** Headers include at least `UsageStartDate`,
`ProductCode`, `UnblendedCost`. Optional: `UsageType`, `CurrencyCode`,
`Region`. The `lineItem/` variants are also recognised.

**Simple fallback.** `date,service,cost`, with optional `usage_type`,
`currency`, `region`.

Rules: cost must be a non-negative number, dates must parse to a
`YYYY-MM` month, unknown ProductCodes are kept as-is. Rows that fail a
rule are skipped and listed in the parse report. See `docs/api.md` and
`sample-data/README.md`.

## Project structure

```
.
├── backend/                 Laravel 11 API
│   ├── app/Http/Controllers/Api/   Upload, Report, Budget, Alert controllers
│   ├── app/Http/Requests/   UploadCsvRequest (file validation)
│   ├── app/Models/          BillingUpload, BillingRecord, Budget, BudgetAlert
│   ├── app/Services/        CsvParser, CostAnalyzer, BudgetChecker
│   ├── config/              cost-tracker.php, filesystems.php
│   ├── database/migrations/ 4 tables
│   ├── database/seeders/    Loads sample-data/sample-aws-billing.csv
│   ├── routes/api.php       All API routes
│   ├── scripts/verify-sample.php   Standalone verification, no vendor/ needed
│   └── tests/               PHPUnit: Unit (14 tests) + Feature (4 tests)
├── frontend/                Next.js 14 + TypeScript + Tailwind dashboard
│   ├── app/                 /, /upload, /compare, /budgets pages
│   ├── components/          SpendCard, MonthlyChart, ServiceBreakdown, AlertList, MonthPicker
│   └── lib/                 api.ts (typed API client), format.ts
├── sample-data/             Synthetic AWS CUR export (76 rows) + README
├── docs/                    Architecture, development, API docs, screenshots
└── docker-compose.yml       postgres + backend + frontend
```

## Testing

Backend core logic, without Composer or a database:

```bash
php backend/scripts/verify-sample.php
```

This parses the sample CSV and checks monthly totals (Aug $794.97,
Sep $949.99, Oct $1,124.02), month-over-month comparison, increasing-cost
detection, and budget alerts. It should print `19 passed, 0 failed`.

Syntax-check every PHP file:

```bash
find backend -name "*.php" -exec php -l {} \;
```

Full test suite (needs Composer dependencies installed):

```bash
cd backend
composer install
vendor/bin/phpunit
```

Frontend:

```bash
cd frontend
npm install
npm run typecheck   # tsc --noEmit
npm run build       # production build, 4 routes
```

## Verification status

Verified on 2026-11-06 on a VM with PHP 8.3 CLI, Node 24, and npm (no
Docker daemon, no Composer, no PostgreSQL):

- `php -l` clean on all 30 PHP files in `backend/`
- `php backend/scripts/verify-sample.php`: 19 checks pass, 0 fail
- `npx tsc --noEmit` clean for the frontend
- `npm run build` clean: 4 routes (`/`, `/upload`, `/compare`, `/budgets`)
- Production dashboard build served with `npm run start` returns HTTP 200
  on all four routes (API not running, so pages return their shell and
  load data in the browser). Captures are in `docs/screenshots/`.

Not verified here (flagged honestly, needs a machine with Docker and
Composer):

- `docker compose up` end to end, the migrations against a live
  PostgreSQL, and the seed against the live database
- `composer install` and `vendor/bin/phpunit` (the PHPUnit tests in
  `backend/tests/`; the same logic is covered by the standalone verify
  script above)
- An upload against a real S3 bucket (local disk is the verified path)

Note: `npm install` reports that `next@14.2.5` has a known security
vulnerability and should be upgraded to a patched version. Upgrading
Next.js is on the roadmap.

## Deployment

The supported deployment is Docker Compose: PostgreSQL, the Laravel API,
and the Next.js dashboard as three services. See `docker-compose.yml`.
There is no hosted deployment of this project.

Before deploying anywhere beyond localhost:

1. Set a strong `DB_PASSWORD` and a real `APP_KEY`
   (`php artisan key:generate`).
2. Add authentication to the API. There is none in v0.1.0.
3. Serve the API over HTTPS behind a reverse proxy. Do not expose the
   API port directly to the internet.
4. Decide on file storage: local disk needs a persistent volume for
   `storage/`; S3 needs the `AWS_*` variables and a bucket with least
   privilege access.

## Security

- The API has no authentication in v0.1.0. It is a single-user app meant
  to run on localhost or a private network.
- Uploaded CSVs can contain real billing data. Never share a real AWS
  billing export in an issue or a pull request. Use the synthetic file in
  `sample-data/` or a redacted sample.
- Keep `.env` files out of version control. Only `.env.example` files
  belong in the repo.
- To report a vulnerability, see `SECURITY.md`. Do not open a public
  issue for a security problem.

## Roadmap

In rough order, drawn from the deliberate limits in
`docs/architecture.md`:

- Live AWS integration: read CUR files from S3 on a schedule instead of
  manual uploads.
- Authentication for the API (API tokens or session auth) so it can be
  exposed beyond localhost.
- Alert delivery: email or webhook notifications when a budget threshold
  is crossed, instead of dashboard-only alerts.
- Multi-currency totals: per-currency reporting instead of mixing
  currencies in one total.
- Upgrade Next.js from 14.2.5 to a patched version (see Verification
  status).
- CI: enable the prepared workflow in `.github/workflows/ci.yml` and add
  a verified PHPUnit job once `composer install` and the migrations have
  been run against a live database.

## Contributing

See `CONTRIBUTING.md`. In short: branch from `main`, one logical change
per pull request, keep the checks in Testing green (or say which ones you
could not run), and update the docs and `CHANGELOG.md` when behaviour
changes.

## License

MIT. See `LICENSE`.
