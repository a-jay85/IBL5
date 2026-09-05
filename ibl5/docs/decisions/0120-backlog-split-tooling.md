---
description: Adds bin/backlog-split, bin/check-backlog-index, and bin/next-backlog-id to split the loop-engineering backlog monolith into per-item files.
last_verified: 2026-09-05
---

# ADR-0120: Backlog split tooling layer

**Status:** Accepted
**Date:** 2026-09-05

## Context

`ibl5/docs/backlog/loop-engineering-backlog.md` is a monolith of dev-efficiency findings, each a level-3 heading (`### L<n> — <slug>`) inside a shared file. It has grown to a size where navigating, linking, and routing individual items requires reading the full file or knowing the exact heading anchor. Dev-efficiency backlog item E49 ("one file per backlog item") proposed splitting the monolith into one `.md` file per item under `ibl5/docs/backlog/loop-engineering/`, retaining the monolith as the canonical source until the migration is complete.

Three primitives are needed: (1) a splitter that extracts items from the monolith and writes them as individual files; (2) an ID allocator that finds the next unused `L<n>` number across the current checkout and any open worktrees; (3) a consistency checker that validates the monolith's class-registry table against the per-item files on disk.

The work also ships `bin/test-backlog-split`, a harness covering all three tools (23 cases), and wires the harness into CI via the "Shell harness regression tests" job.

## Decision

Add three new `bin/` scripts:

- **`bin/backlog-split`** — splits the monolith into per-item files under `ibl5/docs/backlog/loop-engineering/` (or `$BACKLOG_SPLIT_OUTDIR` for tests). Supports `--split <monolith>`, `--index <dir>`, and `--check <monolith> <dir>` modes.
- **`bin/check-backlog-index`** — verifies that every `### L<n>` in the monolith has a matching file in the per-item directory and vice versa. Wired into CI as a meta-check so drift is caught on merge.
- **`bin/next-backlog-id`** — scans the monolith, open worktrees, and origin refs to allocate the lowest unused `L<n>` number without a network call or a database.

The per-item output directory is `ibl5/docs/backlog/loop-engineering/`. The monolith (`loop-engineering-backlog.md`) is unchanged by this PR; the actual split runs after the tooling is reviewed and merged.

## Alternatives Considered

- **A single combined script** — one script for all three modes avoids three separate `bin/` entries, but the ID allocator is invoked from many contexts (interactive, CI, other scripts) and benefits from a clean standalone interface. The consistency checker runs in CI independently of the splitter. A combined script would require dispatching on `$1` anyway, adding complexity without removing the distinct-responsibility boundary.
- **A Python or Node script** — the repo already has shell precedent for `bin/` tooling, and all three operations (`awk`, `grep`, `sed`) are idiomatic in POSIX shell. A Python script would add a runtime dependency and a venv to the CI shell-check matrix.
- **No tooling; manual split** — a one-off split with `awk` inline produces files but leaves no gate to prevent re-merging the monolith or adding items to it without a corresponding per-item file. The gates are the durable part; the one-off script is not.
