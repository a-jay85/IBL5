---
description: Production operations runbook — deploy, rollback, DB restore, sim-file recovery, logs, and running the app without the Claude Code harness.
last_verified: 2026-08-10
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
3. **Post-deploy smoke** (`.github/workflows/smoke-prod.yml`) — curls `https://www.iblhoops.net` from the production box's own IP to avoid WAF bans. On real failure, auto-reverts the `production` branch and DMs Discord.

> **Note — IBLbot slash-command OOM (resolved, #905):** registering IBLbot slash commands during deploy previously ran `tsx src/...`, which transpiles TypeScript through esbuild at runtime. On this memory-tight server the esbuild service was `SIGKILL`ed (`TransformError: The service was stopped`), failing the deploy. Fixed by running the compiled output (`npm run deploy-commands:prod` → `node dist/deploy-commands.js`, no runtime esbuild). The OOM path is gone — not a recurring failure. See the inline comment at `.github/workflows/main.yml` (IBLbot deploy step).

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

## IBL6 Decommission — COMPLETED 2026-07-27

The retired IBL6 SvelteKit app (`ibl6.iblhoops.net`) is torn down. This section is kept as the
record of what changed on the box, plus the host facts the teardown established — the previous
version of this procedure named the wrong docroot, the wrong watchdog, and the wrong resurrection
mechanism.

### Final state

| Thing | State |
|-------|-------|
| pm2 `ibl6` | deleted; `pm2 save` written, so `~/.pm2/dump.pm2` now lists `iblbot` only |
| `localhost:3001` | nothing listening |
| `/home/iblhoops/public_html/IBL6/` | deleted (90M of build output; was untracked by the deploy checkout) |
| `~/ibl6.pid` | deleted |
| `/home/iblhoops/ibl6.iblhoops.net/.htaccess` | serves 301s only (below) |
| crontab | two stale `IBL6 self-healing` comment lines removed; all three real jobs untouched |
| Backups | `~/decom-backup-20260727/` — crontab, original `.htaccess`, IBL6 `.env`, `pm2 describe`, pre-teardown `dump.pm2` |

The live redirect, in `/home/iblhoops/ibl6.iblhoops.net/.htaccess` — the param names are
load-bearing, do not rename `date`/`game`:

```apache
RewriteEngine On
# /{YYYY-MM-DD}-game-{n}/boxscore  ->  PHP boxscore module (301)
RewriteRule ^(\d{4}-\d{2}-\d{2})-game-(\d+)/boxscore/?$ https://iblhoops.net/ibl5/modules.php?name=GameBoxscore&date=$1&game=$2 [R=301,L]
# any other ibl6 path -> site home (301); ACME challenges must stay local
RewriteCond %{REQUEST_URI} !^/\.well-known/
RewriteRule ^ https://iblhoops.net/ [R=301,L]
```

Verified after teardown: the boxscore slug 301s to the PHP module (which returns 200), any other
path 301s to the site home, and `/.well-known/` still serves locally so certificate renewal for
the subdomain keeps working.

### Host facts this established — read before any future subdomain teardown

These are the four things the old procedure got wrong. They generalize to every subdomain on this
cPanel host, so check them rather than assuming.

All four trace to one habit: the procedure was written by reasoning from repo artifacts — the CI
workflow's `cd www/IBL6`, a healthcheck script that existed in-repo — and both authoring PRs (#1639,
#1655) shipped with "No manual testing needed." Nothing about a production box's layout is
derivable from the repo. **Write host procedures from the host.**

1. **Resolve a subdomain's docroot from `rewriteinfo` — the naming convention varies per
   subdomain.** It is never the app's source directory, but it is also not one fixed pattern:
   `ibl6.iblhoops.net` → `~/ibl6.iblhoops.net/`, while `pre.iblhoops.net` → `~/www-pre/`. The IBL6
   proxy `.htaccess` lived in `/home/iblhoops/ibl6.iblhoops.net/`; `~/www/IBL6/` was only the Node
   build tree, with no `.htaccess` in it at all. Read the map, never guess it:
   ```bash
   cat /home/iblhoops/.cpanel/caches/rewriteinfo     # maps each subdomain -> its docroot
   ```
   Confirm the file you found is the one actually being served before editing it — pick a path its
   rules treat differently (here, `/.well-known/`) and check that the response diverges from `/`.

2. **The box runs LiteSpeed, not Apache.** LiteSpeed tolerates unknown `.htaccess` directives that
   Apache would answer with a 500. The old `.htaccess` carried a stray `EOF` line — a leaked
   heredoc terminator — and still served 200s, so "the site is up" is *not* evidence that an
   `.htaccess` is well-formed here. Check `curl -sI` for `server: LiteSpeed` before reasoning about
   directive errors.

3. **No IBL6 watchdog runs on the box.** The only pm2 cron job is
   `*/2 * * * * /home/iblhoops/public_html/bin/iblbot-healthcheck`, which probes port **50000** and
   restarts **IBLbot**. Stale `# IBL6 self-healing: probe port 3001` comments sat directly above it
   and read as if they described it. A repo script `ibl6-healthcheck` did exist —
   added in PR #1524, deleted in PR #1639 — which is why the old procedure expected a matching cron
   entry; no such entry was on the box. **A script in the repo is not a job on the box, and the
   comment above a cron line is not its documentation — read the script the line actually invokes.**
   Deleting that job would have silently removed the IBLbot watchdog.

4. **pm2 resurrection comes from `~/.pm2/dump.pm2`.** `pm2 delete <app>` alone is undone by the
   next `pm2 resurrect`; the `pm2 save` is what makes it stick. Note that cron *does* write that
   dump indirectly — `bin/iblbot-healthcheck` runs `pm2 save` every 2 minutes, so it can persist
   whatever pm2 holds at that moment. Before deleting an app, check that the healthcheck's
   `ecosystem.config.cjs` does not also declare it (for IBL6 it did not; that file names `iblbot`
   only). Confirm the dump before and after — a `grep` for `"name"` is not a reliable check
   against this file:
   ```bash
   python3 -c "import json;print([a['name'] for a in json.load(open('/home/iblhoops/.pm2/dump.pm2'))])"
   ```

### Whitelist cleanup — DONE 2026-07-28

The `ibl6` arm was removed from the canonical-domain redirect in `ibl5/.htaccess`
(`RewriteCond %{HTTP_HOST} !^((www|ibl6)\.)?iblhoops\.net$` → `!^(www\.)?iblhoops\.net$`).

**Proof it was safe — the boxscore fingerprint.** `ibl6.iblhoops.net` is served by its *own*
vhost, not by these rules. The load-bearing evidence is a `Location` that only
`/home/iblhoops/ibl6.iblhoops.net/.htaccess` can emit:

```bash
curl -sI 'https://ibl6.iblhoops.net/2008-03-10-game-7/boxscore'
# location: https://iblhoops.net/ibl5/modules.php?name=GameBoxscore&date=2008-03-10&game=7
```

No ruleset in the main docroot can synthesise that Location. The separate docroot is confirmed by
the cPanel rewrite map (`ibl6.iblhoops.net` → `/home/iblhoops/ibl6.iblhoops.net`, outside the main
tree):

```bash
ssh iblhoops.net 'cat /home/iblhoops/.cpanel/caches/rewriteinfo'
```

Corroborators: `curl -sI https://ibl6.iblhoops.net/` → `location: https://iblhoops.net/` (the ibl6
vhost, not the main docroot's `.../ibl5/index.php`); `/.well-known/` returns 200 (ACME exemption
intact). The `pre.iblhoops.net` control passed but is **corroborating only** — `pre /` returned 200
with no `Location`, and `/home/iblhoops/www-pre/ibl5/.htaccess` exists (577 B), so pre carries its
own copy of this guard; the boxscore fingerprint carries the verdict. No live cron/pm2/config caller
targets `ibl6.iblhoops.net` (`dump.pm2` names `['iblbot']` only).

**Bounded worst case — removal can only make the guard stricter.** LiteSpeed routes legitimate
`ibl6.iblhoops.net` traffic at the vhost level (SNI/IP, before any `.htaccess` is read), so it never
evaluates this rule. The sole residual case is a request reaching the *main* vhost under `/ibl5/`
with a spoofed/mismatched `Host: ibl6.iblhoops.net` — which is now 301'd to the canonical host,
exactly what the anti-mirroring rule exists to do. No legitimate user flow depends on the removed
arm.

**Deploy path (why the repo edit is the whole change):** `.github/workflows/main.yml` deploys by
`cd www && git reset --hard origin/production`. `ibl5/.htaccess` is a tracked repo file with no
special handling — no `rsync`, `scp`, template render, or post-deploy `sed` — so the deployed file
is byte-identical to the committed file.

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
} | mysql --force "$DB"
```

`--force` is required to continue past `ERROR 1906` (generated-column value warnings) without aborting the import. Disabling FK checks is required: the single-pass dump is alphabetically ordered, so FK parents may appear after children. The `perl` strip removes `DEFINER` clauses that reference production users not present in a fresh database. This mirrors `bin/lib/db-helpers.sh`'s `db_import_sql` function.

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

---

## 8. Sim Recap Poller

The sim recap poller is a macOS LaunchAgent (`com.ibl5.sim-recap-poll`, label managed by `bin/sim-recap-cron-setup`) that fires `bin/sim-recap-tick` every 300 s.

### Phase gate — when the poller stops itself

Recap generation is gated to **Regular Season only** (`RecapPhasePolicy::ENABLED_PHASES`). When the season phase is advanced via the League Control Panel admin, `setSeasonPhase()` posts a notice to `#admin-chat` if the new phase is enabled. When the phase changes **away** from Regular Season, the next tick that finds no pending sim and `recaps_enabled: false` will self-unload the LaunchAgent via `launchctl bootout`. The poller logs the reason before unloading.

### Resuming the poller after a phase change

When the season phase is set to Regular Season, `#admin-chat` receives a notification. To re-arm the poller:

```bash
bin/sim-recap-cron-setup --resume
```

This runs `launchctl bootout` (no-op if not loaded) followed by `launchctl bootstrap` against the existing plist. It refuses with an error if the plist does not exist — run `bin/sim-recap-cron-setup --install-schedule` first if the LaunchAgent was never installed.

### Manual self-unload guard

`bin/sim-recap-tick` never self-unloads when:
- `--dry-run` is passed, or
- `--sim=N` is passed (a specific sim was requested manually).

This prevents a manual invocation from pulling the rug out during debugging.
