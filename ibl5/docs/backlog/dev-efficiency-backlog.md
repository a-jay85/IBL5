---
description: Development-efficiency backlog — inner-loop speed (diff-scoped analysis, parallel tests), CI caching, dependency-bump batching, and worktree lifecycle automation, with per-entry status.
last_verified: 2026-09-06
---

# Development-Efficiency Backlog

**Purpose:** Catalogue tooling changes that cut wall-clock and setup waste in the develop-verify loop — locally, in CI, and in the overnight queue. Each open entry is a candidate for a `/plan`.

**Origin:** Advisory sessions (2026-07-07): a codebase + harness efficiency review and an automouse pipeline audit. Statuses verified against `bin/`, `ibl5/composer.json`, `.github/`, and the automouse queue on 2026-07-07.

**Companion to** [`ci-backlog.md`](ci-backlog.md) (CI-workflow simplification proper lives there) and the other backlogs in [README.md](README.md); same status taxonomy.

---

## Taxonomy

**Status** — canonical five-glyph set: see [README.md § Status taxonomy](README.md#status-taxonomy).

**Automouse-readiness** (for items not ✅/🚫): same glyphs as [`ci-backlog.md`](ci-backlog.md) — 🟩 auto-mergeable · 🟦 automouse-safe, human-merge · 🟨 conditional · 🟥 not automouse-safe.

**Effort scale:** **S** — single PR, < 1 day. **M** — multi-step plan, 1–3 days. **L** — platform shift, likely needs an ADR.

---

## Entries

| # | Title | Status | Automouse | Effort |
|---|-------|--------|-----------|-------:|
| E1 | Warm-standby worktree pool | ⬜ Open | 🟨 | M |
| E2 | Dependabot grouping | ✅ Implemented | — | S |
| E3 | PHPStan result-cache in CI | ✅ Implemented | — | S |
| E4 | Flake-quarantine ledger | ⬜ Open | 🟨 | M |
| E5 | Scheduled stale-worktree GC | ◑ Partial | 🟨 | S |
| E6 | Diff-scoped PHPStan wrapper | ✅ Implemented | — | S |
| E7 | Parallel PHPUnit | ✅ Implemented | — | M |
| E8 | Memory lines → mechanical gates (umbrella) | ◑ Partial | 🟨 | M |
| E9 | Meta-tooling growth bar | ✅ Implemented | — | S |
| E10 | Schema baseline auto-regen | ✅ Implemented | — | M |
| E11 | In-PR pre-baked image build | ✅ Implemented | — | M |
| E12 | `bin/wt-new` fails with misleading error when invoked from inside a worktree | ✅ Implemented | — | S |
| E13 | `bin/wt-new --base <branch>` fast-forwards the wrong branch | ✅ Implemented | — | S |
| E14 | `/pr-ready` Monitor exemption in invariants is stale — watcher loop is actually refused | ✅ Implemented | — | S |
| E15 | `/pr-ready` Phase 2 delegation packet tells rebase delegate to push — blocked by sub-agent gate | ✅ Implemented | — | S |
| E16 | `bin/watch-run` declares a run finished on its first poll, before launchd registers the label | ✅ Implemented | — | S |
| E17 | Skill prose carries fixed-count words (`either`, `the two`) that go stale when the enumerated set grows | ✅ Implemented | — | S |
| E18 | `/pr-ready` Phase 6.5 commits new files but never regenerates the PR body `files-changed` block | ✅ Implemented | — | S |
| E19 | `/pr-ready` materialize-from-pin sites declare no fallback, so a pin that predates the script loops forever | ✅ Implemented | — | S |
| E20 | Ship-pipeline coverage assertions (PR-body Manual Testing block, Phase 4B review) are emitted against a narrower slice than the PR's cumulative diff | ✅ Implemented | — | M |
| E21 | Test assertions land as static source greps that pass while the behavior they name is absent | ✅ Implemented | — | S |
| E22 | Plan-declared negatives and PR body Scope dropped during Phase 6.5 remediation | ✅ Implemented | — | S |
| E23 | PR body deletion volumes diverged from code's `EXPECTED` constants | ✅ Implemented | — | S |
| E24 | `/post-plan` Phase 4B can hand-write its review comment, bypassing `post_review_summary` | ✅ Implemented | 🟨 | S |
| E25 | `/pr-review` migration-exclusion `awk` filter is a no-op, silently ingests full migration diffs | ⬜ Open | 🟨 | S |
| E26 | `bin/plan-now` second-resolution timestamp caused label collision on back-to-back invocations | ✅ Implemented | — | S |
| E27 | `filterGitignored()` in `bin/check-docs`: orphaned docblock, unchecked proc exit, non-NUL-delimited check-ignore paths | ✅ Implemented | — | S |
| E28 | PR body hand-authored migration numbers not updated after a forced renumber | ⬜ Open | — | S |
| E29 | Shell-harness cases pre-populate `$WORK` instead of driving invocation 1 | ✅ Implemented | — | S |
| E30 | `bin/pr-ready-now` already-running skip only sees its own launchd jobs, so an interactive `/pr-ready` is invisible and both runs share PR-keyed `/tmp` scratch | ⬜ Open | 🟨 | S |
| E31 | `bin/plan-now` help span truncated by bare `#`; test assertions miss declared secondary-behaviour tokens | ✅ Implemented | 🟦 | S |
| E32 | Phase 6 review notes on PR #1861 — minor plan-vs-implementation drifts and in-flight artifacts on a long-lived branch, all non-blocking | ⬜ Open | — | S |
| E33 | Phase 6 review notes on PR #1815 — F1/F2 blocking (body inaccuracy, backlog 6.24 collision); F3–F7 notes (reflection test, coercion guards, smoke narrative, last_verified); F1/F2/F5 remediated in Phase 6.5 | ⬜ Open | — | S |
| E34 | Auto-generated `codebase-map.md` row added in #1903 — mechanical output of `bin/generate-codebase-map`, no defect | 🚫 Declined | — | XS |
| E35 | Phase 6 review notes on PR #1800 — PR body test count conflated Verification Matrix row count (18) with PHPUnit method count (7 methods / 9 cases); same wrong number replicated into archive entry; both fixed in Phase 6.5 | ⬜ Open | — | XS |
| E37 | Phase 6 review notes on PR #2045 — plan under-specified assertion discriminator and archive step; PR body Scope omitted mutation context; "No manual testing" claim tensioned with hold; check 5 outstanding hold (n/a); PR body fixed in Phase 6.5 | ⬜ Open | — | XS |
| E38 | `/pr-ready` lost-work guard blind to prior-run destructive rebase | ✅ Implemented | — | S |
| E39 | Phase 6 review notes on PR #1824 (StandingsUpdater echo→logger) — B1 wrong seam name + scope count in body; N3 scope creep unbundled; N4 stale plan literal; N5 manual backlog row not retired in plan; N7 wrong method name in body; B1+N7 remediated this pass | ⬜ Open | — | S |
| E40 | `bin/scrub-log-credentials` prod path: unquoted outer heredoc mangles remote jq filter escapes (F1) + local per-file hit report silently discarded (F4) — both fixed Phase 6.5 #1920; case 8 harness guards regression | ⬜ Open | — | S |
| E41 | `ErrorHandlerRegistrarTest` + `bin/test-scrub-log-credentials`: SYNTHETIC_SECRET 32 chars truncated to 15 in `getTraceAsString()` causes vacuous assertion (F2) + closure assertion replaced with unconditional pass (F3) — both fixed Phase 6.5 #1920 | ⬜ Open | — | XS |
| E42 | Phase 6 review notes on PR #1968 — plan-accuracy divergences (N1–N4); all non-blocking; no gate warranted | ⬜ Open | — | XS |
| E43 | `bin/check-plan` PR6 gates: migration literals [A], line-number anchors [L], docker liveness [E], shellcheck CI-class [I], PHP coverage advisory [J], plus gate [V] `/path/to` + `main.localhost` extensions | ⬜ Open | 🟩 | S |
| E44 | Phase 6 review findings on PR #2082 — gate [V] main.localhost bootstrap defect (blocking), body count mismatch, vacuous range fixture, stale corpus count, nonexistent `bin/db-wait` (example) reference, wrong baseline claim; all fixed in Phase 6.5 | ⬜ Open | 🟩 | S |
| E45 | Phase 6 review notes on PR #1924 — stale Scope forward-reference and false Manual Testing claim; PR-body-only fixes; no gate warranted | ⬜ Open | — | XS |
| E46 | PR body "What is NOT in this PR" written before all plan phases complete | ✅ Implemented | — | XS |
| E50 | PR #2092 Phase 6.5 — `--dry-run` counter increment in live-only branch; no harness case for dry-run count (Findings 1+5); both fixed this pass | ⬜ Open | — | XS |
| E51 | PR #2092 Phase 6.5 — notes Findings 2–4: find-regex deviation already filed, benign out-of-plan changes, body claim fixed by E50; class n/a for all | ⬜ Open | — | XS |
| E52 | PR #2064 Phase 6.5 — stale hand-written PR body claims contradicting the final diff and code; all fixed this pass | ⬜ Open | — | XS |
| E53 | PR #1897 Phase 6.5 — plan-text staleness and meta-text accuracy notes (stale plan literals, vestigial test arg, Manual Testing boilerplate, post-plan retrospective scope count); class n/a or no gate warranted for all | ⬜ Open | — | XS |
| E55 | PR #2126 Phase 6.5 — machine-authored post-review commit undisclosed in PR body scope/coverage claims; Checks 3, 4-i, 4-ii fixed this pass | ⬜ Open | — | XS |
| E56 | PR #2126 Phase 6.5 — stale entry-point counts in plan-preserved maintenance-backlog prose; both fixed this pass | ⬜ Open | — | XS |
| E58 | PR #2133 Phase 6.5 — plan verification rows 4.e and 6.a false-positive on correct code; PR authoring notes | ⬜ Open | — | XS |
| E54 | PR #2084 Phase 6.5 — rebase silently dropped implementation commit; lost-work proof blind to pre-run loss | ⬜ Open | — | XS |
| E55 | PR #2129 Phase 6.5 — PR body false E2E claim, omitted grep finding, vacuous VM selector; all fixed this pass | ⬜ Open | — | XS |
| E56 | PR #2129 Phase 6.5 — SKILL.md size-band gate not updated after deliberate file growth; fixed this pass | ⬜ Open | — | XS |
| E62 | PR #2064 Phase 6.5 — stale hand-written PR body claims contradicting the final diff and code; B1/B2/B3/N1 fixed this pass | ⬜ Open | — | XS |
| E63 | PR #1967 Phase 6.5 — duplicate `last_verified:` in ADR index + stale body path reference (Findings 1+2); body note finding; all fixed this pass or filed | ⬜ Open | — | S |
| E64 | PR #1967 Phase 6.5 — stale PR body acceptance list claiming wrong ADR alternatives | ⬜ Open | — | XS |
| E65 | PR #1967 Phase 6.5 — Phase 6.5 backlog entries inserted in wrong structural location | ⬜ Open | — | XS |

### E1 Warm-standby worktree pool
**Location:** `bin/wt-new` (no pool/claim logic today).
**Problem:** Per-plan worktree provisioning (composer install, Docker stack up) is pure serial dead time, paid once per plan in the overnight queue and once per task interactively.
**Suggested direction:** Pre-provision one spare worktree that `bin/wt-new` claims and rebrands (rename branch + Traefik route), then re-provision the spare in the background.
**Risk if untouched:** Minutes of dead time multiplied by every queue slot and every new task.
**Status (2026-07-07):** ⬜ Open — 🟨 (needs one design decision: how a claimed pool worktree gets its branch/route identity swapped safely).

➜ E2 Dependabot grouping — ✅ Implemented (2026-08-14): see [archive](archive/dev-efficiency-backlog-archive.md).

➜ E3 PHPStan result-cache in CI — ✅ Implemented (2026-07-03): see [archive](archive/dev-efficiency-backlog-archive.md).

### E4 Flake-quarantine ledger
**Location:** E2E CI (`.github/workflows/`) — no quarantine mechanism (verified; "flake" mentions are VR-specific).
**Problem:** Specs that pass only on retry are invisible until a red run poison-pills the nightly queue (the post-plan skip-on-red behavior exists because of this).
**Suggested direction:** Auto-detect passed-on-retry specs from Playwright reports, log them to a ledger, and report a quarantine list for triage.
**Risk if untouched:** Recurring lost nights; flake debt accumulates unmeasured.
**Status (2026-07-07):** ⬜ Open — 🟨 (one policy decision: what quarantine *does* — report-only vs auto-skip).

### E5 Scheduled stale-worktree GC
**Location:** `bin/cleanup` (`--all` / `--dry-run` sweep of worktrees, branches, and Docker stacks) + `bin/wt-status` (MERGED / OPEN-PR / UNPUSHED / STALLED / EMPTY classifier — the safety layer).
**Problem:** The sweep runs on the merge-to-prod path (`bin/merge-master-to-prod` invokes `bin/cleanup --all` synchronously), so it fires only when someone promotes to prod — there is still no time-based schedule, so a stretch with no prod promotion lets dead stacks accumulate.
**Suggested direction:** Schedule `bin/cleanup --all` (launchd/cron), sweeping only worktrees `bin/wt-status` classifies as safe (MERGED/EMPTY), surfacing STALLED ones instead of deleting them.
**Risk if untouched:** Disk/RAM held by dead Docker stacks; stale worktrees confuse session coordination.
**Status (2026-08-24):** ◑ Partial — sweep + classifier merged; scheduling absent. Docker teardown was also under-reaping (built `tailwind` images and anonymous node_modules volumes were never removed, build cache was never pruned) — fixed in this branch's `bin/cleanup` change, which added tailwind to both image lists, added a stranded-anonymous-volume sweep, and added an unused-only `docker builder prune`. 🟨 (the schedule itself is host-local, not PR-shippable).

➜ E6 Diff-scoped PHPStan wrapper — ✅ Implemented (2026-07-14): see [archive](archive/dev-efficiency-backlog-archive.md).

### E7 Parallel PHPUnit
**Location:** `ibl5/composer.json:17,21` — `brianium/paratest ^7.23`; `composer run test` runs paratest.
**Status (2026-07-07):** ✅ Implemented — the most-run local command is parallel; `#[Group('database')]` tests stay serial against the shared fixture.

### E8 Memory lines → mechanical gates (umbrella)
**Location:** The memory index (`MEMORY.md`) and its context→mechanical audit; delivered gates land in `bin/` + `.github/workflows/`.
**Problem:** Norms that live only as memory lines are always-loaded context tax and fail silently when stale; a gate enforces the norm at zero per-turn cost.
**Suggested direction:** Keep converting each mechanizable norm to a gate that retires its memory line (pairs with T7 in [token-spend-backlog.md](token-spend-backlog.md)).
**Delivered:** memory-expiry marker + SessionStart hook; the `bin/test` unit/db/e2e dispatcher; the seed-id collision gate (`ibl5/bin/check-seed-id-uniqueness`, CI-wired) — both previously "queued" here, now shipped; plus the bucket-B round-2/3 sweep — bash-guard checks (plans-rm, force-push, worktree-stash, prod-migration SQL), `ibl5/bin/check-config-example`, `ibl5/bin/check-xml-class-refs` + extension-less `bin/` scripts enrolled in `ibl5/phpstan.neon`, `ibl5/bin/check-th-aria-label`, and the ASG conference-split regression lock (`ibl5/tests/LeagueTest.php`).
**Open (2026-07-14):** exactly one — the **free-agents teamid write guard** (`FREE_AGENTS_TEAM_NAME`/null team must not write `teamid=0`). Not cleanly mechanizable as a lint (the guards live legitimately in ~8 sibling controllers, so a call-graph rule is false-positive-prone); the tractable route is characterization tests per write path (the ASG precedent). Needs its own `/plan` — not an ad-hoc, not a today-ship.
**Risk if untouched:** Recall dilution plus repeat failures the gates would have caught.
**Status (2026-07-14):** ◑ Partial — cheap-gate well exhausted; the only remaining item (free-agents guard) is a standalone `/plan`. 🟨.

➜ E9 Meta-tooling growth bar — ✅ Implemented (2026-07-09): see [archive](archive/dev-efficiency-backlog-archive.md).

### E10 Schema baseline auto-regen
**Location:** `.github/workflows/migration-safety.yml` (`regen-schema-dump` job) + `bin/regen-schema-dump`.
**Problem (was):** The schema reference was a stale March snapshot; every schema question paid a verify-against-migrations tax.
**Status (2026-07-07):** ✅ Implemented — on every master push, CI rebuilds the schema from migrations and auto-commits `ibl5/docs/schema/current-schema.sql` if changed. (The `000_baseline` migration snapshot itself is intentionally untouched — the regenerated dump is the source of truth for schema questions.)

➜ E11 In-PR pre-baked image build — ✅ Implemented (2026-07-09): see [archive](archive/dev-efficiency-backlog-archive.md).

➜ E12 `bin/wt-new` fails with misleading error when invoked from inside a worktree — ✅ Implemented (2026-08-19): see [archive](archive/dev-efficiency-backlog-archive.md).

➜ E13 `bin/wt-new --base <branch>` fast-forwards the wrong branch — ✅ Implemented (2026-08-30): see [archive](archive/dev-efficiency-backlog-archive.md).

➜ E14 `/pr-ready` Monitor exemption in invariants is stale — ✅ Implemented (2026-08-25): see [archive](archive/dev-efficiency-backlog-archive.md).

➜ E15 `/pr-ready` Phase 2 delegation packet tells rebase delegate to push — ✅ Implemented (2026-08-25): see [archive](archive/dev-efficiency-backlog-archive.md).

➜ E16 `bin/watch-run` declares a run finished on its first poll, before launchd registers the label — ✅ Implemented (2026-08-31): see [archive](archive/dev-efficiency-backlog-archive.md).


➜ E17 Skill prose carries fixed-count words that go stale when the enumerated set grows — ✅ Implemented (2026-08-25): see [archive](archive/dev-efficiency-backlog-archive.md).

---

## Burn-down process

1. Pick an entry; `/plan` it (or ad-hoc per the work-triage rule for S items).
2. Implement in a worktree; green-green via existing CI.
3. Update this doc's status; bump `last_verified` (CI enforces via `bin/check-docs`).

### E18 `/pr-ready` Phase 6.5 commits new files but never regenerates the PR body's `files-changed` block

`class:` a machine-generated PR-body manifest whose only regeneration triggers live inside the skill that created it, so a *different* skill committing to the same branch leaves the manifest contradicting the diff.

**Occurrences**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `.claude/skills/pr-ready/SKILL.md` Phase 6.5 step 4 (commit) | yes — commits new files, zero mentions of `files-changed` | yes | fixed this pass (body corrected by hand); gate not built — filed |
| 2 | `.claude/skills/post-plan/SKILL.md:109` | no — this is the generator, and it mandates regeneration on its own later body writes | yes | not fixed — correct as written |
| 3 | `.claude/skills/post-plan/SKILL.md:276` | no — Phase 6 regenerates the block inside its own `gh pr edit --body` write | yes | not fixed — correct as written |
| 4 | `bin/`, `.github/workflows/` | n/a — scanned for a verifier | n/a | none found — `grep -rl files-changed bin .github` returns zero hits |

`prevention_ladder:`

- **rung 0 — already covered by an existing gate?** No. Nothing in `bin/` or `.github/workflows/` mentions `files-changed`, so no gate compares the block against the diff.
- **rung 1 — extend an existing gate/host? LANDS HERE.** `/pr-ready` Phase 6.5 step 4 already owns the commit for this exact surface; add a regeneration + `gh pr edit --body` write there, copying verbatim the recipe `/post-plan` `SKILL.md:276` already uses ("regenerate the block as part of this same `gh pr edit --body` write"). Existing host, existing recipe, no new mechanism.
- **rung 2 — a rule doc under `.claude/rules/`?** Insufficient. The failure fires mid-run inside an automated skill, where a documented norm has no reader at the moment it is violated; the skill text itself is the only surface the runtime actually reads.
- **rung 3 — a PHPStan rule?** N/A — no PHP on this surface.
- **rung 4 — a CI gate?** Rejected. Would require a new `check-pr-body-manifest` reading a live GitHub body and diffing it against `origin/master...HEAD` — a new gate on a new surface, when rung 1 fixes the same defect with a prose edit to the one skill that causes it.
- **rung 5 — a new hook?** Rejected. Hooks fire on tool calls, not on PR-body state; a hook could not observe the staleness at all.

Landing rung is **1**, so rungs 3–5 are never reached and the four `.claude/rules/meta-tooling-bar.md` extend-before-add conditions do not apply.

`artifact destination:` `.claude/skills/pr-ready/SKILL.md`, Phase 6.5 step 4 (the commit step). In-repo — it appears in a normal PR diff, not out-of-repo.

**Related.** [E17](#e17-skill-prose-carries-fixed-count-words-that-go-stale-when-the-enumerated-set-grows) came from the same PR but is a different class: stale hand-written *prose* vs. a stale *generated* manifest. They are filed separately because the prevention lands on different hosts and different ladder rungs — E17 warrants no gate at all, E18 lands on rung 1.

*(discovered 2026-08-25 during #1981)*

### E19 `/pr-ready` materialize-from-pin sites declare no fallback, so a pin that predates the script loops forever

`class:` a runtime instruction that materializes a file from a pinned SHA whose only declared recovery is "re-run the whole command", so a **genuinely absent path** (the pin predates the file) is indistinguishable from a torn write — and the prescribed recovery is a deterministic infinite loop.

Surfaced by `/pr-ready` running on PR #1982, its own externalization PR. The Phase 1.3 pin (`4a5ef805d`) predates all five new `scripts/*.sh`, so every script site's `git show <MASTER_SHA>:… && test -s …` failed the `test -s`. The three progressive-disclosure **includes** have a declared worktree fallback with an `include-source:` disclosure string; the **script** sites, added by #1982, inherited none — the invariant said only "re-run the whole command". The run had to depart from the written procedure at five sites with no clause authorising it.

**Occurrences**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `.claude/skills/pr-ready/SKILL.md:34` (Multi-command blocks invariant) | yes — governs all six script sites at once | yes | fixed this pass (sub-bullet fallback added) |
| 2 | `.claude/skills/pr-ready/SKILL.md` lines 171 / 266 / 297 / 325 / 337 (the six script sites) | yes — each defers to the invariant above | yes | fixed this pass, transitively via #1 |
| 3 | `.claude/skills/pr-ready/SKILL.md:37` (include-fallback clause) | no — already carries the declared fallback this class is missing | yes | not fixed — correct as written |
| 4 | `.claude/skills/pr-ready/_rebase-and-conflicts.md:24` (rules-file materialize) | no — delegation packet carries its own declared fallback | yes | not fixed — correct as written |

`prevention_ladder:`

- **rung 0 — already covered by an existing gate?** No. `bin/test-pr-ready-now` asserts on the *shape* of the materialize call line (`test -s` guard, trailing arg) but never on the presence of a recovery arm.
- **rung 1 — extend an existing gate/host? LANDS HERE.** The Multi-command-blocks invariant already owns the shape every script site defers to, and the include-fallback clause two bullets down already contains the exact recovery arm and disclosure string. Extending the former to point at the latter is a prose edit to the one surface the runtime actually reads, with no new mechanism.
- **rung 2 — a rule doc under `.claude/rules/`?** Insufficient, and for the same reason as [E18](#e18-pr-ready-phase-65-commits-new-files-but-never-regenerates-the-pr-bodys-files-changed-block): the failure fires mid-run inside an automated skill, where a documented norm has no reader at the moment it is violated.
- **rung 3 — a PHPStan rule?** N/A — no PHP on this surface.
- **rung 4 — a CI gate?** Rejected. A `check-materialize-fallback` (example) would have to parse skill prose for a recovery arm — a new gate on a new surface, when rung 1 fixes it with one sub-bullet.
- **rung 5 — a new hook?** Rejected. Hooks fire on tool calls; this is a documentation gap that never reaches a tool call.

Landing rung is **1**, so rungs 3–5 are never reached and the four `.claude/rules/meta-tooling-bar.md` extend-before-add conditions do not apply.

`artifact destination:` `.claude/skills/pr-ready/SKILL.md`, the Multi-command blocks invariant. In-repo.

**Related.** [E18](#e18-pr-ready-phase-65-commits-new-files-but-never-regenerates-the-pr-bodys-files-changed-block) is also a `/pr-ready` self-inflicted gap, but it concerns a **stale generated artifact**; E19 concerns a **missing recovery arm** — different failure mode, different trigger, same host file.

*(discovered 2026-08-25 during #1982)*

➜ E20 Ship-pipeline coverage assertions are emitted against a narrower slice than the PR's cumulative diff — ✅ Implemented (2026-09-05): see [archive](archive/dev-efficiency-backlog-archive.md).

➜ E21 Test assertions land as static source greps that pass while the behavior they name is absent — ✅ Implemented (2026-09-04): see [archive](archive/dev-efficiency-backlog-archive.md).

---

➜ E22 Plan-declared negatives and PR body Scope dropped during Phase 6.5 remediation — ✅ Implemented (2026-08-27): see [archive](archive/dev-efficiency-backlog-archive.md).

➜ E23 PR body deletion volumes diverged from code's `EXPECTED` constants — ✅ Implemented (2026-08-27): see [archive](archive/dev-efficiency-backlog-archive.md).

### E24 `/post-plan` Phase 4B can hand-write its review comment, bypassing `post_review_summary` and making the review undetectable

Phase 4B's posting step is prose-only: it *tells* the run to call `post_review_findings` / `post_review_summary` but nothing enforces it. A run that instead composes the comment freehand still performs a real review — the findings are genuine — but the artifact lands outside the helper's envelope, with three downstream consequences: (i) the `/pr-ready` `PHASE_4B_RAN` probe (`4b-probe.sh`, regex `^#{1,6} +Code review`) misses it and recommends a redundant `/pr-review`; (ii) findings are prose-summarized instead of posted as inline threads carrying `<!-- score: N -->`, so `list_open_review_findings` / `resolve_review_finding` cannot disposition them and `unresolved-findings-hold` is blind to them; (iii) the helper's `<details>` envelope and footer are absent. The failure is silent in both directions — the run reports success, and the probe reports "never ran."

The fix belongs at the **emitter**, not the probe. Loosening the probe regex to match freehand prose makes a false `true` more likely, and `.claude/skills/pr-ready/SKILL.md` explicitly names a false `true` as the worse failure mode. The defect is that Phase 4B is permitted to write the comment by hand at all.

**Occurrences**

| # | File:line | Class | Live? | Status |
|---|-----------|-------|-------|--------|
| 1 | `.claude/skills/post-plan/_phase-4-review-audit.md:101-102` — mandates `post_review_findings` / `post_review_summary` in prose; unenforced | A | yes | ✅ fixed |
| 2 | PR #2001 `## Code Review Summary` + `## Security Audit Summary` comments — real Phase 4B output, written freehand (no `<details>`, no `### Code review`, no `PRF_FOOTER`), invisible to the 4B probe; its one finding (`FILL_TEAM_BACKUP` docblock, scored 25) never became a dispositionable thread | A | yes | ✅ prevented (artifact not retro-fixed) |
| 3 | PR #1956 `## Phase 4D — Code Review Summary` comment (2026-08-22, five days before this entry was opened) — four review agents ran and produced two informational findings (A1, D1); freehand envelope, so the 4B probe returned empty and the 2026-09-04 `/pr-ready` verdict asserted "Phase 4B structured code review: NOT RAN … run `/pr-review 1956` before merge" on a PR that had been reviewed. Neither finding became a dispositionable thread | A | yes | ✅ prevented (artifact not retro-fixed — #1956's `/pr-ready` verdict still reads "NOT RAN") |

`prevention_ladder:`

- **rung 0 — already covered?** No. `bin/lib/post-review-findings.sh` is the sanctioned path but is not the *only* reachable path; no gate checks that the comment Phase 4B produced came through it.
- **rung 1 — extend an existing gate? LANDED HERE.** Rewrote `.claude/skills/post-plan/_phase-4-review-audit.md` §4D posting steps from descriptive prose into four verbatim copy-paste command blocks (code review × {findings, no-issues}, security audit × {findings, no-issues}), fronted by a "never compose these comments by hand" callout naming all three downstream consumers of the envelope (the 4B probe, `list_open_review_findings` / `resolve_review_finding`, `unresolved-findings-hold`) and both observed occurrences.
- **rung 2 — a rule doc?** Insufficient alone: the norm has to fire at the exact step that emits the comment, not as ambient guidance. Instead the same prohibition was added as a § to `.claude/review-shared/_posting-procedure.md`, the canonical shared source both `/pr-review` Step 6 and `/security-audit` Step 6 already Read — so the two other emitters of the same envelope inherit it at their own posting step, not ambiently.
- **rung 3 — a mechanical gate?** Landed as a self-check rather than a gate: §4D now closes with a `prf_envelope_count TITLE` helper snippet and requires a **strict increase** across each `post_review_*` call, with instruction to re-post through the helper if the count does not move. Before/after rather than an absolute count, because a PR that already carries a `### Code review` comment (a prior `/pr-review`, a Phase-5 strict-loop re-entry into Phase 4) reads as passing under an absolute threshold even when this run posted nothing — the population the check most needs to cover. A hard failing gate stays available as a follow-on if this proves insufficient.
- **rungs 4–5** — N/A. Explicitly ruled out: relaxing the `4b-probe.sh` regex, which trades a detectable false negative for an invisible false positive.

`artifact destination:` `.claude/skills/post-plan/_phase-4-review-audit.md` + `.claude/review-shared/_posting-procedure.md` (in-repo). Built 2026-09-04.

*(discovered 2026-08-27 during #2001; occurrence 3 found 2026-09-04 while diagnosing #1956's `/pr-ready` verdict)*

### E25 `/pr-review` Step 2c's migration-exclusion `awk` filter is a no-op, so every review silently ingests full migration diffs

`.claude/skills/pr-review/SKILL.md:37` filters migrations out of the diff before the 100 KB size branch:

```
awk '/^diff --git.*migrations\//{skip=1} /^diff --git/{skip=0} skip==0{print}'
```

Both patterns match the same line. A `diff --git a/ibl5/migrations/167_….sql …` header sets `skip=1` in rule 1, then rule 2 — which matches *every* `^diff --git` line, including that one — immediately resets it to `0`. `skip` is therefore never `1` at print time and the filter passes the input through unchanged. Verified empirically on PR #2001: the "filtered" diff still contained both migration file headers (`167_create_phantom_repair_backup_tables.sql`, `168_delete_phantom_season2008_boxscores.php`).

Consequence is size control, not correctness — reviews get *more* context than intended, and the `DIFF_SIZE > 100000` per-file-API fallback trips earlier than designed. On a migration-heavy PR this can push a review onto the fallback path (or, past that, into the test-file-exclusion path) for no real reason. The correct form guards rule 2 with `!/migrations\//`, or uses an explicit else.

**Occurrences**

| # | File:line | Class | Live? | Status |
|---|-----------|-------|-------|--------|
| 1 | `.claude/skills/pr-review/SKILL.md:37` — Step 2c filter; sole copy in the repo (grepped `.claude/`, `bin/`) | A | yes | not fixed this pass |

`prevention_ladder:`

- **rung 0 — already covered?** No. Skill-body shell snippets are prose to every existing gate; nothing executes or lints them.
- **rung 1 — extend an existing gate?** No natural host. `bin/check-docs` resolves path *references* in doc bodies, not shell semantics, and teaching it to evaluate embedded snippets is far outside its responsibility.
- **rung 2 — a rule doc?** No — this is a one-line logic bug, not a norm.
- **rung 3 — fix in place. LANDS HERE.** Correct the `awk` to `/^diff --git/ && !/migrations\//{skip=0}` (or an explicit if/else), and re-verify by grepping the produced diff for `^diff --git.*migrations/` — expecting zero hits. A one-line change with a one-command check; no new tooling, per the extend-before-add bar in `.claude/rules/meta-tooling-bar.md`.
- **rungs 4–5** — N/A.

`artifact destination:` `.claude/skills/pr-review/SKILL.md` Step 2c (in-repo). Not built this pass.

*(discovered 2026-08-27 during #2001)*

➜ E26 `bin/plan-now` timestamp collision on back-to-back invocations — ✅ Implemented (2026-08-30): see [archive](archive/dev-efficiency-backlog-archive.md).

➜ E27 `filterGitignored()` in `bin/check-docs`: unchecked proc exit, non-NUL-delimited check-ignore paths — ✅ Implemented (2026-09-04): see [archive](archive/dev-efficiency-backlog-archive.md).

### E28 PR body hand-authored migration numbers not updated after a forced renumber

**class:** a hand-authored PR body prose claim that becomes stale when a forced migration renumber is applied to code and comments but not to the body's summary bullets and manual-testing rows.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `#2022` PR body — "**169** (DDL)" / "**170** (PHP)" / "After CI deploys migration **170**" contradicted the shipped 170/171 pair | yes | fixed this pass | fixed this pass |
| 2 | Other PRs with migration pairs (generic) | near-miss — only arises on a forced renumber mid-implementation | N/A | not fixed — filed |
| 3 | `#1861` PR body — "Add ADR-0102" contradicted actual ADR-0104 (renumber from 0100 while PR sat open 19 days) | yes | fixed this pass | fixed this pass |

**prevention_ladder:**
- rung 0 — already covered by an existing gate? **LANDS HERE.** `/pr-ready` Phase 6 check 4 ("PR body vs. diff") is designed to catch exactly this mismatch — it fired correctly on PR #2022 and blocked the verdict until the body was corrected. No additional gate is warranted.
- rungs 1–5 — N/A given rung 0 coverage.

`prevention_ladder: no gate warranted — /pr-ready Phase 6 check 4 (PR body vs. diff mismatch) is the existing detection gate; it fired and caught this correctly; building a duplicate gate adds noise without coverage.`

`artifact destination: n/a — no gate`

*(discovered 2026-08-31 during #2022)*

### E29 Shell-harness cases pre-populate `$WORK` instead of driving invocation 1

**class:** test cases for a two-invocation script that pre-populate the intermediate `$WORK` directory and only call invocation 2, letting all invocation-1 logic (isDraft partition, DIRTY bucket awk, `depends-on` fixpoint, `bin/pr-overlap` call) escape test coverage. Compounded in this pass by a SKILL.md prose deletion that silently dropped a guardrail clause on the same edit that widened a permission grant.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/test-pr-attack` cases 6–11 — all drove `--work` on a hand-crafted `$WORK`, never `--gate-candidates` | yes | fixed this pass | fixed this pass |
| 2 | `bin/pr-attack` gate-candidate diff path — gate nominees wrote `gate-candidates.tsv` after the diff-fetch loop, so nominees without contended files never had diffs fetched | yes | fixed this pass | fixed this pass |
| 3 | `.claude/skills/pr-attack/SKILL.md` read-only paragraph — `and it never recommends batching reviews or arming anything` clause deleted; enforcement sentence absent | yes | fixed this pass | fixed this pass |

`prevention_ladder:`

- **rung 0 — already covered by an existing gate?** No gate checks that harness cases for multi-invocation scripts exercise invocation 1 rather than pre-populating its output state.
- **rung 1 — extend an existing gate?** No existing gate owns this surface.
- **rung 2 — a rule doc? Yes — landing rung.** A `.claude/rules/` doc noting that test cases for multi-invocation scripts must call the first invocation rather than pre-populating its output; same doc notes that SKILL.md prose edits must be diffed against the prior version to catch silent deletions. Meta-tooling-bar extend-before-add: no host to extend ✓, distinct trigger ✓, earns its upkeep ✓, no cheaper alternative ✓. Rule not authored this pass.
- **rungs 3–5** — N/A. No mechanical check would detect the pre-population pattern without semantic understanding of what `$WORK` contains and how it is populated.

`artifact destination:` a new `.claude/rules/` doc on test-case design for multi-invocation scripts (not created this pass).

*(discovered 2026-09-01 during #2050)*

### E30 `bin/pr-ready-now` already-running skip only sees its own launchd jobs, so an interactive `/pr-ready` is invisible

**class:** a concurrency guard whose liveness probe covers only the launcher's own spawn mechanism, while the guarded resource is keyed by a launcher-independent identifier. `bin/pr-ready-now:717-720` decides "already running" solely by matching `com.ibl5.pr-ready-now-<pr>` against a one-shot `launchctl list` snapshot, so a `/pr-ready` started interactively in another Claude session carries no launchd label and is never detected. The two runs then collide on shared state: `.claude/skills/pr-ready/SKILL.md:41` mandates scratch filenames keyed to the PR number and explicitly forbids `$$`, and `bin/pr-ready-now:246` (`--stop`) confirms the convention with `rm -f /tmp/pr-ready-*-"${PR}".*`. Two concurrent runs on one PR therefore overwrite each other's mid-phase scratch, and both push to the same branch.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/pr-ready-now:717-720` — `grep -qE "com\.ibl5\.pr-ready-now-${pr}$" <<< "$LAUNCHCTL_LIST"` is the whole liveness test | yes | yes — observed 2026-09-01: a peer session held an interactive `/pr-ready` on #1966 (PR-keyed `/tmp` scratch being written), and `bin/pr-ready-now` dry-run still listed #1966 as `WOULD FIRE` | not fixed — filed |
| 2 | `bin/pr-ready-now:246` (`--stop`) — `rm -f /tmp/pr-ready-*-"${PR}".*` deletes by PR number, so `--stop` on a launchd job also wipes an unrelated interactive run's scratch | yes | yes — same root cause (PR-keyed state, launcher-scoped control) | not fixed — filed |

Not a defect in the anchoring or the SIGPIPE handling: the `$`-anchor (guarding 1892-vs-189) and the snapshot-into-a-variable pattern are both correct and deliberately commented. The gap is scope of the probe, not its mechanics.

**prevention_ladder:**

- **rung 0 — already covered by an existing gate?** No. The launchd-label check is the only concurrency guard, and it is exactly the one that misses.
- **rung 1 — extend an existing gate? Yes — landing rung.** Give `/pr-ready` a PR-keyed lock the skill itself takes at Phase 0 (a `/tmp/pr-ready-lock-<pr>` carrying the owning PID), and extend the existing skip in `bin/pr-ready-now:717-720` to consult it alongside the launchd snapshot. Same fail-closed shape already used by `_label_alive()`: a stale lock whose PID is dead must not block a fire, so only a confirmed-live PID skips. `--stop` (line 246) should likewise refuse to delete scratch it does not own. Meta-tooling-bar extend-before-add does not apply — this extends `bin/pr-ready-now`, adds no new gate, hook, or workflow.
- **rung 2 — a rule doc?** Insufficient alone. The colliding party is a headless batch that no human is watching; a documented norm cannot be honoured by a launchd job.
- **rungs 3–5** — N/A once rung 1 lands.

`artifact destination:` `bin/pr-ready-now` (skip predicate + `--stop` ownership check), `.claude/skills/pr-ready/SKILL.md` (Phase 0 lock acquisition + release), and a case in `bin/test-pr-ready-now` that seeds a live-PID lock and asserts the PR is skipped.

*(discovered 2026-09-01 while assessing en-masse `/pr-ready` readiness; not tied to a single PR)*

---

### E31 `bin/plan-now` help span truncated by bare `#`; test assertions miss declared secondary-behaviour tokens

> ✅ Implemented 2026-09-04 — see [archive](archive/dev-efficiency-backlog-archive.md#e31-binplan-now-help-span-truncated-by-bare--test-assertions-miss-declared-secondary-behaviour-tokens).

### E32 Phase 6 review notes on PR #1861 (long-lived branch drift)

**class:** n/a — minor plan-vs-implementation drifts, scope-creep-adjacent changes, and in-flight operational artifacts surfaced as notes by `/pr-ready` Phase 6 on a 19-day, 23-rebase branch; none reached a blocking clause; documented here because Mode: in-PR requires an entry for every Phase 6 finding.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | Note 2.1: ADR numbered 0104 not plan's 0100 — plan-permitted (`bin/next-adr` decides) | n/a | no — plan-resolved | not fixed — not a defect |
| 2 | Note 2.2: `last_verified` dates set to 2026-08-15 not plan's 2026-08-13 — master already ahead, correct | n/a | no — correct behavior | not fixed — not a defect |
| 3 | Note 2.3: README index row appended after 0113 not 0099 — cosmetic ordering gap, `bin/check-numbering` does not enforce order | n/a | yes — cosmetic | not fixed — cosmetic |
| 4 | Note 2.4: plist-grep realised via pre-existing case 20 (stricter than plan's source grep) | n/a | no — better route | not fixed — better route |
| 5 | Note 3.1: `bin/test-sigpipe-grep-q-guard` rewrite not in plan — necessary consequence of Phase 4 dedupe, net stricter | n/a | no — correct | not fixed — not a defect |
| 6 | Note 3.2: `bin/lib/README.md` index row added — correct convention follow | n/a | no — correct | not fixed — not a defect |
| 7 | Note 3.3: HEAD remediation commit (`933899189`) — Phase 6.5 union-merge artifact cleanup | n/a | no — already done | fixed this pass |
| 8 | Note 5.1: `Truly-manual` row 40 not performed — intentional; needs post-merge prod dispatch per plan | n/a | no — intentional | not fixed — intentional |
| 9 | Note 6.1: earlier rebase union-merge artifacts (duplicate 0103 row + stale `last_verified`) | n/a | no — already remediated | fixed this pass |

**prevention_ladder:** no gate warranted — `/pr-ready` Phase 6 already surfaces these; they are expected review notes for a long-lived branch with 23+ force-pushes; no per-note gate would catch future occurrences reliably without high false-positive cost.

`artifact destination: n/a — no gate`

*(discovered 2026-09-01 during #1861)*

### E33 Phase 6 review notes on PR #1815 (authz verdict extraction)

**class:** n/a — F1 and F2 were blocking; F3–F7 were notes. F1, F2, and F5 remediated in Phase 6.5; F3, F4, F6, F7 addressed in PR body notes.

**occurrence table:**

| # | Finding | Blocking? | Status |
|---|---------|-----------|--------|
| F1 | PR body credited FreeAgencyController DI injection as new; it pre-existed on master (maint 14.6 batch 2) — plan Step 3.1 was skipped as the plan directs | yes | fixed Phase 6.5 — body updated |
| F2 | Backlog residual numbered 6.24; that ID was already taken by the Olympics player-page bug (PR #1825) — renumbered to 6.25 | yes | fixed Phase 6.5 — three sites in maintenance-backlog.md, one in archive |
| F3 | Phase 5 reflection characterization test (`testProcessWaiverSubmissionRefusesNullTeamWithoutProcessing`) was not written — plan marks it transient (write pre-extraction, delete at Step 5.3); semantic preservation covered by WaiversSubmissionServiceTest rows 10–16 | no | not fixed — not a defect; body clarified |
| F4 | `WaiversSubmissionService::submit()` adds `is_string`/`is_numeric` coercion guards beyond "moved verbatim" — a net-tightening deviation | no | not fixed — net-tighter; body notes the deviation |
| F5 | WaiversController ctor fallback used `$this->processor, $this->salaryCapRepo` instead of local params; exposed dead property declarations | yes | fixed Phase 6.5 — switched to params, removed dead props |
| F6 | Smoke B (Action=trade pre-filter) and Smoke C (unauthenticated POST) observations stated more confidently than warranted; B was not runnable end-to-end over HTTP, C short-circuited at isUser() | no | not fixed — not a defect; body notes revised |
| F7 | Phase 9 Step 9.4 last_verified sub-step skipped — master's values already post-date the commit date (2026-08-09) | no | not fixed — intentional; body notes skip |

**prevention_ladder:**

- **rung 0 — already covered by an existing gate?** No. `/pr-ready` Phase 6 catches these after the fact.
- **rung 1 — extend an existing gate?** No existing gate checks plan Step 3.1 skip documentation or backlog item number collision.
- **rung 2 — a rule doc? Partially applicable.** (a) When a plan step is documented as "may already be done — READ THE FILE FIRST," the PR body should say "skipped as plan directs" rather than crediting the work as new. (b) Before assigning a residual backlog item number, confirm the number is free on master. These are author-discipline items, not automatable gates.
- **rungs 3–5** — N/A.

`artifact destination: n/a — author discipline, no gate`

*(discovered 2026-09-01 during #1815)*

### E34 Auto-Generated `codebase-map.md` Row — No Defect (PR #1903 F5)

**class:** n/a — `.claude/rules/codebase-map.md` is auto-generated by `bin/generate-codebase-map`; the added row (`| DraftClassImport | 1 | - | | |`) is mechanical output of the generation script; no enforcement mechanism changed.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `.claude/rules/codebase-map.md` — one new row for `DraftClassImport` | n/a — auto-generated | no — not a defect | not fixed — not a defect |

**prevention_ladder:** no gate warranted — auto-generated file; already governed by `bin/generate-codebase-map`.

`artifact destination: n/a — no gate`

*(discovered 2026-09-01 during #1903)*

### E35 Phase 6 review notes on PR #1800 (game-of-that-day validation floor)

**class:** a hand-written test count in the PR body that conflates the Verification Matrix row count with the PHPUnit method count, replicated permanently into an in-repo archive entry.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `ibl5/docs/backlog/archive/ci-backlog-archive.md:72` — "18 PHPUnit tests" | yes | yes | fixed this pass — corrected to "7 PHPUnit test methods (9 cases)" |
| 2 | PR #1800 body bullet — "18 new PHPUnit test cases" | yes | yes | fixed this pass — corrected to "7 new PHPUnit test methods (9 cases)" |

**prevention_ladder:**

- **rung 0 — already covered by an existing gate?** No. `/pr-ready` Phase 6 catches this class after the fact.
- **rung 1 — extend an existing gate?** No existing gate checks hand-written test counts in PR bodies.
- **rung 2 — a rule doc?** A reminder in `.claude/rules/commit-conventions.md` could note "use method count, not Verification Matrix row count" — but this is author discipline, not automatable.
- **rungs 3–5** — N/A. A PHPStan rule, CI gate, or hook cannot read a PR body or verify a claim inside it.

`prevention_ladder: no gate warranted — the Verification Matrix already records the correct method count ("the SimRecapPayloadTest count is 16 (9+3+4)"); the wrong figure requires ignoring the plan's own count. Author discipline; no mechanical gate is cheaper than re-reading the plan.`

`artifact destination: n/a — no gate`

*(discovered 2026-09-02 during #1800)*

### E36 Phase 6 review notes on PR #1965 (check-plan row-width gate)

**class:** dead variable masking and fence-blindness gaps in `bin/check-plan`/`bin/test-check-plan` that cause test verdicts or gate results to misfire in edge cases

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/test-check-plan:~1877` | yes | yes | fixed this pass |
| 2 | `bin/check-plan:~740-773` (gate N awk, no fence stripping) | near-miss | yes | not fixed — filed |

**prevention_ladder:**

- **rung 0 — already covered by an existing gate?** No existing gate covers dead variables in test scripts or fence-blindness in plan checkers.
- **rung 1 — extend an existing gate?** Could extend check-plan to strip fences before the N awk, but adds complexity.
- **rung 2 — a rule doc?** No rule doc warranted — too specific.
- **rung 3 — PHPStan?** PHPStan doesn't cover bash scripts.
- **rung 4 — CI gate?** CI gate could run a fence-probe fixture in `bin/test-check-plan` for rung 1; for dead variables, shellcheck CI could catch them.
- **landing rung:** no gate warranted — the dead-variable fix is applied this pass; the fence-blindness is a documented design tradeoff with zero current false positives, and adding a fixture for it would encode a behavioral contract before the human reviewer has decided whether to fix it.

`prevention_ladder: no gate warranted — Note 2 fixed in-PR; Note 1 is a design tradeoff with zero live false positives, documented for human-reviewer consideration`

`artifact destination: n/a — no gate`

*(discovered 2026-09-02 during #1965)*

### E37 Phase 6 review notes on PR #2045 (E2E DCE mobile save-flow)

**class:** a plan spec for an E2E test change that (a) omits the behavioral step (deliberate depth-slot mutation via the mobile stepper) that makes the read-back assertion discriminating — making the plan's prescribed recipe vacuous — and (b) omits the mandatory archive step for a backlog-status-glyph flip, causing unplanned scope that `bin/check-docs` would block if the implementation had followed the plan literally.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `~/claude-plans/e2e-d16-dce-mobile-save-readback.md` Phase 1 — plan recipe prescribes `allInputValues()` capture before submit with no prior mutation; the assertion would be vacuous without a prior state change | yes | no — plan file only; implementation diverged by a better route | not fixed — not a defect in the shipped code; body updated to name the mutation |
| 2 | `~/claude-plans/e2e-d16-dce-mobile-save-readback.md` Phase 2 — plan recipe specifies only the glyph flip, omitting the mandatory archive move that `bin/check-docs` enforces | yes | no — plan file only; implementation included the archive step | not fixed — not a defect in the shipped code; noted for future plan templates |

**prevention_ladder:**

- **rung 0 — already covered by an existing gate?** Partially: `bin/check-docs` enforces the archive step at commit time (the implementation was correct), but no gate checks whether the *plan* named it.
- **rung 1 — extend an existing gate?** No existing gate reads plan files or validates that a read-back assertion is discriminating.
- **rung 2 — a rule doc?** A note in `.claude/rules/` (or the plan-authoring guidance in `_architect-contract.md`) could say: "for E2E read-back plans, the plan must include the mutation that makes the assertion discriminating, not just the capture-and-assert shape." And: "a backlog-flip plan must scope the archive move explicitly." Both are author-discipline items.
- **rungs 3–5** — N/A. A PHPStan rule, CI gate, or hook cannot evaluate whether a prescribed test recipe is discriminating without running the test.

`prevention_ladder: no gate warranted — (a) the assertion-discriminator gap is an author-judgment item; no mechanical gate can verify a prescribed assertion is discriminating without executing the test; (b) the archive step gap is already enforced at commit time by bin/check-docs; the plan was under-specified but the implementation was correct.`

`artifact destination: n/a — no gate`

**check 4(i) remediation (PR body Scope):** PR body updated via `gh pr edit` to add: "The test first mutates one depth slot via the mobile stepper, captures the resulting select values, submits, then navigates…" — the causal claim "proving persisted" now holds on its stated evidence.

**check 4(ii) remediation (Manual Testing):** PR body "No manual testing needed — all changes are covered by unit and E2E tests" updated to: "No interactive manual testing needed — E2E covers the save flow. However, a human must confirm two consecutive green CI runs before merging (see Notes)." — no longer contradicts the Notes hold.

**check 5 (Verification Matrix row 2 outstanding):** `class: n/a — no defect; row 2 (second consecutive green CI run) is the expected hold state, gated by human-signoff and the plan's explicit Automouse Hold Justification.`

*(discovered 2026-09-02 during #2045)*

➜ E38 `/pr-ready` lost-work guard blind to prior-run destructive rebase — ✅ Implemented (2026-09-05): see [archive](archive/dev-efficiency-backlog-archive.md).

### E39 Phase 6 review notes on PR #1824 (StandingsUpdater echo→logger migration)

**class:** PR body / plan accuracy drift — wrong method name, wrong scope count, undocumented scope creep, stale plan literal, and a manual backlog row not retired in the plan

**occurrence table:**

| # | Finding | Live? | Status |
|---|---------|-------|--------|
| B1 | Body used `outputBuffer()` instead of `takeOutputBuffer()`; scope said "removes two $log accumulators" not three; `OlympicsFlatStandingsUpdater` subclass not named in Scope | yes | fixed this pass (gh pr edit) |
| N3 | `CheckDocsCliTest.php` rewrite in the diff but not in the plan; unbundled scope creep | yes | noted in PR body (transparency note added) |
| N4 | Plan said "drain both updaters' buffers" but only one updater is injected per step | yes | corrected in PR body |
| N5 | Plan's backlog row 1.18 not marked done in plan after retire | yes | informational — plan already shipped |
| N7 | `$this->outputBuffer[] = ...` literal in body did not match actual `appendOutput()` call | yes | fixed this pass (gh pr edit) |

**prevention_ladder:**

- **rung 0 — already covered?** No existing gate validates that PR body method names match the actual implementation.
- **rung 1 — extend an existing gate?** `bin/check-plan` could cross-reference body method names against the diff, but false-positive risk is high.
- **rung 2 — a rule doc?** Could add a prose reminder in the Phase 6.5 remediation procedure to diff body method/class names against `git diff HEAD~1` before posting.
- **rung 3 — PHPStan?** Not applicable to body prose.
- **rung 4 — CI gate?** No practical CI gate for prose accuracy.
- **landing rung:** no gate warranted — body-prose accuracy is a judgment review; Phase 6 Opus fidelity review is the correct catch surface and fired correctly here.

`prevention_ladder: no gate warranted — Phase 6 Opus review is the correct catch surface; B1+N7 fixed in-PR`

`artifact destination: n/a — no gate`

*(discovered 2026-09-02 during #1824 Phase 6 review)*

### E40 Phase 6 review notes on PR #1920 (scrub-log-credentials remote filter + stdout)

**class:** implementation defects in `bin/scrub-log-credentials` — unquoted outer heredoc collapses jq regex escapes in the remote path (F1), and per-file hit report output silently discarded in local mode (F4)

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/scrub-log-credentials:231–272` (F1: outer heredoc collapses `\\` → `\`, corrupting jq regex; remote path silently no-ops) | yes | yes | fixed this pass |
| 2 | `bin/scrub-log-credentials:156–158` (F4: `result_text` captures report + HITS line, but only HITS extracted; file:line report discarded) | yes | yes | fixed this pass |

**prevention_ladder:**

- **rung 0 — already covered?** No. `bin/test-scrub-log-credentials` only exercised `run_local()`; the remote path and its embedded jq filter had no test coverage.
- **rung 1 — extend existing gate?** Case 8 added to `bin/test-scrub-log-credentials` (shipped this PR) guards remote invocation pattern and filter content against drift. F4 output is implicitly covered by case 1 dry-run hit-count assertions.
- **rung 2 — a rule doc?** No rule doc warranted — too specific.
- **rung 3 — PHPStan?** Not applicable (bash).
- **rung 4 — CI gate?** `bin/test-scrub-log-credentials` is wired to CI; case 8 guards future regression.
- **landing rung:** rung 1 — case 8 harness shipped with the fix.

`prevention_ladder: rung 1 — case 8 in bin/test-scrub-log-credentials guards remote jq filter content and invocation pattern; F4 output fix covered by existing case 1 assertions`

`artifact destination: bin/test-scrub-log-credentials case 8 (shipped this PR)`

*(discovered 2026-09-02 during #1920)*

---

### E41 Phase 6 review notes on PR #1920 (SYNTHETIC_SECRET length + closure assertion)

**class:** verification-quality defects — SYNTHETIC_SECRET constant too long for `getTraceAsString()` 15-char truncation (F2) causes tests to pass vacuously; closure-frame assertion replaced with unconditional pass (F3)

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `ibl5/tests/Bootstrap/ErrorHandlerRegistrarTest.php:174` (F2: SYNTHETIC_SECRET 32 chars; `getTraceAsString()` truncates args to 15 chars — assertion checks for string never present in output) | yes | yes | fixed this pass |
| 2 | `bin/test-scrub-log-credentials:138–140` (F3: closure not-wholesale-redacted assertion replaced with `ok "..."` unconditional pass) | yes | yes | fixed this pass |

**prevention_ladder:**

- **rung 0 — already covered?** No. PHPUnit and the harness ran green, masking both defects.
- **rung 1 — extend existing gate?** F2 fix shortens SYNTHETIC_SECRET to 14 chars (fits verbatim in `getTraceAsString()` output) — tests now genuinely fail on a regression. F3 fix restores the real predicate.
- **rung 2 — a rule doc?** No rule doc warranted — the PHP 15-char truncation behavior is documented in the test comment.
- **rung 3 — PHPStan?** PHPStan cannot detect vacuous string-absence assertions of this form.
- **rung 4 — CI gate?** No gate warranted — both fixes are direct test-quality improvements requiring human judgment.
- **landing rung:** rung 1 — direct fixes applied this pass; no additional gate.

`prevention_ladder: rung 1 — SYNTHETIC_SECRET shortened to fit getTraceAsString() truncation; closure assertion restored as real predicate; no further gate warranted`

`artifact destination: n/a — no new gate`

*(discovered 2026-09-02 during #1920)*

---

### E42 Phase 6 review notes on PR #1968 — plan-accuracy divergences (N1–N4); all non-blocking; no gate warranted

**class: n/a** — Four plan-accuracy divergences surfaced by `/pr-ready` Phase 6 on PR #1968; all instances are non-blocking (implementation correct in every case); no gate warranted on any.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| N1 | `~/claude-plans/impl-model-accept-full-ids.md` Phase 8e literal spec vs. `.claude/rules/agent-tiering.md:22` — implementation compressed the six-literal enumeration to a pointer; the route is better than specified (avoids a second drift surface for the whitelist) | route deviation, non-blocking | no | not fixed — filed |
| N2 | `bin/automouse/run:1235` — `rm -f "$CAP_REFUND_FILE"` added beyond plan Phase 4 fence; safe by symmetry with sibling poison-pill block at `:1205` and exercised by `bin/test-automouse-single` case 6 | safe scope addition, non-blocking | no | not fixed — filed |
| N3 | `.claude/rules/agent-tiering.md` `last_verified` bump dropped by rebase; master advanced past the branch's bump; `bin/check-docs` green | doc-bump dropped by rebase, non-blocking | no | not fixed — filed |
| N4 | `~/claude-plans/impl-model-accept-full-ids.md` matrix row 15: `bin/automouse/queue list` is not a valid subcommand; correct command is bare `bin/automouse/queue`; property holds when re-run correctly | wrong command in plan verification matrix, non-blocking | no | not fixed — filed |

**prevention_ladder: no gate warranted** — all four divergences are plan-author responsibility; runtime gates cannot distinguish intentional route deviation from accidental staleness in plan text; N2 is a correct addition; N3 is self-healed by master; N4 is a plan-text typo discovered post-implementation with the property verified.

`artifact destination: n/a — no gate`

*(discovered 2026-09-04 during #1968)*

### E43 `bin/check-plan` PR6 gates: five new gates and two gate [V] extensions

**class: gate addition** — Adds five new gates and two extensions to `bin/check-plan` to prevent recurring plan-vs-implementation divergences that caused Phase 6 remediation commits:

- **[A] Migration-number literal** (hard-fail): plans must not name specific migration numbers; use `run bin/next-migration at impl time` escape phrase.
- **[L] Line-number anchor** (warn-only, location-cell scope): location cells referencing `line N` create fragile anchors that break when code shifts.
- **[E] Docker liveness precondition** (warn-only): plans that exercise Docker/`bin/db-query` should include a liveness check row.
- **[I] Shellcheck CI-class flags** (hard-fail): VM rows invoking `shellcheck` must use `--severity=warning --shell=bash` to match CI.
- **[J] PHP coverage advisory** (warn-only): plans that introduce a new `ibl5/classes/*.php` file should include an Infection/mutation/coverage row.
- **[V] ext `/path/to`**: CLI-executable rows with a `/path/to` placeholder are caught by the existing `_bad` chain.
- **[V] ext `main.localhost`**: any VM row referencing `main.localhost` is a hard-fail (worktree plans must derive the hostname at runtime).

**prevention_ladder: gates added** — PR #2082 (check-plan-pr6-gates branch).

`artifact destination: bin/check-plan, bin/test-check-plan`

`last_verified: 2026-09-04`

*(discovered 2026-09-04 from pattern analysis of Phase 6 remediation commits)*

### E44 Phase 6 review findings on PR #2082 — gate [V] bootstrap defect and five accuracy notes

**class: gate bootstrap defect** — a `bin/check-plan` gate implementation that matches the full matrix row string (including the prose "What to verify" cell) rather than just the location cell, with no escape phrase, causing a plan that documents or tests the gate to hard-fail the very gate it is proposing — a self-referential bootstrap failure. Five additional accuracy findings (body count, vacuous fixture, stale comment, nonexistent script reference, wrong baseline claim) also remediated in Phase 6.5.

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/check-plan:316` — gate [V] full-row main.localhost match, no escape | yes | yes | fixed this pass |
| 2 | PR body — "15 fixtures" contradicted by diff (19 actual) | near-miss | yes | fixed this pass |
| 3 | `bin/test-check-plan:2182` — gate-L-range-clean fixture used plural form, vacuous | near-miss | yes | fixed this pass |
| 4 | `bin/test-check-plan:2011` — corpus count "26" stale after trim to 20 | near-miss | yes | fixed this pass |
| 5 | `bin/check-plan:1203,1208` — nonexistent `bin/db-wait` (example) in [E] liveness regex and warn string | near-miss | yes | fixed this pass |
| 6 | plan VM row 7 — pre-flight claim "exits 0" wrong at time of writing | near-miss | yes | fixed this pass (plan file) |

**prevention_ladder:**
- rung 0: No existing gate covered the escape-phrase gap in gate [V].
- rung 1: Extended gate [V]'s main.localhost arm with an escape phrase `documents main.localhost gate` (same pattern as gate [A]'s `run bin/next-migration at impl time` escape). **Landing rung: 1** — escape phrase added in Phase 6.5 of this PR.
- rungs 2–5: Not warranted for this class; the fix is a one-line guard in the same gate implementation.

For the accuracy findings (2–6): prevention is the existing `/pr-ready` Phase 6 review pipeline, which caught all five. No new gate warranted — the mechanism works.

`artifact destination: bin/check-plan` (escape phrase guard), `bin/test-check-plan` (escape fixture + range fixture fix)

`last_verified: 2026-09-04`

*(discovered 2026-09-04 during Phase 6 review of #2082)*

### E45 Phase 6 review notes on PR #1924 (architect-explore-fanout) — stale Scope forward-reference and false test claim; PR-body-only fixes

**class:** PR body prose carries a forward-reference to phases already landed on master ("not yet in this diff — see MISSING-FILE note below") and a false Manual Testing claim ("all changes are covered by unit and E2E tests") for a docs-only `.claude/` change with no PHP code. Both are scope-accuracy defects in the PR body, not implementation defects.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | PR #1924 body Scope — "not yet in this diff — see MISSING-FILE note below"; Phases 2 (`sonnet-4-6.md`) and 6 (`agent-tiering.md`) had already landed on master at `beb9874` | yes | fixed this pass (gh pr edit) |
| 2 | PR #1924 body Manual Testing — "all changes are covered by unit and E2E tests"; no PHP code changed; no executable Verification Matrix rows | yes | fixed this pass (gh pr edit); replaced with CLI-executable verification claim and Phase 7.1 behavioral probe disposition |

**prevention_ladder:**

- **rung 0 — already covered?** Yes — `/pr-ready` Phase 6 check 1 (undeclared scope gap) and check 4 (PR body vs. diff accuracy) are the existing detection gate; both fired correctly and blocked the verdict until this pass.
- **rungs 1–5** — N/A given rung 0 coverage.

`prevention_ladder: no gate warranted — /pr-ready Phase 6 checks 1 and 4 are the correct catch surface and fired correctly`

`artifact destination: n/a — no gate`

*(discovered 2026-09-04 during #1924)*

### E46 PR body "What is NOT in this PR" written before all plan phases complete — ✅ Implemented (2026-09-04)

Archived: see [`archive/dev-efficiency-backlog-archive.md`](archive/dev-efficiency-backlog-archive.md) — rung 1 filed as `.claude/rules/pr-body-negative-claim-recheck.md`.

### E47 Phase 6 review notes on PR #2077 — five confirmatory observations

**class: n/a** — five reviewer observations confirmed as correct behaviors, not defects. No gate warranted; the Phase 6 pipeline surfaced them as expected for human confirmation.

| # | Observation | Verdict |
|---|-------------|---------|
| N1 | `Waivers/WaiversController.php` still has 6 superglobal reads — deliberately descoped per plan | correct — backlog 14.8 corrected to `◑ Partial` |
| N2 | Archive-doc skip: `DepthChartEntryApiHandler.php` touched but not archived — plan never scoped it | correct — out of scope |
| N3 | `BanRawSuperglobalsRule` suffix allowlist unchanged — plan Phase 6 §Correction forbids removing `Controller.php` | correct — intentional; `ControllerSuperglobalFreedomTest.php` is the scoped enforcement |
| N4 | `codebase-map.md` regenerated as a side effect of other work | correct — expected artifact |
| N5 | `Team/README.md` `last_verified: 2026-08-16` vs `maintenance-backlog.md` `2026-09-04` — cosmetic inconsistency | fixed this pass (bumped to 2026-09-04) |

**prevention_ladder: no gate warranted** — all five are reviewer-confirmed correct behaviors, not defects; the existing `/pr-ready` Phase 6 review pipeline is the mechanism and it worked correctly here.

`artifact destination: n/a — no gate`

`last_verified: 2026-09-04`

*(discovered 2026-09-04 during Phase 6 review of #2077)*

**Superseded by:** #2109 — N1 (WaiversController superglobal reads) and N3 (allowlist unchanged) are now stale: #2109 converts WaiversController and removes Controller.php from the `_REQUEST` allowlist, fully resolving 14.8.

### E48 Phase 6 review notes on PR #2081 — documentation gaps in bin/pr-cycle toolchain (missing seam declarations and overstated harness assertions)

**class:** undeclared env seams and overstated assertion prose in a bin/ script toolchain that causes plan-fidelity gaps and misleading harness failure messages

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/pr-cycle:27` (env seams header) — `PR_CYCLE_TS` and `PR_CYCLE_LOG` are live env seams missing from the `# Env seams (used by bin/test-pr-cycle):` block the plan required | yes | yes | fixed this pass |
| 2 | `bin/test-pr-cycle` (row 22 failure message) — prose and failure message say "execs with --worker" but predicate is `grep -qF -- '--worker'`, which never checks for exec | yes | yes | fixed this pass |
| 3 | `bin/pr-cycle:171` (guard (b) skipped for `--worker`) — better route than plan §1.6 literal; the literal would fail every --go run; no code action needed | yes | yes | not fixed — better route (no code action) |

**prevention_ladder:**

- **rung 0 — already covered?** No existing gate catches missing seam declarations or overstated harness failure messages.
- **rung 1 — extend existing gate?** Could extend `~/.claude/hooks/plan-gate-edit.sh`, but plan validation is not automated; too narrow.
- **rung 2 — a rule doc?** A `.claude/rules/` doc noting that plan §1.1 seam-header requirement should be verified at review time — but `/pr-ready` Phase 6 is already the catch mechanism; no new rule warranted.
- **rung 3–5 — not warranted** for a documentation-accuracy issue in shell scripts.
- **landing rung:** no gate warranted — plan-fidelity review (Phase 6) is already the catch mechanism; the class is too narrow and high-cost for a static gate.

`prevention_ladder: no gate warranted — plan-fidelity review (Phase 6) is already the catch mechanism; the class is too narrow and high-cost for a static gate`

`artifact destination: n/a — no gate`

*(discovered 2026-09-04 during #2081)*

### E49 Phase 6 review notes on PR #1957 — plan-authoring gaps (body claim vs. remediation commit, verbatim omission, auto_merge contradiction, auto-generated scope)

**class:** plan-authoring imprecisions requiring in-PR remediation — PR body claim contradicted by a remediation commit, a verbatim transcription omitting a side-effect call, a plan prose/frontmatter auto_merge contradiction, and a scope note about auto-generated file changes

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | PR #1957 body (Manual Testing section) — claimed "all changes are covered by unit tests" but remediation commit `0efe66858` added `EventLogger::setAction('trade_offer_rejected')` which is not unit-pinned | yes | yes | fixed this pass — body updated to name the exception |
| 2 | `~/claude-plans/authz-verdict-refactor-1b-trading-reject-service.md` (verbatim `reject()` body) — `EventLogger::setAction('trade_offer_rejected')` omitted from plan's transcription of the controller success path | yes | yes | not fixed — plan is a historical artifact; remediation commit `0efe66858` added the call; maintenance-backlog 15.32 filed |
| 3 | `~/claude-plans/authz-verdict-refactor-1b-trading-reject-service.md` (frontmatter vs. prose) — `auto_merge: false` in frontmatter contradicted by "armed (auto_merge: true)" in plan prose | yes | yes | not fixed — plan is a historical artifact; Phase 7 verdict documents correct behavior |
| 4 | `.claude/rules/codebase-map.md` — auto-generated file changed in diff but not declared in plan scope; Phase 6 reviewer noted as scope creep | yes | yes | no action needed — auto-generated, not plan-authored |
| 5 | `.claude/skills/pr-ready/scripts/wt-bring-up.sh:2` — header comment read "always exits 0" but preamble guards exit 1 (F3b) | yes | yes | fixed this pass |
| 6 | `bin/test-pr-ready-now` — no case exercising `review-owed.sh` fire condition (matrix row 19 unrealised, F5a) | yes | yes | not fixed — filed |
| 7 | `bin/test-pr-ready-now` (size-band comment cites 34047 B but post-rebase SKILL.md is 34552 B, F2c) | near-miss | yes | fixed this pass (appended re-measure note 2026-09-05; band [33047, 35047] still contains 34552 B) |
| 8 | PR body item 7 — "Five new cases" where diff has ten (F4a) | class: n/a | yes | fixed this pass (gh pr edit) |
| 9 | F2a (BRINGUP label), F2b (ten cases vs five), F3a (ADR-0115 added), F5b (row 9 contradicts review-owed.sh spec) | class: n/a | — | not fixed — no code action (implementation divergences that are correct or better than plan prose) |

**prevention_ladder:**
- **rung 0 — already covered?** No existing gate catches PR body contradictions against remediation commits or plan verbatim omissions.
- **rung 1 — extend existing gate?** Not feasible — plan files are freeform prose; no parser exists.
- **rung 2 — a rule doc?** A `.claude/rules/` doc reminding authors that the PR body Manual Testing claim must be reconciled against every remediation commit, and that verbatim transcriptions must be verified against the production source (see maintenance-backlog 15.32 artifact). Low ROI.
- **rungs 3–5 — not warranted** for plan-prose accuracy.
- **landing rung:** no gate warranted — `/pr-ready` Phase 6 is already the catch mechanism; three of four findings are plan-authoring imprecisions; the fourth is a reviewer calibration note, not a defect.

`prevention_ladder: no gate warranted — /pr-ready Phase 6 is the catch mechanism`

`artifact destination: n/a — no gate`

`last_verified: 2026-09-04`

*(discovered 2026-09-04 during Phase 6 review of #1957)*

### E50 PR #2092 Phase 6.5 — `--dry-run` counter increment in live-only branch, no harness case for dry-run count (Findings 1+5)

**class:** a counter increment placed inside the live-only branch of a `--dry-run` conditional in a bin/ script (`bin/prune-vr-galleries`), causing `--dry-run` to always emit `0` as its trailing stdout line regardless of how many directories were selected; and the absence of a harness case asserting the dry-run trailing count equals the number of `WOULD PRUNE:` lines emitted

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/prune-vr-galleries:78` — `removed=$(( removed + 1 ))` inside `else` (live-only) branch, not at the outer `if [ "$ts" -lt "$cutoff" ]` branch | yes | yes | fixed this pass — hoisted increment above the dry_run/live fork |
| 2 | `bin/test-prune-eligibility` — no case asserted the trailing integer in dry-run mode | yes | yes | fixed this pass — added `dry-run-count-matches-would-prune-count` case |

**prevention_ladder:**
- **rung 0 — already covered?** No existing gate checks that a `--dry-run` trailing count is correct.
- **rung 1 — extend existing gate?** The harness (`bin/test-prune-eligibility`) is the natural host — the new case added here is exactly this extension. Rung 1 satisfied by the fix itself.
- **rungs 2–5 — not warranted:** the harness now covers this semantically; a lint rule or hook would be over-specified for a one-off count placement.
- **landing rung:** rung 1 — harness extended; fix ships with its own regression coverage.

`prevention_ladder: rung 1 — harness extended with dry-run-count-matches-would-prune-count case`

`artifact destination: bin/test-prune-eligibility (in-repo, part of this PR)`

*(discovered 2026-09-05 during Phase 6 review of #2092)*

---

### E51 PR #2092 Phase 6.5 — notes Findings 2–4: find-regex deviation, out-of-plan changes, stale body claim

**class:** n/a — three non-blocking review notes that required no code gate

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | Finding 2 — `find -regex` replaced by bash length+charclass filter; declared in-code and already filed in `ibl5/docs/backlog/loop-engineering-backlog.md` as part of this PR's out-of-plan changes | n/a | n/a | not fixed — no gate warranted; declared and filed |
| 2 | Finding 3 — `.gitignore` and one `loop-engineering-backlog.md` row added without a plan phase; benign and scope-adjacent | n/a | n/a | not fixed — no gate warranted; benign out-of-plan changes |
| 3 | Finding 4 — PR body Summary claimed harness covers "stdout-contract semantics" while dry-run count was unasserted and broken | n/a | n/a | fixed this pass — body claim becomes true after E50 fix |

**prevention_ladder:** no gate warranted — Finding 2 is a declared deviation with an existing backlog entry; Finding 3 is a benign scope-adjacency judgment; Finding 4 is resolved as a side-effect of E50.

`prevention_ladder: no gate warranted — all three are either already-filed, benign, or fixed as a side-effect of E50`

`artifact destination: n/a — no gate`

*(discovered 2026-09-05 during Phase 6 review of #2092)*

---

### E52 PR #2064 Phase 6.5 — stale hand-written PR body claims contradicting the final diff and code

**class:** a stale hand-written PR body claim in the ship-pipeline surface (Scope prose or Manual Testing section) that contradicts the final implementation, code, or test counts — specifically: a guard described as hunk-scoped when the code is file-scoped (B1); case range stated as 48–63 when the diff adds 49–64 (B2); and a Manual Testing claim citing E2E tests when the change is bash tooling with no E2E component (B3); plus an omission of a changed file from the Scope prose (N1).

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | PR #2064 body — "provided the file also carries an advancing date bump in the same hunk" (contradicts code, plan, ADR-0104, and the body's own next sentence) | yes | yes | fixed this pass — corrected to "in the same file" |
| 2 | PR #2064 body — "gains cases 48–63" (case 48 exists on master; diff adds 49–64) | yes | yes | fixed this pass — corrected to "cases 49–64" with idempotency note |
| 3 | PR #2064 body — "all changes are covered by unit and E2E tests" (no E2E in diff; CI E2E jobs report skipping) | yes | yes | fixed this pass — corrected to name bash tooling and CI job names |
| 4 | PR #2064 body — bin/docfix-run not mentioned in Scope prose despite being a changed file | near-miss | yes | fixed this pass — added one-line mention |

**prevention_ladder:**
- **rung 0 — already covered?** No existing gate checks PR body prose for consistency with the diff after implementation.
- **rung 1 — extend existing gate?** No existing gate to extend; `/pr-ready` Phase 6 check 4 is the current mechanism — this entry describes a class it is designed to catch, and it did catch it.
- **rung 2 — a rule doc?** A rule noting that plan-authored case ranges and guard descriptions must be verified post-impl before the body is published would help, but is low-value: the failure mode is a hand-edit omission, not ignorance of the rule.
- **rungs 3–5 — not warranted:** a lint rule or hook cannot read implementation intent against prose claims.
- **landing rung:** no gate warranted — `/pr-ready` Phase 6 check 4 is the catch mechanism; the class is inherently judgment-gated.

`prevention_ladder: no gate warranted — Phase 6 check 4 is the existing catch; no mechanical gate can verify prose-vs-intent consistency`

`artifact destination: n/a — no gate`

*(discovered 2026-09-05 during Phase 6 review of #2064)*

---

### E53 PR #1897 Phase 6.5 — plan-text staleness and meta-text accuracy notes

**class:** `n/a` for all five notes — each is a cosmetic plan-accuracy or machine-generated-boilerplate observation with no live defect to fix; the implementation was correct in every case.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `~/claude-plans/maint-2-39-cash-year-range-bound.md` Phase 8 Edit 8e — old→new `last_verified` values stale (`2026-08-08 → 2026-08-09`; master already at `2026-09-05`); implementation satisfied without any diff line | yes | yes (stale plan text) | not fixed — no code change warranted; `bin/check-docs` confirmed correct |
| 2 | `ibl5/tests/Trading/TradeRosterPreviewParamValidatorTest.php` — `testValidateCashYearRangeRejectsAttackInput` passes `2031` as `$maxYear` (calendar-year vestige from earlier design; production ceiling is `6`) | yes | yes | not fixed — test still proves rejection (`999999 > 2031`); real ceiling pinned by three other tests; matches plan Verification Matrix row 1 verbatim |
| 3 | `~/claude-plans/maint-2-39-cash-year-range-bound.md` Verification Matrix row 10 — cites "`resolved (21)`" and "ten files"; master absorbed 2.40–2.41 between plan-write and merge; actual counts are `resolved (24)` and 12 files | yes | yes (stale plan text) | not fixed — implementation tracked master correctly; plan text is accepted as stale historical context |
| 4 | PR #1897 body `## Manual Testing` sentinel — "all changes are covered by unit and E2E tests"; no E2E test in diff | yes | yes (machine-generated) | not fixed — boilerplate from `/post-plan`; clearance correct (zero `Truly-manual` rows); a template concern, not a PR #1897 defect |
| 5 | `~/claude-plans/maint-2-39-cash-year-range-bound.md` Critical Files — "Ten files, one reviewable change"; diff carries 12 files (post-plan retrospective added `loop-engineering-backlog.md` and `.claude/review-shared/_plan-verification.md`) | yes | yes (stale plan text) | not fixed — plan-authoring responsibility; retrospective output is correct by construction |

**prevention_ladder:**
- **rung 0 — already covered?** `/pr-ready` Phase 6 checks 1, 2, 4, and 5 caught all five occurrences.
- **rung 1 — extend existing gate?** Phase 6 check 4's prose-vs-reality scan could flag test arguments that don't match the production constant; not worth the false-positive risk.
- **rungs 2–5 — not warranted:** plan-text staleness is inherent when master evolves between plan-write and merge; no mechanical gate can keep plan literal values current, and the test argument (row 2) still proves the contract correctly.
- **landing rung:** no gate warranted — Phase 6 is the existing catch mechanism; all five are cosmetic and self-resolving.

`prevention_ladder: no gate warranted — Phase 6 caught all; plan-text staleness is inherent; no mechanical gate can verify prose-vs-implementation consistency`

`artifact destination: n/a — no gate`

*(discovered 2026-09-05 during Phase 6 review of #1897)*

### E54 PR #2084 Phase 6.5 — rebase silently dropped implementation commit; lost-work proof blind to pre-run loss

**class:** `rebase-dropped-commit` — an `--onto` rebase replay range that started above the branch's own commits, compounded by a lost-work proof that only compares pre-to-post within a single `/pr-ready` run and cannot detect a branch that arrives already emptied by a previous run's bad rebase.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `.claude/skills/pr-ready/_rebase-and-conflicts.md` 2d — `--onto` replay range; 2026-09-05 05:48:41 −0700 rebase dropped `096320b0e` (13 files, +1252/−4) from PR #2084 by computing the merge-base against a parent SHA that had already moved | yes | fixed | fixed this pass — cherry-picked `096320b0e` onto HEAD; implementation restored |
| 2 | `.claude/skills/pr-ready/scripts/lostwork.sh` — lost-work proof compares pre-rebase snapshot to post-rebase HEAD within one run; passes trivially when the branch arrives already emptied from a prior run's bad rebase | yes | yes | not fixed — filed; rung-1 gate extension needed |

**prevention_ladder:**
- **rung 0 — already covered?** Partially: `_rebase-and-conflicts.md` 2d documents the `--onto` form but does not guard against a replay range that excludes the branch's own commits. `lostwork.sh` has no guard against a pre-run loss.
- **rung 1 — extend existing gate?** Yes — extend `lostwork.sh` to compare `origin/<branch>` as it stood before this run's push: if the pre-push origin branch has more files than the post-rebase HEAD diff, the branch arrived already emptied. Alternatively, assert file count against the plan's Files Changed table (already machine-generated in the PR body).
- **rungs 2–5 — not warranted:** the existing gate surface (`lostwork.sh`) already runs in the right place; extension is the cheaper fix.
- **landing rung:** rung 1 — extend `lostwork.sh` with a pre-push origin comparison.

`prevention_ladder: rung 1 — extend lostwork.sh to compare origin/<branch> before push`

`artifact destination: .claude/skills/pr-ready/scripts/lostwork.sh — add origin/<branch> pre-push comparison`

*(discovered 2026-09-05 during Phase 6 review of #2084; root cause: prior /pr-ready run's Phase 2 rebase dropped 096320b0e using a bad --onto range)*

### E62 PR #2064 Phase 6.5 — stale hand-written PR body claims contradicting the final diff and code

**class:** stale hand-written PR body claims contradicting the final diff and code — the PR body asserted behaviors, file lists, or test coverage that the final diff contradicted.

**occurrence table:**

| # | Finding | Status |
|---|---------|--------|
| B1 | stale PR body claim contradicting final diff — fixed this pass | fixed this pass |
| B2 | stale PR body claim contradicting final diff — fixed this pass | fixed this pass |
| B3 | stale PR body claim contradicting final diff — fixed this pass | fixed this pass |
| N1 | stale PR body note contradicting final diff — fixed this pass | fixed this pass |

**prevention_ladder:** no gate warranted this pass — all four findings fixed in-pass via `gh pr edit`; Phase 6 review is the existing catch mechanism.

`artifact destination: n/a — no gate`

*(discovered during Phase 6 review of #2064; all findings B1/B2/B3/N1 fixed in-pass)*

### E63 PR #1967 Phase 6.5 — ADR index duplicate frontmatter key and stale body path reference

**class (finding 1):** a duplicate YAML frontmatter mapping key (`last_verified:`) in a doc index that accumulates independent date bumps from concurrent auto-merged commits without deduplication, causing invalid YAML that strict parsers reject.

**class (finding 2):** a hand-written PR body path reference to an ADR that was renumbered after the body was written, leaving the manual-test row pointing at a nonexistent file.

**class (findings 3–6 / notes):** class: n/a — scope-prose "unchanged" wording (substance accurate; second clause discloses), unperformed pre-merge manual rows (by design), stale matrix acceptance-list items, cosmetic commit-subject and row-placement inconsistencies; none gatable.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `ibl5/docs/decisions/README.md:3-5` | yes (finding 1) | yes | fixed this pass |
| 2 | PR #1967 body Row 60 | yes (finding 2) | yes | fixed this pass (gh pr edit by caller) |
| 3 | matrix row 60 acceptance list (four named alternatives) | near-miss (finding 2) | yes | not fixed — filed (stale matrix row; ADR content correct) |
| 4 | matrix rows 30, 55 stale commands | near-miss (finding 2) | yes | not fixed — filed |

Scan for other files with duplicate `last_verified:` keys:

```bash
find ibl5/docs -name '*.md' -exec sh -c 'c=$(grep -c "^last_verified:" "$1" 2>/dev/null); [ "$c" -gt 1 ] && echo "$1: $c"' _ {} \;
```

Result: `ibl5/docs/decisions/README.md` is the only occurrence; fixed this pass.

**prevention ladder:**
- rung 0: `bin/check-docs` runs on every PR but accepts any number of `last_verified:` keys per file — not covered.
- rung 1: extend `bin/check-docs` to reject more than one `^last_verified:` line in a single file's frontmatter. This catches the defect at the point of introduction rather than at review time. All four `meta-tooling-bar.md` extend-before-add conditions hold: the extension is 1–2 lines of `grep -c` logic, it lives where the rest of the frontmatter checks already live, it has a direct measurable surface (frontmatter parsing), and no separate tool is needed.
- Landing rung: **rung 1** — extend `bin/check-docs`.
- For finding 2 (stale body path): no gate warranted — PR body references are hand-written and ephemeral; an ADR renumber is a rare event; the existing Phase 6 6d(4) check is the review-time backstop and already caught it.

**artifact destination:** `bin/check-docs` (in-repo script, path `bin/check-docs`).

**provenance:** (discovered 2026-09-05 during #1967)

### E64 PR #1967 Phase 6.5 — stale PR body acceptance list claiming wrong ADR alternatives

**class:** A manual-test acceptance list in a PR body that names specific ADR alternatives-considered items, but lists alternatives that are not present in the ADR's `## Alternatives Considered` section — the list was written against an early draft and never reconciled against the final ADR.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | PR #1967 body Row 60 — claimed four alternatives (GITHUB_TOKEN, lease-based force flag, committed pause file, push-triggered workflow) but ADR-0112 names six different alternatives | yes | fixed | fixed this pass — corrected via gh pr edit to name all six actual alternatives |

**prevention_ladder:**
- rung 0 — no existing gate checks manual-test acceptance lists against ADR content.
- rung 1 — Phase 6 review (6d check) already catches this; no mechanical gate can verify a PR body claim against an ADR's content cheaply.
- **landing rung:** no gate warranted — Phase 6 is the existing catch mechanism; the defect is authoring-time drift between a PR body draft and the final ADR.

`artifact destination: n/a — no gate`

*(discovered 2026-09-06 during Phase 6 review of #1967; fixed this pass via gh pr edit)*

### E65 PR #1967 Phase 6.5 — Phase 6.5 backlog entries inserted in wrong structural location

**class:** A Phase 6.5 remediation run inserted new backlog section entries between an existing entry's `**class:**` line and its `**occurrence table:**`, splitting that entry's content. The occurrence table and prevention_ladder of the split entry ended up rendered inside the newly-inserted entry's section. Additionally, the index table rows for the new entries were appended to a different section's observation table (which has a different column schema) instead of to the main index table at the top of the file.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `ibl5/docs/backlog/dev-efficiency-backlog.md` — E62 (PR #2064) and E63 (PR #1967) section headers inserted between E54/PR#2084's `**class:**` and its `**occurrence table:**`; E54's content orphaned inside E63's section | yes | was live | fixed this pass — structural repair in Phase 6.5 remediation commit |
| 2 | `ibl5/docs/backlog/dev-efficiency-backlog.md` lines 699-700 — E62/E63 index rows appended to E47's 3-column observation table instead of main index table | yes | was live | fixed this pass — moved to main index table |
| 3 | `ibl5/docs/backlog/dev-efficiency-backlog.md` end of file — E63's occurrence table and prevention_ladder orphaned with no section header | yes | was live | fixed this pass — reunited with E63's section header |

**prevention_ladder:**
- rung 0 — no existing gate validates backlog file structural integrity (column counts, section containment).
- rung 1 — a structural lint for backlog files (checking that `**occurrence table:**` follows `**class:**` within the same section before the next `###` heading) could catch this at commit time; high false-positive risk on varied entry formats.
- **landing rung:** no gate warranted this pass — three occurrences of the same root cause; surfacing as a known pattern for the future Phase 6.5 authoring prompt to note "insert at end of file, not between existing sections."

`artifact destination: n/a — no gate`

*(discovered 2026-09-06 during Phase 6 review of #1967; structural repair done in Phase 6.5 remediation)*

### E55 PR #2126 Phase 6.5 — machine-authored post-review commit undisclosed in PR body

**class:** A machine-authored remediation commit riding into an open PR post-review without the PR body's hand-written scope expansion paragraph and manual-testing coverage claim being updated — leaving a false positive "fully covered" assertion and an undisclosed extra file in the diff.
### E60 PR #2129 Phase 6.5 — PR body false E2E claim, omitted grep finding, vacuous VM selector

**class (consolidated — B1, N3, N4):**
- B1: a false test-class assertion in a PR body naming a testing class (E2E) absent from the diff — a verbatim recurrence of the defect the same PR archives.
- N3: an omitted empirical grep result in the PR body `## Why` section, where the plan explicitly directed the author to record which case (live vector / prophylactic) was found.
- N4: a vacuous pytest selector in a Verification Matrix row (`-k prready`) that can never collect any test.

All three fixed this pass: B1 and N3 via `gh pr edit`; N4 via the plan file VM.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | PR #2126 `## Scope` — undisclosed `bin/lighthouse-pr-urls` machine-authored CI fix | yes | was live | fixed this pass — Scope paragraph updated to name and explain the machine-authored commit |
| 2 | PR #2126 `## Manual Testing` — "all changes are covered" false; fallback branch in `bin/lighthouse-pr-urls` uncovered | yes | was live | fixed this pass — Manual Testing section narrowed to the five-module conversion |

**prevention_ladder:**
- rung 0 — `.claude/rules/pr-body-negative-claim-recheck.md` covers stale negative absence claims ("What is NOT in this PR") but does not explicitly extend to stale positive scope/coverage assertions.
- rung 1 — Extend `pr-body-negative-claim-recheck.md`: the same trigger (any commit pushed to an open PR) already fires; add a parallel obligation to re-read hand-written scope expansion prose and coverage claims — not only the negative list.
- rungs 2–5 — not warranted; the existing rule doc is the right surface and the cost is a prose edit only.
- **landing rung:** rung 1 — extend `.claude/rules/pr-body-negative-claim-recheck.md`.

`artifact destination: .claude/rules/pr-body-negative-claim-recheck.md`

*(discovered 2026-09-05 during Phase 6 review of #2126)*

### E56 PR #2126 Phase 6.5 — stale entry-point counts in plan-preserved maintenance-backlog prose

**class:** Plan-prescribed "preserve surrounding clauses" anchor instructions that treat count literals as invariants, when those counts were accurate at plan-write time but became stale relative to the PR's final diff scope (plan expanded from 4 to 9 entry points).

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `ibl5/docs/backlog/maintenance-backlog.md` — "wired into 5 module entry points" | yes | was live | fixed this pass — updated to 9 |
| 2 | `ibl5/docs/backlog/maintenance-backlog.md` — "FreeAgency, DepthChartEntry, Team and Player entry points (four)" | yes | was live | fixed this pass — updated to all nine entry points |

**prevention_ladder:**
- rung 0 — no existing gate checks count literals embedded in backlog preserve-and-anchor instructions against the final diff.
- rungs 1–5 — not warranted; adding a gate for prose count literals in backlog anchor instructions is not cost-justified for a one-off occurrence; the fix belongs in plan-authoring discipline.
- **landing rung:** no gate — plan-authoring care; when writing preserve-and-anchor instructions for backlog edits, explicitly flag any count literal that may change if the plan's scope expands.

`prevention_ladder: no gate warranted — flag stale count literals in plan preserve-and-anchor instructions at plan-write time`

`artifact destination: n/a — no gate`

*(discovered 2026-09-05 during Phase 6 review of #2126)*

### E57 `bin/adr-check` red at merge on #2124 and #2119 — new rule docs landed with no ADR and no `no-adr:` marker

**class:** A decision-trigger-surface PR merged with the "Meta checks" `bin/adr-check` step red, because that check is **advisory** — `master`'s `required_status_checks.contexts` is `["Tests and Analysis", "E2E Tests", "human-signoff"]` only. GitHub permitted every merge; no protection was bypassed and no admin override was used. The residue is an audit-trail gap: `ibl5/docs/decisions/README.md` § "When an ADR is Required" item 2 fires on a new `.claude/rules/*.md` doc, and the policy's own remedy (`<!-- no-adr: reason -->` in the PR body) was never typed, so the record shows a triggered requirement with no disposition.

A second, sharper mechanism showed up inside #2119: its earlier commit `472fe0a4` placed the rule doc at `ibl5/.claude/rules/bin-help-span-and-secondary-assertions.md` (example) — an `ibl5/`-prefixed path that does not exist. The `adr` `dorny/paths-filter` glob is `.claude/rules/*.md` (no prefix), so the filter did not match, the `Run bin/adr-check` step was **skipped**, and the job reported green. Commit `c572dd6b` corrected the path, the filter then matched, and the step ran and genuinely failed ~2.5 minutes before merge. **A path-prefix typo converts a required-by-policy gate into a free green with no signal that anything was skipped.**
### E58 PR #2133 Phase 6.5 — plan verification rows 4.e and 6.a false-positive on correct code; PR authoring notes

**class:** Plan verification shell commands that extract a function body with `sed` then grep the full text—including comment lines—for forbidden identifiers, producing false positives when the function's own comments document the property being verified.
| 1 | PR #2129 body `## Manual Testing` — "all changes are covered by unit and E2E tests"; diff has zero E2E tests | yes (B1) | yes | fixed this pass — corrected to sentinel template with CLI-executable outcome sentence |
| 2 | PR #2129 body `## Why` — grep case (live vector vs. prophylactic) not stated | yes (N3) | yes | fixed this pass — added: 265 plan files carry `## Automouse Hold Justification`; 703 heading/checkbox lines confirm the injection vector is live |
| 3 | `~/claude-plans/postplan-hold-justification-diff-bounds.md` VM row 12 — `-k prready` deselects all 17 tests | yes (N4) | yes | fixed this pass — corrected to `-k pr_ready` in plan file |

**prevention_ladder:**
- rung 0 — already covered? B1: yes — `.claude/rules/pr-body-test-claim.md` + Phase 6 check 4. N3/N4: no prior mechanism.
- rung 1 — extend existing gate? N4 selector could be verified by a VM harness row asserting `pytest -k <selector>` collects at least one test; niche.
- rungs 2–5 — not warranted for any of the three.
- landing rung: no gate warranted — Phase 6 check 4 and `pr-body-test-claim.md` catch B1; N3/N4 are authoring notes caught by Phase 6 checks 2 and 5.

`prevention_ladder: no gate warranted — Phase 6 check 4 + pr-body-test-claim.md catch B1; N3/N4 caught by Phase 6 checks 2 and 5`

`artifact destination: n/a — no gate`

*(discovered 2026-09-06 during Phase 6 review of #2129)*

### E61 PR #2129 Phase 6.5 — SKILL.md size-band gate not updated after deliberate file growth

**class:** a test-maintenance omission — a size-band gate (`bin/test-pr-ready-now` case 25) not updated when the guarded file grew by deliberate plan work in the same PR.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | PR #2124 — added `.claude/rules/scope-expansion-justification.md`; body has zero `no-adr` markers | yes | merged red 2026-09-05T16:43:10Z | disposition recorded below; no ADR owed |
| 2 | PR #2119 — added `.claude/rules/bin-help-span-and-secondary-assertions.md`; body has zero `no-adr` markers | yes | merged red 2026-09-05T13:03:01Z | disposition recorded below; no ADR owed |
| 3 | PR #2121 — added a rule doc, `adr-check` green | no (control) | n/a | body carries `<!-- no-adr: new always-loaded rule doc, authoring discipline only -->` — the correct precedent for this file class |
| 4 | `472fe0a4` misplaced path ⇒ `adr` filter miss ⇒ step skipped ⇒ green | yes (skip-as-green) | mechanism still live | not fixed — a skipped `if:`-gated step is indistinguishable from a passing one in `statusCheckRollup` |

**retroactive disposition (the marker that should have been in each body):** both files are path-conditional / always-loaded **authoring-discipline** rule docs — they govern how a PR body or a `bin/` help comment is written. Neither records an architectural decision, so neither owes an ADR; `ibl5/docs/decisions/README.md`'s bypass clause ("changes that genuinely don't need an ADR") is the correct disposition, exactly as applied in #2121. Writing an ADR for either would violate `.claude/rules/doc-freshness.md` § "Decision Records Are Append-Only" by asserting a decision nobody took.

**prevention_ladder:**
- rung 0 — `bin/adr-check` already exists and already produced the correct FAIL on both PRs. The gate is not broken; it is **non-blocking** (advisory context) and **`pull_request`-event-only**, so nothing re-flags the debt after merge.
- rung 1 — no rule-doc fix available: a rule doc cannot make an advisory check blocking, and the authoring discipline it would encode ("type the marker") is already stated verbatim in `ibl5/docs/decisions/README.md`.
- rung 2 — promote `Meta checks` to a required context on `master`. **Deliberately not taken here.** That is a branch-protection change on the ship-pipeline surface, which trips `.claude/rules/work-triage.md`'s safety mirror and wants a `/plan`, not an ad-hoc edit riding a backlog entry.
- rung 3 — for occurrence 4, make the `adr` filter's skip observable (e.g. an explicit `else`-branch step that prints "adr-check skipped: no decision-trigger paths in diff"), so a free green from a path typo is legible in the log. Cheap and additive; also wants its own change, not this one.
- **landing rung:** rung 0 for the two merged PRs — record the disposition here, **and** carry it in each merged PR body as the `no-adr:` marker the policy names. Both bodies were edited on 2026-09-05 (user-authorized; a merged PR body is still editable) and each marker is stamped "retroactively recorded" so it cannot be misread as having been present at merge time. Rungs 2 and 3 are surfaced, not built.

`artifact destination: this entry, plus retroactive <!-- no-adr: --> markers applied to the bodies of #2124 and #2119`

*(discovered 2026-09-05 while investigating recently merged PRs that carried red CI)*
| 1 | `~/claude-plans/pr-cycle-dirty-rescue.md` row 4.e — greps `_rescue_one()` body for `MERGES\|armed.txt\|ledgered.txt\|pr merge\|_poll_merge\|_arm_and_classify\|POLL_CEILING`; hits five comment lines that document the "rescue is not a merge" invariant | yes | was live at plan-write | not fixed — filed (plan already merged; fix is authoring discipline) |
| 2 | `~/claude-plans/pr-cycle-dirty-rescue.md` row 6.a — greps `_report_dry_run()` body for `_rescue_one\|_ready_pr\|--go`; hits three comment lines and one operator-facing echo string | yes | was live at plan-write | not fixed — filed |

**prevention_ladder:**
- rung 0 — no existing gate; plan files live at `~/claude-plans/` (outside the repo; CI cannot reach them).
- rungs 1–5 — not warranted; fix is plan-authoring discipline only.
- **landing rung:** no gate warranted — when writing function-body grep verification commands, exclude comment lines first: pipe through `grep -v '^[[:space:]]*#'` before the forbidden-token search.

`prevention_ladder: no gate warranted — plan-authoring discipline: exclude comment lines before grepping function bodies for forbidden tokens`

`artifact destination: n/a — no gate`

*(discovered 2026-09-05 during Phase 6 review of #2133)*

**Additional n/a notes from the same Phase 6 review (consolidated):**

- **Check 4 Note (a) — "unit and E2E tests" label:** `class: n/a` — fixed in-PR; Manual Testing updated to "the `bin/test-pr-cycle` shell harness". prevention_ladder: no gate warranted — one-off cosmetic label imprecision; plan's Required Test Methods block already names the correct phrasing.
- **Check 2 deviation — `${arr[@]+"${arr[@]}"}` form:** `class: n/a` — deliberate correctness fix over the plan's literal text; required for `set -uo pipefail` + bash 3.2 unbound-variable guard; reviewer confirmed it is the better route. prevention_ladder: no gate warranted.
- **Check 4 Note (b) — `feat:` vs `chore:` title typing:** `class: n/a` — maps to no 6d clause per reviewer; `feat:` is consistent with plan intent (`auto_merge: false` + human-signoff hold before unattended merge). prevention_ladder: rung 2 — `commit-conventions.md` already documents the GM-test rubric; no additional gate.
- **Check 4 Note (c) — commit subject under-describes:** `class: n/a` — ephemeral; this repo squash-merges and master takes the PR title, not the branch commit subject. prevention_ladder: no gate warranted.
| 1 | `bin/test-pr-ready-now:1880` — band [34616, 36616]; SKILL.md grew to 37174 B after diff-bounds block added by E20; gate fired correctly, band not updated | yes | yes | fixed this pass — band re-centred to [36174, 38174] |

**prevention_ladder:**
- rung 0 — already covered? Yes — the band gate itself caught the violation in CI.
- rung 1 — extend existing gate? The gate could read expected size from a comment in SKILL.md; adds coupling.
- rungs 2–5 — not warranted.
- landing rung: no gate warranted — the existing gate caught it; authoring discipline (update the band when SKILL.md grows intentionally) is the prevention.

`prevention_ladder: no gate warranted — existing band gate caught it; rung-0 is the prevention mechanism`

`artifact destination: n/a — no gate`

*(discovered 2026-09-06 during Phase 6 remediation of #2129)*

### E59 PR #2140 Phase 6.5 — implementation omitted plan-declared test methods; method renaming breaks name-keyed conformance checks

**class:** An automouse implementation run delivers tests that satisfy intent but omit plan-declared method names: some methods are renamed (N1), some are missing entirely (F1), and one assertion arm is dropped (N2). A name-keyed conformance check (e.g. grepping the diff for the exact names in `## Required Test Methods`) would read all three as failures.

**occurrence table:**

| # | Location | Same class? | Live at review? | Status |
|---|-----------|-------------|-----------------|--------|
| 1 (F1) | PR #2140 — `testCanAddContractHardCapVerdictFlipsBetweenPhases`, `testInjectedSeasonPreventsInternalSeasonConstruction`, `testTotalCurrentSeasonSalariesIsZeroForEmptyRosterInBothPhases`, `testPlayerFacadeCurrentSeasonSalaryUsesRawContractYear` absent from diff | yes | 2026-09-06 Phase 6 review | fixed — added in Phase 6.5 remediation |
| 2 (N1) | PR #2140 — six delivered methods renamed from plan's exact names (e.g. `testGetCurrentSeasonSalaryUsesNextYearWhenPhaseAdvancesContractYears` → `testGetCurrentSeasonSalaryAdvancesWhenAdvancesContractYearsIsTrue`) | yes | 2026-09-06 Phase 6 review | not fixed — assertions equivalent; renaming is authoring discipline |
| 3 (N2) | PR #2140 — `testGetNextSeasonSalaryIsAlwaysOneYearAheadOfCurrent` asserts only the `advances=true` arm; plan also wanted the `advances=false` arm | yes | 2026-09-06 Phase 6 review | not fixed — filed; narrow gap, pre-existing `testGetNextSeasonSalary` covers the phase-blind case |

**prevention_ladder:**
- rung 0 — no existing gate verifies plan-declared test method names against the diff.
- rung 1 — a rule doc encoding "name tests exactly as the plan specifies" is authoring discipline; no enforcement mechanism runs against `~/claude-plans/` (outside the repo).
- rung 2 — a post-implementation grep of the diff against the plan's `## Required Test Methods` block could flag missing names; would require a new `bin/` script and a CI step, each with maintenance cost.
- **landing rung:** no gate warranted this pass — three occurrences of the same class in one PR, all authoring-discipline failures. Surface here as a candidate for a future automouse-fidelity probe (e.g. a `/post-plan`-phase grep against Required Test Methods); defer gate authoring to that planning context.

`artifact destination: this entry`

*(discovered 2026-09-06 during Phase 6 review of #2140)*
