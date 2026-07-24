---
description: Fire-and-forget product-analytics request logging to the ibl_events table.
last_verified: 2026-07-24
---

# EventLog

Single repository class (`EventLogRepository`) that writes request events to the `ibl_events` database table for product analytics. The pattern is fire-and-forget: callers wrap writes in a `try/catch` and never rethrow on failure, so a logging error never disrupts the request. String fields are pre-truncated by the caller before the insert to avoid column-width violations.
