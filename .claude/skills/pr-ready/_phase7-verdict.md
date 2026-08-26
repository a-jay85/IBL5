---
description: /pr-ready runtime Phase 7 — arm-hold evaluation, sticky verdict comment, hard terminator. Loaded by SKILL.md via git show at Phase 7.
last_verified: 2026-08-26
---

# /pr-ready runtime Phase 7 — holds, sticky verdict, terminator

Purpose: the full Phase 7 procedure, lifted out of `SKILL.md` so it is resident only from the turn it loads.

Read at runtime via `git show <MASTER_SHA>:.claude/skills/pr-ready/_phase7-verdict.md`.

`<MASTER_SHA>`, `<N>`, and `<HEAD_SHA>` below are **literals to substitute** with the values pinned in Phase 1.3 — a value captured in one Bash call does not survive into the next.

**Phase 7 — verdict and stop.**

1. **Run the shared hold predicates.** `bin/lib/pr-armable.sh` is **sourced, not executed** — it carries no `set -euo pipefail` at file scope by design. Reuse its six predicates rather than re-deriving any hold logic:

   `git show <MASTER_SHA>:.claude/skills/pr-ready/scripts/holds.sh > /tmp/pr-ready-holds-<N>.sh && test -s /tmp/pr-ready-holds-<N>.sh && bash /tmp/pr-ready-holds-<N>.sh <N> <slug>`. The script is that single invocation, and prints exactly six labelled lines — fewer means a predicate aborted, itself a finding.

   Report each predicate's result as one line in the verdict. These are **advisory inputs to the human's merge decision** — `/pr-ready` never arms auto-merge and never merges.

2. **Post the sticky verdict.** Marker, placed as the **last line of the body** so an update matches:

   `<!-- pr-ready-verdict -->`

   **First write the composed comment body to `/tmp/pr-ready-verdict-<N>.md` with the `Write` tool.** The path is keyed to the PR number for the same reason Phase 2a's is: a `tmpfile=$(mktemp)` assigned in one Bash call is gone by the next one, so the post below would send an empty `--body-file`. Compose the body in full, write it, then run the post.

   There is no helper in `bin/lib/` for this, so use the find-and-update-else-create shape from `bin/pr-canary-check` (see its `STICKY_MARKER` constant and the `post_sticky()` below it — grep the symbols rather than trusting a line number, which drifts).

   `git show <MASTER_SHA>:.claude/skills/pr-ready/scripts/post-verdict.sh > /tmp/pr-ready-post-<N>.sh && test -s /tmp/pr-ready-post-<N>.sh && bash /tmp/pr-ready-post-<N>.sh <N> <slug>`

   Comment body sections, in order: **rebase result** (the master SHA used, conflicts resolved), **CI result**, **files-changed refresh** (the Phase 5.9 `FILES-CHANGED:` line verbatim — `REPLACED` / `APPENDED` / `UNCHANGED` / `AMBIGUOUS`, with its file count and `+added -removed` delta; on `AMBIGUOUS`, state that the body was left untouched and that the markers need repair), **plan-fidelity verdict**, **remediation** (what Phase 6.5 fixed, each backlog item filed with its file and ID, anything left `not fixed — filed`, and the post-remediation CI result), **hold predicates**, and one explicit **READY / NOT READY** line — the last reflecting the state *after* remediation, not the Phase 6 findings. If any include was loaded by the declared fallback rather than from the pin, say so here — one `include-source:` line — so the verdict states which revision of its own procedure it followed. The files-changed block reflects the diff as of Phase 5.9. If Phase 6.5 pushed remediation commits after it, say so on the refresh line — the block is one commit behind by design, and the next `/post-plan` body write regenerates it. Never open a second body edit to catch it up.

3. **STOP — hard terminator.** The run ends at the posted-or-updated comment. No merge. No auto-merge arming. No `/backlog-housekeep` chain beyond the row and `last_verified` bump Phase 6.5 already filed. No `/post-plan` chain. No worktree teardown. No second comment. The user reviews every PR deliberately.
