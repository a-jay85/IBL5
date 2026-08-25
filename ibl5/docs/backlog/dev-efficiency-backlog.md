---
description: Development-efficiency backlog — inner-loop speed (diff-scoped analysis, parallel tests), CI caching, dependency-bump batching, and worktree lifecycle automation, with per-entry status.
last_verified: 2026-08-25
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
| E13 | `bin/wt-new --base <branch>` fast-forwards the wrong branch | ⬜ Open | 🟩 | S |
| E14 | `/pr-ready` Monitor exemption in invariants is stale — watcher loop is actually refused | ⬜ Open | 🟩 | S |
| E15 | `/pr-ready` Phase 2 delegation packet tells rebase delegate to push — blocked by sub-agent gate | ⬜ Open | 🟩 | S |
| E16 | `bin/watch-run` declares a run finished on its first poll, before launchd registers the label | ⬜ Open | 🟩 | S |
| E17 | Skill prose carries fixed-count words (`either`, `the two`) that go stale when the enumerated set grows | ✅ Implemented | — | S |
| E18 | `/pr-ready` Phase 6.5 commits new files but never regenerates the PR body `files-changed` block | ✅ Implemented | — | S |
| E19 | `/pr-ready` materialize-from-pin sites declare no fallback, so a pin that predates the script loops forever | ✅ Implemented | — | S |

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

### E13 `bin/wt-new --base <branch>` fast-forwards the wrong branch
**Location:** `bin/wt-new` — the pre-branch sync block (`git fetch` → `rev-list --count` → `merge --ff-only`).
**Problem:** The staleness check is computed on `$BASE_BRANCH` (`rev-list --count "$BASE_BRANCH..origin/$BASE_BRANCH"`), but the merge that acts on it is a plain `git -C "$REPO_ROOT" merge --ff-only "origin/$BASE_BRANCH"` — which merges into whatever branch the main checkout has *checked out*, i.e. `master`. So `bin/wt-new <slug> --base <other-branch>` — the documented stacked-PR path — leaves local `<other-branch>` stale (the worktree forks from a stale tip, exactly what the sync exists to prevent) and drags `master` toward `origin/<other-branch>` instead, or aborts with `Not possible to fast-forward` once the two have diverged. Silent today only because a stacked base is usually already current, so `BEHIND=0` skips the merge entirely.
**Suggested direction:** Update the base branch without checking it out — `git -C "$REPO_ROOT" fetch origin "$BASE_BRANCH:$BASE_BRANCH"` (which is ff-only by default for non-current branches), falling back to the existing `merge --ff-only` only when `$BASE_BRANCH` *is* the checked-out branch. Extend `bin/test-wt-new-root` with a `--base` case; its temp-repo harness already builds a stale-local/diverged fixture.
**Risk if untouched:** Stacked PRs silently branch from a stale parent, and a diverged base turns `wt-new` into a hard failure that also mutates `master` on the way there.
**Status (2026-08-19):** ⬜ Open — 🟩 (no design fork; found while fixing E12 and deliberately left out of that PR's scope).

### E14 `/pr-ready` Monitor exemption in invariants is stale — watcher loop is actually refused
**Location:** `.claude/skills/pr-ready/SKILL.md` — the invariant bullet beginning "**After `EnterWorktree`, no Bash command may contain `$(…)` or `<(…)`.**", specifically its `**Exempt:**` clause claiming `Monitor` is not gated.
**Problem:** The invariant exempts commands passed to `Monitor` on the basis of a probe showing `Monitor(command: 'X=$(echo hi); echo "$X"')` emits `hi`. During a live `/pr-ready 1947` run on 2026-08-24, `Monitor` refused the Phase 4.5 watcher loop written exactly as the skill prescribes, with "this command is too complex to verify that it stays inside the worktree." The probe result is stale; the exemption does not hold in practice, so the skill has a documented shape that silently breaks at runtime.
**Suggested direction:** Delete the `Monitor` exemption clause from the invariant. Rewrite Phase 4.5 to use the same script-file shape mandated everywhere else (write the loop to `/tmp/pr-ready-ciwatch-<N>.sh`, arm `Monitor(command: "bash /tmp/pr-ready-ciwatch-<N>.sh")`), giving the skill one consistent shape with no inline exception.
**Risk if untouched:** Every `/pr-ready` run that reaches Phase 4.5 fails with a refused command and requires undocumented recovery; misleading skill prose delays diagnosis.
**Status (2026-08-24):** ⬜ Open — 🟩 (no design fork; drop one clause, rewrite one code block).

### E15 `/pr-ready` Phase 2 delegation packet tells rebase delegate to push — blocked by sub-agent gate
**Location:** `.claude/skills/pr-ready/SKILL.md` Phase 2 delegation packet and its include `.claude/skills/pr-ready/_rebase-and-conflicts.md`, which instruct the `sonnet-4-6` delegate to run `git push --force-with-lease`.
**Problem:** `~/.claude/hooks/plan-gate-commit.sh` blocks `git push` from sub-agents by design. During a live `/pr-ready 1947` run on 2026-08-24, the rebase delegate returned `pushed: no (blocked by plan-gate-commit.sh sub-agent ship gate — main thread must push)`. The orchestrator had to push itself, which is undocumented recovery — the skill provides no fallback path.
**Suggested direction:** Change the Phase 2 packet so the delegate rebases and verifies only, and reports the resulting local SHA. Move `git push --force-with-lease` explicitly onto the orchestrator in Phase 4, where the existing lost-work proof already lives, making the separation of delegate (rebase) vs. orchestrator (push) explicit and gated.
**Risk if untouched:** Every `/pr-ready` run that needs a rebase silently fails the push step and requires ad-hoc orchestrator recovery with no documented handoff path.
**Status (2026-08-24):** ⬜ Open — 🟩 (no design fork; move one instruction from the delegate packet to the orchestrator phase).

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
