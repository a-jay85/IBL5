---
description: Why ibl5/scripts/archive/ exists and the policy for what lands here — retained-but-not-run one-time scripts kept for reference.
last_verified: 2026-06-21
---

# `scripts/archive/` — retained-but-not-run scripts

This directory holds **one-time / legacy scripts kept for reference only**. They
are **not** part of any routine workflow and are **not** wired into CI, cron, or
the app. They remain in the repository as a record of how a specific historical
data operation was performed, in case a similar fix-up is ever needed again.

> **Do not run these against a live database without first re-reading the script
> and confirming the target data and date ranges still apply.** They were written
> for a specific point-in-time backfill and hard-code dates, team-id maps, and
> file-format offsets.

## Contents

| Script | Archived | Purpose |
|--------|----------|---------|
| `importBoxscoresFromHtml.php` | 2026-02-12 | One-time import of Dec 11-13 2007 box scores from HTML pages on iblhoops.net, plus backfill of Dec 7-10 quarter scores / attendance / capacity / W-L. Writes via `Boxscore\BoxscoreRepository`. |
| `importBxsMissing.php` | 2026-02-12 | One-time extraction of Dec 7-10 2007 box scores from the legacy `IBL5.bxs` binary (3000-byte records, 94-byte player entries — differs from the `.sco` 2000/53 format). Writes via `Boxscore\BoxscoreRepository`. |

## Policy — what lands here

A script belongs in `scripts/archive/` when **all** of these hold:

- It was a **one-time** or **point-in-time** operation (a backfill, a migration
  fix-up, a historical import) that has already been run and is **not** expected
  to run again on a schedule.
- It is worth **keeping for reference** — it documents a non-obvious data shape,
  format, or recovery procedure — rather than deleting outright.
- It is **not** referenced by CI, cron, the app, or any other tooling.

When archiving a script, move it here and add a row above with its **archived
date** (the date it was moved/added) and a one-line **purpose**. If a script is
genuinely dead and not worth keeping for reference, **delete** it instead of
archiving — this directory is for scripts with documentary value, not a graveyard.
