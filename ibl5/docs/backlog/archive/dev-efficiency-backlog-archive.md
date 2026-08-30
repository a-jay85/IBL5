---
description: Historical archive: completed development-efficiency backlog entries, extracted from dev-efficiency-backlog.md.
last_verified: 2026-08-30
---

# Development-Efficiency Backlog — Archive

Read-only historical record of ✅ Implemented / 🚫 Declined entries. For OPEN items see ../dev-efficiency-backlog.md. Not governed by bin/check-docs (historical dead refs tolerated).

---

### E2 Dependabot grouping
**Location:** `.github/dependabot.yml`.
**What shipped:** Added `groups:` blocks to all 5 ecosystems (github-actions, composer, bun, docker, npm@IBLbot) batching minor/patch bumps into one weekly PR per ecosystem; majors stay individual so a breaking bump is never entangled with routine ones. TypeScript `semver-major` ignore entry in IBLbot block preserved verbatim.
**Status (2026-08-14):** ✅ Implemented — shipped in PR #1873.

### E6 Diff-scoped PHPStan wrapper
**Location:** `ibl5/bin/analyse-diff`.
**What shipped:** `bin/analyse-diff` runs PHPStan only on `.php` files changed vs a base ref (default: `master`), routing them through `composer run analyse` / `composer run analyse:tests` so it inherits the exact `--memory-limit`/`--autoload-file` flags and honors baselines. Covers committed branch changes, staged/unstaged edits, and untracked new files. Full-project run remains the CI gate.
**Status (2026-07-14):** ✅ Implemented — shipped in PR #1362.

### E3 PHPStan result-cache in CI
**Location:** `.github/workflows/tests.yml` (the `phpstan` job).
**What shipped:** A `Cache PHPStan result cache` step (`actions/cache` v6.1.0) persists `ibl5/tmp` + `ibl5/tmp-tests` (the `phpstan.neon`/`phpstan-tests.neon` `tmpDir`s where PHPStan writes `resultCache.php`), keyed on the phpstan config files + `phpstan-rules/**`. PHPStan's own file-hash invalidation keeps results correct across restores. Only `tests.yml` runs `composer run analyse`/`analyse:tests`; `pr-meta-checks.yml`/`mutation.yml` don't invoke PHPStan.
**Status (2026-07-03):** ✅ Implemented — shipped in PR #1309 (predates the entry's own 2026-07-07 "verified" claim; the entry was stale).

### E9 Meta-tooling growth bar
**Location:** Plan: `$HOME/claude-plans/meta-tooling-bar.md` (queued) — extend-before-add rule + quarterly cull.
**Problem:** ~27 of 101 `bin/` scripts exist to test the other scripts; the gate layer itself has had bugs. Nothing pushes back on meta-tooling growth.
**Suggested direction:** Extend-before-add policy gate + quarterly cull process.
**Risk if untouched:** Unbounded meta-tooling growth; gate layer debt accumulates.
**Status (2026-07-09):** ✅ Implemented — PR #1387 (`meta-tooling-bar`): extend-before-add rule + quarterly cull automation shipped.

### E11 In-PR pre-baked image build
**Location:** Plan: `$HOME/claude-plans/in-pr-prebaked-image-build.md` (queued). Today only `.github/workflows/cache-dependencies.yml` builds the image, on schedule/push — never in-PR.
**Problem:** A PR changing the Dockerfile or composer deps is E2E-tested against the *previous* master image; the mismatch surfaces only after merge.
**Suggested direction:** Build the Docker image in-PR for PRs touching the Dockerfile or composer deps (paths-filtered so normal PRs are unaffected).
**Risk if untouched:** Dockerfile/dep changes are validated against a stale image; mismatch only surfaces post-merge.
**Status (2026-07-09):** ✅ Implemented — PR #1386 (`in-pr-prebaked-image-build`): in-PR image build wired via paths-filter; normal PRs unaffected.

### E12 `bin/wt-new` fails with misleading error when invoked from inside a worktree
**Location:** `bin/wt-new` — `REPO_ROOT` derivation and `git merge --ff-only` sync.
**Problem:** `bin/wt-new` computed `REPO_ROOT` from the script's own path. When invoked through a worktree copy (e.g. `bin/wt-new foo` from `IBL5-worktrees/<slug>/`), `REPO_ROOT` resolved to that worktree rather than the main checkout. The `--ff-only` then targeted the worktree's feature branch, which had diverged from `origin/master`, and the script aborted with `fatal: Not possible to fast-forward, aborting.` before creating anything. Observed 2026-07-29 running `bin/wt-new matrix-fence-strip` from the `critical-files-parser-unification` worktree. `.claude/rules/workflow-continuity.md` documents the bare `bin/wt-new <slug>` form as the standard invocation — exactly the shape that failed from a worktree. Workaround was: invoke the main checkout's copy by absolute path.
**What shipped:** PR #1934 — `bin/wt-new` now calls `resolve_canonical_root "$SCRIPT_ROOT"` (from `bin/lib/git-helpers.sh`) to walk up to the canonical main checkout regardless of call site. `bin/test-wt-new-root` regression harness exercises both the positive case (worktree invocation, sync lands on master) and a negative control (defect re-introduced, failure re-appears); wired into CI.
**Status (2026-08-19):** ✅ Implemented — shipped in #1934.

### E14 `/pr-ready` Monitor exemption in invariants is stale — watcher loop is actually refused
**Location:** `.claude/skills/pr-ready/SKILL.md` — the invariant bullet beginning "**After `EnterWorktree`, no Bash command may contain `$(…)` or `<(…)`.**", specifically its `**Exempt:**` clause claiming `Monitor` is not gated.
**Problem:** The invariant exempts commands passed to `Monitor` on the basis of a probe showing `Monitor(command: 'X=$(echo hi); echo "$X"')` emits `hi`. During a live `/pr-ready 1947` run on 2026-08-24, `Monitor` refused the Phase 4.5 watcher loop written exactly as the skill prescribes, with "this command is too complex to verify that it stays inside the worktree." The probe result is stale; the exemption does not hold in practice, so the skill has a documented shape that silently breaks at runtime.
**Suggested direction:** Delete the `Monitor` exemption clause from the invariant. Rewrite Phase 4.5 to use the same script-file shape mandated everywhere else (write the loop to `/tmp/pr-ready-ciwatch-<N>.sh`, arm `Monitor(command: "bash /tmp/pr-ready-ciwatch-<N>.sh")`), giving the skill one consistent shape with no inline exception.
**Risk if untouched:** Every `/pr-ready` run that reaches Phase 4.5 fails with a refused command and requires undocumented recovery; misleading skill prose delays diagnosis.
**What shipped (pr-ready-skill-prose-drift):**
- `.claude/skills/pr-ready/SKILL.md` line 33: the `**Exempt:** any command passed to Monitor…` clause deleted — Monitor is NOT exempt from the `EnterWorktree` substitution gate.
- SKILL.md Phase 4.5 step 5: inline substitution-heavy `Monitor(command: <bash loop>)` rewritten to Write-to-tmp shape — `Write` the loop to `/tmp/pr-ready-ciwatch-<N>.sh` (PR-keyed, with `<HEAD_SHA>` baked in as a literal), then `Monitor(command: "bash /tmp/pr-ready-ciwatch-<N>.sh")`.
- SKILL.md Phase 6.5 step 6: updated to match — re-`Write` the script with the new `<HEAD_SHA>` literal, then arm the same Monitor shape.
- `bin/test-pr-ready-now`: new case `e14-monitor-subst-free` pins it (0 `**Exempt:**` clauses, 0 substitutions on any `command:` line, exactly 2 `command: "bash /tmp/pr-ready-ciwatch-<N>.sh"` lines).
**Status (2026-08-25):** ✅ Implemented — shipped in pr-ready-skill-prose-drift.

### E15 `/pr-ready` Phase 2 delegation packet tells rebase delegate to push — blocked by sub-agent gate
**Location:** `.claude/skills/pr-ready/SKILL.md` Phase 2 delegation packet and its include `.claude/skills/pr-ready/_rebase-and-conflicts.md`, which instruct the `sonnet-4-6` delegate to run `git push --force-with-lease`.
**Problem:** `~/.claude/hooks/plan-gate-commit.sh` blocks `git push` from sub-agents by design. During a live `/pr-ready 1947` run on 2026-08-24, the rebase delegate returned `pushed: no (blocked by plan-gate-commit.sh sub-agent ship gate — main thread must push)`. The orchestrator had to push itself, which is undocumented recovery — the skill provides no fallback path.
**Suggested direction:** Change the Phase 2 packet so the delegate rebases and verifies only, and reports the resulting local SHA. Move `git push --force-with-lease` explicitly onto the orchestrator in Phase 4, where the existing lost-work proof already lives, making the separation of delegate (rebase) vs. orchestrator (push) explicit and gated.
**Risk if untouched:** Every `/pr-ready` run that needs a rebase silently fails the push step and requires ad-hoc orchestrator recovery with no documented handoff path.
**What shipped (pr-ready-skill-prose-drift):** The code fix — `.claude/skills/pr-ready/_rebase-and-conflicts.md` line 28 already reads "You may NOT run `git commit` or `git push`" — landed in a prior merged PR. This branch adds the regression test: new case `e15-delegate-no-push` in `bin/test-pr-ready-now`.
**Status (2026-08-25):** ✅ Implemented — shipped in pr-ready-skill-prose-drift.

### E17 Skill prose carries fixed-count words that go stale when the enumerated set grows
**Location:** `.claude/skills/pr-ready/SKILL.md` Phase 7 step 2 — the `include-source:` sentence, which read "If **either** include was loaded by the declared fallback…" after this PR raised the skill's progressive-disclosure includes from two to three.
**class:** A skill/rule doc names a set with a fixed-count word (`either`, `both`, `the two`, `two includes`) rather than a count-agnostic one (`any`, `each`, `every`); when a later change grows the set, the prose silently under-specifies and nothing detects it. Distinct from a wrong claim — the sentence stays *true* for two of three members, so review reads past it.

**Occurrence scan (2026-08-25, `.claude/skills/**` + `.claude/rules/**`, `grep -rniE '\b(either|both|the two|two includes)\b'`):**

| # | Location | Verdict | Status |
|---|----------|---------|--------|
| 1 | `.claude/skills/pr-ready/SKILL.md` Phase 7 step 2 — `include-source:` sentence | stale: `either` spans 3 includes | fixed this pass (`either` → `any`) |
| 2 | ~30 other `either`/`both`/`the two` hits across `post-plan`, `pr-attack`, `backlog-housekeep`, `pr-ready/_plan-fidelity-review.md` | all genuine two-item references (two mandatory statements, both sub-gates, both modes, the two PRs) | none found — no action |

**prevention ladder:**
- rung 0 — already covered by an existing gate? No. `bin/check-docs` gates frontmatter freshness, dead path references, and retired figures; it has no notion of set-cardinality agreement.
- rung 1 — extend an existing gate? The natural host is `bin/check-docs`, but the check it would need is a *semantic* one (does the count-word's referent set still have that cardinality?), which no grep can decide — occurrence 2 shows ~30 legitimate uses against 1 stale one, a ~97% false-positive rate for any pure-lexical rule.
- rung 2 — a rule doc under `.claude/rules/`? Cheapest rung, and the only one that survives the false-positive problem: a one-line authoring norm ("when a doc enumerates a set, prefer `any`/`each`/`every` over `either`/`both` unless the cardinality is structurally fixed") in `.claude/rules/doc-freshness.md`. But the defect is rare (1 occurrence in the whole skill+rule surface) and self-correcting at the moment of edit, so even a rule doc buys little.
- rung 3 — a PHPStan rule? Not applicable; the surface is markdown, not PHP.
- rung 4 — a CI gate? Same false-positive wall as rung 1, plus new upkeep.
- rung 5 — a new hook? Fails all four `.claude/rules/meta-tooling-bar.md` extend-before-add conditions — `bin/check-docs` is an available host, the trigger is not distinct, the surface is not recurring (one occurrence), and rung 2 is a cheaper alternative.

**prevention_ladder: no gate warranted** — a lexical gate would fire on ~30 correct uses to catch 1 stale one, and the class is cheap to catch later (the sentence is still readable and the fix is one word). If a second occurrence ever lands, revisit at rung 2 (an authoring line in `.claude/rules/doc-freshness.md`), not rung 4.

**artifact destination:** n/a — no gate lands. Had rung 2 been taken, the artifact would be `.claude/rules/doc-freshness.md` (in-repo, appears in a PR diff).

**Related:** E14 and E15 are the two prior instances of the broader pattern — `/pr-ready` SKILL.md prose drifting from the runtime it describes. E17 differs in kind (both of those are behaviourally wrong instructions that fail at runtime; this one is prose that stays true but under-specifies), so it is filed separately rather than consolidated.

**Status (2026-08-25):** ✅ Implemented — the single occurrence was fixed in PR #1981 (`either` → `any`); no gate was warranted (prevention_ladder: no gate warranted); watch-item closed by owner decision on 2026-08-25. *(discovered 2026-08-25 during #1981)*

### E22 Plan-declared negatives and PR body Scope dropped during Phase 6.5 remediation

**Class A** (finding 2): a test case rewrite during Phase 6.5 remediation that silently drops plan-declared negative assertions from the harness, leaving the asserted regressions unpinned.

**Class B** (finding 4): a PR body Scope section whose file count and diff-stat numbers are not updated after Phase 6.5 remediation adds files and expands test cases beyond plan estimates.

**Occurrences**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/test-pr-ready-now` case 21 — `grep -qF 'DO NOT ADD ONE'` and `grep -qF 'Spawning any sub-agent for this phase is a defect'` assertions dropped during E21 harness rewrite | class A | yes | fixed this pass (re-added both assertions) |
| 2 | `PR #2000 body ## Scope` — "Six files change" vs. 7-file diff; `+38 -0` vs. actual `+82 -0`; `ibl5/docs/backlog/dev-efficiency-backlog.md` unmentioned | class B (same as E20) | yes | fixed this pass (body updated: count, stat, backlog file named) |

`prevention_ladder:`

Class A:
- **rung 0 — already covered?** No. Nothing at implementation time checks that plan-declared negatives survive a harness rewrite.
- **rung 1 — extend an existing gate? LANDS HERE.** Extend `.claude/skills/plan/_architect-contract.md` to require that each verification phase listing a negative assertion also name the mutation it must catch ("delete the `--model` arm ⇒ assertion fails"). An implementer cannot substitute a vacuous text grep without the mutation target going visibly missing; a Phase 6 reviewer gets a stated predicate instead of re-deriving intent from plan prose.
- **rung 2 — a rule doc?** Insufficient alone: the norm must fire at authoring time, inside the architect contract.
- **rungs 3–5** — N/A.

Class B:
- **rung 0 — already covered?** E20 tracks the same class; no mechanical gate exists.
- **rung 1 — extend an existing gate? LANDS HERE.** Extend the `/pr-ready` SKILL.md Phase 6.5 commit step to include a micro-check: before committing, confirm that any explicit file counts and diff stats in the PR body Scope prose match the post-remediation `git diff --numstat`.
- **rungs 2–5** — ruled out.

`artifact destination:` Class A → `.claude/skills/plan/_architect-contract.md` (in-repo). Class B → `.claude/skills/pr-ready/_phase65-remediation.md` step 4 (in-repo); the original Class B address (`SKILL.md` Phase 6.5) was stale because Phase 6.5 was extracted out of `SKILL.md`. Both built this pass.

**Status (2026-08-27):** ✅ Implemented — Class A landed in `_architect-contract.md`'s negative-path bullet as a "name the mutation it catches" requirement; Class B landed as a pre-commit `git diff --numstat HEAD` vs. PR-body-Scope reconciliation in `_phase65-remediation.md` step 4.

*(discovered 2026-08-26 during #2000)*

### E23 PR Body Deletion Volumes Diverged from Code's `EXPECTED` Constants, Undetected Until /pr-ready Phase 6

Body summary text was authored pre-implementation and hand-updated during coding, allowing four separate factual errors: (4a) player-row volume understated ~23× (627 vs 14502); (4b) backup table suffix wrong (`_bak` vs `_backup`); (4c) claimed rollback test coverage that doesn't exist; (4d) claimed full unit+E2E coverage while the same PR dropped the coverage baseline 0.85 pp.

**Occurrences**

| # | File:line | Class | Live? | Status |
|---|-----------|-------|-------|--------|
| 1 | PR #2001 body Summary — "627 player rows" vs code's 14502; `_bak` vs `_backup`; "and rollback" in test claim; "No manual testing needed — all changes are covered by unit and E2E tests" | A | yes | fixed this pass (body corrected via `gh pr edit`) |

`prevention_ladder:`

- **rung 0 — already covered?** No. Nothing checks that PR body deletion volumes match the code's `EXPECTED` constants at review time.
- **rung 1 — extend an existing gate? LANDS HERE.** Extend the `/pr-ready` Phase 6 `_plan-fidelity-review.md` 6d checklist with a class item: "for any PR whose diff introduces a class with a named `EXPECTED` constant array (or equivalent named-constant deletion count), confirm the PR body Summary names volumes consistent with those constants." This is a reviewer prompt, not a mechanical gate — body prose cannot be auto-parsed for intent, but a named reviewer check prevents it from being silently skipped.
- **rungs 2–5** — N/A.

`artifact destination:` `.claude/skills/pr-ready/_plan-fidelity-review.md` 6d.4 sub-clause **4a** (in-repo); implemented as a sub-clause rather than a new class item because `_phase65-remediation.md` says "across all six 6d classes" and `_plan-fidelity-review.md` 6a says "performs all six 6d checks itself" — a 7th class item would falsify both. Built this pass.

**Status (2026-08-27):** ✅ Implemented — landed as `_plan-fidelity-review.md` 6d.4 sub-clause 4a, "Named-constant volumes."

*(discovered 2026-08-27 during #2001)*

### E13 `bin/wt-new --base <branch>` fast-forwards the wrong branch

**Location:** `bin/wt-new` — the pre-branch sync block (`git fetch` → `rev-list --count` → `merge --ff-only`).
**Problem:** The staleness check is computed on `$BASE_BRANCH` (`rev-list --count "$BASE_BRANCH..origin/$BASE_BRANCH"`), but the merge that acts on it is a plain `git -C "$REPO_ROOT" merge --ff-only "origin/$BASE_BRANCH"` — which merges into whatever branch the main checkout has *checked out*, i.e. `master`. So `bin/wt-new <slug> --base <other-branch>` — the documented stacked-PR path — leaves local `<other-branch>` stale (the worktree forks from a stale tip, exactly what the sync exists to prevent) and drags `master` toward `origin/<other-branch>` instead, or aborts with `Not possible to fast-forward` once the two have diverged. Silent today only because a stacked base is usually already current, so `BEHIND=0` skips the merge entirely.
**Suggested direction:** Update the base branch without checking it out — `git -C "$REPO_ROOT" fetch origin "$BASE_BRANCH:$BASE_BRANCH"` (which is ff-only by default for non-current branches), falling back to the existing `merge --ff-only` only when `$BASE_BRANCH` *is* the checked-out branch. Extend `bin/test-wt-new-root` with a `--base` case; its temp-repo harness already builds a stale-local/diverged fixture.
**Risk if untouched:** Stacked PRs silently branch from a stale parent, and a diverged base turns `wt-new` into a hard failure that also mutates `master` on the way there.
**Status (2026-08-19):** ⬜ Open — 🟩 (no design fork; found while fixing E12 and deliberately left out of that PR's scope).

**Status (2026-08-30):** ✅ Implemented — `bin/wt-new` now detects whether `$BASE_BRANCH` is checked out in the main checkout; if not, uses `fetch origin "$BASE_BRANCH:$BASE_BRANCH"` to fast-forward the local ref directly. `bin/test-wt-new-root` extended with Case 3 regression pin. Shipped in #2033.

### E26 `bin/plan-now` timestamp collision on back-to-back invocations

`bin/plan-now:130` derived all artifact paths and the launchd label from `TS=$(date +%Y%m%d-%H%M%S)` — second-resolution only. Two invocations within the same wall-clock second produced identical `$LABEL`, `$LOG`, `$PROMPT`, `$RUNNER`, and `$OUT` paths. The second invocation's `launchctl bootout … || true` silently unregistered the first job, and the first invocation's runner script was overwritten before launchd executed it. `bin/post-plan-now:123` carried the same pattern.

**Fix:** Changed `TS=$(date +%Y%m%d-%H%M%S)` to `TS=$(date +%Y%m%d-%H%M%S)-$$` (PID suffix) in both scripts. Added a backstop guard in `bin/plan-now` that exits nonzero if `$PROMPT` already exists at write time. Extended `bin/test-plan-now` with a collision case.

`prevention_ladder:`

- **rung 0 — already covered?** No.
- **rung 1 — extend an existing gate?** Extended `bin/test-plan-now` (already wired to CI at `.github/workflows/tests.yml:876-877`). No new harness.
- **rung 2 — a rule doc?** N/A — a one-liner code fix plus a runtime backstop guard.
- **rung 3 — fix in place. LANDS HERE.**
- **rungs 4–5** — N/A.

`artifact destination:` `bin/plan-now:130`, `bin/post-plan-now:123` (in-repo). **Status:** ✅ Implemented.

*(discovered 2026-08-30, fixed in #2034)*
