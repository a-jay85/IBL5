# WideUnit Tests

These tests exercise multi-class workflows but use `MockDatabase` rather than a real MariaDB connection. They are wide unit tests, not integration tests.

For real-database integration tests, see `tests/DatabaseIntegration/`.

## When to write a WideUnit test
- The workflow spans 3+ classes but the database layer is well-covered elsewhere.
- You want fast iteration on business logic without seed-data overhead.

## When to write a DatabaseIntegration test
- The workflow's correctness depends on actual DB state (FKs, constraints, generated columns, triggers).
- You want to catch mock/prod divergence (see saved incident: FA migration regression).
