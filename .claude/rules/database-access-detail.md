---
description: Read-on-demand only (no auto-attach trigger). Long-form database sections: the db-query wrapper and its routing, and the local database integration tests. database-access.md links here at each moved heading.
last_verified: 2026-11-12
paths: "~/.claude/hooks/plan-gate-edit.sh"
---

# Database Access Reference: Detail

Long-form sections moved verbatim from `.claude/rules/database-access.md`. The core file keeps each heading with a one-line stub.

## Claude Code Database Queries (Auto-Approved)

```bash
# Use this wrapper script for database queries - it auto-approves without user confirmation
# Invokable from BOTH the repo root and ibl5/ — a symlink at bin/db-query points to ibl5/bin/db-query
./bin/db-query "SELECT * FROM ibl_plr LIMIT 5"
./bin/db-query "SELECT COUNT(*) FROM ibl_team_info"
./bin/db-query "DESCRIBE ibl_plr"
```

**When to use `db-query`:** Use this script to explore the database schema, verify data after making changes, check record counts, and validate your work. This is the preferred method for Claude to query the local database since it's configured for auto-approval in the user's Claude Code settings.

**Which database it hits:** the wrapper resolves its own symlink and reads the `ibl5/config.php` **next to that resolved script** (never the repo-root `config.php`), then routes on the script's own location — not on your cwd: <!-- slop-ok -->

- **Main checkout's copy** → the main stack over `127.0.0.1:3306`, exactly as before.
- **A worktree's copy** → that worktree's own container, `ibl5-db-<slug>`, via `docker exec`. Worktree DBs publish no host port, so this is the only way to reach them. The slug comes from `<worktree>/.wt-slug` (written by `bin/wt-up`), falling back to the worktree directory's basename. <!-- wt-up-ok: names the script that writes .wt-slug -->

Because routing keys on the script's location, invoking the **main** checkout's `bin/db-query` by absolute path still hits the main stack no matter what directory you are standing in — that is what keeps `bin/bug-pipeline-check` (cron-invoked with an arbitrary working directory) reading the live main-stack `ibl_bug_reports`. <!-- slop-ok -->

It announces the resolved target on **stderr** on every run (`db-query: target = main stack (127.0.0.1:3306)` or `db-query: target = container ibl5-db-<slug>`). Nothing is added to stdout — callers that parse rows with `grep -E '^\|'` are unaffected. <!-- slop-ok -->

**There is no silent fallback.** If a worktree's DB container is missing or stopped, `db-query` exits **3** and names the container and the `bin/wt-stack start <slug>` that starts it (stop rule: `.claude/rules/workflow-continuity.md` § Worktree Setup), rather than answering from a different database. Set **`DB_QUERY_TARGET=main`** to force the main stack from anywhere; any other value is rejected with exit **2**. An unset or empty `DB_QUERY_TARGET` means "route normally".

**On `ERROR 1049` (unknown database), `db-query` self-diagnoses.** It prints the resolved `$dbname`, the absolute `config.php` it was read from, whether `DB_NAME` was set in the environment, and the databases that do exist. On a host shell `DB_NAME` is normally **unset**, so the file's fallback *is* the value that gets used — the env line is there to tell you the exceptions (`php -r` inherits the shell's environment, so an exported `DB_NAME` overrides the fallback). Read that block instead of assuming the container is down. The diagnostic fires **only** for 1049 — an ordinary bad-table or syntax error still just prints MariaDB's message — and the script's exit status is still MariaDB's. <!-- slop-ok -->

The companion guard is in `materialize_worktree_config` (`bin/lib/git-helpers.sh`), which copies main's `config.php` into every new worktree: when no `DB_NAME` is in the environment it echoes the `$dbname` fallback it just propagated. It validates nothing (the file is deliberately league-agnostic) — it only makes the inherited name visible, since a stale fallback in the main checkout otherwise fans out silently to every worktree created afterwards. <!-- slop-ok -->

## Local Database Integration Tests

```bash
bin/db-test-up [worktree-name] [--no-run]
```

Bootstraps a sibling `ibl5_test` database for `phpunit --group database` runs. Drops and recreates `ibl5_test` each run; never touches `iblhoops_ibl5`. Without arguments, uses the main checkout Docker environment. With a worktree name, uses the worktree's Docker stack. `--no-run` bootstraps the database only.

**In a worktree, ALWAYS run DB-integration tests via `bin/db-test-up <slug>` — never hand-run `DB_HOST=127.0.0.1 vendor/bin/phpunit --group database` from the host.** Only the main stack's `ibl5-mariadb` publishes host port 3306; worktree DB containers (`ibl5-db-<slug>`) are reachable on the Docker network only. So a host-side `127.0.0.1` connection silently targets the **main** stack's `ibl5_test` — typically a stale DB from an earlier run — producing phantom failures (wrong counts, duplicate-key errors) that don't reproduce in CI. `bin/db-test-up <slug>` runs phpunit inside the worktree php container against `DB_HOST=db`, hitting the freshly-built worktree DB. Verify port ownership with `docker ps --format '{{.Names}} {{.Ports}}' | grep 3306`. <!-- slop-ok -->
