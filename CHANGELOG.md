# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

- Open-source readiness files: LICENSE, CONTRIBUTING.md, CODE_OF_CONDUCT.md,
  SECURITY.md, SUPPORT.md, issue and pull request templates, and expanded
  documentation in `docs/`.

## [0.1.0] - 2026-11-05

Initial commit (`0af170f`). There is no tagged GitHub release yet; 0.1.0
here means the first public commit on `main`, not a published release.

### Added

- Laravel 11 API with PostgreSQL storage for AWS billing data.
- CSV upload endpoint accepting AWS Cost and Usage Report (CUR) headers and
  a simple `date,service,cost` fallback format. Malformed rows are skipped
  and reported with line numbers and reasons instead of failing the upload.
- ProductCode normalisation to short service names (AmazonEC2 to EC2,
  AmazonS3 to S3, and more; see `CsvParser::SERVICE_MAP`).
- Monthly spend reporting: totals, per-service breakdown with share
  percentages, and top services.
- Month-over-month comparison in dollars and percent, per service.
- Increasing-cost detection: a service is flagged when its latest month is
  up more than a configurable threshold (default 20%) against the previous
  month, or when it rose in 2 or more consecutive months.
- Monthly budgets, overall and per service, by period (`YYYY-MM`).
- Budget alerts at 80% (warning) and 100% (exceeded) of a budget, created
  after every upload and on demand, with acknowledgement and duplicate
  suppression for unacknowledged alerts at the same level.
- Next.js 14 + TypeScript + Tailwind dashboard with four pages: overview,
  upload, month comparison, and budgets/alerts.
- Docker Compose setup for PostgreSQL, the Laravel API, and the dashboard.
- Optional S3 storage for uploaded CSVs; local disk is the default.
- Synthetic sample billing data (76 rows, Aug-Oct 2026) and a database
  seeder that loads it with example budgets.
- Standalone verification script `backend/scripts/verify-sample.php` that
  runs the core logic without Composer or a database.

[Unreleased]: https://github.com/mrpythonist/cloud-cost-tracker/compare/0af170f...HEAD
