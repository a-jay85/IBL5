---
description: Google Sheets export pushes from the server with no Google client library, sodium-encrypted refresh tokens, and a cron-drained refresh queue.
last_verified: 2026-09-30
---

# ADR-0151: Google Sheets Server-Push Export

**Status:** Accepted
**Date:** 2026-09-30
**Deciders:** Project owner

## Context

GMs want the player export in a Google Sheet they own, refreshed after every sim, without pasting an API key into a sheet formula or installing an Apps Script (the earlier Apps Script approach was removed). That needs a Google refresh token per GM stored server-side, HTTP calls to Google's OAuth and Sheets endpoints, and a trigger after each sim. The sim pipeline (`UpdaterService`) must never wait on or fail because of Google; ~30 GMs; the Sheets write quota is 300 requests/min/project. The repo has no Google client library, no encryption helper, and no prod-side cron consumer, but does have `ext-sodium` and `ext-curl` in its PHP image, a prod crontab, and a `Discord::sendCurlPOST` precedent for hand-rolled HTTP.

## Decision

Three linked choices. (1) No Google client library: five endpoints are called through `ext-curl` behind `GoogleHttpClientInterface`, giving a fake for record-and-replay tests and adding no composer dependency. (2) Refresh tokens are encrypted at rest with `ext-sodium` `crypto_secretbox` in a `v1.`-prefixed envelope, keyed by `GOOGLE_TOKEN_KEY` (32 bytes, base64) with `GOOGLE_TOKEN_KEY_PREVIOUS` for rotation; a missing, empty, or malformed key fails closed (feature disabled, logged once), a lost key marks every connection `broken` and GMs reconnect; the key never derives from any other secret. (3) Refresh is queued, not inline: an Updater step flips `refresh_pending` on active rows and a prod cron worker (`ibl5/scripts/googleSheetRefreshTick.php`, `*/5`, flock, 240 s budget) drains them one GM at a time, recording each outcome on the row; `invalid_grant` marks the row `broken` and is never rethrown.

## Alternatives Considered

- **Google's PHP client library.** A composer dependency that wraps the same endpoints. Rejected because the export needs five endpoints and the library would add a large dependency tree and no test seam.
- **Refresh inside the sim pipeline.** Call Google from `UpdaterService` after the sim. Rejected because the pipeline must never wait on or fail because of Google.
- **Apps Script or an `IMPORTDATA` key in the sheet.** Rejected because the Apps Script approach was already removed and the `?key=` flow keeps a secret inside the sheet. The `?key=` flow stays as the zero-setup alternative.

## Consequences

Refresh lands up to five minutes after a sim; the cron line and the Google Cloud project are manual, documented in `ibl5/docs/GOOGLE_SHEETS_SETUP.md`; adding a Google endpoint means one more hand-written call; rotating the key is a two-env-var procedure plus one `--all` run; the `?key=` IMPORTDATA flow stays as the zero-setup alternative.

## References

- `ibl5/docs/GOOGLE_SHEETS_SETUP.md`
- `ibl5/classes/GoogleSheets/README.md`
- `ibl5/scripts/googleSheetRefreshTick.php`
- `ibl5/migrations/190_google_sheet_connections.sql`
