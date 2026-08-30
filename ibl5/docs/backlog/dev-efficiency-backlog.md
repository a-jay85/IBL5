---
description: Development-efficiency backlog — inner-loop speed (diff-scoped analysis, parallel tests), CI caching, dependency-bump batching, and worktree lifecycle automation, with per-entry status.
last_verified: 2026-08-30
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
| E16 | `bin/watch-run` declares a run finished on its first poll, before launchd registers the label | ⬜ Open | 🟩 | S |
| E17 | Skill prose carries fixed-count words (`either`, `the two`) that go stale when the enumerated set grows | ✅ Implemented | — | S |
| E18 | `/pr-ready` Phase 6.5 commits new files but never regenerates the PR body `files-changed` block | ✅ Implemented | — | S |
| E19 | `/pr-ready` materialize-from-pin sites declare no fallback, so a pin that predates the script loops forever | ✅ Implemented | — | S |
| E20 | Ship-pipeline coverage assertions (PR-body Manual Testing block, Phase 4B review) are emitted against a narrower slice than the PR's cumulative diff | ⬜ Open | 🟦 | M |
| E21 | Test assertions land as static source greps that pass while the behavior they name is absent | ⬜ Open | 🟩 | S |
| E22 | Plan-declared negatives and PR body Scope dropped during Phase 6.5 remediation | ✅ Implemented | — | S |
| E23 | PR body deletion volumes diverged from code's `EXPECTED` constants | ✅ Implemented | — | S |
| E24 | `/post-plan` Phase 4B can hand-write its review comment, bypassing `post_review_summary` | ⬜ Open | 🟨 | S |
| E25 | `/pr-review` migration-exclusion `awk` filter is a no-op, silently ingests full migration diffs | ⬜ Open | 🟨 | S |

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

➜ E13 `bin/wt-new --base <branch>` fast-forwards the wrong branch — ✅ Implemented (2026-08-30): `bin/wt-new` now detects whether `$BASE_BRANCH` is checked out in the main checkout; if not, uses `fetch origin "$BASE_BRANCH:$BASE_BRANCH"` to fast-forward the local ref directly. `bin/test-wt-new-root` extended with Case 3 regression pin. Shipped in PR `wt-new-base-branch-sync`.

➜ E14 `/pr-ready` Monitor exemption in invariants is stale — ✅ Implemented (2026-08-25): see [archive](archive/dev-efficiency-backlog-archive.md).

➜ E15 `/pr-ready` Phase 2 delegation packet tells rebase delegate to push — ✅ Implemented (2026-08-25): see [archive](archive/dev-efficiency-backlog-archive.md).

### E16 `bin/watch-run` declares a run finished on its first poll, before launchd registers the label
**Location:** `bin/watch-run` — the `label_alive()` check at the top of the poll loop, which runs before any startup grace period.
**Problem:** `bin/post-plan-now` prints its watch command and returns before launchd has finished registering the job, so a watcher armed immediately after sees `launchctl list` without the label and reports "launchd label gone but the log has no RESULT line" within seconds of a run starting. The `sleep 3` retry that follows only re-checks the log for a `RESULT:` line — and the harness flushes its log at exit by design, so the log is empty for the whole run. Observed 2026-08-24: a `bin/post-plan-now` run on `plan-gate-skill-inline` was reported finished after ~14s; the job was in fact alive (PID 26804) and ran another ~2.5 minutes to a successful `terminal=shipped-held`. This is the readiness-predicate failure mode described in `.claude/rules/work-triage-detail.md` § The readiness predicate — a confident verdict on an unstarted run, indistinguishable from a real completion.
**Suggested direction:** Do not treat label-absence as terminal until the label has been seen alive at least once, or until a bounded startup grace (a few poll intervals) has elapsed. Alternatively require label-absence on two consecutive polls before exiting, so a transient `launchctl` gap cannot terminate the watch.
**Risk if untouched:** Every caller that arms the watch command `bin/post-plan-now` prints — the documented usage — can get a false "finished" on a run that just started, and act on a verdict that does not exist yet.
**Status (2026-08-24):** ⬜ Open — 🟩 (no design fork; add a seen-alive latch or a startup grace to one loop).


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

### E20 Ship-pipeline coverage assertions are emitted against a narrower slice than the PR's cumulative diff

`class:` a ship-pipeline step that emits a **coverage assertion** — a PR-body `## Manual Testing` claim, a Phase 4B structured-review verdict — scoped to something narrower than the PR's cumulative diff, so the assertion reads as endorsing lines it never examined.

Surfaced by `/pr-ready` running on PR #1964 (a docs-only J7 RE correction). Two independent instances of the same shape landed on one PR:

1. The body's `## Manual Testing` block reads *"No manual testing needed — all changes are covered by automated tests."* The diff is one markdown backlog file; **no** automated test covers it, and the governing plan's own § *Automouse Hold Justification* names three properties that need subjective human confirmation and states "Human eyes required before merge." The boilerplate asserts a coverage source that does not exist for this diff class.
2. The Phase 4B review (comment `5384312129`, 2026-08-23T04:58:43Z, head `205e851f5`) posted *"No issues found… a one-line date stamp added to the J29 backlog entry."* That description is the **incremental commit** `c0effc44a..205e851f5` — 1 changed line. The PR's cumulative diff at that same head is **12 changed lines**; the other 11 (the J29 table rewrite, the J7 `[CORRECTED]` append, the `RETIRED-OK` marker, the Next-RE rewrite, the `last_verified` bump) were never structurally reviewed. The verdict is worded as if it covered the PR.

Both are silent: the assertion is well-formed, the gate is green, and nothing in the artifact records which slice it actually read. A reader of either one over-trusts it by exactly the amount of diff it skipped.

**Occurrences**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | PR #1964 body, `## Manual Testing` block | yes — asserts automated coverage that does not exist for a docs-only diff | yes | fixed this pass (body rewritten to the plan's three human-confirmation properties) |
| 2 | PR #1964 comment `5384312129` (Phase 4B verdict) | yes — verdict scoped to the last commit, worded as covering the PR | yes | not fixed — filed; `/pr-review 1964` recommended before merge |
| 3 | `.claude/skills/post-plan/SKILL.md` — the body generator that emits the `## Manual Testing` default | yes — the producer of occurrence 1; picks the boilerplate without consulting the plan's hold justification | yes | not fixed — filed (the gate belongs here, not on #1964) |
| 4 | `.claude/skills/pr-ready/_plan-fidelity-review.md:6c(a)` | no — already *requires* bounding 4B's coverage; it is the check that caught occurrence 2 | yes | not fixed — correct as written |

`prevention_ladder:`

- **rung 0 — already covered by an existing gate?** Partially, and only downstream: `/pr-ready` 6c(a) bounds 4B coverage and 6d.4 catches a body claim the diff contradicts. Neither fires until a human invokes `/pr-ready`, which is optional — both instances here sat green for three days first.
- **rung 1 — extend an existing gate? LANDS HERE.** Two one-surface extensions, both on producers that already own the artifact: (a) `/post-plan`'s body generator picks `## Manual Testing` boilerplate from the plan it already reads — when that plan carries an `Automouse Hold Justification` naming human-confirmation properties, emit those as unchecked boxes instead of the "no manual testing needed" default; (b) the Phase 4B runner already computes the diff it reviews — have it print the base ref and changed-line count in its verdict block, so scope is legible without recomputation. Neither adds a mechanism.
- **rung 2 — a rule doc under `.claude/rules/`?** Insufficient. Both artifacts are emitted by automated skills mid-run; a documented norm has no reader at the moment it is violated (same reasoning as [E18](#e18-pr-ready-phase-65-commits-new-files-but-never-regenerates-the-pr-bodys-files-changed-block) and [E19](#e19-pr-ready-materialize-from-pin-sites-declare-no-fallback-so-a-pin-that-predates-the-script-loops-forever)).
- **rung 3 — a PHPStan rule?** N/A — no PHP on this surface.
- **rung 4 — a CI gate?** Rejected for (a): a gate would have to decide whether a diff "needs" manual testing, which is the subjective judgment the plan's hold justification already records — cheaper to read it than to re-derive it. Worth reconsidering for (b) only if rung 1 proves insufficient.
- **rung 5 — a new hook?** Rejected. Neither failure reaches a tool call the hook layer can intercept.

Landing rung is **1**, so rungs 3–5 are never reached and the four `.claude/rules/meta-tooling-bar.md` extend-before-add conditions do not apply.

`artifact destination:` `.claude/skills/post-plan/SKILL.md` (the `## Manual Testing` body-generation step) and the Phase 4B review runner's verdict block. Both in-repo.

**Related.** [E18](#e18-pr-ready-phase-65-commits-new-files-but-never-regenerates-the-pr-bodys-files-changed-block) also concerns a PR body that stops matching its diff, but there the artifact is *machine-generated and stale*; here it is *hand-shaped and over-broad from the start* — the body was never right, rather than having drifted.

*(discovered 2026-08-26 during #1964)*

### E21 Test assertions land as static source greps that pass while the behavior they name is absent

`class:` a verification phase specifies a **behavioral** assertion (run the thing, inspect what it emitted) and the implementation lands a **static source grep** instead. The grep matches text that can be present while the behavior is absent — comment prose, or a key line that could never structurally carry the token being banned — so the assertion is green from birth and stays green through the exact regression it was written to catch. It is worse than no test: the coverage matrix records the row as backed.

Surfaced by `/pr-ready` running on PR #2000 — the `/pr-ready` Sonnet-orchestrator PR, dogfooding on itself. The plan (`~/claude-plans/pr-ready-sonnet-orchestrator.md` Phase 8) wrote case 21f as a two-arm dry-run over the runner file `bin/pr-ready-now` emits; the implementation substituted two `grep` calls against `bin/pr-ready-now`'s own source. One of those two matched **comment prose only** — `--model claude-opus-5` appears at `bin/pr-ready-now:14` and `:112` as documentation — so the PR's named rollback lever, the single thing that makes the Opus→Sonnet orchestrator switch reversible, could be deleted outright with the harness still passing. Confirmed by mutation on 2026-08-26: gutting the `--model` parse arm at `bin/pr-ready-now:151` left the old assertion green and makes the replacement fail with `21f: --model claude-opus-5 did not override the default — the revert lever is broken`.

Two lesser instances of the same class shipped alongside it, both vacuous by construction rather than by accident of matching prose.

**Occurrences**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/test-pr-ready-now` case 21f — `grep -q '--model claude-opus-5' bin/pr-ready-now` | yes — matches comment prose; the revert lever had no test | yes | fixed this pass (replaced with a two-arm `PR_READY_NOW_DRY_RUN=1` run asserting `MODEL=` in the emitted runner; mutation-verified) |
| 2 | `bin/test-pr-ready-now` case 21c — `grep -F 'subagent_type: "pr-ready-phase6"' \| grep -qE 'model:'` | yes — vacuous by construction: the only matching line *is* the `subagent_type:` key, which can never also be a `model:` key | yes | fixed this pass (assertion now spans the whole `Agent( ... )` block via `awk`, and fails when the block is missing) |
| 3 | `bin/test-pr-ready-now` case 21d — grep scoped to the single `test -s /tmp/pr-ready-phase6-verdict` line | yes — the fail-closed read spans several lines; the continuation arms were unasserted | yes | fixed this pass (widened to every line naming the verdict path, plus a `>= 3` line floor) |
| 4 | `bin/test-pr-ready-now` case 21 name — landed as `sonnet-orchestrator-static`, plan declared `phase6-opus-delegate-static` | no — naming drift, not vacuity; but it breaks the plan's Phase 8 acceptance grep on harness output | yes | fixed this pass (renamed; stray `begin_case` argument dropped — `begin_case()` ignores it) |
| 5 | PR #2000 body `## Scope` — omits the `bin/pr-ready-now` default + `--model` lever, the additive `.claude/agents/**` paths-filter widening, and the new harness case; asserts the tier guarantee unconditionally | no — a body claim narrower than the diff; this is [E20](#e20-ship-pipeline-coverage-assertions-are-emitted-against-a-narrower-slice-than-the-prs-cumulative-diff)'s class, on a new PR | yes | fixed this pass (Scope rewritten; the `files-changed` block left untouched) |
| 6 | PR #2000 commit subject `feat: … (phases 1-4)` — stale and wrong type | no — cosmetic only; squash-merge lands the PR **title**, which is already `chore: …` with no phase suffix | no | not fixed — correct as-is; amending would cost a second force-push for text `master` never sees |
| 7 | `bin/test-pr-ready-now` case 36 `stop-fails-closed` | no — a pre-existing flake, unrelated to this PR (it failed once then passed twice on an unchanged tree, and runs 500 lines above the edits) | yes | not fixed — out of scope for #2000; belongs to [E4](#e4-flake-quarantine-ledger) (flake-quarantine ledger) |

`prevention_ladder:`

- **rung 0 — already covered by an existing gate?** Only by a human-invoked one. `/pr-ready` Phase 6 (6d.2 plan-fidelity) is what caught all three, and it is optional — nothing fires at implementation time, when the substitution is made, or at PR time. The harness itself cannot detect it: a vacuous assertion is a *passing* assertion.
- **rung 1 — extend an existing gate? LANDS HERE.** One doc-surface extension on a producer that already owns the artifact: `.claude/skills/plan/_architect-contract.md`'s verification-phase contract already requires each phase to state its acceptance check. Extend it so that when a phase asserts a **named behavior or lever**, the phase must also state **the mutation that assertion is required to catch** ("delete the `--model` arm ⇒ 21f fails"). That single line makes the substitution visible at three separate moments — the implementer cannot swap in a text grep without leaving the stated mutation unmet, the Phase 6 reviewer gets a stated predicate instead of having to re-derive intent, and anyone editing the case later has the teeth written down. No new mechanism, no new script.
- **rung 2 — a rule doc under `.claude/rules/`?** Insufficient alone, and it would sit in the wrong place: the norm has to be read at the moment a verification phase is *authored*, which is inside the architect contract, not in an always-loaded rule. It would also add resident-context weight for a norm that fires on one narrow authoring surface.
- **rung 3 — a PHPStan rule?** N/A — no PHP on this surface.
- **rung 4 — a CI gate?** Rejected. Deciding whether a given `grep` is a legitimate static assertion or a stand-in for a behavioral one is the judgment the plan already records; a gate would have to re-derive it and would false-positive on the many static greps that are correct (case 21a, 21b, 21e and the retained 21f default check are all properly static — they assert *source text* because source text is the property).
- **rung 5 — a new hook?** Rejected. The substitution happens inside a file edit that the hook layer cannot distinguish from any other test edit.

Landing rung is **1**, so rungs 3–5 are never reached and the four `.claude/rules/meta-tooling-bar.md` extend-before-add conditions do not apply.

`artifact destination:` `.claude/skills/plan/_architect-contract.md`, the verification-phase contract section. In-repo. Not built this pass — the occurrences above are fixed, the prevention is the open work this entry tracks.

**Related.** [E20](#e20-ship-pipeline-coverage-assertions-are-emitted-against-a-narrower-slice-than-the-prs-cumulative-diff) is the sibling: there an artifact *claims* coverage it does not have, here a test *records* coverage it does not have. Both are silent, well-formed, and green.

*(discovered 2026-08-26 during #2000)*

---

➜ E22 Plan-declared negatives and PR body Scope dropped during Phase 6.5 remediation — ✅ Implemented (2026-08-27): see [archive](archive/dev-efficiency-backlog-archive.md).

➜ E23 PR body deletion volumes diverged from code's `EXPECTED` constants — ✅ Implemented (2026-08-27): see [archive](archive/dev-efficiency-backlog-archive.md).

### E24 `/post-plan` Phase 4B can hand-write its review comment, bypassing `post_review_summary` and making the review undetectable

Phase 4B's posting step is prose-only: it *tells* the run to call `post_review_findings` / `post_review_summary` but nothing enforces it. A run that instead composes the comment freehand still performs a real review — the findings are genuine — but the artifact lands outside the helper's envelope, with three downstream consequences: (i) the `/pr-ready` `PHASE_4B_RAN` probe (`4b-probe.sh`, regex `^#{1,6} +Code review`) misses it and recommends a redundant `/pr-review`; (ii) findings are prose-summarized instead of posted as inline threads carrying `<!-- score: N -->`, so `list_open_review_findings` / `resolve_review_finding` cannot disposition them and `unresolved-findings-hold` is blind to them; (iii) the helper's `<details>` envelope and footer are absent. The failure is silent in both directions — the run reports success, and the probe reports "never ran."

The fix belongs at the **emitter**, not the probe. Loosening the probe regex to match freehand prose makes a false `true` more likely, and `.claude/skills/pr-ready/SKILL.md` explicitly names a false `true` as the worse failure mode. The defect is that Phase 4B is permitted to write the comment by hand at all.

**Occurrences**

| # | File:line | Class | Live? | Status |
|---|-----------|-------|-------|--------|
| 1 | `.claude/skills/post-plan/_phase-4-review-audit.md:101-102` — mandates `post_review_findings` / `post_review_summary` in prose; unenforced | A | yes | not fixed this pass |
| 2 | PR #2001 `## Code Review Summary` + `## Security Audit Summary` comments — real Phase 4B output, written freehand (no `<details>`, no `### Code review`, no `PRF_FOOTER`), invisible to the 4B probe; its one finding (`FILL_TEAM_BACKUP` docblock, scored 25) never became a dispositionable thread | A | yes | not fixed this pass |

`prevention_ladder:`

- **rung 0 — already covered?** No. `bin/lib/post-review-findings.sh` is the sanctioned path but is not the *only* reachable path; no gate checks that the comment Phase 4B produced came through it.
- **rung 1 — extend an existing gate? LANDS HERE.** Rewrite `.claude/skills/post-plan/_phase-4-review-audit.md` §4B/§4D posting steps from descriptive prose into a single verbatim copy-paste command block (source the helper, write the findings file, call the helper) with an explicit "do not compose this comment by hand — the envelope is machine-parsed downstream" note naming the 4B probe as the consumer. Same treatment for the Security audit arm, which has the identical shape.
- **rung 2 — a rule doc?** Insufficient alone: the norm has to fire at the exact step that emits the comment, not as ambient guidance.
- **rung 3 — a mechanical gate?** Possible follow-on: have Phase 4B re-read its own comment after posting and assert the `PRF_FOOTER` string is present, failing the phase if not. Cheap, but only worth building if rung 1 proves insufficient.
- **rungs 4–5** — N/A. Explicitly ruled out: relaxing the `4b-probe.sh` regex, which trades a detectable false negative for an invisible false positive.

`artifact destination:` `.claude/skills/post-plan/_phase-4-review-audit.md` (in-repo). Not built this pass.

*(discovered 2026-08-27 during #2001)*

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
