---
description: Fire-and-forget product-analytics request logging to the ibl_events table, with traffic classification and domain-event instrumentation.
last_verified: 2026-07-25
---

# EventLog

Single repository class (`EventLogRepository`) that writes request events to the `ibl_events` database table for product analytics. The pattern is fire-and-forget: callers wrap writes in a `try/catch` and never rethrow on failure, so a logging error never disrupts the request. String fields are pre-truncated by the caller before the insert to avoid column-width violations.

## Columns (migration 157)

Four columns were added in migration 157:

- **`session_id`** (`VARCHAR(64) NULL`) — SHA-256 hash of `session_id()`. **NOT the raw session token** — the hash is one-way and cannot be replayed. Rotates on login (`session_regenerate_id(true)` at `classes/Auth/AuthService.php`), so one visit spanning a login produces two distinct hashes. `NULL` when no PHP session is active. Do not treat this as a stable visit key.
- **`http_status`** (`SMALLINT NULL`) — HTTP response code, captured at shutdown by `EventLogger::flush()`. `NULL` when the request died before shutdown or returned a code outside 100–599.
- **`traffic_class`** (`VARCHAR(32) NULL`) — Reporting label derived from the user agent and username. One of exactly five literals, evaluated in this order: `smoke-test`, `authenticated`, `crawler`, `spam`, `anonymous-human`. **NEVER use this column for authorization or rate-limiting** — it is a reporting label only, and an attacker can self-select into any class by crafting their user agent.
- **`action`** (`VARCHAR(64) NULL`) — Domain-event literal set by a controller via `EventLogger::setAction()`. Always a hardcoded PHP string literal at the call site, never a value derived from `$_POST`/`$_GET`. `NULL` for plain pageviews.

## Return contract

`EventLogRepository::insert()` now returns the new row id (`int`), or `0` when the write did not affect exactly one row. Callers that need to arm the shutdown flush must use this value — `getLastInsertId()` is `protected` on `BaseMysqliRepository` and is not reachable from outside the repository.

## Shutdown pattern

`RequestEventLoggingBootstrap::boot()` arms the outcome flush immediately after the insert:

```php
$eventId = (new EventLogRepository($db))->insert(/* ... */);
if ($eventId > 0) {
    EventLogger::arm($eventId, $db);
}
```

POST handlers set the domain event on their success path:

```php
EventLogger::setAction('trade_offer_submitted');
```

At shutdown, PHP calls `EventLogger::flush()` which issues `EventLogRepository::updateOutcome()`. The flush is silent (empty `catch`) and self-resetting via `finally`, so a logging failure can never surface to a user. It runs after `exit`/`die` and after `HtmxHelper::redirect()`, which is why 302 responses are captured.

## Adding a new domain event

Call `\EventLog\EventLogger::setAction('your_event')` on the **success path** of the POST handler, **before** any redirect or `exit`. Use a hardcoded snake_case literal ≤ 64 chars — **never** a value derived from `$_POST`/`$_GET`. The call must be placed *below* any CSRF or validation guard so failed attempts never inflate conversion metrics.
