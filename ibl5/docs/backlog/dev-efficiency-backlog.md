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
| E17 | Skill prose carries fixed-count words (`either`, `the two`) that go stale when the enumerated set grows | ⬜ Open | 🟩 | S |

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

**Status (2026-08-25):** ⬜ Open — 🟩 (the occurrence is already fixed; the entry is open only as a watch-item for a second occurrence). *(discovered 2026-08-25 during #1981)*

---

## Burn-down process

1. Pick an entry; `/plan` it (or ad-hoc per the work-triage rule for S items).
2. Implement in a worktree; green-green via existing CI.
3. Update this doc's status; bump `last_verified` (CI enforces via `bin/check-docs`).
