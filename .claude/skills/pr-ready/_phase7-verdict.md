---
description: /pr-ready runtime Phase 7 — arm-hold evaluation, sticky verdict comment, machine-readable verdict marker, hard terminator. Loaded by SKILL.md via git show at Phase 7.
last_verified: 2026-09-04
---

# /pr-ready runtime Phase 7 — holds, sticky verdict, terminator

Purpose: the full Phase 7 procedure, lifted out of `SKILL.md` so it is resident only from the turn it loads.

Read at runtime via `git show <MASTER_SHA>:.claude/skills/pr-ready/_phase7-verdict.md`.

`<MASTER_SHA>` and `<N>` below are **literals to substitute** with the values pinned in Phase 1.3 — a value captured in one Bash call does not survive into the next.

**Phase 7 — verdict and stop.**

1. **Run the shared hold predicates.** `bin/lib/pr-armable.sh` is **sourced, not executed** — it carries no `set -euo pipefail` at file scope by design. Reuse its six predicates rather than re-deriving any hold logic:

   `git show <MASTER_SHA>:.claude/skills/pr-ready/scripts/holds.sh > /tmp/pr-ready-holds-<N>.sh && test -s /tmp/pr-ready-holds-<N>.sh && bash /tmp/pr-ready-holds-<N>.sh <N> <slug> | tee /tmp/pr-ready-holdsout-<N>.txt`. The script is that single invocation, and prints exactly six labelled lines — fewer means a predicate aborted, itself a finding. The `tee` is what step 2's verdict marker reads: a value captured in one Bash call does not survive into the next, so the holds reach the marker through the file, never through a variable.

   Report each predicate's result as one line in the verdict. These are **advisory inputs to the human's merge decision** — `/pr-ready` never arms auto-merge and never merges.

2. **Post the sticky verdict.** Marker, placed as the **last line of the body** so an update matches:

   `<!-- pr-ready-verdict -->`

   **First write the composed comment body to `/tmp/pr-ready-verdict-<N>.md` with the `Write` tool.** The path is keyed to the PR number for the same reason Phase 2a's is: a `tmpfile=$(mktemp)` assigned in one Bash call is gone by the next one, so the post below would send an empty `--body-file`. Compose the body in full, write it, then run the post.

   **Before composing, materialise the digest lines.** The Phase 6 agent wrote them into
   `/tmp/pr-ready-phase6-verdict-<N>.md` under a trailing `## DIGEST` section; `digest.sh`
   extracts them, normalises them, and prints exactly five labelled lines:

   `git show <MASTER_SHA>:.claude/skills/pr-ready/scripts/digest.sh > /tmp/pr-ready-digest-<N>.sh && test -s /tmp/pr-ready-digest-<N>.sh && bash /tmp/pr-ready-digest-<N>.sh /tmp/pr-ready-phase6-verdict-<N>.md > /tmp/pr-ready-digest-lines-<N>.txt && cat /tmp/pr-ready-digest-lines-<N>.txt`

   The trailing `cat` is load-bearing: without it the five lines sit on disk and never enter
   context, so there is nothing to paste into the `Write` call below. `digest.sh` exits 0 on
   every degrade path and emits five `unavailable — <reason>` lines rather than failing, so
   this chain does not abort the run when Phase 6 was skipped or wrote no `## DIGEST` section.

   There is no helper in `bin/lib/` for this, so use the find-and-update-else-create shape from `bin/pr-canary-check` (see its `STICKY_MARKER` constant and the `post_sticky()` below it — grep the symbols rather than trusting a line number, which drifts).

   `git show <MASTER_SHA>:.claude/skills/pr-ready/scripts/post-verdict.sh > /tmp/pr-ready-post-<N>.sh && test -s /tmp/pr-ready-post-<N>.sh && bash /tmp/pr-ready-post-<N>.sh <N> <slug>`

   Comment body sections, in order: **rebase result** (the master SHA used, conflicts resolved), **CI result**, **files-changed refresh** (the Phase 5.9 `FILES-CHANGED:` line verbatim — `REPLACED` / `APPENDED` / `UNCHANGED` / `AMBIGUOUS`, with its file count and `+added -removed` delta; on `AMBIGUOUS`, state that the body was left untouched and that the markers need repair), **plan-fidelity verdict**, **merge digest** (the `### Merge digest` block, below), **remediation** (what Phase 6.5 fixed, each backlog item filed with its file and ID, anything left `not fixed — filed`, and the post-remediation CI result), **hold predicates**, and one explicit **READY / NOT READY** line — the last reflecting the state *after* remediation, not the Phase 6 findings. If any include was loaded by the declared fallback rather than from the pin, say so here — one `include-source:` line — so the verdict states which revision of its own procedure it followed. The files-changed block reflects the diff as of Phase 5.9. If Phase 6.5 pushed remediation commits after it, say so on the refresh line — the block is one commit behind by design, and the next `/post-plan` body write regenerates it. Never open a second body edit to catch it up.

   **`### Merge digest` block — fixed shape.** Emit the literal heading `### Merge digest` on
   its own line, immediately after the plan-fidelity verdict section and immediately before the
   remediation section, followed by **exactly five** lines in this order, each starting with its
   bold label: `**What changed:**`, `**Why:**`, `**Watch:**`, `**Touches:**`,
   `**Machine-authored fixes:**`. Paste the five lines from
   `/tmp/pr-ready-digest-lines-<N>.txt` verbatim — do not re-word, re-order, merge, or add a
   sixth line. A sibling parser reads this block positionally, so the shape is a contract, not a
   style preference. If the file is absent or empty (the materialise chain above did not run, or
   produced nothing), emit the heading anyway with all five labels carrying the body
   `unavailable — digest script did not produce output`; a stable-shaped degraded block is
   required, an omitted block is not acceptable. `<!-- pr-ready-verdict -->` remains the last
   line of the body, after the READY / NOT READY line — the digest never displaces it.

   **Then emit the machine-readable verdict marker — the last tool call before the STOP terminator; the detached review-owed fire in step 3 is the sole exception.** The comment is posted; this publishes the same verdict in a form `bin/pr-ready-now` can read without parsing prose. Pick the token mechanically from the READY / NOT READY line just written — no judgment: `NOT-READY` if that line says NOT READY; `READY-WITH-NOTES` if it says READY **and** any hold predicate printed something other than `(clear)` or Phase 6.5 left anything `not fixed — filed`; `READY` otherwise.

   `git show <MASTER_SHA>:.claude/skills/pr-ready/scripts/verdict-marker.sh > /tmp/pr-ready-vmark-<N>.sh && test -s /tmp/pr-ready-vmark-<N>.sh && bash /tmp/pr-ready-vmark-<N>.sh <N> <TOKEN>`

   It writes `/tmp/pr-ready-marker-<N>.txt` and prints one line beginning `PR-READY-VERDICT-MARKER-V1|`. **Your final message must end with that line, copied verbatim as its last line.** This is not decoration: `bin/pr-ready-now` runs this skill as `claude -p` in default text mode, which captures **only** your final assistant message into the log, so a marker printed only by the Bash step above reaches the file but never the log. The file is the primary channel and that last line is the fallback; emit both. Everything else you would normally say goes above it.

3. **STOP — hard terminator.** The run ends at the posted-or-updated comment. No merge. No auto-merge arming. No `/backlog-housekeep` chain beyond the row and `last_verified` bump Phase 6.5 already filed. No `/post-plan` chain. No worktree teardown. No second comment. The user reviews every PR deliberately. One amendment: after the verdict comment is posted, when Phase 6 determined a structured code review is owed and no `/pr-review` slot is already live for this PR, fire it detached — `git show <MASTER_SHA>:.claude/skills/pr-ready/scripts/review-owed.sh > /tmp/pr-ready-owed-<N>.sh && test -s /tmp/pr-ready-owed-<N>.sh && bash /tmp/pr-ready-owed-<N>.sh <N>` — and record the printed `REVIEW-OWED:` line. That script reads the `REVIEW-COVERAGE:` marker Phase 6 wrote and fires only on `NONE`, `STALE` or `UNKNOWN`, never on `CURRENT`. This is the one new permitted action; everything the invariant still forbids stays forbidden — no merge, no auto-merge arming, no `/backlog-housekeep` chain beyond the row and `last_verified` bump Phase 6.5 already filed, no `/post-plan` chain, no worktree teardown, no second comment. The fire is detached and fire-and-forget: never wait on it, never read its log, never let it extend this run. `scripts/review-owed.sh` is the only channel — `/pr-ready` carries `disallowed-tools: [EnterPlanMode, ExitPlanMode, Skill]` and cannot call `Skill` at all, so the launcher is reached through Bash.
