---
description: Post-plan engine internals — compiled harness vs. Sonnet skill fallback, what `--auto`'s skip gate does, and where the auto-merge arming decision is made. Lazy companion to workflow-continuity.md; loads only when a post-plan surface is in play.
last_verified: 2026-10-24
paths:
  - ".claude/skills/post-plan/SKILL.md"
  - ".claude/skills/ship/SKILL.md"
  - "tools/postplan-harness/**"
no-adr: lazy-load companion relocating existing guidance out of workflow-continuity.md behind a glob; introduces no new decision surface, mechanism, or default — exactly mirrors agent-tiering-detail.md and work-triage-detail.md
---

# Workflow Continuity — Post-Plan Detail

Companion to `.claude/rules/workflow-continuity.md` § Post-Plan. That rule carries the
triggers. This file carries the internals. Read it before debugging or changing a
post-plan run.

## Engine

`bin/post-plan-now --auto` spawns a detached, launchd-supervised post-plan run on the current
branch; it survives you closing Claude Code. Engine selection:

- **Compiled post-plan harness** (`tools/postplan-harness`) when present — a
  deterministic sequencer with bounded LLM calls. `bin/post-plan-now` pins the MAIN-CHECKOUT
  copy, never the worktree's (ADR-0092).
- **Fallback:** a fresh **Sonnet 5.5** `/post-plan` skill session, used if the harness
  fails or is absent. `POST_PLAN_SKILL=1` forces the skill path.
- **No partial resume.** The harness runs Phase 5.5's plan-intent review itself
  (`harness/fidelity.py`), posts the sticky `<!-- pr-ready-verdict -->` comment, and feeds the
  verdict to arming condition (12). It exits **0**, **1** or **3** only. There is no exit 4 and
  no Phase-5.5 re-entry. Any harness failure outside exit 3 re-runs the **full** skill from
  Phase 0. Exit **3** is the fail-closed sentinel and suppresses the skill fallback entirely.
  These reach it: a rebase conflict, a **diverged remote head** (the PR branch was rewritten
  on GitHub), a **local gate denial** (a `bin/pre-commit-hook` or `bin/pre-push-adr-hook`
  refusal), a **usage limit** (`llm-usage-limit`),
  an **expired login** (`llm-auth-expired`), and a **hung step**: `subprocess-timeout` (a
  bounded step, including every harness `git` call, past its timeout) or `precommit-hang`
  (`bin/pre-commit-hook` past 300 s). The `RESULT:` line names the step. When
  `bin/post-plan-fleet`'s 90-minute timeout fires, its DM names the last step started.
  A skill re-run would hit the same wall, so the run stops for a human. After a usage limit,
  re-run `bin/post-plan-now` once it resets. After an expired login, run `claude`, `/login`,
  then re-run it.
  A conflict on a generated file flagged `regen_on_conflict` in the ladder's `GENERATED_FILES`
  registry skips the LLM resolver. Two entries carry the flag: `ibl5/phpstan-baseline.neon` and
  the glob `ibl5/tests/e2e/smoke/visual-regression.spec.ts-snapshots/*.png` (that one directory
  only). For a VR PNG both sides modified, the harness takes master's copy with no generator run;
  a modify/delete conflict still goes to the resolver. The branch's screenshot is dropped on
  purpose. Visual Regression stays red until a human applies the `update-baselines` label, which
  regenerates it in CI. The harness never adds that label. For the baseline,
  once every other conflicted file resolves, the harness takes master's copy, runs
  `bin/regen-baselines` on the merged tree and commits the output in the merge commit. It then
  rewrites that file's section of the lost-work pre-patch to the master-to-merged diff, so the
  proof checks every other file unchanged. A generator that fails, times out or writes a file
  outside its registry outputs declines with `resolver declined: <path>`, the same exit-3 reason
  as before. `conflict_rung` reads `regen` when no file needed the resolver.
  Before a rebase conflict reaches exit 3, a first resolution that fails the lost-work proof
  goes through the conflict ladder in `tools/postplan-harness/harness/conflict_ladder.py`.
  Rung 1 takes master's copy of each generated file and reruns its generator, with no model
  call. Rung 2 retries the resolver once on Opus with the exact LOST lines. Rung 3 runs one
  headless `claude -p` session capped at $5, 20 minutes and 80 turns. The harness re-runs the
  unchanged proof after every rung, and `result.json` records the outcome in `conflict_rung`.
  Rung 4 runs one headless Sonnet 5.5 session capped at $5, 25 minutes and 100 turns on the same merge commit (`tools/postplan-harness/harness/conflict_fix.py`). It may edit the conflicted files, the branch's own files, and test files master added under `ibl5/tests/`, `bin/test-*` or `tools/postplan-harness/tests/`. The harness amends the merge commit and re-runs the proof. It runs at most once per branch head and master tip. The marker is a `<key>.sonnet-fix.json` file in the harness state dir, `tools/postplan-harness/out/state/`. The session cannot push, commit, fetch, run gh, or move any ref, and a moved ref or HEAD fails the rung. When it ran and the proof still failed, `blocked-ship.txt` adds a `Headless Sonnet fix:` line and the session's summary labelled as untrusted text.
  On exit 3 the harness writes a plain-words block to `blocked-ship.txt` in its run dir: what stopped the ship, the offending paths when the hook output names them, and numbered copy-paste fix commands ending in `bin/post-plan-now`. `bin/post-plan-now` prints that block between `=== post-plan blocked ship ===` marker lines and prints a plain block of the same shape when the file is missing. The DM below carries the same block. `bin/automouse/run` copies the block into the skip report. When a command failed, the one-line `RESULT:` verdict and the block name it and quote the last few lines of its error, with credentials redacted. When none failed, they name the stage where the run stopped. The block ends with a paste line, `<main checkout>/bin/postplan-fix <PR|slug>`, which opens an interactive Claude session in the worktree with the failing stage, the error text, and the log paths in its opening prompt. It does not ship. With `--headless` it instead fetches, merges `origin/master`, runs one Sonnet 5.5 session with the rung's denied-tool list to resolve the markers and fix tests, commits the merge locally, and prints the `bin/post-plan-now` paste line. It never pushes. `--headless --dry-run` prints the argv and prompt without touching git. The post-plan-fleet failure DMs and the pr-cycle-tick retry-cap DM end with the same line.
  `bin/post-plan-fail-dm` sends the DM. With no live interactive Claude session in the
  worktree it DMs at once. Headless `claude -p` sessions do not count. With an interactive
  one, it holds the DM 15 min and sends it only if nobody re-fired the branch.

On the commit path that detection is structural: any non-zero `git commit` is treated as a
gate denial, so a hook message nobody enumerated in `_LOCAL_GATE_MARKERS` is still caught.
`bin/post-plan-now` additionally exits **6** from its own in-flight duplicate-run guard.
It is caller-side: it refuses to start a second supervised run for a slug whose launchd job is still loaded, and `--force` bypasses it.
It also exits **7** when run from the main checkout (ADR-0062). Nothing was fired and the
skill fallback is suppressed. `cd` into the target worktree and re-run.
It exits **8** when it declines a repeat hold: the last run for this slug held on the same
structural reasons, the repeat DM was already sent, and plan, diff and harness are unchanged
(`harness/holdrepeat.py`). Nothing was fired. `--force` overrides.
The harness also declines inside a run, after the conflict probe and before its first LLM
call, when the diff changed but condition (3)'s `MISSING:` and `MISSING-FILE:` items equal
the ones the last hold recorded. A diff that never adds the missing files cannot clear that
hold, so a fresh review would only spend tokens. The run ends with terminal
`hold-repeat-declined`, exits **0** so no skill fallback starts, and opens no PR.
It prints `RESULT: post-plan DECLINED` with this phrase:
`declined: same hold as last run (<reason>), no tokens spent`.
It DMs once per recorded hold. A strict subset (some files added) or a new
missing item runs normally, and so do holds on conditions (7), (8) and (13).
A hold whose condition (3) items are only `UNREALISED-ASSERTION: row` rows gets the same
early decline. The harness re-runs `bin/lib/plan-matrix-assertions` on the pre-merge diff and
declines only when the row set equals the recorded one, the worktree is clean, a fresh fetch
of `origin/master` succeeds, and no missing token from the script's stderr appears in
`origin/master`. Any of those failing means the run proceeds, because a pushed test, an
uncommitted edit or a master merge can realise a row. A hold that mixes MISSING items with
UNREALISED rows keeps the MISSING rule above.
`bin/post-plan-now --force` passes `POSTPLAN_FORCE=1` to the harness and skips this check
too; every other launch passes `POSTPLAN_FORCE=0`.
`bin/post-plan-now --state-changed` (and `--rebase-only`, which implies it) passes `POSTPLAN_STATE_CHANGED=1` the same way, so the harness logs `phase2 early hold-repeat: skipped (POSTPLAN_STATE_CHANGED=1)` and proceeds without writing the marker; every other launch passes `POSTPLAN_STATE_CHANGED=0`, and only the exact value `1` skips.
The in-run decline also writes a held-unfixable marker through `harness/heldmarker.py`:
`tools/postplan-harness/out/state/<slug>.held-unfixable.json` in the main checkout, which
`.gitignore` already covers. It stores a hash of condition (3)'s MISSING items and
UNREALISED-ASSERTION rows plus the plan file's mtime. For a hold with UNREALISED-ASSERTION
rows it also stores the branch head SHA and the rows' missing tokens. That marker stays
active only while the head SHA matches, the worktree (when checked from one) is clean, and a
fresh `origin/master` carries none of the tokens. `bin/post-plan-now` reads the head from the
worktree and `bin/pr-cycle` reads it from `headRefOid`. An unknown head means proceed.
Markers written before schema version 2 read as inactive. While the hash and mtime still
match the live hold record, `bin/post-plan-now` exits **8**
before launching anything, and `bin/pr-cycle` Stage 1 skips the PR so its `--max-ready`
slot goes to the next one. The decline DM names the marker. Editing the plan makes it
stale, and so does a later run that arms or holds on a different reason. To clear it by
hand, delete the file. `--force` and `--state-changed` bypass it for one run, and a forced
run that holds on the same reason keeps it. Automouse treats exit **8** as terminal, so a
decline in its resume path never trips the postplan env-breaker. The marker only
suppresses re-runs. It never ticks a matrix row, arms a PR or changes an arming condition.

**Rebase-only mode.** `bin/pr-cycle` fires a DIRTY PR with `--rebase-only-prs=<N>` when the
PR's last hold record names only conditions (3), (7) and (8) and its patch-id still equals the
sticky `**Reviewed diff:**`. `PR_CYCLE_REBASE_ONLY_HELD=0` turns this off. The flag reaches the
harness as `--rebase-only` through `bin/post-plan-fleet` and `bin/post-plan-now`, where it also
implies `--state-changed`. The harness re-checks the record with
`python3 -m harness.holdrepeat rebase-eligible` and runs the full path when the record is
absent or names any other condition. An eligible run merges `origin/master` with the usual
conflict resolver and lost-work proof, pushes, and ends with terminal `rebased-held` and exit
**0**. It runs no review, fidelity or manual-test call, never posts the sticky verdict and never
arms. A failed proof stops before the push. When the patch-id is unchanged, the run refreshes
the hold record's fingerprint so the next cycle still declines. A changed patch-id leaves the
record alone, and the next cycle reviews the new tree. A failed rebase-only run never falls
back to the skill. The fleet outcome word is `rebased`, and `bin/pr-cycle-tick` logs it
without charging an attempt. The same tick also removes a `post-plan-running` label that is at least `PCW_STALE_LABEL_MIN` minutes old when no post-plan run for the PR is alive, so a run that died stops holding the PR out of the merge line; `bin/pr-cycle-tick --dry-run` shows what it would remove. When a PR's only failure is the `All checks green` gate timing out on anchors that have since gone green on the same head, the tick runs `gh run rerun <id> --failed` in place of a rescue, at most `PCW_GATE_RERUN_CAP` (2) times per head SHA, and the gate's fail-closed check is unchanged (ADR-0167, addendum `gate-timeout reruns`).

## What `--auto` adds

`--auto` adds exactly one safety gate; a bare `bin/post-plan-now` skips it, because running it by
hand IS the decision to ship. The gate: **skip** when already inside a headless/automouse
run — the automouse runner fires post-plan itself.

It does **not** hold post-plan for a "risky" plan. Post-plan **always** runs and opens
the PR. Whether the PR then **auto-merges** is decided at `/post-plan` Phase 6.5, which
honors a plan's `auto_merge: false` (see `/plan` Step 4) and otherwise leaves the PR open
for a human to merge.
A plan seeded by `bin/docfix-run` carries `auto_merge: docs-only-ready` instead; Phase 6.5 arms it only when the realized PR is a docs-only refresh with a two-way merge-danger line and a READY verdict, and holds it otherwise with the failed clause named.

## Ad-hoc branches

An ad-hoc branch with no plan file makes post-plan run **plan-blind**. That is expected,
not an error. The merge-arming decision still happens at `/post-plan` Phase 6.5, so
auto-opening the PR never auto-merges a `feat:` / `auto_merge: false` / visual PR without
human signoff.

The per-branch arming marker is `$(git rev-parse --absolute-git-dir)/postplan-ready`. Only
`~/.claude/hooks/auto-commit-reminder.sh` reads it, using it to decide which nudge to show
at turn-end: unarmed tells the model to commit and, if the work verified clean, end its reply with the `cd <worktree> && bin/post-plan-now` paste line; the model must not run `bin/post-plan-now` or /post-plan, as the branch is unarmed (unless the creator-session marker below matches); armed shows `bin/post-plan-now --auto`. Nothing else
watches the marker or fires post-plan when it appears. The arming requirement was added after
PR [#2340](https://github.com/a-jay85/IBL5/pull/2340) (2026-09-22), where a post-plan nudge
caused a model to ship half-done ad-hoc work.

The creator-session marker is `$(git rev-parse --absolute-git-dir)/wt-new-session`. `bin/wt-new`
writes `$CLAUDE_CODE_SESSION_ID` into it when it creates a new branch. It writes nothing for a
human run or for `--existing`, whose branch can carry earlier work. The same hook reads it: when
the marker matches the Stop payload's `session_id`, the nudge says ship with
`bin/post-plan-now --auto` once the whole task verified clean. Any other session gets the hold
text. The marker is session-scoped by content, so a later session in the same worktree never
inherits the ship default the way it would inherit `postplan-ready`. Added 2026-09-25.

## No deadlock

`/post-plan` resolves the **highest-numbered** plan variant for the branch slug (`<slug>-3.md`
beats `<slug>-2.md` beats `<slug>.md`; a non-numeric suffix like `-shared-context.md` is never a
variant), so no handoff file is needed, and the detached child reparents under launchd and clears
its own plan-gate independently. Override the selection with `bin/post-plan-now --plan <abs-path>`.

When no `<slug>.md` exists at all, resolution falls back once to a **slug-drift** match —
exactly one `<prefix>-<slug>.md` (the shape `/plan` produces when the plan filename and the
branch name disagree). An adopted drift match HOLDS auto-merge for that run via arming
condition (13), because adoption is a guess; two or more candidates is ambiguous and stays
plan-blind.
