---
description: Historical archive: completed autonomous-loop engineering entries, extracted from loop-engineering-backlog.md.
last_verified: 2026-09-06
---

# Autonomous-Loop Engineering Backlog — Archive

Read-only historical record of ✅ Implemented entries. For OPEN items see ../loop-engineering-backlog.md. Not governed by bin/check-docs (historical dead refs tolerated).

---

### L14 Escalate-on-retry (Sonnet-first, just-in-time Opus)
**Location:** `bin/automouse/run` — `MAX_ATTEMPTS=3`, every attempt at the same `impl_model`; genuine failures park the plan in `skipped/` after 3.
**Problem (was):** `impl_model: sonnet` adoption is throttled by its downside: a plan that turns out to need judgment burns all three attempts on the same model, then a queue slot. The rational response is conservative labeling — Opus-by-default — which is the exact spend the marker exists to avoid.
**Suggested direction (was):** On a genuine (non-environmental) failure of a Sonnet-model plan, escalate the final retry to Opus, feeding the prior attempt's failure report into the retry context. Cheap plans stay cheap; hard plans get Opus exactly when the evidence demands it. Once proven, this makes Sonnet-first safe enough to consider as the *default* for unmarked plans, inverting today's Opus-by-omission.
**Risk if untouched (was):** Gate 13(b)'s Sonnet default stays capped at "obviously mechanical" plans; every borderline plan pre-commits to Opus.
**Status (2026-07-11):** ✅ Implemented — a genuine (non-environmental) failure of any non-Opus plan escalates ONLY its final retry to Opus, fed the prior attempt's capped `.failure` report; policy in ADR-0085. Pairs with T1/T11 in [token-spend-backlog.md](token-spend-backlog.md).

### L17 Shared-context artifact for multi-plan splits
**Location:** `/plan` Step 2.5 multi-PR path (`.claude/skills/plan/SKILL.md`) — Steps 3–5 run once per unit, each plan fully self-contained; the Discord bug pipeline hand-rolled a shared-context spec file to avoid exactly this.
**Problem (was):** When a task splits into N plans, each plan-architect run and each implementation session re-derives the shared orientation (blast radius, patterns, front-loaded decisions) independently — N× the exploration spend — and each plan re-inlines the shared background, inflating it toward gate `[C]`. This is a tax on splitting, i.e. a disincentive against the very decomposition the context-budget gate demands.
**Suggested direction (was):** Formalize the pattern the Discord pipeline improvised: when Step 2.5 splits, persist Step 2's exploration pointers (`path:line` + load-bearing fact, never file bodies) plus recorded Step 3.5 decisions once to `$HOME/claude-plans/<program>-shared-context.md`; each split plan references it instead of restating it. Plans get smaller, and each architect run becomes targeted confirmation instead of re-exploration.
**Risk if untouched (was):** Splitting stays expensive, so plans skew large — working against L16/T11.
**Status (2026-07-11):** ✅ Implemented — formalizes the shared-context artifact in `/plan`: on a Step 2.5 split, SKILL.md Steps 2/2.5/3/3.5/5 seed `$HOME/claude-plans/<program>-shared-context.md` once and each unit references it instead of restating shared background, with matching guidance in `_architect-contract.md`. L16 and T11 remain open.

### L13 Per-phase impl-model routing
**Location:** `bin/automouse/run` (single `--model` per plan, resolved once by `bin/lib/plan-impl-model`); plans already label every phase Sonnet / Haiku / self per `.claude/skills/plan/_architect-contract.md` § Agent-tiering guidance — nothing consumes those labels at run time (verified 2026-07-08).
**Problem (was):** Model selection is whole-run: a mixed plan runs every mechanical phase at the top tier, and the only relief is a tier-boundary split (T11 in [token-spend-backlog.md](../token-spend-backlog.md)), which can't reach plans whose judgment and mechanical phases interleave.
**Suggested direction (was):** Make the in-plan tier labels binding rather than advisory: the impl orchestrator MUST delegate a Sonnet/Haiku-labeled phase as a sub-agent per its delegation packet (packet-carrying phases were already bound from 2026-06-07; the residual gap was bare sub-tier labels carrying no packet). Bulk spend moves down-tier AND out of the orchestrator's context — dumb-zone relief and tier savings from the same change. A runner-driven per-phase `claude -p` sequence with a state handoff is the heavier fallback if in-run delegation proves unreliable.
**Risk if untouched (was):** Per-phase tiering stays a plan-authoring ritual with no runtime effect; mixed plans pay top-tier for mechanical sweeps.
**Status (2026-07-11):** ✅ Implemented — in-plan sub-tier labels are now binding: `.claude/skills/plan/_architect-contract.md` requires a `### Delegate` packet or an explicit `(inline — …)` marker on every below-run-model phase, `bin/check-plan` gate `[T]` enforces it, and `bin/automouse/prompt-impl` binds each case at run time. Packet-carrying phases were already bound (2026-06-07); this closes the bare-label gap. The heavier runner-driven per-phase `claude -p` fallback was not needed.

### L2 Per-plan circuit breaker
**Location:** `bin/automouse/run` — per-phase `timeout` caps (`MAX_IMPL_SECS`/`MAX_PP_SECS` = 3600s), outer `MAX_ELAPSED` ≈ 4h45m, `MAX_ATTEMPTS=3` then the plan is parked in `skipped/` with a report.
**Problem (was):** One runaway plan could eat the night.
**Residual (token-budget breaker) — ✖ Won't do, empirically refuted.** Built as PR #1477 (`MAX_PLAN_COST_USD`, default $5.00, parks the plan in `skipped/` before postplan), then **closed unmerged 2026-07-15**. Measured against 47 `exit:` lines (2026-07-07→07-15):
- **Keys on the wrong variable.** Impl cost does not predict postplan cost — the most expensive impl in the dataset (`ibl6-retirement-1-boxscore-php-port`, $18.72) had nearly the *cheapest* postplan ($1.95), while the three most expensive postplans ($5.68, $5.41, $4.22) all rode cheap impls that never trip the cap. Correlation across 17 paired plans r ≈ 0.08 (noisy at n=17; the inversions are the robust signal).
- **Destroys completed work.** The breaker is gated on `$HANDOFF_FILE`, so it fires *only on impls that succeeded*, then `mv`s the plan away and `rm`s the handoff. At $5.00 it would discard ~$83.49 of working implementation across 7 of 28 runs to avoid postplans averaging ~$3.35; recovery means re-running impl, spending model-hours twice to save a notional figure once.
- **Wrong unit.** automouse runs on subscription auth (bare `claude -p`, no `ANTHROPIC_API_KEY`), so `cost=$X` is an API list-price equivalent, not spend. Weekly automouse load is ~10.4 model-hours — a small fraction of a Max 5x weekly cap. Long overnight impls are *desirable* use of otherwise-idle budget, not waste.
- **Doesn't bound the real constraint.** A per-plan cap can't bound a queue total: on 2026-07-11 the queue ran 5.8 model-hours (breaching a 5-hour session window) and this breaker would have fired once, saving ~14 minutes.

**Superseded by:** L18 (tier-default correction) — the measured waste is tier misallocation, not plan length.
**Status (2026-07-15):** ✅ Implemented — wall-clock + attempts breakers live and sufficient; the token-cap residual is closed as refuted (above), not deferred. Surfaced L18 as the real measured cost driver. PR #1481.

### L15 Sonnet-recipe completeness lint
**Location:** `bin/check-plan` — gates cover matrix presence, forbidden tokens, staleness, and size; none check *recipe completeness*. Gate 13 judges Sonnet-eligibility by verification (a machine check fails on a wrong edit) only.
**Problem (was):** "Sonnet-capable" has two halves and only one is enforced: verifiable, but not *specified*.
**Status (2026-07-15):** ✅ Implemented — `bin/check-plan` gate `[S]` now checks, for `impl_model: sonnet` plans only: every `### Delegate` packet carries a `**Self-verify:**` line (fence-aware, reusing gate T's parse), and a phased plan carries >=1 edit-anchor signal (Anchor keyword / `line NN` ref / 4-backtick fence). A `sonnet-recipe:` marker clears the gate. Tested in `bin/test-check-plan` (gateS-* cases).

### L9 JSB AutoResearch loop
**Location:** JSB sim engine + RE distribution targets; instrumentation groundwork exists (`$HOME/claude-plans/jsb-l1-gate1-counterfactual-instrument.md` and siblings).
**Problem (was):** Engine-parameter tuning is human-paced despite having exactly what a self-improvement loop needs: an objective metric (simulated stat distributions vs real targets).
**Suggested direction (was):** An eval harness that perturbs engine params in a worktree, sims N seasons, scores distribution error, keeps only improvements, and logs each trial — overnight, hundreds of trials. Wants an ADR (metric definition, param search space, acceptance rule).
**Risk if untouched (was):** RE convergence stays bottlenecked on human iteration bandwidth.
**Status (2026-07-23):** ✅ Closed — harness shipped as J14 (PR #1545); this PR wires it into use — stand-in registry re-centered to the live 17.7 pace baseline, a `make research` overnight run path, and a leverage-report review gate in `jsb-engine-post-work.md` — completing the loop. Per ADR-0087 §3 the loop is deliberately human-in-loop: the harness emits a ranked leverage table and NEVER auto-commits, so the original "keeps only improvements" auto-accept framing was superseded by the faithfulness-constrained design, not left unbuilt.
**ADR:** satisfied by ADR-0087 (2026-07-20) — metric/legal-space/acceptance rule defined; harness built (J14, PR #1545) and wired into use by this PR (#1594).

### L18 Tier-default correction (`impl_model:` fails open to Opus)
**Location:** `bin/lib/plan-model-consistency` — the shared gate-13 check, called by **both** `bin/check-plan` (authoring time) and `bin/automouse/queue` (queue time) so the two cannot drift. It reads the **raw** `impl_model:` frontmatter and already requires a deliberate tier choice — except for one exempt branch: `Truly-manual rows >= 1 AND impl_model absent -> ok`. Downstream, `bin/lib/plan-impl-model` **resolves** the raw value, with fallthrough `*) echo "claude-opus-5" ;; # opus, empty, garbled, or unknown → safe default`, so an exempted absence silently resolves to Opus. `bin/lib/automouse-escalate-model` escalates any non-Opus base → Opus on the final attempt (ADR-0085), but never fired in the measured window — every retried plan was already Opus base.
**Problem (was):** A single `Truly-manual` row exempted a plan from declaring a tier at all, and the absent field then silently bought Opus at ~5.4× Sonnet's per-run cost. Measured 2026-07-07→07-15 (28 impl runs): Opus 13 runs / $99.11 total / $7.62 avg vs Sonnet 15 runs / $21.33 / $1.42 avg — Opus was **82% of impl spend**. Two plans reached the queue through the exemption and were silently routed to Opus: `ibl6-retirement-1-boxscore-php-port` ($18.72 — the single most expensive run in the dataset) and `mobile-target-size-a11y-sitewide` ($7.38 + $0.94 retry). Both carried exactly one `Truly-manual` row and no `impl_model:`; both post-dated gate 13 (shipped 2026-07-07, `abbde03d5`, PR #1372) and both passed `bin/lib/plan-model-consistency`. Both were mechanical work (a PHP port; a sitewide a11y sweep) — textbook Sonnet jobs. That was **~$27, ~15% of the week, spent on Opus because a YAML field was absent** — nobody ever made a tier decision. The exemption conflated two different things: a `Truly-manual` row is about **verification** (a human eyeballs subjective UI/UX at PR time; it also forces `auto_merge: false`), while `impl_model:` is about **implementation**. Needing a human to *look at* the result is not evidence that Opus must *write* it.
**Suggested direction (was):** **Fail closed at the gate, not at the resolver.** Flip the exempt branch in `bin/lib/plan-model-consistency` to a violation: `Truly-manual rows >= 1 AND impl_model absent -> VIOLATION`, so a manual-row plan must declare `impl_model: opus` **explicitly** (with a reason in the body, as the existing zero-manual-row rule already demands). ~3 lines in the shared script; closes `bin/check-plan` and `bin/automouse/queue` at once; an **extend**, not a new gate.
**Related:** Both flagged plans were *mixed* (a mechanical bulk plus one subjective UI row) — the case `/plan` Step 2.5 says to **split at the tier boundary** (a small Opus judgment plan + a stacked `impl_model: sonnet` mechanical plan) rather than force whole to Opus. Neither was split, so the separability lever also failed to fire; closing the exemption makes the Opus choice explicit enough to argue with, but does not by itself produce the split. L13 (per-phase routing) tracks the interleaved variant.
**Provenance:** Surfaced 2026-07-15 while reviewing L2/PR #1477; the cost telemetry gathered to refute the token-budget breaker located the real waste here. Direction corrected 2026-07-15 (PR #1481 shipped the resolver-inversion version, which targets the wrong layer).
**Status (2026-07-16):** ✅ Implemented — `bin/lib/plan-model-consistency` gate 13 now treats `Truly-manual rows >= 1 AND impl_model absent` as a VIOLATION (exit 1) instead of ok (exit 0). Header comment updated to show two rows instead of one combined row. `bin/test-check-plan` gate13 case (e) flipped to exit 1 and renamed `gate13-manual-absent-violation`; new case (f) `gate13-manual-explicit-opus-ok` added. Gate 3 test fixtures updated to include `$FM_OPUS` prefix for isolation. Full `bin/test-check-plan` suite passes.

### L24 Phase 5.0 conformance is path-level only; planned method names absent from diff pass undetected
*(discovered 2026-07-31 during #1753)*
**Location:** `.claude/skills/post-plan/_phase-5-final-verification.md` — greps for the test *file path* from the Verification Matrix (`grep -qF "$T" /tmp/post-plan-changed-$PPID`), not individual method names. `.claude/review-shared/_plan-verification.md`.
**Problem (was):** When an implementer substitutes weaker test methods for plan-specified ones, Phase 5.0 passes — the file path appeared in the diff. This is how 4 of the 5 plan-specified test cases in PR #1753 were replaced without triggering a `MISSING:` signal. Sibling entry L21 covers the fail-open from unclosed fences; this entry covers the fail-open from path-only conformance.
**Suggested direction (was):** Extend Phase 5.0 to extract planned test method names from the matrix and phase bodies and emit `MISSING:` for any method absent from the diff. *Confirmed uncovered by #1665/#1667/#1668/#1714 (dedup 2026-07-31).* Route: one `/plan` with L25/L26 (C2/C3), `plan-architect-xhigh` (ship-pipeline invariant), `auto_merge: false`.
**Risk if untouched (was):** Any future implementer can substitute weaker tests than the plan specified; CI stays green and post-plan conformance passes silently.
**Closes gap:** root cause of gaps #6 and #7 (and the general weakened-test class) from `$HOME/claude-plans/sim-recap-testing-gaps-breakdown.md`
**Dedup:** reconciled 2026-07-31 — uncovered by #1668 / #1665 / #1667 / #1714.
**Status (2026-08-04):** ✅ Implemented — gate `[M]` in `bin/check-plan` requires a `## Required Test Methods` section for plans with PHPUnit rows; `_phase-5-final-verification.md` + `tools/postplan-harness/harness/conformance.py` now grep the diff body for `function <name>` / `def <name>` and emit `MISSING-METHOD: <name>` for any absent required method. PR #1765.

### L25 CI-wiring gap: matrix CLI-executable rows may live in jobs the PR's own path filters never trigger
*(discovered 2026-07-31 during #1753)*
**Location:** `.claude/skills/post-plan/_phase-5-final-verification.md`; `.github/workflows/tests.yml` path-filter coupling (see ci-backlog 6.1 for the structural fix).
**Problem (was):** A `CLI-executable / post-impl` matrix row can be "wired into CI" on paper but in a job whose path filter the PR never triggers. Neither gate 15 nor gate 16 asks whether the CI job actually fires on the PR's changed paths. Matrix rows 11 and 20 in PR #1753 were one-shot commands that provided zero permanent regression protection.
**Suggested direction (was):** Add a residual check: for each `CLI-executable` matrix row, either (a) the row's CI job is triggered by the PR's path filter, or (b) the row is explicitly marked `one-shot`. *Dedup completed 2026-07-31: uncovered by #1668/#1665/#1667/#1714 — #1668 executes CLI matrix cells locally once more but covers neither half of the CI-wiring question.* Route: one `/plan` with L24/L26 (C1/C3), `plan-architect-xhigh`, `auto_merge: false`.
**Risk if untouched (was):** A test that appears in a CI job but whose job is never triggered by the PR's path filters provides zero coverage — indistinguishable from a wired test until examined.
**Closes gap:** #4 (meta-tooling half, complementary to ci-backlog 6.1) from `$HOME/claude-plans/sim-recap-testing-gaps-breakdown.md`
**Dedup:** reconciled 2026-07-31 — uncovered by #1668 / #1665 / #1667 / #1714.
**Status (2026-08-04):** ✅ Implemented — gate `[G]` in `bin/check-plan` flags a matrix row whose location cell names a `bin/test-*` / `bin/check-*` script not present in any `.github/workflows/*.yml`, without a `(one-shot)` annotation; `/plan` SKILL.md gate 16 gains failure shape (d) for CI path-filter judgment; `tests.yml` wires `bin/test-check-plan` and `bin/test-postplan-arm-conditions`. PR #1765.

### L26 Gate 15 never examines silent-fallback paths when the hold is security-justified
*(discovered 2026-07-31 during #1753)*
**Location:** `.claude/skills/plan/SKILL.md` Step 4 gate 15 (loud-failure signal lever list); the gate currently fires only when the hold is justified by a *verification gap*.
**Problem (was):** Gate 15 includes "a loud-failure signal replacing a silent fallback" in its lever list. It did not engage for PR #1753 because the hold was justified on gate-14b security-surface grounds — not a verification gap. The `qctx()` failure → `WARNING` → `{}` → roster-blind-recap path was never examined. The detectability concern is orthogonal to what justifies the hold.
**Suggested direction (was):** Extend gate 15 (do not add a new gate — meta-tooling-bar.md extend-before-add). Add a named trigger: a new silent-fallback / degraded path in a synchronous sim path or a `bin/*-tick` script requires a loud signal (Discord), independent of what justifies the hold. *Confirmed uncovered by #1665/#1667/#1668/#1714 (dedup 2026-07-31).* Route: one `/plan` with L24/L25 (C1/C2), `plan-architect-xhigh`, `auto_merge: false`.
**Risk if untouched (was):** Future silent-fallback paths in ship-adjacent code will not be examined at plan time when the plan hold is security-motivated rather than verification-gap-motivated.
**Closes gap:** #9 (meta-tooling — prevents future versions of this class) from `$HOME/claude-plans/sim-recap-testing-gaps-breakdown.md`
**Dedup:** reconciled 2026-07-31 — uncovered by #1668 / #1665 / #1667 / #1714.
**Status (2026-08-04):** ✅ Implemented — gate 15 in `plan/SKILL.md` gains a second unconditional arm keyed on the *diff* rather than on the hold's justification: a new silent-fallback or degraded path in a synchronous sim path or a `bin/*-tick` script requires a loud failure signal (Discord, a required-blocking CI check, or an equivalent alarm), regardless of whether the hold is security-, destructive-migration-, UI/UX- or verification-gap-motivated. PR #1765.

### L23 sim-recap degraded path emits no Discord signal; qctx() failure ships roster-blind with CI green
*(discovered 2026-07-31 during #1753)*
**Location:** `bin/sim-recap-tick` (calls `qctx()`; on failure logs `WARNING` to launchd only and continues with `{}`); `ibl5/classes/Discord/Discord.php` (Discord class surface); `bin/bug-pipeline-tick` + `bin/lib/bug-pipeline-gh.sh` (existing pattern to copy).
**Problem (was):** When `qctx()` fails, the recap ships roster-blind. CI stays green — the fix can no-op in prod indefinitely with no visible signal. Only a human reading launchd logs would notice. Also: Block 8's "authoritative" header always emits followed by bare `{}`, so the documented roster-blind mode (Block 8 omitted) is unreachable in prod.
**Suggested direction (was):** Emit a Discord signal on `qctx()` failure, copying the `bin/bug-pipeline-tick` + `bin/lib/bug-pipeline-gh.sh` pattern. Also decide Block 8's empty-`{}` behavior at plan time.
**Risk if untouched (was):** A qctx() failure in prod is undetectable until a GM notices the recap is wrong — the exact failure mode PR #1753 was written to fix.
**Closes gap:** #9 from `$HOME/claude-plans/sim-recap-testing-gaps-breakdown.md`
**Status (2026-08-14):** ✅ Implemented — edge-triggered Discord alert on roster-context degradation (healthy→degraded) and recovery (degraded→healthy); state persisted in `~/.claude/projects/.../sim-recap/ops-alert.json` to prevent notification floods; opt-in via `SIM_RECAP_OPS_ALERT_THREAD_ID`; Block 8 empty-`{}` gate fixed to require `.sim` key. PR #1878.

### L7 Queue-add shift-left preflight
**Location:** `bin/automouse-queue` `add` runs zero preflight (verified); staleness is caught only at 2am by the impl agent, then self-heal requeues (L8). Plan: `$HOME/claude-plans/staleness-guard-fp-fix-and-queue-check.md` (not yet queued).
**Problem (was):** A stale anchor costs a night when it could be fixed in 30 seconds at queue-add time, while a human is at the keyboard.
**Suggested direction (per the plan):** Run `bin/check-plan` + `bin/check-plan-staleness` at add time; also fixes known staleness-check false positives.
**Risk if untouched (was):** Recurring burned queue slots for trivially-fixable staleness.
**Status (2026-06-27):** ✅ Implemented — PR #1225: `bin/automouse-queue add` now runs `bin/check-plan` + `bin/check-plan-staleness` as a shift-left preflight; staleness false-positive fixes included.

### L10 Discord intake loop
**Location:** `bin/bug-pipeline-tick`, `bin/bug-pipeline-cron-setup`, `bin/bug-pipeline-classify-prompt`, `bin/bug-pipeline-gather-prompt` (live); remainder of the 6-PR Discord bug pipeline program per its shared-context spec.
**Problem (was):** Bug reports ended at a human reading Discord.
**Suggested direction (was):** Automated gather → classify → hunter pipeline with Discord as the intake channel; human checkpoints (plan review + `feat:` signoff gate) stay in place by design.
**Risk if untouched (was):** Bug reports manually triaged from Discord; no persistent pipeline.
**Status (2026-07-11):** ✅ Implemented — 7 pipeline PRs: #1327 (2026-07-05), #1326 (2026-07-05), #1353 (2026-07-06), #1354 (2026-07-06), #1356 (2026-07-06), #1355 (2026-07-07), #1418 (2026-07-11). Full gather/classify/tick machinery merged and cron-installable; hunter stages complete. Human checkpoints (plan review + `feat:` signoff gate) in place. The residual program is tracked in its own pipeline, not re-planned here.

### L16 Context-budget gate v2 (work-size proxies + measured calibration)
**Location:** `bin/check-plan` gate `[C]` (≥ 500 lines OR ≥ 12 numbered phases — thresholds hand-set once from the 2026-07-07 automouse-corpus audit); the T1 per-phase cost rows carry no peak-context column.
**Problem (was):** Two blind spots. (1) Plan size ≠ work size: a 100-line plan phase saying "sweep every call site" triggers a marathon implementation the gate can't see, while a reference-heavy plan false-trips and gets papered over with a `context-budget:` marker. (2) No feedback loop: nothing re-checks the thresholds as plan style evolves, so the gate drifts from the dumb-zone reality it proxies.
**Suggested direction (was):** (a) Add work-size proxies — Verification-Matrix row count, Critical-Files change-target count, and sweep-verb detection ("all call sites", "every occurrence") in a phase without a delegation packet. (b) Log peak context tokens per impl run into the T1 ledger (the stream-json usage events already carry them) and add a report correlating plan proxies against measured peaks — recalibrate thresholds from data, and flag any run breaching ~150K as a Step 2.5 split miss for the retro.
**Risk if untouched (was):** Dumb-zone breaches keep happening under the gate's radar, and the thresholds stay a one-shot guess.
**Status (2026-07-15):** ✅ Implemented — PR #1479 (`context-budget-gate-v2`): `bin/check-plan` [C] proxy counts (VM rows, CF change-target count, sweep-verb advisory [W]), stream-filter `peak_ctx` tracking, and `costs.md` Peak Ctx column shipped.

### L34 `bin/pr-ready-now` has no working stop path; `launchctl bootout` orphans the session and corrupts slot accounting
*(discovered 2026-08-23 during #1948, by the row-22 live-fire smoke test against PR #1899)*
**Location:** `bin/pr-ready-now` — the emitted runner body (`/tmp/pr-ready-now-runner-<N>.sh`), whose trailing `rm -f "$PLIST"` is the only thing that releases a slot, plus `live_slots()` / `reap_stale()` / `wait_for_slot()`, which count `~/Library/LaunchAgents/com.ibl5.pr-ready-now-*.plist` as the slot token. Secondary surface: the PR-keyed scratch files the `/pr-ready` skill writes (`/tmp/pr-ready-*-<N>.*`), which are deliberately not `$$`-keyed so they survive the launchd boundary.
**Problem:** Three coupled facts, all observed in one run, not inferred. (1) **`launchctl bootout` does not stop a fired session.** It kills only the runner shell; `timeout` / `caffeinate` / `claude` are reparented to PID 1 and keep going — the observed run completed its Phase 4.3 force-push to `origin` *after* bootout returned, and needed an explicit `kill -TERM` on the reparented PIDs to actually die. (2) **An aborted job leaks its slot.** The runner's `rm -f "$PLIST"` sits after the `claude` invocation, so killing the runner skips it and the plist stays on disk. (3) **`reap_stale()` then makes it worse, not better.** It deletes a plist whose launchd label is gone — which is exactly the aborted-job state — so it frees a slot whose `claude` is still alive; `live_slots()` under-counts and a re-fire can race a live session on the same PR. Separately, the aborted run leaves its `/tmp/pr-ready-*-<N>.*` scratch behind, so a later `/pr-ready <N>` reads a **stale pre-rebase baseline** as its own lost-work comparison — present-but-wrong, which the skill's fail-closed guards do not catch because they only catch missing.
**Interim workaround (2026-08-23):** `launchctl bootout "gui/$(id -u)/com.ibl5.pr-ready-now-<N>"`, then `rm -f ~/Library/LaunchAgents/com.ibl5.pr-ready-now-<N>.plist`, then `pkill -TERM -f 'name com.ibl5.pr-ready-now-<N>'`, then `rm -f /tmp/pr-ready-*-<N>.*`. Verified to leave no orphan process and no leftover plist. Documented in #1948's body; not wired into the script.
**Suggested direction:** Give the driver a real stop verb (`--stop <N>` / `--stop-all`) that boots the label out, TERMs the reparented descendants, removes the plist, and clears the PR-keyed scratch — so the release path is the same code whether the run completes or is aborted. Move slot release out of the runner's happy path (a `trap`, or make `reap_stale` verify no live `claude` carries `--name <label>` before reclaiming the slot) so an abort cannot leak or double-issue a slot. `bin/test-plan-now`'s stub pattern (`PLAN_NOW_CLAUDE` shim, no real launchd job) is the closest existing test host to copy.
**Risk if untouched:** There is no safe way to abort a fired run — an operator who thinks they stopped one has in fact left it free to rebase and force-push a real branch, and the slot cap that is supposed to bound concurrency silently over-issues afterwards. The stale-scratch facet is the quieter one: it makes a *future* `/pr-ready` run compare against the wrong baseline while every guard reports green.
**Closes gap:** abort-path correctness — every fire-path row in #1948 passes; nothing exercises the stop path.
**Status (2026-08-25):** ✅ Implemented — `bin/pr-ready-now` gained `--stop N[,N...]` / `--stop-all`: bootout → TERM → KILL by end-anchored `--name <label>`, a fail-closed liveness probe that releases the slot only once nothing carries the label, and a PR-anchored clear of `/tmp/pr-ready-*-<N>.*` (also run pre-fire, for provably-dead labels only). `reap_stale()` no longer frees a slot whose `claude` is still alive. Locked by `bin/test-pr-ready-now` cases 32/32c/33/34/35/36. The interim workaround above is superseded by `--stop <N>` — kept for the record only.

### L22 Sweep queue-vs-review disposition gates across other skills/scripts
*(discovered 2026-07-30 during plan-prompt-blast-radius-disposition)*
**Location (was):** `.claude/skills/` — every other skill or script carrying `--queue` vs `--implement` (or equivalent queue-vs-human-review) disposition guidance. `.claude/skills/plan-prompt/SKILL.md` Step 5 item 2 is already converted (this entry's originating PR). Candidates to enumerate: `.claude/skills/plan/SKILL.md` (Step 4 gate 14 and its `auto_merge` guidance), `.claude/skills/post-plan/SKILL.md` (the Phase 6.5 arm conditions), and `bin/plan-now`'s disposition coda. **Peer conflict:** `.claude/skills/plan/SKILL.md` was owned by branch `plan-frontmatter-scaffold-strip` during the originating PR's implementation window, so the sweep is deferred until that branch merges.
**Problem (was):** The blast-radius predicate now governing `/plan-prompt`'s gate — reach for `--implement` if and only if the work triggers `plan-architect-xhigh` — applies equally to every other queue-vs-review gate in the pipeline. Those gates still key on subjective self-assessment (novelty, felt scope, drafting-session confidence), which is unfalsifiable and fires hardest exactly when the deciding session has the least information. Left alone, each gate re-accretes its own carve-outs on its next edit and the pipeline ends up holding several mutually inconsistent answers to one question.
**Suggested direction (was):** Once `plan-frontmatter-scaffold-strip` has merged, enumerate every disposition gate under `.claude/skills/` and `bin/`, apply the same three-trigger predicate (security surface or trust boundary; destructive or schema-tightening migration; `.claude/skills` ship-pipeline invariant — authoritative in `.claude/rules/agent-tiering.md` § Tiers), and ship it as one `chore:` PR. **Explicit non-target:** `.claude/rules/work-triage.md` § Ad-hoc safety mirror is deliberately out of scope — it answers a different question (should this work be planned at all, where a UI/UX or subjective-judgment surface is a legitimate trigger), not whether a human reads the plan before it implements. Do not fold it in.
**Risk if untouched (was):** The predicate lives in one skill while its peers keep subjective carve-outs, so the queue-vs-review decision stays inconsistent across the pipeline and each gate drifts independently.
**Status (2026-08-31):** ✅ Implemented — sweep complete across all named candidates. (1) `.claude/skills/plan/SKILL.md` Step 5.5 item 2: converted — subjective queue-vs-implement criterion replaced with `plan-architect-xhigh` blast-radius predicate. (2) `.claude/skills/plan/SKILL.md` gate 14 and `auto_merge` guidance: assessed out of scope — gate 14 triggers (a)–(d) already enumerate blast-radius conditions, and it answers whether auto-merge arms (not whether to queue vs. implement). (3) `.claude/skills/post-plan/SKILL.md`: assessed — no analogous queue-vs-review language present (grep-confirmed). (4) `bin/plan-now` disposition coda: assessed out of scope — mechanical flag routing, declared non-target in plan. Deferral condition (`plan-frontmatter-scaffold-strip` merge) confirmed met. (implemented 2026-08-31 via PR #2040)
### L36 `/post-plan` Phase 3 writes a hardcoded "covered by unit and E2E tests" clause into the PR body without checking the diff contains those test types
*(discovered 2026-08-25 during #1969)*

**class:** a skill or generator asserts a fact about its own environment — test coverage present, a tool exempt from a gate — as a template constant or a hand-written invariant, with nothing checking that the assertion is still true. Two live instances found this pass: a PR-body clause naming test types the diff does not contain, and a skill invariant declaring `Monitor` exempt from the worktree command-substitution gate when it is not.

**Location:** `.claude/skills/post-plan/SKILL.md` line 121 — "If the matrix has zero truly-manual rows (or the plan says 'All verification is automated'), write: `No manual testing needed — all changes are covered by unit and E2E tests.`"

**Problem:** The clause is a template constant, not a claim derived from the diff. PR #1969 added PHPUnit unit and `DatabaseIntegration` tests and **no** Playwright E2E spec, yet the body asserted E2E coverage. The failing half is only the tail clause: the `No manual testing needed` prefix is a load-bearing machine sentinel that `bin/lib/pr-armable.sh:59` prefix-matches (`^[[:space:]]*No manual testing needed`) to clear auto-merge condition (1), so the sentinel itself must survive any fix. `.claude/skills/plan/SKILL.md:288` already names this exact sentence as a known plan-side failure mode ("Silence is not coverage"), but names it only as a *plan matrix* defect — nothing checks the generated PR body against the realized diff.

**Occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | PR #1969 body § Manual Testing (generated from `.claude/skills/post-plan/SKILL.md:121`) | yes | yes | fixed this pass (body prose corrected in-PR) |
| 2 | `.claude/skills/post-plan/SKILL.md:121` (the generator itself) | yes | yes | fixed — PR #2043 |
| 3 | `.claude/skills/post-plan/SKILL.md:276` — fallback clause says "automated tests", type-agnostic | near-miss | yes | not fixed — correct as written; names no specific type |
| 4 | `.claude/skills/plan/SKILL.md:288` | near-miss | yes | not fixed — this is the documented warning, not an occurrence |
| 5 | `.claude/skills/pr-ready/SKILL.md` — Invariants § "**Exempt:** any command passed to `Monitor`, which is not gated" | yes | yes | **already fixed on master** — independently found and shipped the same day as [E14](dev-efficiency-backlog.md) via #1991, which deleted the clause. Measured false here first (2026-08-25: the inline watcher was refused with "too complex to verify that it stays inside the worktree" and had to be written to a `/tmp` script), then confirmed resolved on rebase. Retained as evidence the class generalises beyond the `post-plan` generator. |

**prevention ladder:**
- rung 0 — already covered? No. `bin/lib/pr-armable.sh` prefix-matches the sentinel and never reads the tail clause; `bin/check-docs` does not read PR bodies at all.
- rung 1 — extend an existing gate? **Yes — this is the landing rung.** `bin/lib/pr-armable.sh` already parses the `## Manual Testing` section and already owns the Manual-Testing clearance predicate; extending `pr_manual_testing_clearance` (or adding a sibling predicate beside it) to reject a tail clause naming a test type absent from the PR's changed-file list reuses the existing parse and the existing `ibl5/tests/Cli/PrArmableLibCliTest.php` harness.
- rung 2 — a rule doc? Insufficient alone: `.claude/skills/plan/SKILL.md:288` already *is* prose guidance against this exact sentence and it did not prevent the occurrence.
- rung 3 — a PHPStan rule? N/A — the artifact is a GitHub PR body, not PHP.
- rung 4 — a CI gate? Overkill given rung 1 lands it, and a CI job cannot see the body on a `pull_request` event without an extra API call.
- rung 5 — a new hook? Overkill; rung 1 is strictly cheaper.

Rung 1 does not require the `.claude/rules/meta-tooling-bar.md` extend-before-add conditions (those bind rungs 3–5), and it *is* the extend-before-add outcome those conditions push toward.

**artifact destination:** `bin/lib/pr-armable.sh` (in-repo, appears in the PR diff), locked by `ibl5/tests/Cli/PrArmableLibCliTest.php`. A companion one-line correction to the template at `.claude/skills/post-plan/SKILL.md:121` ships with it. Occurrence 5 needs no artifact — #1991 already deleted the clause (dev-efficiency E14, ✅ Implemented 2026-08-25). Two independent discoveries of the same class on the same day is itself the argument for rung 1: prose asserting an environment fact is not self-checking, so it drifts silently until something trips over it.

**Suggested direction:** Change the `.claude/skills/post-plan/SKILL.md:121` template to a type-agnostic clause ("all changes are covered by automated tests", matching line 276), and extend `pr_manual_testing_clearance` to fail closed when the tail clause names a test type with no corresponding file in the PR's changed-file list.

**Risk if untouched:** Every plan whose matrix has zero `Truly-manual` rows ships a PR body asserting E2E coverage it may not have. A reviewer who trusts the sentence skips exactly the verification the plan deferred, and the false claim is the *positive clearance signal* auto-merge arming reads — so the sentence that is wrong is also the one that unblocks the merge.

**Note:** `.claude/skills/post-plan/SKILL.md` and `bin/lib/pr-armable.sh` are ship-pipeline surfaces; route through `/plan`, not ad-hoc.

**Status (2026-08-31):** ✅ Implemented — PR #2043: `pr_manual_testing_clearance()` extended with second `changed_files` parameter and tail-clause keyword gate; `.claude/skills/post-plan/SKILL.md` template updated to type-agnostic "automated tests". Four new PHPUnit tests lock the gate.

### L40 Compiled post-plan harness crashes on any PR containing a binary file (`git diff` decoded as strict UTF-8)
*(discovered 2026-09-01 while shipping #2056, whose diff contained the binary artifact `ibl5/data/finals2008-g4.rec`)*

**Location:** `tools/postplan-harness/harness/adapters/gitad.py:18` — `_run()` calls `subprocess.run([...], capture_output=True, text=True)` with no `errors=` argument. The crash surfaces at `gitad.py:42` (`diff_vs_base`), which shells out to `git diff <merge-base>`. Same unguarded `text=True` at six other call sites: `gitad.py:85`, `gitad.py:88`, `ciwatch.py:71`, `llm.py:68`, `ghad.py:116`, `verify.py:42`. (`llm.py:58` is the only place in the harness that passes `errors=` at all.) Regression-test host: `tools/postplan-harness/tests/test_gitad_live.py`.

**Problem:** `text=True` decodes the child's stdout as strict UTF-8. `git diff` emits raw bytes for a binary file, so any diff touching one raises `UnicodeDecodeError` and kills the compiled harness *before any phase runs*. Observed 2026-09-01 at 18:19: `UnicodeDecodeError: 'utf-8' codec can't decode byte 0x9e in position 23462: invalid start byte`, traceback `gitad.py:42 diff_vs_base` → `gitad.py:17 _run`. This is not a corner case — it fires on **every** PR whose diff contains a binary file, which is the entire boxscore-restore class of work (`.rec` artifacts) plus any image, font, or fixture blob.

The failure is quiet because the two-engine design absorbs it: the harness exits non-zero, `should_fallback()` returns true for any rc except 0 and 3, and `bin/post-plan-now` silently hands off to the slower Sonnet `/post-plan` skill. Work still completes, so nothing alarms — but the run costs ~40 min instead of a few, and the fallback agent reasons without the harness's guardrails. In #2056 it invented a `@codeCoverageIgnore` annotation with zero precedent anywhere in the repo, which did not clear the coverage gate anyway (83.98% → 84.24%, minimum 84.46%); the fix had to be reverted by hand and replaced with the repo's actual precedent (lowering `coverage-baseline.json`, as in #2001 and #2022).

**Suggested direction:** Add `errors="replace"` to `_run()` in `gitad.py` — mojibake in a diff string the harness only pattern-matches over is strictly better than a crash. Then sweep the other six `text=True` sites for the same guard, since `git log`, `gh` output, and CI logs can all carry non-UTF-8 bytes. Regression test in `tests/test_gitad_live.py`: create a temp repo, commit a file containing byte `0x9e`, and assert `diff_vs_base()` returns a string rather than raising.

**Risk if untouched:** Every binary-touching PR silently loses the fast, guardrailed engine and falls through to an unconstrained agent — the expensive path, taken invisibly, with lower-quality output. Because the fallback usually *succeeds*, there is no signal that the primary engine has been dead for that whole class of PR.

**Status (2026-09-01):** ⬜ Open — 🟥 (self-contained fix in a dev-tooling adapter; no user-facing surface, no gate weakened).

**Resolved (2026-09-05):** PR #2112 adds `errors="replace"` to all three `text=True` sites in `gitad.py`; regression pin wired into CI (`python-tests.yml`).

### L47 `/pr-ready` folds a recoverable pre-push-hook rebase rejection into the terminal `PUSH FAILED` verdict, stranding the Phase 6.5 remediation commit locally

**class:** Any `/pr-ready` push site that pushes long after its last rebase treats a **pre-push-hook** rejection — `rc 1`, remote ref *unmoved*, nothing clobbered, fully recoverable by one `fetch` + clean `rebase` — as the same terminal `PUSH FAILED` as a genuine divergence, so the run ends `NOT READY` with committed work stranded locally.

**Mechanism, end to end.** `bin/pre-push-adr-hook:61-71` gates on `git merge-base --is-ancestor origin/master HEAD` against the **local** remote-tracking ref, deliberately without fetching (its own comment: *"No fetch here — keep push latency low; wt-new already syncs at creation time"*). `SKILL.md:182` (Phase 5) runs a second `git fetch origin`, which advances local `origin/master` mid-run. Phase 6.5 then spends 10–15+ minutes in CI-wait plus the Opus fidelity review and pushes with **no fetch+rebase step anywhere before its push** (`_phase65-remediation.md` steps 1–5). If master merged anything in that window, the hook rejects. `scripts/push.sh` captures the push output at line 55 but inspects `$OUT` **only** for `"stale info"` (line 66); with no `pre-push-adr-hook` branch, control falls to the line-79 catch-all and the run dies on a condition that a single clean rebase would have cleared.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `.claude/skills/pr-ready/scripts/push.sh:79` | yes — the catch-all `PUSH FAILED` branch; `$OUT` (captured line 55) is grepped only for `stale info` at line 66, never for `pre-push-adr-hook` | yes | not fixed — filed |
| 2 | `.claude/skills/pr-ready/_phase65-remediation.md:46` | yes — *"`PUSH FAILED` — origin does not hold this HEAD; stop."* Phase 6.5 has no fetch+rebase before its push, so it is the site with the widest staleness window | yes | not fixed — filed (**the PR #1956 kill site**) |
| 3 | `.claude/skills/pr-ready/_phase4-push-and-ci.md:30` | yes — *"`PUSH FAILED` (rc 1) … Print it and stop; do not continue to Phase 5."* Same terminal treatment of `rc 1` | yes | not fixed — filed; see rung 0 for why the pending push-retry plan does **not** close this |
| 4 | `bin/pr-ready-now:434` | yes — the runner classifies any `PUSH FAILED` substring in the log as a hard-stop marker, so a new non-terminal verdict word must be taught here too | yes | not fixed — filed |
| 5 | PR #1956, branch `authz-verdict-refactor-1a-trading-pins` (the reported occurrence) | n/a — instance, not a code site | — | fixed this pass, manually: `fetch origin` → clean rebase onto `origin/master` (2/2, no conflicts) → lease-guarded force push; head `0a7dc8315` → `0da61d9bc`, CI green (32 SUCCESS / 14 SKIPPED / 0 failures) |

**Frequency — one confirmed occurrence.** A transcript sweep of `~/.claude/projects/` first appeared to show ~23 affected runs, but that count is an artifact: `scripts/push.sh` contains the literal string `PUSH FAILED`, and the hook's message text appears in `bin/pre-push-adr-hook` and in skill prose, so any transcript that merely `cat`'d those files scores a double hit. Discriminating on a real *emission* (`push rc: 1` — the line-79 format string, resolved — co-occurring with the hook message) leaves PR #1956 alone. This is why the entry lands at rung 1 rather than a new gate: the class is real and the failure is expensive, but it is not yet recurring.

**prevention_ladder:**

- rung 0 — **partially covered, and not for this case.** `~/claude-plans/pr-ready-dm-and-push-retry.md` Phase 6 (authored 2026-09-04; **not implemented** — no branch, no worktree, no PR as of this entry) adds a bounded three-attempt retry ladder, but it lands **only** in `_phase4-push-and-ci.md`, routes **only** `STALE LEASE` (rc 2), `mergeStateStatus=DIRTY`, and `MERGE CONFLICT` (rc 2) into that ladder, and its §6.1 states verbatim that *"`PUSH FAILED` is genuinely non-retriable and stays a hard stop."* Its §6.5 puts `_phase65-remediation.md` explicitly out of scope. PR #1956 failed at Phase 6.5 with `PUSH FAILED`, so **shipping that plan unchanged leaves this defect intact.** Whoever plans L47 should read that plan first and decide whether to extend it or stack on it.
- rung 1 — **extend the existing mechanism. This is the landing rung.** Three coordinated edits, no new tooling:
  1. `scripts/push.sh` — alongside the existing `stale info` check, grep `$OUT` for `pre-push-adr-hook` and, **only when the post-push remote read shows the ref unmoved**, emit a distinct verdict word (e.g. `HOOK REJECTED`, `rc 3`). Semantically this belongs next to `STALE LEASE` — nothing was clobbered and remote state is known — not next to `PUSH FAILED`, which means *remote state unknown or diverged*. The lease derivation, the explicit refspec, and every fail-closed path stay byte-identical.
  2. `_phase4-push-and-ci.md:30` and `_phase65-remediation.md:46` — name the new verdict and route it into **one bounded** `fetch` + `rebase` + single re-push. `rc 3` is unhandled by both callers today, so this is a two-file coordination, not a one-liner; a new verdict word that only one caller knows is worse than none.
  3. `bin/pr-ready-now:434` — must not classify the new word as a hard stop.
- rung 2 — a rule doc under `.claude/rules/`: insufficient on its own. The failure happens inside a headless `claude -p` run whose behaviour is fixed by the skill includes it loads; there is no human reading a rule mid-run.
- rung 3 — a PHPStan rule: not applicable (shell and markdown, no PHP).
- rung 4 — a CI gate: not applicable as *the* gate — the trigger is a runtime race (master merging during a CI-wait), not a static property any checkout can evaluate. `bin/test-pr-ready-now` should gain arms for the new verdict word and for the two callers naming it, but that is test coverage *for* the fix, not the prevention itself.
- rung 5 — a new hook: not warranted. `.claude/rules/meta-tooling-bar.md`'s extend-before-add bar fails its first condition, **"no host to extend"**: `scripts/push.sh` is the natural host and already owns the verdict vocabulary. Conditions "distinct trigger" and "no cheaper alternative" also fail.

Landing rung: **1** (extend `scripts/push.sh`'s verdict vocabulary plus its two callers and the runner classifier).

**Design constraint the implementing plan must carry — or it will stall.** `_phase65-remediation.md:62` forbids looping back into Phases 2–3 after Phase 6, because *"re-rebasing here would invalidate the fidelity review Phase 6 has already performed on a diff that no longer exists."* That invariant is sound but its prose over-forbids: the hazard it actually guards is a **conflict-resolving** rebase, which introduces content nobody reviewed. A **clean** rebase is patch-preserving — every branch commit's diff is unchanged, so the completed fidelity review still describes exactly what ships. The bounded shape that respects the invariant is therefore: on `HOOK REJECTED` only, one `fetch` + `rebase`; **clean** → re-push once; **any conflict** → `git rebase --abort` and fall through to today's terminal behaviour. One attempt, never a loop, and it never re-enters Phases 2–3. This is precisely the path the manual PR #1956 recovery took.

**Planning tier and merge posture.** This relaxes a terminal STOP in the ship-pipeline surface (`.claude/skills`) and is a bootstrap hazard — it edits the push path the pipeline uses to merge itself. `/plan` Step 3 check 1 therefore selects **`plan-architect-xhigh`**, and the plan should carry `auto_merge: false`.

**artifact destination:** `.claude/skills/pr-ready/scripts/push.sh`, `.claude/skills/pr-ready/_phase4-push-and-ci.md`, `.claude/skills/pr-ready/_phase65-remediation.md`, `bin/pr-ready-now`, `bin/test-pr-ready-now` — all in-repo; nothing out-of-repo, so the whole change appears in the PR diff.

**Status (2026-09-04):** ✅ Implemented — `scripts/push.sh` now emits `HOOK REJECTED` (rc 3) for a pre-push-hook rebase rejection with the remote provably unmoved, and `_phase4-push-and-ci.md` / `_phase65-remediation.md` route it into one bounded, clean-only fetch + rebase + re-push. Landed by PR branch `pr-ready-hook-rejected-recovery`. `bin/pr-ready-now` needed no source edit — the new verdict word contains no `PUSH FAILED` substring, so its `:434` classifier already treats it as non-terminal (see L57).

**provenance:** (discovered 2026-09-04 during #1956)

### L48 Planning pipeline prose coverage gap: code-block path expressions in `SKILL.md` are invisible to `bin/check-docs`, so they can diverge from `bin/plan-now`'s runtime slug derivation silently

**class:** Any shell code block inside `.claude/skills/plan/SKILL.md` that constructs a file path is invisible to `bin/check-docs`'s dead-reference checker, because the check operates on prose tokens matching `bin/<name>` / `ibl5/<path>` / `.claude/<path>` patterns — not on dynamic expressions inside fenced blocks. A path expression that silently produces the wrong value causes the gate to read a nonexistent file and exit 0 without firing.

**Immediate instance fixed in PR #1946:** `SKILL.md` Step 5 pre-finalize drift check derived the draft path via `$(git rev-parse --abbrev-ref HEAD)`. On the dominant `bin/plan-now` path the branch is `master`, so the check silently read `$HOME/claude-plans/.drafts/master.draft.md` (nonexistent), exiting 0 as "no scaffold found" and never detecting drift. Fixed by substituting the `<slug>` placeholder already established earlier in Step 5.

**Prevention gap.** `bin/check-docs` explicitly skips paths containing shell variable syntax (`$FOO/bar`). Dynamic expressions inside code fences are not covered. A PR that changes a path expression in a SKILL.md code block passes all CI gates while quietly introducing a runtime divergence.

**prevention_ladder:**

- rung 0 — no existing gate covers this surface.
- rung 1 — extend `bin/check-docs` (or add a narrow `bin/check-plan-skill-paths` (example)) to grep fenced blocks in `SKILL.md` for `DRAFT=` assignments and assert the path uses the `<slug>` placeholder, not a `$(git rev-parse ...)` expression. Structural grep, no behavioral execution required. Effort: S.
- rung 2 — a rule doc under `.claude/rules/`: useful but not enforcement.
- rung 3 — PHPStan: not applicable (shell/markdown).
- rung 4 — a CI gate extension: `bin/test-check-plan` already covers the path-not-found case (`gateD-no-path-exits-2`); a wrong-but-present path cannot be caught without knowing the expected slug — rung 1 is the natural landing.
- rung 5 — a new hook: not warranted per `meta-tooling-bar.md` (no distinct trigger; a `bin/check-docs` extension is the natural host).

Landing rung: **1** (extend `bin/check-docs` or add a narrow lint for `DRAFT=` expressions in SKILL.md fenced blocks).

**artifact destination:** `bin/check-docs` or a new `bin/check-plan-skill-paths` (example) (in-repo)

**Status (2026-09-04):** ✅ Implemented — 🟦. PR: check-docs-skill-draft-placeholder.

**provenance:** (discovered 2026-09-04 during PR #1946 plan-intent review)

---

### L20 post-plan body-rewrite clobbers `Depends-on:`, bypassing arm condition (6)
**Location:** `.claude/skills/post-plan/_phase-6.5-arm-auto-merge.md` (arm condition 6: `depends-on-merge-order`) and `.claude/skills/post-plan/SKILL.md:93` (prescribing `Depends-on: #<n>` as the alternative to `--base` stacking in this squash-merge repo).
**Problem (was):** Arm condition (6) reads the live PR body via `gh pr view` and refuses to arm auto-merge until every PR named on a `Depends-on: #<n>` line is merged. `SKILL.md:93` prescribes `Depends-on:` as the correct alternative to `--base` when the repo squash-merges (a squash collapses the parent's commits, so a stacked child's branch carries pre-squash commits that conflict on auto-retarget). Observed 2026-07-29 on PR #1734 (`fence-parity-guard`): `Depends-on: #1715` was added as line 1 of the body. A later post-plan run rewrote that PR body wholesale; `gh pr view 1734 --json body` then returned a body starting `## Summary` with no `Depends-on:` line, so condition (6) evaluated `blocked=False` and #1734 armed and merged ahead of its declared dependency (commit `1b8249f4f7a651fb78b8e8bc3d60b7af25b460a4`). Effect was harmless this time only because the branch already contained #1715's commits. The structural problem: the same pipeline that reads the `Depends-on:` marker also overwrites the text carrying it — the prescribed alternative to `--base` is silently unreliable as a dependency declaration.
**Suggested direction (was):** (a) Make body rewrites preserve/re-emit any existing `Depends-on:` lines before overwriting. (b) Move the dependency declaration somewhere the pipeline does not overwrite (a label, or plan frontmatter `depends_on:` — see **L1**, which proposes exactly this field for queue ordering). (c) Have condition (6) read from a source other than the mutable PR body. This needs design; do not pick a direction ad-hoc (touches a `.claude/skills` ship-pipeline invariant per `.claude/rules/work-triage.md` § Ad-hoc safety mirror — wants a `/plan`).
**Blocked by:** peer session active on branch `postplan-arm-unresolved-findings`; coordinate before touching arm conditions to avoid duplicating work.
**Risk if untouched (was):** Silent merge-order violations in future stacked-plan programs where the parent branch is not yet in the child's commit history.
**Status (2026-07-29):** ⬜ Open — 🟥 (ship-pipeline invariant; loop-machinery changes should default to `auto_merge: false`). (discovered 2026-07-29 during PR #1734 fence-parity-guard)
**Status (2026-09-06):** ✅ Implemented — took direction (a): `/post-plan` Phase 6 Step 3 now captures the markers an earlier phase wrote (`Depends-on:`, plus the `<!-- no-adr: -->` / `<!-- no-refactor-tests: -->` bypass comments read by `bin/adr-check` and `bin/refactor-flag`), re-emits them at the top of the rewritten body, then verifies and self-heals via `gh pr edit --body-file`. Guarded by executable cases in `bin/test-postplan-arm-conditions`. Directions (b) and (c) were not taken — arm condition (6) and where Phase 1 writes the marker are unchanged.

---

### L46 Queued matrix-less plan with non-canonical `impl_model:` alias slips all pre-queue gates; runner disposes on first nightly run

**class:** A plan in the automouse queue declares a non-canonical `impl_model:` alias (e.g., `sonnet-4-6`) that slips through `bin/automouse/queue`'s add-time backstop (which calls `plan-model-consistency`, which skips matrix-less plans at its matrix-presence guard) and through `bin/check-plan` gate `[13]` (same skip), so the bad alias is not caught until `bin/automouse/run` disposes the plan to `skipped/` on the first nightly run — wasting one nightly slot.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `~/claude-plans/pr-ready-dm-and-push-retry.md` line 2: `impl_model: sonnet-4-6` (not a canonical alias; resolves to Opus silently pre-PR, rejected post-merge) | yes | yes | fixed this pass (changed to `impl_model: sonnet`) |

**prevention_ladder:**

- rung 0 — partially covered: `bin/automouse/run` Phase 4 disposal block (added by this PR) catches a bad alias at runtime and disposes with a report. Not sufficient: burns one nightly slot per occurrence.
- rung 1 — extend `bin/automouse/queue add` to call `bin/lib/plan-model-tier` (not `plan-model-consistency`, which skips matrix-less plans) and reject a nonzero exit at queue-add time. This is the landing rung: it catches the alias before the plan enters the queue, at zero slot cost.
- rung 2 — a rule doc alone is insufficient: the validator does not run during plan authoring.
- rung 3 — not applicable (PHPStan cannot gate plan-file parsing).
- rung 4 — not applicable (CI has no plan-corpus sweep over `~/claude-plans/`).
- rung 5 — not warranted.

Landing rung: 1 (extend `bin/automouse/queue add` validation to cover all plans, not just matrix-bearing ones).

**artifact destination:** `bin/automouse/queue` (in-repo)

**provenance:** (discovered 2026-09-04 during #1968)

**Status (2026-09-05):** ✅ Implemented — `bin/automouse/queue add` now validates `impl_model:` presence and validity for **every** plan, before and independently of the `bin/lib/plan-model-consistency` call, so a matrix-less plan no longer reaches the queue with the field unchecked. Landed rung 1, with one deviation from the ladder as written: the check calls `bin/lib/plan-model-tier` rather than `bin/lib/plan-impl-model`, because `plan-impl-model` resolves an absent or unrecognized field to `claude-opus-5` and so cannot distinguish "no marker" from a deliberate `impl_model: opus`. `plan-model-tier` returns `absent` for the former and exits nonzero for the latter — the discrimination the rung needs. Covered by `bin/test-automouse-queue` rows 21-24 and 26, with row 15 tightened to pin the check ordering.
### L33 CLI entrypoints accept unknown flags silently; no static rule enforces argv option allowlisting
**Location:** `ibl5/phpstan-rules/` — no rule inspects `$argv` / `getopt()` option parsing (verified 2026-08-09: zero rule files mention either). The one hardened entrypoint is `ibl5/scripts/bug-pipeline/transition.php`, allowlisted by hand in PR #1654.
**Problem (was):** A CLI entrypoint that ignores an unrecognized option runs with the caller's intent silently dropped — a typo'd or renamed flag produces a successful-looking run that did something else. It has now recurred three times (#1354, #1496, #1654), each fixed one entrypoint at a time, which is the signature of a class that needs a mechanical check rather than another point fix.
**Suggested direction (was):** A PHPStan rule over argv/`getopt()` option parsing in CLI entrypoints, asserting that an unrecognized option is rejected rather than ignored. Extend the existing `ibl5/phpstan-rules/` set — this is Rung 1 on the `/post-plan` Phase 9 ladder, and the class registry routes it there.
**Interim backstop (2026-08-08):** PR #1668 added a forced integration-verification trigger — a plan that adds a CLI flag or flag-parsing branch must carry a row asserting the rejected form fails loudly (`.claude/review-shared/_plan-verification.md` § Forced integration-verification trigger). That is plan-time, so it catches *new* flags only; it does not sweep the entrypoints that already exist.
**Risk if untouched (was):** A fourth occurrence, and the existing unhardened entrypoints stay unswept — the backstop above never looks at them.
**Provenance (2026-08-09):** Surfaced by the `## Class registry` seed row for this class, which routed it to Rung 1 and recorded it as queued; nothing was in fact queued. This entry is that queue.
**Status (2026-08-30):** ✅ Implemented — `BanUnknownCliOptionRule` added in `ibl5/phpstan-rules/`; 2 existing callers baselined as temporary. Rule fires on all `getopt()` calls; developers suppress with `@phpstan-ignore ibl.unknownCliOption` after implementing the guard. PR #2042.
---
### L43 Autonomous-loop doc-fix PR body contains stale claims and inconsistent ADR authoring format after post-review commit
*(discovered 2026-09-02 during #2059)*
**class:** a PR body hand-authored by an autonomous-loop run that contains specific version claims, figure values, or scope descriptions which become inaccurate when a post-review commit changes the referenced content without triggering a body update.
**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | PR #2059 body — bullet 2 claimed gitleaks workflow v2→v3 upgrade; no workflow file was in the diff | yes | yes | fixed this pass (via `gh pr edit`) |
| 2 | PR #2059 body — bullet 3 said "147 → 17 call sites"; authoritative count per `ibl5/phpstan-baseline.neon` is 134 sites across 17 files | yes | yes | fixed this pass (via `gh pr edit`) |
| 3 | PR #2059 body — Manual Testing said "verified by automated tests"; all CI test jobs skipped by docs-only path filter | yes | yes | fixed this pass (via `gh pr edit`) |
| 4 | PR #2059 — ADR-0026 Threshold Rationale was an in-place rewrite, inconsistent with addendum format used in ADR-0034 and ADR-0077 | yes | yes | fixed this pass (ADR restored to original + addendum section added) |
`prevention_ladder:`
- **rung 0 — already covered by an existing gate?** No — `bin/check-docs` validates ADR frontmatter and doc content vs. reality, but no gate re-validates hand-authored PR body claims against the final diff or authoritative source files after a post-review commit lands.
- **rung 1 — extend an existing gate?** Partial landing rung. `bin/check-docs` could be extended to parse known structured claim patterns (version strings, numeric baselines cited as `X → Y`) from PR bodies and verify them against the diff or a declared source file. However, free-form prose patterns are hard to parse reliably and this would add significant false-positive risk. Better as a rule doc.
- **rung 2 — a rule doc under `.claude/rules/`?** **Landing rung.** Add a companion note to `.claude/rules/auto-commit.md` or a new `.claude/rules/pr-body-claims.md` (example) rule requiring: (a) any autonomous-loop run that authors a PR body with specific version strings or numeric figures must cite the authoritative source file inline; (b) any post-review commit that modifies a file mentioned in the PR body Summary must trigger a body re-review before the commit is pushed. This addresses both the stale-claim defect and the ADR format inconsistency.
- **rungs 3–5 — PHPStan rule / CI gate / hook?** Not applicable — the surface is PR body text, not PHP code, and a CI gate cannot validate semantic accuracy of free-form prose against an authoritative source at PR-check time.
`artifact destination:` `.claude/rules/pr-body-claims.md` (example) — or an addendum to `.claude/rules/auto-commit.md`. Ships in a repo worktree as a normal PR.
`provenance:` (discovered 2026-09-02 during #2059)
**Status (2026-09-05):** ✅ Implemented (#2131) — `.claude/rules/pr-body-claims.md` landed (rung 2 of L43 prevention ladder).
