---
description: CI and maintenance utilities — test coverage checking, PHPStan baseline counting, and maintenance DB operations.
last_verified: 2026-07-24
---

# Maintenance

Internal tooling used by CI pipelines and maintenance scripts — there is no module entry point. `CoverageChecker` parses Clover XML to verify test coverage against a threshold; `CoverageComparator` detects regressions by comparing two `CoverageResult` values. `PhpstanBaselineCounter` counts PHPStan baseline entries by error identifier to track static-analysis debt. `MaintenanceRepository` provides database operations used by maintenance scripts (tradition updates, franchise history reconciliation, settings).

| Class | Role |
|---|---|
| `CoverageChecker` / `CoverageComparator` | Test coverage validation and regression detection |
| `CoverageResult` / `CoverageComparisonResult` | Value objects for coverage data |
| `PhpstanBaselineCounter` | Counts PHPStan baseline entries per error type |
| `MaintenanceRepository` | DB operations for maintenance scripts |
