---
name: post-plan
description: Single orchestrator for the post-plan workflow. Runs commit/push/PR, backlog housekeeping, diff classification, code review, security audit, verification, CI monitoring, retrospective, worktree teardown, and background process cleanup as one uninterrupted sequence.
disallowed-tools:
  - EnterPlanMode
  - ExitPlanMode
  - Skill
last_verified: 2026-07-20
---

# Post-Plan Orchestrator

Execute all phases below **sequentially in a single response**. Do NOT stop, ask for input, or return control between phases.

**Phase 11 (Background Process Cleanup) is MANDATORY and must ALWAYS be the last thing you execute before ending your turn.** No phase — including Phase 9 (Retrospective) — is a valid stopping point. If you reach Phase 9 and have nothing to save, continue directly to Phase 10 and Phase 11. Ending your turn before Phase 11 leaves background processes alive, which prevents the `claude` process from exiting and triggers a stall-kill in the automouse runner.

**No background-completion re-invocation exists here.** Post-plan runs headless (`claude -p` under the automouse runner), NOT in the interactive harness. When a `run_in_background` task finishes there is **nothing that re-invokes you** — emitting `end_turn` ends the run for good. So **"Waiting for PHPUnit/PHPStan/E2E to complete" is NEVER a valid final message or a stopping point.** If you launch any background work, you MUST drain it **within the same turn** — poll `BashOutput` until every background shell reports a terminal result — before you compute that phase's status and move on. Ending the turn with a background task still alive is the exact failure that stall-kills the run and (after 3 burns) poison-pills the plan.

Phase numbers below are local to this skill. The variables computed in Phase 3 (`HAS_PHP`, `NON_CODE_ONLY`, `DOCS_ONLY`, `CSS_ONLY`, `MIGRATION_ONLY`, `HAS_MODIFIED`, `HAS_COMMENTS_IN_DIFF`, `HAS_GO`, `GO_TOUCHED`, `ENGINE_ONLY`, `GOLDEN_CHANGED`, `DIFF`, etc.) are consulted by every downstream phase to gate sub-agent launches — never recompute them locally.

## Incremental Checkpoints

After any phase that modifies files, commit and push before moving to the next phase. Never carry uncommitted work across phase boundaries — if the session dies mid-skill (usage limit, crash), the latest checkpoint is preserved on the branch and a fresh session can resume.

```bash
cd <worktree>
git add -A
if ! git diff --cached --quiet; then
    git commit -m "<scope>: <what this phase changed>"
    git push origin HEAD
fi
```

Phase 2 makes the initial commit and opens the PR. Phases that may modify files after Phase 2 (Phase 2.5 backlog housekeeping, Phase 4D follow-up fixes if review identifies real bugs, Phase 5 fix-and-rerun loops, Phase 6 test writing, Phase 7 CI fixes) MUST checkpoint before continuing. The squash-merge armed in Phase 6.5 collapses the chain.

---

## Phase 0: Fresh-Session Cost Advisory (interactive only, non-blocking)

The single exception to the "never stop or ask" rule above is that this phase may emit **one** advisory line — it still does **not** stop, wait, or return control. Run the gate, emit at most one line, then continue straight into Phase 1.

```bash
# Interactive only. In automouse/headless mode this skill is already invoked as a
# fresh `claude -p` session (the cheap path), so the advisory must NEVER fire there.
[ -n "$CLAUDE_HEADLESS" ] && echo "ADVISORY=skip-headless" || echo "ADVISORY=eligible-interactive"
```

If `ADVISORY=skip-headless`, skip this phase entirely and go to Phase 1.

Otherwise, self-assess the one thing only you can know: **did you perform a substantial implementation earlier in this same conversation** (you wrote the code across many turns and/or multiple files), rather than opening this session fresh just to run post-plan? Every phase below re-pays cache-read on that whole implementation context, so an inline run after a long session costs several times a fresh one. If so — and only if so — emit exactly this line, then continue:

> 💡 Large in-session context detected — post-plan re-reads it on every phase. To save roughly half the cost on big sessions, you can interrupt now and re-run `/post-plan` in a **fresh** session on this branch (it auto-resolves the plan from the slug and fetches the diff itself). Continuing in this session…

Do **not** wait for a response, and do **not** abort on your own — the human decides. If the implementation was trivial, or this is already a fresh post-plan-only session, emit nothing and proceed silently. Either way, fall through to Phase 1 in the same response.

---

## Phase 1: Clear Plan Gate & Locate Plan

Remove the plan workflow gate so that commits and edits within this skill are not blocked by PreToolUse hooks:

```bash
rm -f /tmp/claude-plan-active-$PPID
```

Then locate the plan backing this branch so later phases can verify the implementation against its intent. The plan is the spec; phases 4–6 check conformance to it.

```bash
# Authoritative in automouse mode: the handoff JSON's plan_file (the postplan prompt passes its path).
# Interactive fallback: branch slug -> ~/.claude/plans/<slug>.md.
SLUG=$(git rev-parse --abbrev-ref HEAD)
PLAN_FILE=""
[ -f "$HOME/.claude/plans/$SLUG.md" ] && PLAN_FILE="$HOME/.claude/plans/$SLUG.md"
if [ -n "$PLAN_FILE" ]; then
    echo "PLAN_FOUND=$PLAN_FILE"
    grep -qiE '^\s*\|.*Test type' "$PLAN_FILE" && echo "HAS_MATRIX=true" || echo "HAS_MATRIX=false"
    grep -qiE '^#+ *Security'    "$PLAN_FILE" && echo "HAS_PLAN_SECURITY=true" || echo "HAS_PLAN_SECURITY=false"
    grep -qiE 'Reuse'            "$PLAN_FILE" && echo "HAS_PLAN_REUSE=true" || echo "HAS_PLAN_REUSE=false"
else
    echo "PLAN_FOUND=none — plan-blind mode: skip every plan-conformance step below; behave exactly as before."
fi
```

When the automouse postplan prompt supplied an authoritative plan path, use it instead of the branch-slug derivation above. Record `$PLAN_FILE` and the flags. **Plan conformance is additive, never a hard dependency** — when `PLAN_FOUND=none` (any PR with no `/plan` file), every "if a plan exists" gate below is skipped and post-plan runs as it does today.

---

## Phase 2: Commit, Push & PR

If the working tree is clean and `git diff origin/master...HEAD` is also empty (nothing to ship), abort the entire skill — there is nothing to post-plan.

1. **If working tree has uncommitted changes:** stage relevant changes, review with `git diff --staged`, commit, push. Commit-type rubric: `.claude/rules/commit-conventions.md` (the single source of truth for `feat:` vs. `chore:`/`fix:`/`refactor:`/`docs:`). **Decision test for the PR/commit title:** "Would a league GM notice a new ability they didn't have before?" — Yes → `feat:`; invisible to a GM (dev tooling, a new slash command, an internal refactor) → not `feat:` (`chore:`/`refactor:`/`docs:`). **Classify by what the diff IS, never by the desired merge outcome** — `feat:` triggering the human-signoff hold is the gate working, not a cost to route around. Skip this sub-step if the working tree is already clean (user committed before invoking the skill).
2. **If no PR exists for the current branch:** create one with `gh pr create`. **Stacked PRs:** If branched from a feature branch (not `master`), use `--base <parent-branch>`. Skip if a PR already exists. **Merge-order dependency:** When this PR shares files with, or must merge after, a sibling PR that is also based on `master` (so stacking via `--base` is unavailable / fragile under squash-merge), add a `Depends-on: #<n>[, #<n>...]` line to the PR body — **on its own line** (the parser anchors to start-of-line, so an inline prose mention of the marker is ignored). Phase 6.5 condition (6) reads it and refuses to arm auto-merge until every named PR is `MERGED`, so the series cannot ship out of order. Use this rather than stacking when the repo squash-merges (a squash collapses the parent's commits, leaving a stacked child's branch carrying the pre-squash commits → conflict on auto-retarget).
3. **Manual testing in PR description:** Check the plan file for a Verification Matrix. If one exists, copy only the rows classified as `Truly-manual` into the PR's `## Manual Testing` section. If the matrix has zero truly-manual rows (or the plan says "All verification is automated"), write: `No manual testing needed — all changes are covered by unit and E2E tests.` If no plan file or no matrix exists, fall back to the original rule: list only steps requiring subjective human judgment on new or redesigned UI/UX ("does this look/feel good?", "does this flow work well?"). Production comparison and "does output still match?" are visual-regression-replaceable, not manual. Do NOT list CLI commands or script invocations — Phase 6 executes those.
4. Use Haiku agents for commit message generation if delegating

---

## Phase 2.5: Backlog Housekeeping

If the shipped work implemented or resolved a backlog item, backlog housekeeping ships in **THIS** PR — not as a follow-up. The PR exists (Phase 2), so provenance stamps can reference `#<PR>`. Running here (before Phase 3 classifies and Phase 6.5 arms auto-merge) is what puts the housekeeping edits in the diff that gets reviewed and merged.

**Detect (run once — the PR gives the correct base):**

```bash
BL_TOUCHED=$(gh pr diff --name-only 2>/dev/null | grep -E '^ibl5/docs/backlog/[^/]+-backlog\.md$' || true)
```

Also treat housekeeping as triggered if the plan file (`$PLAN_FILE` from Phase 1) declares a resolved or discovered backlog item (a `## Backlog` / tracking-doc status-update section, per `.claude/skills/plan/_architect-contract.md`). The regex **excludes** `README.md` (no `-backlog.md` suffix) and anything under `archive/` (`[^/]+` cannot cross `/`), so a README-only or archive-only diff does **not** trigger.

**If neither signal fires:** print `Phase 2.5: no backlog item resolved — skipping.` and continue to Phase 3.

**If triggered — run INLINE, no subagent.** Post-plan runs as **Sonnet 4.6** and its `disallowed-tools` includes **`Skill`**, so it **cannot** call `/backlog-housekeep`. Instead **Read `.claude/skills/backlog-housekeep/SKILL.md` and follow it inline**, taking that skill's **Sonnet/Haiku caller branch** (execute inline — a same-tier subagent spawn is pure overhead per `feedback_default_sonnet_execution`). Apply its housekeeping checklist with `#<PR>` as the `<ref>` and the PR base as the `--since` base (default `origin/master`; the PR's `baseRefName` for a stacked PR).

**Self-verify + checkpoint (mandatory):** run `bin/check-docs --since=origin/master` (substitute the PR base for a stacked PR), fix any diagnostic, then **commit + push** the housekeeping edits. Per Incremental Checkpoints, this phase modifies files, so the edits must be committed and pushed before Phase 3 — they belong in the diff that Phase 3 classifies and Phase 6.5 arms on.

---

## Phase 3: Classify Diff

Run this bash block once. It computes classification flags and writes the filtered diff to `/tmp/post-plan-diff-$PPID` (same `$PPID` pattern Phase 1 uses — stable across Bash tool calls in the session). Uses `gh pr diff` when a PR exists (correct base for stacked PRs), falls back to `git diff origin/master...HEAD` pre-PR. Every later phase consults these flags and reads the diff file — do not recompute.

> **Read `.claude/skills/post-plan/_phase-3-classify-diff.md` now and run its bash block exactly once**, then record the printed `=== Diff classification ===` summary. Those flags (enumerated in the top-of-file invariant above) are the gate inputs every later phase consults — never recompute them.

Each Bash tool call runs in a fresh shell, so the classification flags are **not** bash state you can reference later — they're output Claude records from this block's stdout and applies as gates in later phases. The filtered diff is bridged via `$DIFF_FILE` (same `$PPID` across calls).

---

## Phase 4: Code Review + Security Audit

> Phase 4 runs code review (up to four sub-agents A–D) and a conditional security audit, then scores, filters, and posts findings to the PR. Every sub-agent launch is gated on the Phase 3 flags (`NON_CODE_ONLY`, `ENGINE_ONLY`, `HAS_PHP`, `HAS_MODIFIED`, `HAS_COMMENTS_IN_DIFF`, `HAS_E2E_SPECS`, `LINES_PHP_CHANGED`) — a non-code diff skips the code agents cleanly. Phase 4 emits PR comments only; no Phase-4 output is a variable a later phase keys on.
>
> **Read `.claude/skills/post-plan/_phase-4-review-audit.md` now and follow 4A→4D in order.** It holds the PR-data fetch (4A), the Agent A/B/C/D launch gates + model tiers + Agent-D diff pre-slice (4B), the security-audit agent (4C), and the score/filter/post procedure including the `bin/lib/post-review-findings.sh` sourcing (4D).

---

## Phase 5: Final Verification

### Phase 5.0: Plan→test & Plan→file conformance — skip if `PLAN_FOUND=none` or `! $HAS_MATRIX`

**INLINE invariant — Critical-Files must-appear rule (do NOT move to the reference file):** every file listed in the plan's `## Critical Files` section MUST appear in the PR diff, **unless** its annotation carries an explicit reference marker (`reference` / `read-only` / `verify` / `template` / `no-edit` / `unchanged` / `context`). A must-appear Critical File absent from the diff is a `MISSING-FILE:` finding that stays UNRESOLVED — and **blocks Phase 6.5 arming** — until you either make the dropped change (the #923 remedy) or note the legitimate cut in a PR comment. The matching regex, the `awk` that enforces it, and the sibling planned-test conformance check live in the reference file.

Phase 5 consumes the Phase-3 flags `$HAS_PHP`, `$HAS_GO`, `$HAS_MATRIX`, `$PLAN_FOUND` — carried from Phase 3, never recomputed. **You MUST Read `.claude/skills/post-plan/_phase-5-final-verification.md` and run every block it lists, in order,** before computing the status. It writes the two carry-forward artifacts Phase 6.5 reads: the UNRESOLVED-items bridge `/tmp/post-plan-missing-tests-$PPID` and the status file `/tmp/post-plan-phase5-status-$PPID`.

### Phase 5 END: emit `PHASE5_VERIFY_STATUS`

Emit `PHASE5_VERIFY_STATUS` ∈ {`pass`, `fail`, `skipped`}: `pass` = at least one track ran and every launched track is green (or flaky-then-green on retry with no code change); `fail` = a deterministic failure survived the fix-and-rerun loop in ANY launched track (PHPUnit, PHPStan, Go, or E2E) — a red Go `cover`/`TestGolden` counts exactly like a red PHPUnit; `skipped` = **no** track ran at all (docs-only/CSS-only: `! $HAS_PHP` and `! $HAS_GO` and E2E mapped to nothing). `skipped` is NOT `fail`. **Drain barrier FIRST:** poll `BashOutput` until every backgrounded track reports a terminal result — never aggregate or advance to Phase 6 with a Phase-5 task still pending. The drain barrier, the `PHASE5_VERIFY_STATUS` status-file emit block, and the full value rules live in the reference file.

---

## Phase 6: Manual Testing Automation

**Skip if** PR description says "No manual testing needed."

### Step 1: Extract

```bash
EXTRACTED=$(gh pr view --json body --jq '.body' | sed -n '/## Manual Testing/,/^## /p')
echo "$EXTRACTED"
```

**Also skip Phase 6 entirely if `$EXTRACTED` is empty or whitespace-only** — the section is absent or was already cleared. Do not launch the Sonnet review gate on empty input.

### Step 2: Sonnet Review Gate

**Skip this gate when `PLAN_FOUND` and the surviving Manual Testing steps came from the plan's matrix** — `/plan` gates 3, 9, and 12 already classified automatable-vs-manual upstream and authoritatively, so re-litigating it here is wasted. Treat the remaining steps as truly-manual and leave them in the PR. Run the gate below only for plan-less PRs, where no upstream classification occurred.

Launch a **single Sonnet 4.6 agent** (`subagent_type: "sonnet-4-6"`, omit `model`) with the QA-classification prompt in `.claude/skills/post-plan/_phase-6-manual-testing.md` (substitute the extracted steps from Step 1 and the changed-file list from Phase 4A). The prompt classifies each surviving manual step into CLI-executable / PHPUnit-replaceable / API-test-replaceable / E2E-replaceable / Visual-regression-replaceable / Truly-manual and returns a JSON array; the full prompt text + JSON schema live in that reference — Read it before spawning.

### Step 3: Execute findings

Using the Sonnet agent's classifications:

1. **CLI-executable:** Run directly in the worktree. Fix failures, commit.
2. **PHPUnit/API-test/E2E-replaceable:** Write the appropriate test type. Fix until green. Do not reclassify as truly manual — if the test is hard to write, that's a reason to spend more effort, not less. After 3 failed attempts, keep the item in the PR description as-is (not reclassified) and note what was tried.
3. **Truly manual:** Keep in PR description.
4. **Update PR:** Remove verified/automated steps. If none remain, replace section with `No manual testing needed — all changes are covered by automated tests.` Apply: `gh pr edit --body "<updated>"` This `No manual testing needed` sentinel is exactly what **feeds Phase 6.5 condition (1)** (`pr_manual_testing_clearance`) to clear the manual-testing gate — if this write is skipped or reworded, condition (1) holds the PR for human review.
5. **Checkpoint:** If any new tests were written or files modified, commit and push before continuing to Phase 6.5.

---

## Phase 6.5: Arm Auto-Merge

Enable auto-merge **before** watching CI. This is the earliest point all gating conditions are known — review/audit findings are scored in Phase 4, manual-testing is resolved in Phase 6, and Phase 5's local verification status is recorded at its END — and arming here (rather than after the watch) is the whole point: if this post-plan phase is later killed mid-watch by the per-phase cap (`MAX_PP_SECS`) or a usage limit, GitHub still holds the queued merge and fires it once required checks pass, with no further agent action needed. Arming after the watch (the old Phase 8 placement) meant any phase that ran out of budget during the watch shipped a PR that was never set to auto-merge.

**Already merged?** If `gh pr view --json state --jq '.state'` returns `MERGED`, there is nothing to arm — skip to Phase 7 (which will early-exit).

**All ten** conditions must be true — an AND-of-not-blocked set (any one can HOLD; none can RELEASE another):

1. Manual testing cleared — the PR body carries the `No manual testing needed` sentinel Phase 6 writes.
2. No review/audit finding scored `>= 80` (scored in Phase 4).
3. No unresolved `MISSING:` planned-test **or** `MISSING-FILE:` planned-file items from Phase 5.0 — `/tmp/post-plan-missing-tests-$PPID` is absent or empty (absent/empty = PASS, non-blocking).
4. Phase 5 did not deterministically fail — `PHASE5_VERIFY_STATUS` is `pass` or `skipped`, **not** `fail`.
5. Golden-snapshot safety — a change to `engine/internal/sim/testdata/golden.json` does NOT auto-ship unattended (headless-only block).
6. Merge-order — every PR named in a `Depends-on:` line is already `MERGED`.
7. Plan-time hold — the plan's line-1 `auto_merge: false` frontmatter is absent.
8. Commit-type floor — the PR title is not a conventional-commit `feat:` (unless `human-approved`-labeled).
9. PR-time safety verdict — the realized diff surfaces no reason to hold for a human.
10. Pipeline-authored floor — the PR does NOT carry the `pipeline-authored` label.

**These conditions only ever HOLD, never RELEASE.** They are an AND-of-not-blocked set: every condition can *add* a block; none can clear another's. Conditions (7)–(9) are **additive brakes on top of** the deterministic floors (1)–(6), the pipeline-authored floor (10), and the independent `human-signoff` required GitHub check — they exist to catch what those miss, never to override them. post-plan **always runs and opens the PR**; these conditions decide only whether auto-merge *arms*. A held PR stays open for a human to merge.

**Conditions (1)/(5)/(6)/(8)/(10) come from the shared predicate `bin/lib/pr-armable.sh`** — the single source of truth also used by `bin/pr-triage`, so the live-readable arming judgment has **one executable home** and cannot drift between consumers (hand-re-derived divergence is exactly what mis-armed #1163/#1188). The run-only conditions (2)/(3)/(4)/(7)/(9) stay inline below — they read post-plan-run-local state (`/tmp`, the local plan file, the realized diff) that no cross-PR consumer can see, so they cannot move into the shared predicate.

**Each condition block is SELF-CONTAINED** — it `source`s the predicate and fetches its own inputs in-block, exactly as condition (7) re-derives `$PLAN_FILE` and the original (6)/(8) ran their own `gh pr view`. **Do not** hoist the `source` or a shared `PR_JSON` into a preamble block: a sourced function or a shell variable does not survive into a separately-executed block (only exported env vars like `$CLAUDE_HEADLESS` do), and a missing `source` would make `pr_feat_hold` a no-op — **failing OPEN, auto-arming a `feat:` PR**. Each block re-`source`ing the lib is idempotent and cheap. Every block extracts gh output with `gh ... --jq` (gh does the decode — no `echo`/`printf` round-trip needed); when a block must round-trip a multi-field `PR_JSON` it uses `printf '%s'` (never `echo`, whose zsh `\n` expansion corrupts jq's parse).

**You MUST Read `.claude/skills/post-plan/_phase-6.5-arm-auto-merge.md` and run each condition's block, in order, BEFORE arming — do not arm without it.** The reference holds the seven per-condition bash blocks (conditions 1, 4, 5, 6, 7, 8, 10 — conditions 2/3/9 are the Phase-4 score check, the `/tmp/post-plan-missing-tests-$PPID` bridge check, and the realized-diff hold-enumeration, run against state you already hold), each **self-contained** per the SELF-CONTAINED invariant above (every block re-`source`s the predicate in-block — a hoisted `source` does not survive into a separately-executed block and would fail OPEN). It also holds the per-condition blocker-reporting detail. Phase 6.5 consumes carried state only — the Phase-3 flags `$GOLDEN_CHANGED`/`$HAS_MIGRATION`/`COUNT_*`, the env `$CLAUDE_HEADLESS`/`$PPID`, the Phase-4 finding scores, the Phase-6 manual-testing sentinel, and the Phase-5 status file — never recompute them.

**Fail-closed default:** if any condition is indeterminate, errors, or you are unsure, treat it as **BLOCKED** and do NOT arm. A false HOLD costs one manual human merge; a false ARM ships unreviewed code — only under-holding is dangerous.

**If every condition passes:** arm with `gh pr merge --squash --auto --delete-branch` — `--auto` *queues* the merge (it does not merge now); GitHub fires it once required checks pass. Do not sync local to master here.

**If any condition blocks:** do NOT arm. Report which condition(s) blocked — the per-condition report text is in the reference (e.g. `cat /tmp/post-plan-missing-tests-$PPID` for (3); which Phase-5 track failed for (4)). Continue to Phase 7 regardless to monitor and fix CI; a re-run clears a red-track block, but the intent/type holds (7), (8), (10) stay held until a human acts.

**Interactive golden warning:** when `$GOLDEN_CHANGED` is `true` and `$CLAUDE_HEADLESS` is unset (so condition 5 did not block), still surface the warning prominently so the human confirms the simulation change was an intentional `make -C engine golden-update`, not a masked regression.

---

## Phase 7: CI Monitoring

**Auto-merge is already armed (Phase 6.5). Monitor CI and fix failures so the queued merge can fire; then continue to Phase 8.**

> **Field-shape gotcha — read before editing this phase.**
> Two `gh` commands return different shapes; mixing them produces unsatisfiable conditions.
> - `gh pr checks <pr> --json name,state,link` → `state` ∈ `SUCCESS | FAILURE | SKIPPED | PENDING | CANCELLED | NEUTRAL`. **No `conclusion` field, no `COMPLETED` value.**
> - `gh pr view <pr> --json statusCheckRollup` → `status` ∈ `COMPLETED | IN_PROGRESS | QUEUED`, `conclusion` ∈ `SUCCESS | FAILURE | SKIPPED | …`.
> Do not write `state == "COMPLETED"` or `conclusion == "failure"` against `gh pr checks` — both are silently false forever.

0. **Early-exit on merged PR:** Before any polling, run `gh pr view <pr> --json state --jq '.state'`. If `MERGED`, skip the rest of Phase 7 and continue at Phase 8 — the auto-merge armed in Phase 6.5 has already fired (required checks were already green) and watching CI to completion adds nothing but wall-clock burn. This is the load-bearing optimization that keeps the automouse loop from burning a full watch timeout on an already-shipped PR.
1. **Wait for checks to register:** Poll `gh pr checks <pr> --json name,state 2>/dev/null | jq 'length'` up to 4 times with 15s waits. If count stays 0, warn user and continue to Phase 8.
2. **Block until CI settles:** `gh pr checks <pr> --watch --fail-fast --interval 20` (Bash timeout 1200000 = 20 min cap — leaves a ~40-min cushion under `MAX_PP_SECS=3600` for Phase 5.0 conformance + Phases 8-11 cleanup). The gh CLI handles the polling and exit logic itself; do not re-implement it in jq. Exit codes: `0` = all checks passed, `8` = at least one failed, other = transport error.
3. **If exit 0** → Phase 8. (Mid-watch merge detection was intentionally dropped: `gh pr checks --watch` exits as soon as the last check settles, so the only window auto-merge could fire inside the watch is the ~5–30s between final-check-pass and auto-merge action — not worth a hand-rolled poll loop. Step 0 already covers the case where the PR merged before Phase 7 started.)
4. **If exit 8:** Get failed checks via `gh pr checks <pr> --json name,state,link --jq '[.[] | select(.state == "FAILURE")]'` (uppercase `FAILURE`, field is `state` not `conclusion`). Download logs (`gh run view <id> --log-failed`). **Fix all failures** — master's CI is green, so any failure on this PR is this PR's fault (even in files outside the diff). The only exception is a flaky test that passes on retry with no code change; note it in a PR comment and move on. Fix, commit, push, loop back to step 1.

   **When out of depth, escalate to Opus** — failing-check `name` matching `mutation|MSI|engine|golden|migration` (case-insensitive) → Opus on attempt 1; otherwise Sonnet does attempts 1–2 and Opus takes attempt 3. **Read `.claude/skills/post-plan/_phase-7-ci-monitoring.md`** for the escalation procedure: capture the failed log + `origin/master...HEAD` diff to temp paths and pass the **paths** (never summarize the log), spawn **one** `Agent(model: "opus")` that fixes/commits/pushes itself and returns one line. The Opus attempt **counts toward** the 3-iteration ceiling. Loop back to step 1.
5. **If bash times out (20 min elapsed):** Run one final `gh pr checks <pr>` (no `--watch`) to capture settled state. If it now exits 0, continue to Phase 8. If 8, jump to step 4. Otherwise report to user and continue to Phase 8 — do not re-enter watch.

---

## Phase 8: Confirm Merge State

Auto-merge was already armed (or deliberately not armed) in Phase 6.5 — this phase only confirms the outcome and syncs local if the merge landed. Do not re-run `gh pr merge` here.

- **If `gh pr view --json state --jq '.state'` returns `MERGED`:** the queued merge fired. Run `cd <repo-root> && git checkout master && git pull origin master` to sync local, then continue to Phase 9.
- **If still `OPEN` and auto-merge was armed in Phase 6.5:** the merge is queued and will fire when required checks pass — no further action. Do not sync local to master; the merge has not happened yet. Continue to Phase 9.
- **If still `OPEN` and auto-merge was NOT armed (a Phase 6.5 condition blocked):** report which condition(s) blocked. The user merges manually.

---

## Phase 9: Retrospective

Before saving any memory, ask: **"Can this be a PHPStan rule instead?"** If the mistake is mechanical and deterministic, it belongs in `ibl5/phpstan-rules/` as a new custom rule — open a TODO comment in the plan file rather than a memory entry. (For an **engine-side** learning, the analog is: "Can this be a `golangci-lint` linter or a `go vet` rule?" — a mechanical, deterministic Go mistake belongs in `engine/.golangci.yml` config or a custom analyzer, not memory.) Memories are for things a linter cannot express (architectural judgment, environment quirks, incident context).

Save to memory only if something was learned that would **prevent a bug** in a future session AND cannot be mechanized AND isn't already in MEMORY.md, CLAUDE.md, `.claude/rules/`, or an existing PHPStan rule. Read the target memory file first to avoid duplicates. If nothing qualifies, skip silently.

---

## Phase 10: Preview Environment

**Skip entirely if** `$CLAUDE_HEADLESS` is set (automouse autonomous mode — no human present to verify).

Check the PR state using the PR number captured in Phase 4A:

```bash
PR_STATE=$(gh pr view <PR_NUMBER> --json state --jq '.state')
```

**Branch on `$PR_STATE`:** if `MERGED` → **Path A** (sync local, then rebuild the main Docker stack with fresh prod data — skip Path A entirely if `$ENGINE_ONLY`, since an engine-only change touches no `ibl5/` PHP; requires `REMOTE_*` prod credentials in `.env`). Otherwise → **Path B** (worktree preview via `bin/wt-down`/`bin/wt-up --prod`; skip if the worktree pre-existed or earlier phases left uncommitted fixes). **Read `.claude/skills/post-plan/_phase-10-preview-environment.md`** for the full step-by-step of whichever path applies before running it. Do NOT run `wt-remove` or `git branch -D`.

---

## Phase 11: Background Process Cleanup (MANDATORY — never skip)

**You MUST execute this phase before ending your turn.** This is not optional even if all earlier phases succeeded cleanly.

Background shells from earlier phases (`bin/e2e-wt.sh` in Phase 5, `gh pr checks --watch` in Phase 7) may still be running. If they hold the pipeline open after you emit your final response, the automouse runner's stall-kill fires after 10 minutes and burns an attempt — three burns poison-pill the plan.

Kill known lingering patterns so their tool results deliver immediately (cache warm) rather than hours later (cache miss):

```bash
pkill -f 'bin/e2e-wt\.sh' 2>/dev/null
pkill -f 'bunx.*playwright' 2>/dev/null
pkill -f 'gh pr checks.*--watch' 2>/dev/null
rm -f /tmp/post-plan-spec-diff-$PPID /tmp/post-plan-spec-prod-diff-$PPID /tmp/post-plan-missing-tests-$PPID 2>/dev/null
echo "Background process cleanup complete"
```

This is a single Bash call — no agent needed. Ignore any "no matching processes" output; the `2>/dev/null` suppression handles it. The killed processes deliver their tool results immediately as errors, which is expected and harmless.
