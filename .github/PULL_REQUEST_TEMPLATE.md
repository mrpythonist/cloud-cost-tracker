## What changed

<!-- Describe the change in a few sentences. Link the issue it closes, if any: Fixes #123 -->

## Why

<!-- The problem this solves, or the reason for the change. -->

## How I tested it

<!-- List the exact commands you ran and their results. Example: -->

- [ ] `php backend/scripts/verify-sample.php` (19 checks pass)
- [ ] `cd backend && vendor/bin/phpunit` (not run: no Composer on this machine)
- [ ] `cd frontend && npm run typecheck`
- [ ] `cd frontend && npm run build`
- [ ] Manual check: <!-- what you clicked or called, and what you saw -->

## Checklist

- [ ] I updated the docs (`README.md` or `docs/`) if behaviour, endpoints, or config changed
- [ ] I added an entry to `CHANGELOG.md` under Unreleased
- [ ] I did not commit `.env` files, `vendor/`, `node_modules/`, `.next/`, or real billing data
- [ ] Existing tests still pass (or I explained why a check could not run)
