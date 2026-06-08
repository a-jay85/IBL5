---
description: Production operations runbook — deploy, rollback, DB restore, sim-file recovery, logs, and running the app without the Claude Code harness.
last_verified: 2026-06-08
---

# IBL5 Operations Runbook

**Purpose:** How to operate the production application without the Claude Code harness. Deploy, roll back, restore data, read logs, interpret alerts, rotate secrets.

**When to reference:** Emergency response, maintainer unavailability, onboarding a second operator.

---

## 1. Deploy

### Normal deploy (via GitHub Actions)

Push a commit to the `production` branch:

```bash
git push origin <your-branch>:production
```

`.github/workflows/main.yml` fires automatically:

1. **Pre-flight** (`.github/workflows/deploy-rehearsal.yml`) — runs `composer install --no-dev`, pending migrations, and `ibl5/bin/validate-schema` in a dry-run environment. Blocks the deploy on failure.
2. **Build and Deploy** — if pre-flight passes:
   - `git reset --hard origin/production` on the server
   - `composer install --no-dev --optimize-autoloader`
   - `php ibl5/bin/migrate`
   - `php ibl5/bin/validate-schema`
   - SCP compiled CSS (`ibl5/themes/IBL/style/style.css`)
   - OPcache flush
   - IBL6 restart (pm2)
3. **Post-deploy smoke** (`.github/workflows/smoke-prod.yml`) — curls `https://www.iblhoops.net` from the production box's own IP to avoid WAF bans. On real failure, auto-reverts the `production` branch and DMs Discord.

### Manual deploy (if GitHub Actions is unavailable)

SSH to the production box, then:

```bash
cd www
git fetch origin
git reset --hard origin/production

cd ibl5
composer install --no-dev --no-interaction --optimize-autoloader
php bin/migrate
php bin/validate-schema

# Build CSS (requires Node 22)
npm install
npx @tailwindcss/cli -i design/input.css -o themes/IBL/style/style.css --minify

# Flush OPcache — requires an authenticated request or a PHP CLI helper
# if the server exposes an opcache reset endpoint; alternatively restart PHP-FPM:
# sudo systemctl reload php-fpm   (or equivalent for the host)
```

After a manual deploy, trigger `bin/smoke-prod` manually or curl the health endpoint (see §5) to confirm the app is up.

---

## 2. Rollback

### Automatic rollback

The smoke test at `.github/workflows/smoke-prod.yml` auto-reverts on a real failure:

1. `git revert HEAD` on the `production` branch.
2. Push — triggers a fresh deploy of the reverted commit.
3. DM to `OWNER_DISCORD_ID` with the commit SHA and revert status.

The auto-revert is skipped when HEAD is already a revert (loop guard) or when the deploy changed only docs/markdown (cannot affect runtime).

### Manual rollback

```bash
# On your local machine
git log --oneline origin/production   # find the safe SHA
git revert HEAD                       # or: git revert <bad-sha>
git push origin HEAD:production       # triggers a fresh deploy
```

If migrations ran, a data rollback is required before reverting code — see §3.

---

## 3. Database Restore

### Backup location

Daily dumps live on the production box at:

```
~/backups/db/ibl5-<YYYY-MM-DD>.sql.gz
```

14 daily dumps are retained. The CI job at `.github/workflows/db-backup.yml` produces and verifies each dump at 07:30 UTC. "Verified" means the dump was restored into an ephemeral MariaDB container and sanity-checked (≥ 28 teams, > 0 players).

### Restore procedure

```bash
# On the production box, as the deploy user
DB="iblhoops_ibl5"
DUMP="$HOME/backups/db/ibl5-<YYYY-MM-DD>.sql.gz"

mysql -e "DROP DATABASE IF EXISTS \`$DB\`; CREATE DATABASE \`$DB\`;"

{
  echo "SET FOREIGN_KEY_CHECKS=0;"
  gunzip -c "$DUMP" | perl -pe 's/ DEFINER=\S+ / /g'
  echo "SET FOREIGN_KEY_CHECKS=1;"
} | mysql "$DB"
```

Disabling FK checks is required: the single-pass dump is alphabetically ordered, so FK parents may appear after children. The `perl` strip removes `DEFINER` clauses that reference production users not present in a fresh database. This mirrors `bin/lib/db-helpers.sh`'s `db_import_sql` function.

### Sanity checks after restore

```bash
mysql "$DB" -e "SELECT COUNT(*) AS teams  FROM ibl_team_info;"   # expect >= 28
mysql "$DB" -e "SELECT COUNT(*) AS players FROM ibl_plr;"        # expect > 0
mysql "$DB" -e "SELECT migration FROM migrations ORDER BY id DESC LIMIT 1;"
```

**Same-host limitation:** The production box is the only host with SSH access to itself, so offsite restoration requires copying the `.sql.gz` file to another machine first.

---

## 4. Sim-File Recovery

Sim files (JSB engine output, `.zip` archives) are stored on the production box at:

```
~/backups/{season-label}/*.zip
```

The iblhoops.net server replicates these files independently from the DB backup workflow.

`ibl5/classes/Updater/Steps/ExtractFromBackupStep.php` reads from `backups/{season-label}/` during season-data imports. If a sim-file archive is missing or corrupt:

1. Check the production box's `~/backups/{season-label}/` directory.
2. Check iblhoops.net for a replicated copy.
3. If neither has the file, it must be regenerated by re-running the JSB engine for that season.

---

## 5. Logs and Alerts

### Log files

Logs are JSON-formatted `RotatingFileHandler` files in `ibl5/logs/`:

| File pattern | Content | Default retention |
|---|---|---|
| `ibl5/logs/ibl5-*.log` | All channels (debug+) | 30 days |
| `ibl5/logs/ibl5-audit-*.log` | Audit channel | 365 days |
| `ibl5/logs/ibl5-admin-*.log` | Admin channel | 365 days |

Log config: `ibl5/config/logging.config.php` (untracked; template at `ibl5/config/logging.config.example.php`).

Each log entry is a JSON object. Fields include `level_name`, `message`, `context`, `extra.url`, `extra.uid` (7-char request ID), and `extra.user` (if authenticated). PII is redacted by `PiiRedactionProcessor` before writing.

### Discord error alerts

When `discord_webhook_url` is set in `ibl5/config/logging.config.php`, any log entry at `discord_alert_level` (default: `error`) or above is sent to that Discord channel via `Logging\DiscordWebhookHandler`.

**A Discord error alert means:** an uncaught exception or explicit `$logger->error(...)` call reached the `error` level. First-response steps:

1. Open the most recent `ibl5/logs/ibl5-*.log` on the production box.
2. Search for the `uid` from the alert (7-char request ID in the Discord message).
3. Check `context.exception` or `context.trace` for the stack trace.
4. If it's database-related, check the `/health` endpoint (below) and recent slow-query entries in the log.

### Health endpoint

```
GET https://www.iblhoops.net/ibl5/api/v1/health
```

No authentication required. Response:

```json
{ "status": "ok", "db": true, "checkedAt": "2026-06-08T12:00:00Z" }
```

HTTP 200 = DB reachable. HTTP 503 = DB unreachable (`"status": "degraded"`).

### Weekly log review

`.github/workflows/log-review.yml` runs every Sunday at ~09:00 ET. It SSHes to the production box, fetches a digest via `bin/log-fetch-prod`, and DMs the summary to Discord. This covers total entry counts, severity breakdown, slow query count, and the top 3 deduplicated messages.

---

## 6. Running Without the Harness

These are the minimum commands to run the app and its tests using plain Docker, Composer, and npm — no `bin/` convenience scripts required.

### Start the app

```bash
docker compose up -d          # from repo root — starts PHP + MariaDB
```

The app is served at `http://main.localhost/ibl5/` once Docker is up (requires Traefik; see `ibl5/docs/DEVELOPMENT_ENVIRONMENT.md` for Traefik setup). Never navigate to the bare root — the app lives under `/ibl5/`.

### Run PHP tests

```bash
cd ibl5
composer install              # install dependencies including test tooling
vendor/bin/phpunit            # run all tests (integration tests require Docker up)
```

For PHPStan analysis:

```bash
# From ibl5/
composer run analyse          # production code
composer run analyse:tests    # test code
```

Never call `vendor/bin/phpstan` directly — the composer scripts add required memory limits and autoload bootstrap.

### Build CSS

```bash
cd ibl5
npm install
npx @tailwindcss/cli -i design/input.css -o themes/IBL/style/style.css
```

For watch mode: `npx @tailwindcss/cli -i design/input.css -o themes/IBL/style/style.css --watch`

### Run database migrations manually

```bash
# From repo root
php ibl5/bin/migrate
php ibl5/bin/validate-schema   # config in ibl5/config/schema-assertions.php
```

---

## 7. Secrets

### Where secrets live

| Secret | Location | Notes |
|---|---|---|
| DB credentials, app key | `ibl5/config.php` | Untracked. Template: `ibl5/config.php.example` |
| Logging (Discord webhook) | `ibl5/config/logging.config.php` | Untracked. Template: `ibl5/config/logging.config.example.php` |
| Discord bot config | `ibl5/config/discord.config.php` | Untracked. Template: `ibl5/config/discord.config.example.php` |
| Mail (SMTP) | `ibl5/config/mail.config.php` | Untracked. Template: `ibl5/config/mail.config.example.php` |
| Deploy SSH key | GitHub Actions secret `PRIVATE_KEY` | Private key; public key installed on prod box's `authorized_keys` |
| Production host, port, user | GitHub Actions secrets `HOST`, `PORT`, `USERNAME` | |
| Discord notification target | GitHub Actions secret `OWNER_DISCORD_ID` | Snowflake ID for DM delivery |
| CI PAT (auto-revert push) | GitHub Actions secret `CI_PAT` | Scoped to push `production` branch |

### Rotation procedure

1. **DB password** — update on the MariaDB host, then update `ibl5/config.php` on the production box. No redeploy needed; `config.php` is read at runtime.
2. **Deploy SSH key** — generate a new key pair (`ssh-keygen -t ed25519`), add the public key to `authorized_keys` on the production box, update the `PRIVATE_KEY` GitHub Actions secret, then remove the old public key.
3. **Discord webhook** — regenerate in Discord server settings, update `ibl5/config/logging.config.php` on the production box.
4. **CI PAT** — generate a new token in GitHub (scoped to `contents: write` for this repo), update `CI_PAT` secret in GitHub Actions settings, then revoke the old token.

All config files are `.gitignore`d — never commit them. See the `.example` templates for the expected structure.
