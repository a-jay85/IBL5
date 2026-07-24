---
description: Database migration runner, schema validator, and pending-migration tracking.
last_verified: 2026-07-24
---

# Migration

Manages database schema migrations. `MigrationRunner` compares available migration files against the tracking table and executes pending ones in order; PHP migrations run in a subprocess to guard against `exit()`/`die()` calls inside migration files. `MigrationFileResolver` discovers migration files on disk. `SchemaValidator` validates the live DB schema against expected column assertions using a batched `INFORMATION_SCHEMA` query, with `SchemaAssertion` and `SchemaValidationResult` as supporting value types.

| Class | Role |
|---|---|
| `MigrationRunner` | Detects and runs pending migrations in order |
| `MigrationFileResolver` | Discovers migration files on disk |
| `MigrationRepository` | Tracks which migrations have run |
| `SchemaValidator` | Validates live schema against column assertions |
