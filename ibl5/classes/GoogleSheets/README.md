---
description: Google Sheets player-export module. Class map, the refreshConnection() outcome contract, and pointers to the setup guide and ADR.
last_verified: 2026-09-30
---

# GoogleSheets

Pushes the players export into a sheet in each GM's Google Drive. A GM connects through the ApiKeys page (`ibl5/classes/ApiKeys/README.md`). A prod cron worker rewrites every connected sheet after a sim. The module calls Google over `ext-curl` with no client library. Setup steps, environment variables, and the cron line are in `ibl5/docs/GOOGLE_SHEETS_SETUP.md`. The decision record is `ibl5/docs/decisions/0151-google-sheets-server-push.md`.

Storage is one row per GM in `ibl_google_sheet_connections` (migration `ibl5/migrations/190_google_sheet_connections.sql`). The refresh token column holds a `Security\SecretBox` ciphertext.

## Classes

| Class | Role |
|---|---|
| `GoogleSheetConnectionRepository` | Prepared-statement access to `ibl_google_sheet_connections` (find, upsert, pending queue, mark refreshed, mark broken). Contract: `Contracts/GoogleSheetConnectionRepositoryInterface` |
| `GoogleOAuthConfig` | Reads `GOOGLE_OAUTH_CLIENT_ID`, `GOOGLE_OAUTH_CLIENT_SECRET`, and `GOOGLE_OAUTH_REDIRECT_URI`; `isConfigured()` backs the card's not-configured state |
| `Contracts/GoogleHttpClientInterface` | The HTTP seam. Tests replace it with a fake |
| `CurlGoogleHttpClient` | The `ext-curl` implementation of that seam |
| `GoogleOAuthClient` | Builds the consent URL and calls the token and revoke endpoints |
| `GoogleSheetsClient` | The Sheets v4 calls: create the spreadsheet, ensure the tab, overwrite it |
| `GoogleSheetExportService` | `connect()`, `refreshForUser()`, `refreshConnection()`, `disconnect()`, `connectionSummaryFor()`, and `buildRows()` |
| `GoogleOAuthState` | Session `state` value for one sign-in (`_google_oauth_state`, 600 s TTL, single use, bound to the user) |
| `GoogleOAuthFlowHandler` | The `start()` and `callback()` halves the controller calls; returns the flash text |
| `GoogleSheetRefreshWorker` | Drains the pending queue for `ibl5/scripts/googleSheetRefreshTick.php` |
| `GoogleTokens` | Value object for an access and refresh token pair |
| `GoogleJson` | Safe JSON decode; malformed or empty bodies yield null |
| `GoogleApiException`, `GoogleGrantRevokedException`, `GoogleSheetMissingException`, `GoogleHttpException`, `GoogleOAuthNotConfiguredException` | Typed failures. Each carries a status and reason and no token or response body |

`Security\SecretBox` (`ibl5/classes/Security/`) encrypts the refresh token at rest.

## `refreshConnection()` outcome contract

`GoogleSheetExportService::refreshConnection(array $row): string` never throws. One GM's failure cannot stop the next row. It returns one of three values and records the outcome on the row.

| Result | Meaning | Row effect |
|---|---|---|
| `ok` | The sheet was rewritten | Clears `refresh_pending`, stores `last_refresh_status` |
| `error` | A transient failure (HTTP, API, or an unexpected exception) | Clears `refresh_pending`, stores `last_error`; the row stays `active` |
| `broken` | The connection cannot work until the GM reconnects | Sets `status = 'broken'` and a `broken_reason` |

Broken reasons:

- `invalid_grant`: Google rejected the refresh token.
- `sheet_missing`: the spreadsheet no longer exists or is not accessible.
- `key_unavailable`: the stored token cannot be decrypted with the configured keys.

`refreshForUser()` adds a fourth value, `missing`, when the user has no connection row. It makes no Google call for a broken row.
