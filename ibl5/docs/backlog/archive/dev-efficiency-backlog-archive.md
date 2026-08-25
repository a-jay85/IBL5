---
description: Historical archive: completed development-efficiency backlog entries, extracted from dev-efficiency-backlog.md.
last_verified: 2026-08-25
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
