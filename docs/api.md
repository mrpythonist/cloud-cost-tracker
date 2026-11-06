# API Reference

Base URL: `http://localhost:8000/api`

All responses are JSON. There is no authentication in v0.1.0; the API is
meant to run on localhost or a private network. Do not expose it to the
public internet without adding authentication first.

Error responses use the shape `{"message": "..."}`. Validation errors
return 422, a missing or malformed month returns 404 on the monthly
report, and unknown IDs return 404 through Laravel route model binding.

## Health

### `GET /api/health`

Liveness check. Returns `{"status": "ok"}`.

## Uploads

### `POST /api/uploads`

Upload a billing CSV. Multipart form, field name `csv_file`. The file must
be a `.csv` and 10 MB or smaller.

```bash
curl -s -X POST http://localhost:8000/api/uploads \
  -F "csv_file=@../sample-data/sample-aws-billing.csv"
```

Success returns 201:

```json
{
  "upload": { "...": "BillingUpload record" },
  "rows_imported": 76,
  "rows_skipped": 0,
  "skipped_rows": [],
  "total_cost": 2868.98,
  "months": ["2026-08", "2026-09", "2026-10"],
  "alerts_created": 2
}
```

Malformed rows are skipped and listed in `skipped_rows` with `line` and
`reason`; they never fail the whole upload. A file with unrecognised
headers, or one that cannot be read, returns 422 with a message.

### `GET /api/uploads`

Upload history, newest first, paginated (20 per page, Laravel paginator
shape with `data`, `links`, and `meta`).

## Reports

### `GET /api/reports/monthly?month=YYYY-MM`

Total spend and per-service breakdown for one month. If `month` is
omitted, the latest month with data is used. Returns 404 when there is
no billing data yet or the month is not `YYYY-MM`.

```bash
curl -s 'http://localhost:8000/api/reports/monthly?month=2026-10'
```

Response:

```json
{
  "month": "2026-10",
  "total": 1124.02,
  "currency": "USD",
  "by_service": [
    { "service": "EC2", "total": 690.01, "share_percent": 61.39, "row_count": 6 }
  ],
  "top_services": [{ "service": "EC2", "total": 690.01 }]
}
```

### `GET /api/reports/compare?from=YYYY-MM&to=YYYY-MM`

Month-over-month comparison. Both query params are required and must be
`YYYY-MM`; otherwise 422.

```bash
curl -s 'http://localhost:8000/api/reports/compare?from=2026-09&to=2026-10'
```

Response:

```json
{
  "from": "2026-09",
  "to": "2026-10",
  "from_total": 949.99,
  "to_total": 1124.02,
  "delta": 174.03,
  "delta_percent": 18.32,
  "per_service": [
    {
      "service": "EC2",
      "from_total": 539.99,
      "to_total": 690.01,
      "delta": 150.02,
      "delta_percent": 27.78
    }
  ]
}
```

### `GET /api/reports/trends?months=3&threshold=20`

Per-service monthly series plus the services flagged as increasing cost.
`months` is clamped to 2-12 (default 3). `threshold` is the
month-over-month rise percent that flags a service (default 20, also
configurable with `COST_INCREASE_THRESHOLD_PERCENT`). A service is
flagged when its latest month is up more than the threshold against the
previous month, or when it rose in 2 or more consecutive months.

```bash
curl -s 'http://localhost:8000/api/reports/trends?months=3'
```

Response:

```json
{
  "months": ["2026-08", "2026-09", "2026-10"],
  "series": { "EC2": { "2026-08": 420.0, "2026-09": 539.99, "2026-10": 690.01 } },
  "increasing_cost_services": [
    {
      "service": "EC2",
      "reason": "up 27.78% vs 2026-09 (threshold 20%); rising for 2 consecutive months (539.99 -> 690.01)",
      "latest_month": "2026-10",
      "latest_total": 690.01,
      "previous_total": 539.99,
      "delta_percent": 27.78,
      "consecutive_increases": 2
    }
  ]
}
```

## Budgets

### `GET /api/budgets`

List budgets, ordered by period, with current actual spend, usage
percent, and status attached to each. Optional filter: `?period=YYYY-MM`.

```bash
curl -s http://localhost:8000/api/budgets
```

Each budget: `id`, `period`, `service` (null means overall), `amount`,
`currency`, `actual`, `usage_percent`, `status` (`ok`, `warning`, or
`exceeded`).

### `POST /api/budgets`

Create a budget. `period` must be `YYYY-MM`, `amount` must be at least
0.01, `service` is null for an overall budget or a service name such as
`EC2`. `currency` is an optional 3-letter code (default `USD`).

```bash
curl -s -X POST http://localhost:8000/api/budgets \
  -H 'Content-Type: application/json' \
  -d '{"period":"2026-11","service":null,"amount":1200}'

curl -s -X POST http://localhost:8000/api/budgets \
  -H 'Content-Type: application/json' \
  -d '{"period":"2026-11","service":"EC2","amount":600}'
```

Success returns 201 with the budget. Creating a second budget for the
same (period, service) pair returns 422; update it instead.

### `PUT /api/budgets/{id}`

Update `amount` and/or `currency`. Both are optional; `amount` must be
at least 0.01 when given.

```bash
curl -s -X PUT http://localhost:8000/api/budgets/1 \
  -H 'Content-Type: application/json' -d '{"amount":1500}'
```

### `DELETE /api/budgets/{id}`

Delete a budget. Returns `{"message": "Budget deleted."}`.

## Alerts

### `GET /api/alerts`

List alerts, newest first, paginated (20 per page). Filters:
`?level=warning|exceeded` and `?acknowledged=true|false`.

```bash
curl -s http://localhost:8000/api/alerts
curl -s 'http://localhost:8000/api/alerts?acknowledged=false'
```

Each alert: `id`, `budget_id`, `period`, `service`, `level`
(`warning` or `exceeded`), `threshold_percent`, `actual`,
`budget_amount`, `usage_percent`, `message`, `acknowledged_at`,
`created_at`.

### `POST /api/alerts/check`

Re-evaluate all budgets for every month that has billing data, creating
alerts where thresholds are crossed. Returns the number created and the
months checked:

```json
{ "alerts_created": 2, "months_checked": ["2026-08", "2026-09", "2026-10"] }
```

No duplicate is created when an unacknowledged alert at the same level
already exists for that budget and period.

### `PATCH /api/alerts/{id}/acknowledge`

Mark an alert as acknowledged. Returns the updated alert.

```bash
curl -s -X PATCH http://localhost:8000/api/alerts/1/acknowledge
```

## CSV format

Two formats are accepted, detected from the headers:

**AWS CUR (preferred).** Headers must include at least a date, a product
code, and a cost column: `UsageStartDate`, `ProductCode`, `UnblendedCost`.
Optional: `UsageType`, `CurrencyCode`, `Region`. The `lineItem/` variants
(`lineItem/ProductCode`, `lineItem/UnblendedCost`, and so on) are also
recognised, as are `BlendedCost` and `bill/BillingPeriodStartDate`.

**Simple fallback.** Headers `date,service,cost`, with optional
`usage_type`, `currency`, `region`. Aliases `usage_date`, `billing_date`,
`product_code`, `amount`, and `unblended_cost` are recognised.

Rules:

- Cost must be a non-negative number.
- The date must parse to a `YYYY-MM` month.
- Unknown ProductCodes are kept as-is; known codes are mapped to short
  names in `CsvParser::SERVICE_MAP` (for example `AmazonEC2` to `EC2`).
- Rows that fail a rule are skipped and reported; the upload still
  succeeds.
- Currency defaults to `USD` when the column is absent.
