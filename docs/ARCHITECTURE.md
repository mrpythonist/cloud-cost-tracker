# Architecture

## Data flow

```
AWS billing CSV (manual export)
        │  multipart POST /api/uploads, field `csv_file`
        ▼
UploadController
  ├─ validate: .csv, ≤ 10 MB (UploadCsvRequest)
  ├─ store file: local disk by default, S3 when FILESYSTEM_DISK=s3
  ├─ parse: CsvParser (framework-free, unit-tested with plain PHP)
  │    ├─ detect format: AWS CUR headers OR simple date,service,cost
  │    ├─ normalise ProductCode → service (AmazonEC2 → EC2)
  │    ├─ derive month (YYYY-MM) from the usage date
  │    └─ skip malformed rows, recording line + reason
  ├─ persist: BillingUpload (summary + parse report) + BillingRecord rows
  └─ re-check budgets for every month in the file (BudgetChecker)
        │  80% → warning alert, 100% → exceeded alert
        ▼
PostgreSQL: billing_uploads, billing_records, budgets, budget_alerts
        │
        ▼
ReportController / BudgetController / AlertController
  (CostAnalyzer does the aggregation on plain row arrays)
        │
        ▼
Next.js dashboard (browser → Laravel API, NEXT_PUBLIC_API_URL)
  / dashboard · /upload · /compare · /budgets
```

## Core classes

| Class | Responsibility |
|---|---|
| `App\Services\CsvParser` | CSV format detection, row parsing/validation, service normalisation, malformed-row reporting. No Laravel dependencies. |
| `App\Services\CostAnalyzer` | Monthly totals, per-service breakdown + share %, MoM comparison ($ and %), trends + increasing-cost detection. No Laravel dependencies. |
| `App\Services\BudgetChecker` | Budget evaluation: 80% warning / 100% exceeded, usage %, status. No Laravel dependencies. |
| `UploadController` | Upload validation, file storage, parse → persist, budget re-check after each upload, dedupe of unacknowledged alerts at the same level. |
| `ReportController` | `/reports/monthly`, `/reports/compare`, `/reports/trends`. |
| `BudgetController` / `AlertController` | Budget CRUD (with live actual/usage/status) and alert list / re-check / acknowledge. |

## API endpoints

| Method | Path | Description |
|---|---|---|
| GET | `/api/health` | Liveness check |
| POST | `/api/uploads` | Upload billing CSV (multipart `csv_file`) |
| GET | `/api/uploads` | Upload history (paginated) |
| GET | `/api/reports/monthly?month=YYYY-MM` | Total, currency, by-service, top services |
| GET | `/api/reports/compare?from=YYYY-MM&to=YYYY-MM` | Totals, delta $/%, per-service deltas |
| GET | `/api/reports/trends?months=3&threshold=20` | Per-service monthly series + increasing services |
| GET | `/api/budgets` | Budgets with actual spend, usage %, status |
| POST | `/api/budgets` | Create (period, service null = overall, amount) |
| PUT | `/api/budgets/{id}` | Update amount/currency |
| DELETE | `/api/budgets/{id}` | Delete |
| GET | `/api/alerts` | Alerts (filter by level / acknowledged) |
| POST | `/api/alerts/check` | Re-evaluate all budgets for months with data |
| PATCH | `/api/alerts/{id}/acknowledge` | Acknowledge an alert |

## CSV format expected

**AWS CUR (preferred)** — headers include at least `UsageStartDate`,
`ProductCode`, `UnblendedCost`. Optional: `UsageType`, `CurrencyCode`,
`Region`. Variants `lineItem/ProductCode`, `lineItem/UnblendedCost`
etc. are also recognised.

**Simple fallback** — `date,service,usage_type,cost,currency,region`
(only `date`, `service`, `cost` are required).

Rules: cost must be a non-negative number; dates must parse to a
`YYYY-MM` month; unknown ProductCodes are kept as-is (only known codes
are mapped to short names). Rows that fail a rule are skipped and listed
in the upload's parse report — they never fail the whole upload.

## S3 (optional)

`FILESYSTEM_DISK=local` (default) stores uploaded CSVs under
`storage/app/private/billing-csv/` and needs no AWS credentials.
Set `FILESYSTEM_DISK=s3` plus `AWS_ACCESS_KEY_ID`,
`AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET` to store
uploads in S3 instead (see `config/filesystems.php`). Parsing always
runs against a local temp copy, so the rest of the app is unchanged.

## Local run steps

See the README Quickstart. In short: `docker compose up --build`,
then `php artisan migrate --seed` in the backend container; or run
PostgreSQL via Compose and the backend (`php artisan serve`) and
frontend (`npm run dev`) locally.

## Deliberate limits (v1)

- Manual CSV uploads only — no live AWS Cost Explorer / CUR delivery
  integration, no scheduled sync.
- Single-user, no auth on the API — add authentication before exposing
  the API beyond localhost.
- No email/webhook delivery for alerts yet — alerts are records in the
  database, listed and acknowledged in the dashboard.
- One currency per upload is assumed in the summary; multi-currency
  files keep per-row currency but totals mix currencies (the sample data
  is all USD).
