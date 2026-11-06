# Development

## Prerequisites

- Docker and Docker Compose (full stack), or:
- PHP 8.2 or newer with Composer, PostgreSQL 16, and Node 20+ with npm
- No AWS account is needed. The sample data in `sample-data/` is synthetic.

## Running with Docker Compose (recommended)

```bash
cp .env.example .env
# Edit .env and set DB_PASSWORD to something other than the placeholder.
docker compose up --build
docker compose exec backend php artisan migrate --seed
```

- API: http://localhost:8000 (`GET /api/health` to check)
- Dashboard: http://localhost:3000

The seed loads `sample-data/sample-aws-billing.csv` (76 rows, Aug-Oct 2026)
and creates example budgets for 2026-10 (overall $1,200, EC2 $600). Run
`POST /api/alerts/check`, or use the "Re-check budgets now" button in the
dashboard, to generate the warning and exceeded alerts.

Stop the stack with `docker compose down`. Add `-v` to remove the
PostgreSQL volume as well.

## Running locally without Docker

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

## Checks

### Backend

The framework-free services can be verified without Composer or a
database:

```bash
php backend/scripts/verify-sample.php
```

This parses the sample CSV and checks monthly totals, month-over-month
comparison, increasing-cost detection, and budget alerts. It should print
`19 passed, 0 failed` and exit 0.

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

The suite in `backend/tests/` has 14 unit tests for `CsvParser`,
`CostAnalyzer`, and `BudgetChecker`, plus 4 feature tests for the upload
API. Note: `composer install` and PHPUnit have not been run in every
environment; see the Verification status section in the README.

### Frontend

```bash
cd frontend
npm install
npm run typecheck   # tsc --noEmit
npm run build       # production build, 4 routes
npm run dev         # development server on :3000
```

`npm run lint` is defined in `package.json` (`next lint`).

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

## Working with the sample data

`sample-data/sample-aws-billing.csv` is a synthetic AWS Cost and Usage
Report export: 76 rows, Aug-Oct 2026, across EC2, S3, RDS, Lambda, and
CloudFront, all USD. Monthly totals are Aug $794.97, Sep $949.99,
Oct $1,124.02. EC2 rises in both steps, so it is flagged by the
increasing-cost detection. It contains no real account information.

Try it: upload the file at `/upload`, or run
`php backend/scripts/verify-sample.php`.

## Common tasks

- Add a service mapping: extend `CsvParser::SERVICE_MAP` in
  `backend/app/Services/CsvParser.php`. Unknown ProductCodes are kept
  as-is, so this is only needed for a shorter display name.
- Change alert thresholds: set `BUDGET_WARNING_THRESHOLD` and
  `BUDGET_EXCEEDED_THRESHOLD` in the backend `.env` (fractions, for
  example `0.80` and `1.00`).
- Change the increase-detection threshold: set
  `COST_INCREASE_THRESHOLD_PERCENT` in the backend `.env`.
- Re-check budgets after changing thresholds or budgets:
  `POST /api/alerts/check`.
