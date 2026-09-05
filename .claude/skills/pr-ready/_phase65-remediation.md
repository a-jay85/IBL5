---
description: /pr-ready runtime Phase 6.5 — fix every Phase 6 finding in-PR, commit, re-push, re-arm CI. Loaded by SKILL.md via git show at Phase 6.5.
last_verified: 2026-09-04
---

# /pr-ready runtime Phase 6.5 — in-PR remediation

Purpose: the full Phase 6.5 procedure, lifted out of `SKILL.md` so it is resident only from the turn it loads.

Read at runtime via `git show <MASTER_SHA>:.claude/skills/pr-ready/_phase65-remediation.md`.

`<MASTER_SHA>` and `<N>` below are **literals to substitute** with the values pinned in Phase 1.3 — a value captured in one Bash call does not survive into the next. `<HEAD_SHA>` is **not** a Phase 1.3 pin: step 6 captures it fresh after this phase's own push. Never carry one in from an earlier phase.

**Phase 6.5 — Remediation.**

Every Phase 6 finding gets fixed and its prevention filed, in this PR's existing worktree. This is the one amendment to the stop-at-verdict invariant; everything that invariant still forbids stays forbidden.

1. **Load the shared procedure.** `git show <MASTER_SHA>:.claude/skills/fix-and-prevent/_remediation.md` — same pin, same reason as the Phase 2 and Phase 6 includes (the `git show` include invariant in `SKILL.md`). Declared fallback, per the include-fallback clause: if `git show` fails and the file is genuinely present in this worktree, `Read` it by path and record `include-source: worktree (pin predates skill)` in the verdict. If neither source yields it, print `STOP: cannot load _remediation.md from <MASTER_SHA> or from the worktree` and stop.

2. **Prove the tree carried nothing else — before the first remediation edit.** `git status --porcelain`. If it prints anything, print `STOP: worktree dirty before remediation` followed by that output, and stop. Phase 0.3 may have entered a worktree that is a peer's active workspace; the `git add -A` in step 4 would sweep their uncommitted work into this commit and step 5 would force-push it. A clean tree here is the normal case — Phase 4.3's `scripts/push.sh` already verified that origin holds this HEAD. Run this once, here at Phase 6.5 entry; from step 3 onward the tree is dirty by design, so never repeat it.

3. **Remediate every finding.** For each Phase 6 finding — **notes as well as blocking**, across all six 6d classes — follow `_remediation.md` in **`Mode: in-PR`**. State that mode line out loud before its step 1; the procedure refuses to run without a declared mode. In-PR mode overrides `/fix-and-prevent`'s § Calibration "Out of scope" carve-outs: a finding too small to name a defect class still gets an entry, with `class: n/a — <reason>`. Never zero entries.
"Never zero entries" binds the findings Phase 6 actually emitted — it does not manufacture one. A Phase 5.9 outcome of `REPLACED`, `APPENDED` or `UNCHANGED` is a routine refresh, not a finding: it produces no remediation entry, no backlog row, and no `last_verified:` bump. Only `AMBIGUOUS` reaches this phase, as the 6d.4 finding it is, and that one does get an entry.

   - **Worktree:** this PR's existing one. Never `bin/wt-new`, never a second worktree, never a teardown.
   - **Backlog:** append the table row and the prose entry to the LIVE backlog file `_remediation.md` step 3 selects, and bump that file's `last_verified:`. Bump no other file's, and do not run the rest of the `/backlog-housekeep` chain. Consolidate findings sharing a surface into one entry.
   - **Fifth-file gate — design the handoff before you reach it, not after the denial.** `~/.claude/hooks/plan-gate-edit.sh` Check 1 **denies** the 5th distinct repo file edited on the main thread in one turn. Count the distinct files edited this turn. Before the 5th, route the remaining fixes to **one** `subagent_type: "sonnet-4-6"` sub-agent (omit `model`; `model: "sonnet"` now resolves to Sonnet 5). State the delegate's boundary out loud before spawning: **remaining code fixes and backlog-row appends only — no commit, no push, no auto-merge arming, no worktree change, and no further delegates** (flat fan-out, per the invariants). Exactly one delegate; if its share is still too large, apply the overflow rule rather than spawning a second.
   - **Overflow rule.** Fix what is clearly in scope of this PR; file the remainder as backlog rows marked `not fixed — filed`; say so in the Phase 7 verdict. A `/pr-ready` run never expands into a sweep.

4. **Reconcile the PR body Scope with the real diff, then commit — exactly one.** Step 2 already proved the tree carried nothing but this phase's own edits.

   **Before committing**, run `git diff --numstat HEAD` and compare it against every explicit **file count, line count, or diff stat** written in the PR body's hand-authored Scope prose. Step 3's remediation routinely adds files and expands test cases past the plan's estimates, so a Scope written at Phase 4 is stale by default here. Any number the numstat contradicts gets corrected in the body via `gh pr edit` **in this step** — not deferred to Phase 7, which posts a comment and never touches the body. This is the 6d.4 "PR body vs. reality" check applied to this phase's own output; leaving it stale re-creates the exact blocking finding Phase 6 just cleared. The machine-generated `<!-- files-changed:begin -->` block is out of scope — Phase 5.9 owns it.

   `git show <MASTER_SHA>:.claude/skills/pr-ready/scripts/commit.sh > /tmp/pr-ready-commit-<N>.sh && test -s /tmp/pr-ready-commit-<N>.sh && bash /tmp/pr-ready-commit-<N>.sh <N> <slug>`

   `chore:` is correct per `.claude/rules/commit-conventions.md` — a fidelity remediation plus a backlog row is invisible to a league GM. Classify by what the diff is; never retitle to route around a hold.

5. **Push — one Bash call, the Phase 4.3 shape.** Never a bare push, never a bare `--force-with-lease` (it publishes nothing on a branch with no upstream).

   ```bash
   git show <MASTER_SHA>:.claude/skills/pr-ready/scripts/push.sh > /tmp/pr-ready-push-<N>.sh && test -s /tmp/pr-ready-push-<N>.sh && bash /tmp/pr-ready-push-<N>.sh <N>
   ```

   `/tmp/pr-ready-push-<N>.sh` was already materialized in Phase 4.3, so the `git show` is a cheap no-op refresh on the ordinary path and the correct recovery on any path where Phase 4.3 did not run — re-materialising costs nothing and removes an ordering dependency.

   Verdict words are the Phase 4.3 list, unchanged. `PUSHED PR #<N> <sha>` — proceed to step 6. `STALE LEASE` — someone pushed concurrently; git rejected with `stale info` and nothing was clobbered. Stop and report it in the Phase 7 verdict; never re-read and retry with a fresh lease. `PUSH FAILED` — origin does not hold this HEAD; stop. "No error printed" is not evidence of a push.

6. **Re-watch CI on the new head — exactly one delegate.** The Phase 4.5 delegate has already returned (that is what re-invoked you), so there is nothing to stop. Run `git rev-parse HEAD` bare and record the new `<HEAD_SHA>` literal.

   **Re-materialize the watcher first — never `bash` a leftover `/tmp` copy** (the materialize invariant in `SKILL.md`). The watcher takes the SHA as an **argument**, so re-running the materialize changes nothing on the ordinary path; what it buys is the failure path. A failed Phase 4.5 `git show` leaves a **0-byte** file at that path; `bash` on an empty file exits **0** printing nothing; the packet reads a no-output run as a killed window and re-runs it, so all 6 windows burn in seconds, no verdict ever returns, and the run is timeout-killed with no verdict comment (PR #2077, 2026-09-04). Re-run the Phase 4.5 step 5 materialize **verbatim** — including the `.part` staging, which is what stops a failure from replacing a good copy with an empty one — and require `MATERIALIZED` to print. If it does not, stop and report the failed materialize in the Phase 7 verdict.

   Then spawn a second delegate on the new literal, using the **identical shape and packet** as Phase 4.5 step 5 (`Agent`, `model: "haiku"`, foreground `timeout: 600000`, up to 6 windows), whose command line is:

   ```bash
   if test -s /tmp/pr-ready-ciwatch-<N>.sh; then bash /tmp/pr-ready-ciwatch-<N>.sh <N> <HEAD_SHA> 540; else echo "STOP: watcher /tmp/pr-ready-ciwatch-<N>.sh is missing or empty"; fi
   ```

   Then end your turn. **Never** re-arm this with `Bash(run_in_background: true)`, and never with a bare foreground `Bash` call: Phase 4.5 step 5 explains why both are killed or truncated under headless `claude -p`, and this step is where a fixed run most often relapses. Passing the stale Phase 4.5 SHA here is the other failure to avoid: the `seen` gate would never fire and the run would idle to `CI TIMEOUT`. Verdict words and exit codes are the Phase 4.5 step 5 list, unchanged.

7. **One bounded staleness check, then stop.** After `CI COMPLETE`, and only when `<STRICT>` is `true`, read once:

   ```bash
   gh pr view <N> --json mergeStateStatus --jq .mergeStateStatus
   ```

   If `BEHIND`, state it in the Phase 7 verdict and stop. **Do NOT loop back into Phases 2–3.** Phase 5's 3-iteration rebase loop is a pre-Phase-6 invariant; this is a separate, deliberately single-shot check. Re-rebasing here would invalidate the fidelity review Phase 6 has already performed on a diff that no longer exists.

Then proceed to Phase 7, which posts the single verdict comment covering both the findings and this remediation.

