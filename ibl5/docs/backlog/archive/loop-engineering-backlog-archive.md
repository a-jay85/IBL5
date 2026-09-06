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

### L40 Compiled post-plan harness crashes on any PR containing a binary file (`git diff` decoded as strict UTF-8)
*(discovered 2026-09-01 while shipping #2056, whose diff contained the binary artifact `ibl5/data/finals2008-g4.rec`)*

**Location:** `tools/postplan-harness/harness/adapters/gitad.py:18` — `_run()` calls `subprocess.run([...], capture_output=True, text=True)` with no `errors=` argument. The crash surfaces at `gitad.py:42` (`diff_vs_base`), which shells out to `git diff <merge-base>`. Same unguarded `text=True` at six other call sites: `gitad.py:85`, `gitad.py:88`, `ciwatch.py:71`, `llm.py:68`, `ghad.py:116`, `verify.py:42`. (`llm.py:58` is the only place in the harness that passes `errors=` at all.) Regression-test host: `tools/postplan-harness/tests/test_gitad_live.py`.

**Problem:** `text=True` decodes the child's stdout as strict UTF-8. `git diff` emits raw bytes for a binary file, so any diff touching one raises `UnicodeDecodeError` and kills the compiled harness *before any phase runs*. Observed 2026-09-01 at 18:19: `UnicodeDecodeError: 'utf-8' codec can't decode byte 0x9e in position 23462: invalid start byte`, traceback `gitad.py:42 diff_vs_base` → `gitad.py:17 _run`. This is not a corner case — it fires on **every** PR whose diff contains a binary file, which is the entire boxscore-restore class of work (`.rec` artifacts) plus any image, font, or fixture blob.

The failure is quiet because the two-engine design absorbs it: the harness exits non-zero, `should_fallback()` returns true for any rc except 0 and 3, and `bin/post-plan-now` silently hands off to the slower Sonnet `/post-plan` skill. Work still completes, so nothing alarms — but the run costs ~40 min instead of a few, and the fallback agent reasons without the harness's guardrails. In #2056 it invented a `@codeCoverageIgnore` annotation with zero precedent anywhere in the repo, which did not clear the coverage gate anyway (83.98% → 84.24%, minimum 84.46%); the fix had to be reverted by hand and replaced with the repo's actual precedent (lowering `coverage-baseline.json`, as in #2001 and #2022).

**Suggested direction:** Add `errors="replace"` to `_run()` in `gitad.py` — mojibake in a diff string the harness only pattern-matches over is strictly better than a crash. Then sweep the other six `text=True` sites for the same guard, since `git log`, `gh` output, and CI logs can all carry non-UTF-8 bytes. Regression test in `tests/test_gitad_live.py`: create a temp repo, commit a file containing byte `0x9e`, and assert `diff_vs_base()` returns a string rather than raising.

**Risk if untouched:** Every binary-touching PR silently loses the fast, guardrailed engine and falls through to an unconstrained agent — the expensive path, taken invisibly, with lower-quality output. Because the fallback usually *succeeds*, there is no signal that the primary engine has been dead for that whole class of PR.

**Status (2026-09-01):** ⬜ Open — 🟥 (self-contained fix in a dev-tooling adapter; no user-facing surface, no gate weakened).

**Resolved (2026-09-05):** PR #2112 adds `errors="replace"` to all three `text=True` sites in `gitad.py`; regression pin wired into CI (`python-tests.yml`).

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

### L50 `bin/pr-cycle` logs gate nominees as "excluded this run" but then orders and readies them

**class:** A log line that states a disposition the code does not apply — the worker prints `excluded this run (gate nominee, unjudged)` for every `### #N` nominee in `bin/pr-attack --gate-candidates` output, then calls `bin/pr-attack --work <WORK> --gate-edges /dev/null`, which is the *judged-empty* form: every nominee is re-admitted as orderable with no gate edges. The first live run (2026-09-05, `/tmp/pr-cycle-20260905-023625-80966.log`) printed seven "excluded" lines and then readied #2108, the first one on that list.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/pr-cycle` — the `excluded this run (gate nominee, unjudged)` echo inside the nominee loop, followed by the `--gate-edges /dev/null` re-run | yes | fixed — log now matches behavior | ✅ Implemented |

**Why it matters:** The plan (`~/claude-plans/pr-cycle-driver.md`) said nominees are excluded for the run; the implementation orders them unjudged. Either is a defensible overnight policy — arming stays fail-closed in `bin/pr-triage`, and a gate PR merged out of order lands the affected PR in BLOCKED-CHECK for the human rather than merging it wrong. But the log must not lie: a reader debugging a surprising merge order will trust "excluded" and look elsewhere.

**Fix (pick one, S):**
- Reword to `ordered with no gate edges (gate nominee, unjudged)` and say so in the usage header — matches what the code does today; or
- Actually exclude: pass each nominee to `bin/pr-attack` as excluded (or filter them from `tried`/pick) so the log and behavior agree, at the cost of fewer merges per night.

The static-guard case in `bin/test-pr-cycle` should pin whichever wording lands, so the two cannot drift again.

**Status (2026-09-05):** ✅ Implemented — message-only reword (the first of the two fix options listed above); behavior unchanged. `bin/pr-cycle` now prints `ordered with no gate edges (gate nominee, judged-empty)` and the usage header carries a `Gate nominees:` block stating that `--gate-edges /dev/null` re-admits every nominee. Behavior is unchanged and asserted so: `bin/test-pr-cycle` case 6 pins the second pr-attack call as `--work abc123 --gate-edges /dev/null` and `calls ready == 3`. Wording note: shipped `judged-empty` rather than this entry's suggested `unjudged`, because `/dev/null` *is* pr-attack's documented judged-empty form (`bin/pr-attack:66`) — "unjudged" would leave a smaller version of the same lie in the parenthetical. The "static-guard case should pin whichever wording lands" suggestion is satisfied more strongly by runtime assertions: case 6 now greps the rendered line for the new wording and asserts `excluded this run` is absent from the driver's actual output, which a source-level static guard cannot do; case 1 pins the header note via `--help`. No new static guard was added.

**provenance:** (discovered 2026-09-05 during the first live `bin/pr-cycle --go` run, right after #2081 merged)
