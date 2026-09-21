---
description: bin/db-sync-prod has no dry run for the prod path — --print-plan only plans a --snapshot load, and on its own it used to fall through to the real destructive sync.
last_verified: 2026-09-21
paths: "bin/db-sync-prod"
---

# `bin/db-sync-prod` Has No Dry Run

## Rule

`--print-plan` is **only** a planning flag for `--snapshot`. It prints the
`gunzip -c <file> | db_import_sql <container>` line and exits. There is **no dry-run mode for
the prod path**: with no `--snapshot` there is nothing to plan, and the flag never suppressed
the sync.

Before 2026-09-21 that fall-through was silent. `bin/db-sync-prod --print-plan` looked like a
preview, printed no plan, and ran the full prod → local sync, dropping and reloading the target
container's tables. It was worse than a bare invocation: the same `PRINT_PLAN` guard also
skipped the Homebrew keg-path preflight, so the destructive path ran without the PATH fix that
makes `mariadb-dump` resolve.

The script now rejects the combination:

```
$ bin/db-sync-prod --print-plan
ERROR: --print-plan requires --snapshot <file.sql.gz>.
```

**Keep that guard.** Removing it restores a flag that reads as a dry run and is not one.

## What to reach for instead

- **Plan a snapshot load:** `bin/db-sync-prod --snapshot <file.sql.gz> --print-plan`. Needs no
  running container and no credentials file.
- **Rehearse a sync safely:** load a snapshot, or target a throwaway worktree container
  (`bin/db-sync-prod <worktree-name>`), which leaves the main stack's database alone.
- **See what the prod path will do:** read `bin/db-sync-prod` from the per-table dump passes
  down. That is the only accurate preview.

Local-only tables survive a snapshot load through the `PRESERVE_EXISTING` backup-and-replay in
`bin/db-sync-prod` (ADR-0080). That protection is snapshot-mode only, so it is no reason to
treat a prod sync as reversible.
