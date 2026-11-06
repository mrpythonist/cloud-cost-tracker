# Contributing to Cloud Cost Tracker

Thanks for your interest. This is a small, focused project: a Laravel API
that ingests AWS billing CSVs plus a Next.js dashboard that reports on the
spend. Contributions that keep that scope tight are welcome.

## Setup

You need Docker and Docker Compose for the full stack, or PHP 8.2+ with
Composer and Node 20+ for local development. There is no live AWS account
needed: the sample data in `sample-data/` is synthetic.

```bash
git clone https://github.com/mrpythonist/cloud-cost-tracker.git
cd cloud-cost-tracker
cp .env.example .env
docker compose up --build
docker compose exec backend php artisan migrate --seed
```

For local development without Docker, see `docs/development.md`.

## Branches and commits

- Branch from `main`. Use a short descriptive name: `fix/upload-validation`,
  `feature/budget-export`, `docs/api-reference`.
- One logical change per pull request. Keep unrelated changes out.
- Write commits in the imperative present tense, for example
  `Fix month parsing for CUR timestamps` or `Add per-service budget filter`.
- Do not commit `.env` files, `vendor/`, `node_modules/`, `.next/`, uploaded
  CSVs, or any real AWS billing data. The sample CSV in `sample-data/` is
  synthetic and is the only billing data that belongs in the repo.

## Tests

Backend tests live in `backend/tests/` (PHPUnit). The framework-free
service classes (`CsvParser`, `CostAnalyzer`, `BudgetChecker`) are also
covered by the standalone script `backend/scripts/verify-sample.php`,
which runs without Composer or a database:

```bash
php backend/scripts/verify-sample.php
```

Run the full test suite when you have Composer dependencies installed:

```bash
cd backend
composer install
vendor/bin/phpunit
```

Frontend checks:

```bash
cd frontend
npm install
npm run typecheck
npm run build
```

A pull request should keep all of these green. If you cannot run a check
(for example, no Docker on your machine), say so in the pull request
description and state which checks you did run.

## Pull request process

1. Open an issue first for anything larger than a small fix, so the
   approach can be agreed before you write a lot of code.
2. Fill in the pull request template: what changed, why, how you tested it.
3. Update the docs (`README.md`, `docs/`) when behaviour, endpoints, or
   configuration change. Update `CHANGELOG.md` under the Unreleased
   section.
4. Keep the existing code style: PHP follows the Laravel conventions
   already in the codebase, TypeScript uses the existing patterns in
   `frontend/`.
5. Do not add new dependencies without a clear reason stated in the PR.
   Prefer the standard library and what is already installed.
6. A maintainer reviews every PR. Address review comments or explain why
   you disagree; either is fine.

## Reporting bugs

Use the bug report template in `.github/ISSUE_TEMPLATE/bug_report.yml`.
Include the steps to reproduce, what you expected, what happened, and the
relevant versions (PHP, Node, Docker, browser). If the bug involves a CSV
file, attach a redacted sample, never a real billing export.

## Code of conduct

This project follows the Contributor Covenant. See `CODE_OF_CONDUCT.md`.
Be direct, be respectful, and assume good intent.
