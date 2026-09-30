---
description: Manages API key generation and display for GM users, and hosts the Google Sheets connect, refresh, and disconnect operations.
last_verified: 2026-09-30
---

# ApiKeys

Manages API key generation and display for GM users. Follows the standard Repository/Service/View pattern. Entry point: `ibl5/modules/ApiKeys/index.php`.

The same page hosts the Google Sheets card. The controller delegates to the `GoogleSheets` module (`ibl5/classes/GoogleSheets/README.md`).

## Operations

`ApiKeysController` routes on the `op` query value. Every POST op is CSRF-guarded, and a non-POST request to a POST op redirects to the main page. `google_callback` is a GET from Google, so it carries no CSRF form token. It is protected by the single-use `state` value in the session key `_google_oauth_state` (600 s TTL).

| `op` | Method | Effect |
|---|---|---|
| `generate` | POST | Creates an API key for the session user |
| `revoke` | POST | Revokes the session user's API key |
| `google_start` | POST | Validates the `google_start` CSRF form token, then redirects to Google's consent screen |
| `google_callback` | GET | Google redirects here with `state` and `code`; creates the sheet and the connection row |
| `google_refresh` | POST | Runs a manual refresh for the session user |
| `google_disconnect` | POST | Revokes the grant at Google on a best-effort basis and deletes the connection row |

Results reach the view through the session flash key `_apikeys_flash`.

## Optional constructor dependencies

The constructor takes two trailing optional arguments: `?GoogleSheets\GoogleOAuthFlowHandler $googleFlow = null` and `?GoogleSheets\GoogleSheetExportService $googleExport = null`. When either is null, the Google ops report the feature as unavailable and the view shows the card as not configured. The key generate and revoke ops do not depend on them.
