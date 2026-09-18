---
description: /post-plan Phase 2 — resolve a rebase conflict, prove no work was lost, and arm the conflict hold. Loaded only when the Phase 2 rebase block prints STOP-AND-RESOLVE.
last_verified: 2026-09-18
paths:
  - .claude/skills/post-plan/SKILL.md
  - .claude/skills/pr-ready/_rebase-and-conflicts.md
  - .claude/skills/pr-ready/scripts/lostwork.sh
  - .claude/skills/pr-ready/scripts/collapse-guard.sh
  - .claude/rules/linear-history-squash-merge.md
---

# /post-plan Phase 2 — Rebase Conflict Resolution

Loaded **only** when the Phase 2 rebase block printed `STOP-AND-RESOLVE:`. On a clean or
already-rebased run this file is never read.

`<MASTER_SHA>`, `<KEY>` and `<BRANCH>` are **literals the orchestrator substitutes** — the
SHA and `key=` value printed by the Phase 2 pre-rebase capture block and recorded as run
notes there. They are never shell variables: nothing survives between Bash blocks except
exported env vars (same discipline as `.claude/skills/post-plan/_phase-5.5-fidelity.md`).

**No command substitution** — no `$(…)`, no `<(…)` — in any block below. That is the same
constraint that pushed `/pr-ready` to externalize its `scripts/*.sh`; a substitution inside
a worktree-scoped resolution block is the shape that silently runs against the wrong tree.

---

## Step 1 — Load the guide and the two scripts by pinned SHA

Reuse is by `git show`, never by copy (the pattern established in
`.claude/skills/post-plan/_phase-5.5-fidelity.md`). `.claude/skills/pr-ready/_rebase-and-conflicts.md`
is present in only 1 of 34 worktrees, so a plain `Read` of the worktree path fails for
essentially every run, while a pinned-SHA read always succeeds.

```bash
git show <MASTER_SHA>:.claude/skills/pr-ready/_rebase-and-conflicts.md > /tmp/post-plan-rebase-guide-<KEY>.md \
  && git show <MASTER_SHA>:.claude/skills/pr-ready/scripts/lostwork.sh > /tmp/post-plan-lostwork-<KEY>.sh \
  && git show <MASTER_SHA>:.claude/skills/pr-ready/scripts/collapse-guard.sh > /tmp/post-plan-collapse-guard-<KEY>.sh \
  && test -s /tmp/post-plan-rebase-guide-<KEY>.md \
  && test -s /tmp/post-plan-lostwork-<KEY>.sh \
  && test -s /tmp/post-plan-collapse-guard-<KEY>.sh \
  && echo "REBASE-GUIDE=loaded"
```

The `test -s` chain is the fail-closed arm: `git show` on a path that moved exits non-zero
and leaves a zero-byte file. **No `REBASE-GUIDE=loaded` ⇒ halt with a `STOP:` line.** Do not
improvise a resolution from memory.

## Step 2 — Read the guide, selectively

Read `/tmp/post-plan-rebase-guide-<KEY>.md`. Apply **§2c** (hook-safe squash), **§2d**
(`--onto` rebase) and **§2e** (three-way resolution).

- **Skip §2b entirely.** It hands the rebase chore to a `/pr-ready` sub-agent packet, which
  assumes that skill's Phase 0 worktree entry and an interactive turn boundary. post-plan is
  already in the worktree and runs headless under `claude -p`, where work handed off past the
  turn boundary is unrecoverable. The orchestrator does §2c/§2d/§2e itself, inline.
- **Skip §2a's re-capture.** The Phase 2 pre-rebase capture block in
  `.claude/skills/post-plan/SKILL.md` already performed it, at the only moment it was still
  pre-rewrite. Re-running it now would overwrite the pre-diff with post-conflict state and
  make the proof in step 6 vacuous. Read §2a for rationale only.

## Step 3 — Collapse guard, before any `--onto`-shaped rebase

An `--onto` rebase is the one shape that can silently drop commits, and this guard is the
only thing that notices a *prior* run already did so. Run `check` first:

```bash
bash /tmp/post-plan-collapse-guard-<KEY>.sh check <KEY> <BRANCH> ; echo "CG-CHECK-RC=$?"
```

- `STOP: PRIOR-COLLAPSE-DETECTED` (exit 1) **halts the run.** Follow the rescue-branch
  instructions the script prints; never rebase on top of an already-collapsed history.
- `COLLAPSE-GUARD: NO-PRIOR-REWRITE`, `NO-COLLAPSE`, and `WARN — partial loss…` (all exit 0)
  proceed. **Record the `WARN` text in a run note** — it becomes part of the sticky PR
  comment posted at the top of Phase 6.5.

Then, immediately before the rebase:

```bash
bash /tmp/post-plan-collapse-guard-<KEY>.sh record <KEY> <BRANCH> ; echo "CG-RECORD-RC=$?"
```

`record` writes the `.meta` file and echoes `COLLAPSE-GUARD: RECORDED <sha> -> <meta>`, which
is what gives a *future* run a recorded head to compare against. `<KEY>` is the sanitized
slug from the capture block; the script sanitizes the branch itself via `tr '/' '-'`.

## Step 4 — Re-run the rebase in the `--onto` form

The Phase 2 conflict arm already ran `git rebase --abort`, so the tree is clean and history
is untouched.

> **Squash trap.** If this branch was stacked on a now-merged parent: `master` is linear, so
> the parent's SHAs never landed in it, and a plain `git rebase origin/master` tries to replay
> the parent's commits too — which conflicts every time. Replay only your own commits with
> `git rebase --onto origin/master <parent-tip-before-merge> <branch>`. See
> `.claude/rules/linear-history-squash-merge.md`. Determine `<parent-tip-before-merge>` from
> the `Depends-on:` parent's pre-merge tip, not by guessing a merge-base.

For the ordinary (non-stacked) case use the pinned-SHA form, matching §2d:

```bash
git rebase --onto <MASTER_SHA> <merge-base> <BRANCH>
```

Pinning to the recorded literal rather than to `origin/master` guarantees Phase 4 review,
Phase 5.0 conformance and Phase 5.5 fidelity all judge the same base even if master moves
mid-run.

## Step 5 — Resolve three-way, per §2e

Inspect each conflicted path with `git show :1:<path>` (base), `:2:<path>` (ours),
`:3:<path>` (theirs). **Never** take `--ours` / `--theirs` wholesale unless every hunk in
that file genuinely takes that side. `git add` each resolved path, then:

```bash
GIT_EDITOR=true git rebase --continue
```

**Record every resolved path and a one-line description of each resolution in a run note.**
Conflict-resolution output is new code that no code review has ever seen, and the sticky
comment in Phase 6.5 must name those files.

If the rebase re-conflicts on a later commit, repeat this step. If a resolution is genuinely
ambiguous, `git rebase --abort` and halt with a `STOP:` line rather than guessing.

## Step 6 — The lost-work proof, as a precondition for the push

```bash
bash /tmp/post-plan-lostwork-<KEY>.sh <KEY> ; echo "LOSTWORK-RC=$?"
```

**This is a gate, not a report.** The push in `SKILL.md` Phase 2 step 3 is permitted **only
if** this prints `TREE-EQUIVALENT` **and** `LOSTWORK-RC=0`. Any other outcome —
`TREE DIVERGED — inspect before pushing`, a non-zero rc, or no output at all — halts the run
with a `STOP:` line naming the script and both patch paths. Every failure path inside
`lostwork.sh` emits `TREE DIVERGED`, but only the early guards (missing arg, absent or empty
patch, failed `git apply --numstat`, empty pre-numstat) also exit 1 — the final
differing-numstat branch prints `TREE DIVERGED — inspect before pushing` and exits **0**.
That is exactly why this check is conjunctive on the printed verdict and not on the rc alone:
gating on `LOSTWORK-RC=0` by itself would wave the commonest divergence straight through.

Reviewing a resolution *after* it shipped is exactly what this replaces: an unreviewed
resolution that dropped a hunk would otherwise reach master through auto-merge.

<!--
Reviewer note on the /tmp namespace: the pre-side patch is written as
pr-ready-diff-pre-<KEY>.patch on purpose. That prefix is hardcoded inside lostwork.sh and
cannot be parameterized without editing /pr-ready, which is out of scope here; and <KEY> is a
non-numeric branch slug, so it can never collide with the PR-number keys a concurrent
/pr-ready run uses. This is NOT the PR-number-keyed verdict namespace that
_phase-5.5-fidelity.md warns post-plan away from.

The conflict-resolution verdict is written to
/tmp/postplan-conflict-verdict-<KEY>-<POST_RESOLUTION_SHA>.ok, keyed by branch slug plus
commit sha. It never uses a PR number, so it cannot collide with the /tmp/pr-ready-verdict-
namespace a concurrent /pr-ready run owns. The /pr-ready scripts/post-verdict.sh script is
never called for this artifact.
-->

## Step 7 — Confirm the hold flag, and write the resolution manifest

The hold flag for this run was already created by the Phase 2 conflict arm in `SKILL.md`, at
the moment the conflict was *detected*. **It is deliberately not written here.** Arming the
hold only after a successful `TREE-EQUIVALENT` would invert the fail-closed property: a run
that conflicts and then dies mid-resolution — or is re-driven from a later phase — would find
no flag and could arm auto-merge on a partially-resolved tree. The flag means "this run
touched a conflict", which is true from detection onward, not "the resolution succeeded".
Confirm it exists; if it does not, halt with a `STOP:` line rather than creating one.

What *is* written here, after `TREE-EQUIVALENT`, is the manifest the announcement consumes:

```bash
git diff --name-only <MASTER_SHA>...HEAD > /tmp/postplan-conflict-files-<KEY>.txt
test -s /tmp/postplan-conflict-files-<KEY>.txt && echo "CONFLICT-MANIFEST=written"
echo "POST_RESOLUTION_SHA=$(git rev-parse HEAD)"
```

The value printed as `POST_RESOLUTION_SHA=<sha>` is recorded once and substituted as the literal `<POST_RESOLUTION_SHA>` in step 7.5 and in the Phase 7 re-arm gate. Shell variables do not survive block boundaries, so it is never re-derived in a later block of this file.

The sha names the tree the resolution produced. If commits land after this point, the verdict written in step 7.5 no longer covers `HEAD`, and Phase 6.5 condition (14) holds. This matches the "covering the current `HEAD` tree" property condition (12) already requires of the Phase 5.5 fidelity verdict: a tree that was not reviewed does not inherit a clearance.

If `git rev-parse HEAD` fails or prints nothing, the orchestrator has no literal to substitute. Step 7.5 must then halt with a `STOP:` line rather than writing to a path containing an empty sha component.

Then, with the **`Write` tool** (not a heredoc — the resolved-path list and descriptions come
from run notes, and a `mktemp` variable does not survive between blocks), write
`/tmp/postplan-conflict-resolution-<KEY>.md` containing:

- each resolved path from step 5, one line apiece, naming which side the resolution took and why;
- any `COLLAPSE-GUARD: WARN` text recorded in step 3.

Phase 6.5 renders this into the sticky PR comment.

## Step 7.5: Review the resolution and write the verdict

**Preconditions.** All three must hold before spawning the reviewer:

- `TREE-EQUIVALENT` was printed in step 6.
- `CONFLICT-MANIFEST=written` was printed in step 7.
- `POST_RESOLUTION_SHA` is a non-empty sha string from step 7.

If any precondition is missing, halt with a `STOP:` line and do not spawn.

The lost-work proof in step 6 answers whether anything was dropped. This step answers a separate question: whether the resolution that survived is semantically correct.

Spawn exactly one sub-agent: `subagent_type: "sonnet-4-6"`, `model` omitted (the in-repo def pins Sonnet 4.6), `run_in_background: false`. One spawn, no retry loop.

The reviewer's inputs are limited to:

- `/tmp/postplan-conflict-files-<KEY>.txt`: the manifest of resolved paths.
- `/tmp/postplan-conflict-resolution-<KEY>.md`: the descriptions written in step 7.
- The conflicted hunks in each listed path.

The reviewer reads only these three sources. Phase 4 reviews the full PR diff and runs later; this step is scoped to the conflict resolution only.

The reviewer answers one question per resolved path: did the three-way merge keep the intent of both sides? Name a finding when:

- A resolution picked one side's version over a behavioral change the other side made to the same code.
- A resolution merged two edits into a state neither side would accept.
- A `COLLAPSE-GUARD: WARN` entry from step 3 is not explained by the manifest.

The reviewer writes the verdict with the `Write` tool to:

```
/tmp/postplan-conflict-verdict-<KEY>-<POST_RESOLUTION_SHA>.ok
```

Line 1 must be exactly `CONFLICT-REVIEW=CLEAN` or `CONFLICT-REVIEW=FOUND-PROBLEM`, bare: no leading marker, no trailing prose. Condition (14) in `SKILL.md` compares line 1 by exact string equality, so `CONFLICT-REVIEW=CLEAN (all paths)` reads as not-clean and holds.

A `FOUND-PROBLEM` verdict is written to the same `.ok` path. The `.ok` suffix names the artifact slot; the first line carries the verdict. A future editor reaching for `test -f` on this path would fail open on a negative review: read line 1.

### Findings from the conflict-resolution review

For a clean verdict: one bullet per reviewed path confirming the resolution kept both sides' intent.

For `FOUND-PROBLEM`: a `## Findings` list, each entry naming the path and what the resolution lost.

After the sub-agent returns, print `CONFLICT-REVIEW=CLEAN` or `CONFLICT-REVIEW=FOUND-PROBLEM` into the run log and carry the findings forward for the Phase 6.5 step 0 sticky render.

On `FOUND-PROBLEM`: continue into step 8 and open the PR. Phase 6.5 condition (14) blocks, and the sticky comment renders the held branch with the findings. Do not re-resolve, do not re-spawn, do not edit the verdict file.

If the sub-agent returns without writing the file, or writes an unreadable one, print `CONFLICT-REVIEW=ABSENT` and continue. Absence holds; no recovery is attempted.

## Step 8: Return to `SKILL.md`

Proceed to Phase 2 step 3 (push) and step 4 (`gh pr create`). The PR body's files-changed
block must reflect the post-resolution diff, and per
`.claude/skills/post-plan/_pr-body-claims.md` every residual / out-of-scope bullet is
re-read against that diff before the PR is opened.

---

## Appendix — sticky conflict-hold comment body

Phase 6.5 step 0 renders this into `/tmp/post-plan-conflict-comment-<KEY>.md` with the `Write`
tool and posts it under the marker `<!-- post-plan-conflict-hold -->`. The wording is
maintained **here only**; `SKILL.md` points at this appendix rather than carrying a copy.

To select which render to post, read the first line of `/tmp/postplan-conflict-verdict-<KEY>-<POST_RESOLUTION_SHA>.ok` and compare it to the exact string `CONFLICT-REVIEW=CLEAN`. A match selects branch **(b-clean)** below. Every other outcome selects **(b-held)**: a missing file, an empty file, a first line carrying anything else, and a first line that merely starts with the token are all held outcomes. The `.ok` suffix does not indicate a clean verdict; the same path holds a `FOUND-PROBLEM` verdict when the reviewer found a problem.

**Branch (b-clean).** Render when line 1 is exactly `CONFLICT-REVIEW=CLEAN`:

````markdown
<!-- post-plan-conflict-hold -->
## Rebase conflict auto-resolved, then reviewed clean

**(a) What happened.** Rebasing this branch onto `master` conflicted. `/post-plan` resolved
the conflict automatically (three-way, per `.claude/skills/pr-ready/_rebase-and-conflicts.md` §2e)
and proved no work was lost: `lostwork.sh` reported **TREE-EQUIVALENT** against the pre-rebase
diff, which is a precondition for the push that produced this PR.

**(b) Review result.** A dedicated conflict-resolution review read every path in the resolution
manifest and found no dropped semantics on either side. The lost-work proof also passed
(`TREE-EQUIVALENT`), so nothing was silently dropped in bytes. Auto-merge is armed. The PR still
has to clear every other Phase 6.5 condition and CI before it merges.

**(c) Files the resolution touched.**

<one bullet per path from /tmp/postplan-conflict-resolution-<KEY>.md, each with the
one-line description of which side the resolution took and why>

<COLLAPSE-GUARD: WARN line from step 3, if any>

_Posted by `/post-plan`. Updated in place on re-run._
````

**Branch (b-held).** Render on every other outcome (missing file, empty file, non-CLEAN first line):

````markdown
<!-- post-plan-conflict-hold -->
## Auto-merge held — this run auto-resolved a rebase conflict

**(a) What happened.** Rebasing this branch onto `master` conflicted. `/post-plan` resolved
the conflict automatically (three-way, per `.claude/skills/pr-ready/_rebase-and-conflicts.md` §2e)
and proved no work was lost: `lostwork.sh` reported **TREE-EQUIVALENT** against the pre-rebase
diff, which is a precondition for the push that produced this PR.

**(b) Why auto-merge is held.** Conflict-resolved lines are code no structured review has seen.
`/post-plan` Phase 6.5 condition (14) therefore refuses to arm auto-merge on this PR. This is
the gate working as designed, not a failure — **merge it by hand after reviewing the files below.**

**(c) Files the resolution touched.**

<one bullet per path from /tmp/postplan-conflict-resolution-<KEY>.md, each with the
one-line description of which side the resolution took and why>

<COLLAPSE-GUARD: WARN line from step 3, if any>

### Findings from the conflict-resolution review

<when the verdict was FOUND-PROBLEM, paste the ## Findings body from the verdict file here;
when the verdict was absent, empty, or unparseable, write: the review did not produce a
readable verdict; the hold is the fail-closed default rather than a specific finding>

_Posted by `/post-plan`. Updated in place on re-run._
````

The "merge it by hand" sentence is load-bearing: a held PR with no stated remedy reads as a
bug, and the stated remedy is what turns the hold into a one-line human action.

When the Phase 7 CI-watch re-rebase loop is what hit the conflict, add one line to section (a)
naming that loop as the trigger. Same marker, same comment — no second mechanism.
