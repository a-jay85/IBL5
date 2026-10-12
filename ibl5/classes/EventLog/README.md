---
description: Fire-and-forget product-analytics request logging to the ibl_events table, with traffic classification and domain-event instrumentation.
last_verified: 2026-10-03
---

# EventLog

Single repository class (`EventLogRepository`) that writes request events to the `ibl_events` database table for product analytics. The pattern is fire-and-forget: callers wrap writes in a `try/catch` and never rethrow on failure, so a logging error never disrupts the request. String fields are pre-truncated by the caller before the insert to avoid column-width violations.

## Columns (migration 159)

Four columns were added in migration 159:

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

## Weekly review

`bin/events-review` is the only reader of `ibl_events` outside the backup workflow. launchd runs it Mondays at 09:17 on the owner's Mac. It reads the last 7 days through the ADR-0093 SELECT-only credential and builds a digest with these sections: traffic classes against the prior week, active GMs and an event-count histogram, top routes for logged-in GMs and for anonymous humans, domain actions, status classes, referer buckets, and a per-day trend. A tool-less `claude -p` run drafts 3 to 5 recommendations. The reply is defanged and sent to the owner only, through `bin/discord-dm`. Nothing is applied automatically. A week with fewer than 20 human events sends one line and spawns no model. The decision record is `ibl5/docs/decisions/0168-weekly-events-review.md`.

**Privacy contract.** The aggregate never selects `request_uri` or `user_agent`. It reduces `referer` to four literals: `none`, `internal`, `search`, `external`. `username` and `session_id` reach the output only as counts.

**Rendering rule for authors of new events.** An `action` literal must match `^[a-z][a-z0-9_]{0,47}$` or the digest folds it into `other`. A route renders only if a logged-in GM hit it that week. Every other route name folds into `other`, because `route_name` comes from `?name=` and anyone can mint one.

**Setup, in this order:**

1. On prod, confirm the ADR-0093 SELECT-only user can read `ibl_events`. The script's preflight fails loudly if it cannot.
2. Write that user's password to a mode-600 file outside the repo.
3. Export the four `EVENTS_REVIEW_*` variables: `EVENTS_REVIEW_SSH_HOST`, `EVENTS_REVIEW_DB_USER`, `EVENTS_REVIEW_DB_NAME`, `EVENTS_REVIEW_DB_PASSWORD_FILE`.
4. Run `bin/events-review --print-schedule` to inspect the plist, then `--install-schedule` from the main checkout.
5. Run `bin/events-review --dry-run` for an on-demand look with no model call and no DM.
