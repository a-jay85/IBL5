# Phase 10 — Preview Environment (post-plan reference)

Purpose: the full Path A / Path B procedures for Phase 10 preview.

### Path A: Main-stack rebuild (when `$PR_STATE` = `MERGED`)

**Skip the rebuild if `$ENGINE_ONLY`** — an engine-only change touches no `ibl5/` PHP and cannot affect the rendered app, so tearing down and re-streaming prod data adds nothing. Print "Engine-only change — skipping Path A main-stack rebuild." and end Phase 10.

The PR has been merged (either auto-merge fired during CI watch, or it was already merged before post-plan started). Run `cd <repo-root> && git checkout master && git pull origin master` to sync local, then rebuild the main Docker stack with fresh prod data.

1. **Update vendor** (may be stale after merge):
   ```bash
   (cd <repo-root>/ibl5 && composer install)
   ```

2. **Check for prod credentials** before tearing down the running stack:
   ```bash
   grep -q '^REMOTE_HOST=' <repo-root>/.env \
     && grep -q '^REMOTE_USER=' <repo-root>/.env \
     && grep -q '^REMOTE_PASSWORD=' <repo-root>/.env
   ```
   If any `REMOTE_*` credential is missing: warn "Fresh prod data unavailable — REMOTE_* credentials not found in .env. Skipping main-stack rebuild." and **stop Phase 10** (leave the existing main stack untouched).

3. **Tear down and restart** with stale seed skipped:
   ```bash
   cd <repo-root> && docker compose down -v
   docker compose up -d
   ```
   `docker compose down -v` removes only the main project's volume (`ibl5-mariadb-data`) — worktree volumes are in separate compose projects and are not affected.

4. **Wait for MariaDB to be healthy:**
   ```bash
   RETRIES=0
   until docker exec ibl5-mariadb healthcheck.sh --connect --innodb_initialized &>/dev/null; do
       RETRIES=$((RETRIES + 1))
       if [ "$RETRIES" -ge 30 ]; then
           echo "Error: MariaDB did not become healthy after 30 attempts"
           break
       fi
       sleep 2
   done
   ```

5. **Stream fresh prod data** (redirect to log to keep context clean):
   ```bash
   bin/db-sync-prod > /tmp/db-sync-prod.log 2>&1 && echo "PASS: db-sync-prod completed" && tail -20 /tmp/db-sync-prod.log || { echo "FAIL: db-sync-prod"; tail -40 /tmp/db-sync-prod.log; }
   ```
   With no arguments, targets the main `ibl5-mariadb` container. Streams from prod, handles generated columns, strips DEFINER clauses, backfills `schema_migrations`, and runs `bin/db-migrate` for pending migrations.

6. **Smoke test** — verify main.localhost loads with prod content:
   ```bash
   HTTP_CODE=$(curl -s -o /dev/null -w '%{http_code}' http://main.localhost/ibl5/)
   if [ "$HTTP_CODE" != "200" ]; then
       echo "FAIL: main.localhost returned HTTP $HTTP_CODE"
       docker logs ibl5-php --tail 30
   fi

   BODY=$(curl -s http://main.localhost/ibl5/)
   if echo "$BODY" | grep -qi 'fatal error\|500 Internal'; then
       echo "FAIL: PHP fatal error detected in response"
       docker logs ibl5-php --tail 30
   fi

   if echo "$BODY" | grep -qi 'standings\|scores\|roster'; then
       echo "PASS: Prod content detected"
   else
       echo "WARN: Could not confirm prod content in response"
   fi
   ```
   If the smoke test fails: print the error details. Do NOT retry the full rebuild — the logs are more useful for diagnosis.

7. **Print preview URL:** `http://main.localhost/ibl5/`

### Path B: Worktree preview (when `$PR_STATE` != `MERGED`)

**Skip if** worktree was pre-existing or earlier phases left uncommitted fixes.

1. Tear down and restart with production data:
   ```bash
   bin/wt-down <worktree-name>
   bin/wt-up <worktree-name> --prod
   ```
2. Print preview URL: `http://<slug>.localhost/ibl5/`
3. Do NOT run `wt-remove` or `git branch -D`
