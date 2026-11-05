# Sample billing data

`sample-aws-billing.csv` is a synthetic AWS Cost and Usage Report (CUR)
export — 76 rows, Aug–Oct 2026, across EC2, S3, RDS, Lambda and
CloudFront, all USD, account id and tags redacted to placeholders.
It is **not real AWS billing data** and contains no real account
information.

Monthly totals (computed by `backend/scripts/verify-sample.php`):

| Month | Total | Notes |
|---|---|---|
| 2026-08 | $794.97 | EC2 $420.00 |
| 2026-09 | $949.99 | EC2 $539.99 (+28.6%) |
| 2026-10 | $1,124.02 | EC2 $690.01 (+27.8%) |

EC2 rises in both steps, so it is flagged by the increasing-cost
detection (over the 20% threshold, and 2 consecutive increases).
The seed budgets (overall $1,200, EC2 $600 for 2026-10) trigger a
warning (93.67% used) and an exceeded alert (115.00% used) respectively.

Try it: upload this file at `/upload` or run
`php backend/scripts/verify-sample.php`.
