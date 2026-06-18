---
description: Why the ibl5/backups archive syncs prod<->local via rsync over SSH, with a self-built static rsync uploaded to the rootless prod host.
last_verified: 2026-06-18
---

# ADR-0063: Sync the backups archive via a self-built static rsync

**Status:** Accepted
**Date:** 2026-06-18

## Context

`ibl5/backups` holds ~55 GB of write-once season `*.zip` files — the JSB sim source of truth, extracted at runtime by `ExtractFromBackupStep`. It is git-ignored (`.gitignore`); tracking 55 GB of already-compressed binaries in git history would mean irreversible repo bloat and breach GitHub's file/repo limits, and Git LFS meters storage for no benefit on write-once artifacts. We needed a reproducible way to keep a developer's local copy in sync with production. The blocker: the production server is shared cPanel hosting with **no root and no rsync installed**, and rsync must exist on both ends of a transfer.

## Decision

Sync `ibl5/backups` with `bin/backups-sync` — incremental rsync over the existing `PROD_SSH_HOST`/`.env` SSH path (pull by default, `--push` to reverse; `-z` omitted because the payload is already-compressed zips). Because prod ships no rsync, `bin/backups-sync-setup` builds a **fully-static x86_64 rsync from source in an Alpine container** (reproducible — no trusting an opaque prebuilt binary) and uploads it to the remote `~/bin/rsync`, a single rootless, reversible userland file; `bin/backups-sync` invokes it explicitly via `--rsync-path` so it never depends on the remote login PATH.

## Alternatives Considered

- **Track backups in git (or Git LFS)** — version the archive directly. Rejected because: 55 GB of compressed binaries permanently bloats git history and hits GitHub limits; LFS meters storage with no gain on write-once files.
- **rclone/lftp over SFTP** — sync tools that need nothing installed on prod (sftp-server is present). Rejected because: adds a new local-only dependency and a second tool surface, where rsync over SSH already matches the repo's `bin/log-fetch-prod` conventions; kept as the documented fallback if a prod binary is ever disallowed.
- **Hand-rolled scp loop** — enumerate + copy only missing/size-differing files using tools already on prod. Rejected because: reimplements rsync's incremental/resume/atomicity logic badly (no mid-file resume, owns its own edge cases).
- **Grab a prebuilt static rsync** — download a third-party static binary and upload it. Rejected because: unauditable provenance for a binary placed on the production host; building it ourselves in a pinned container is reproducible and inspectable.

## Consequences

- Positive: incremental, resumable (`--partial`) sync that reuses existing SSH/`.env` conventions; no prod changes beyond one reversible userland file.
- Positive: the remote rsync is reproducible from source and re-installable with one idempotent command if the host wipes `~/bin`.
- Negative: a manual one-time bootstrap step (`bin/backups-sync-setup`) is required per host, and the static binary must be rebuilt to pick up future rsync versions or a CPU-arch change.

## References

- `bin/backups-sync` — the sync entry point (rsync over SSH, `--rsync-path` pin).
- `bin/backups-sync-setup` — builds and uploads the static remote rsync.
- `bin/log-fetch-prod` — the `--ssh-host`/`--ssh-port`/`--ssh-key` + `PROD_SSH_HOST` convention reused here.
- `.gitignore` — `backups` is excluded from version control.
