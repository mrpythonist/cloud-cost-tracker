# Screenshots

There are no GUI screenshots here, and that is deliberate. This VM has no
headless browser, and the Laravel API cannot fully run here because
PostgreSQL is not available. Fabricating a dashboard screenshot would be
dishonest, so this folder holds genuine terminal captures instead: real
commands, run on 2026-11-06, with their real output.

## What each file is

| File | What it shows |
| --- | --- |
| `php-lint.txt` | `php -l` on all 30 PHP files in `backend/`. All clean. |
| `verify-sample.txt` | `php backend/scripts/verify-sample.php`: parses the 76-row sample CSV, checks monthly totals, month-over-month comparison, increasing-cost detection, and budget alerts. 19 checks pass, 0 fail. |
| `typescript-check.txt` | `npx tsc --noEmit` in `frontend/`. Exit code 0; empty output means no type errors. |
| `nextjs-build.txt` | `npm run build` in `frontend/`. Exit code 0; 4 routes (`/`, `/upload`, `/compare`, `/budgets`). |
| `dashboard-smoke-test.txt` | The production build served with `npm run start`, then `curl` against each dashboard route. All four return HTTP 200. The Laravel API was not running, so the pages return their shell and load data in the browser from the API. |

## What is not verified here

- The dashboard running against a live API with real data (needs Docker
  and PostgreSQL).
- `docker compose up` end to end, migrations against a live database, and
  `composer install` / PHPUnit (no Composer on this VM).
- An upload against a real S3 bucket.

See the Verification status section in the README for the full list.
