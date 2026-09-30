---
description: One-time Google Cloud, environment, key-rotation, and prod-cron setup for the Google Sheets player export, plus what each broken state means.
last_verified: 2026-09-30
---

# Google Sheets Export Setup

## 1. What this is

The site writes the players export into a sheet in the GM's own Google Drive. The sheet holds no API key and no script. The `IMPORTDATA` `?key=` flow keeps working unchanged for GMs who prefer it.

A GM connects from the ApiKeys page (`modules.php?name=ApiKeys`). After every sim, a cron worker rewrites the sheet. Design record: `ibl5/docs/decisions/0151-google-sheets-server-push.md`. Class layout: `ibl5/classes/GoogleSheets/README.md`.

## 2. Google Cloud project (manual, one-time)

1. Create a Google Cloud project.
2. Enable the Google Sheets API and the Google Drive API.
3. Configure the OAuth consent screen. Choose External. Request one scope only: `https://www.googleapis.com/auth/drive.file`. It is non-sensitive and needs no Google verification. While the app is in Testing, add each GM as a test user. Publish the app when you are ready for everyone.
4. Create an OAuth client of type Web application.
5. Add authorized redirect URIs:
   - Prod: `https://iblhoops.net/ibl5/modules.php?name=ApiKeys&op=google_callback`
   - Each worktree: `http://<slug>.localhost/ibl5/modules.php?name=ApiKeys&op=google_callback`

   Google allows `http://` only for the localhost family. `<slug>.localhost` resolves to loopback and belongs to it. If the console still rejects a specific entry, set `GOOGLE_OAUTH_REDIRECT_URI` on that worktree to a registered URI instead.
6. Copy the client id and secret.

## 3. Environment variables

| Variable | Required | Unset | Empty | Notes |
|---|---|---|---|---|
| `GOOGLE_OAUTH_CLIENT_ID` | yes | feature disabled (the card says not configured) | same as unset | from the OAuth client |
| `GOOGLE_OAUTH_CLIENT_SECRET` | yes | feature disabled | same as unset | never printed; use `~/.claude/bin/show-secrets` to inspect files holding it |
| `GOOGLE_OAUTH_REDIRECT_URI` | no | derived from the request host | same as unset | set only when the derived URI is not registered |
| `GOOGLE_TOKEN_KEY` | yes | feature disabled, logged once per request or tick | same as unset | `php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'` |
| `GOOGLE_TOKEN_KEY_PREVIOUS` | no | no previous key | same as unset | rotation only; a malformed non-empty value disables the feature |

Where to set them:

- **Prod.** In the same place the `MAIL_*` variables live.
- **Local worktrees.** In `ibl5/.env.test`, beside `DEV_AUTO_LOGIN`.
- **CI E2E.** The placeholder values in `.github/workflows/e2e-tests.yml`.

## 4. Key rotation

1. Set `GOOGLE_TOKEN_KEY_PREVIOUS` to the old key.
2. Set `GOOGLE_TOKEN_KEY` to the new key.
3. Run `php scripts/googleSheetRefreshTick.php --all` from `ibl5/`. Each row decrypts under the old key and is re-encrypted under the new one.
4. Unset `GOOGLE_TOKEN_KEY_PREVIOUS`.

A lost key cannot be recovered. Every connection is then marked `broken` with reason `key_unavailable`, and each GM presses Reconnect.

## 5. Prod cron

Print the crontab line:

```bash
php ibl5/scripts/googleSheetRefreshTick.php --print-cron
```

Install it with one single-line command, then verify (`.claude/rules/shell-portability.md`):

```bash
ssh iblhoops.net "crontab -l | { cat; echo '<line>'; } | crontab -"
ssh iblhoops.net "crontab -l | grep googleSheetRefreshTick"
```

The line runs every five minutes and appends to `logs/google-sheet-tick.log`.

Flags: `--dry-run`, `--all`, `--limit=N` (default 100), `--budget=N` (seconds, default 240), `--print-cron`, `--help`.

Exit codes:

| Code | Meaning |
|---|---|
| 0 | All rows ok, nothing pending, or another tick holds the lock (the script prints `already running`) |
| 1 | Any row ended `error` or `broken`, or the database was unavailable |
| 2 | Bad arguments |
| 3 | `GOOGLE_TOKEN_KEY` or the OAuth config is missing or malformed; no row is touched |

## 6. Operations

`broken_reason` values, and what the GM sees:

| Reason | Cause | GM action |
|---|---|---|
| `invalid_grant` | Google rejected the refresh token (the GM removed access, or Google revoked it) | Reconnect |
| `sheet_missing` | The GM deleted the sheet or lost access to it | Reconnect |
| `key_unavailable` | The stored token cannot be decrypted with the current keys | Reconnect |

A broken row makes no Google call until the GM reconnects. A transient failure ends as `error`, clears the pending flag, and shows on the row as `last_error`. The next sim or the GM's Refresh button retries it.

- **Read-only look at the queue.** `php scripts/googleSheetRefreshTick.php --dry-run` lists pending rows and the row count the export would write. It makes no Google call. Adding `--all` still marks every active connection pending in the database before listing them, so avoid that combination on production when you want a zero-write inspection.
- **Logs.** The worker and the export service log to the `google-sheets` channel. Output carries user ids, statuses, and counts. Tokens are never logged.
- **Quota.** The Sheets API allows 300 write requests per minute per project and 60 per minute per user. The worker spends 4 to 5 requests per GM, one GM at a time.

## 7. Not automated

Creating the Cloud project, publishing the consent screen, and installing the cron line are manual by design.
