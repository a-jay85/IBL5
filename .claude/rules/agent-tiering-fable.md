---
description: Read-on-demand only (no auto-attach trigger) — when Fable is justified over Opus, and how to use it, effort per surface, how the /plan architect tier is judged. No user approval needed; state the justification in one line and spawn. Parent agent-tiering.md carries the resident one-line summary.
last_verified: 2026-11-10
paths: ".claude/rules/agent-tiering-fable.md"
---

Read-on-demand companion to `agent-tiering.md` § Tiers (Fable row).

## When Fable is justified

Default to Opus. Fable 5.2 costs 2× Opus 5.5 per token ($8/$40 vs $4/$20 per MTok, claude-api skill, checked 2026-11-10), so it must buy something Opus likely can't. Cache reads cost the same on both ($0.20 per MTok). Use Fable when **both** hold:

- **Opus is likely to get it wrong.** The task needs novel reasoning, an exhaustive negative proof ("X never happens anywhere"), or a verdict where Opus has a track record of confident wrong answers.
- **A wrong answer is expensive.** It would ship, land in an ADR or backlog, or need a redo loop (revert, regen goldens, rebaseline tests) that costs more than the 2×.

If Opus stalled at medium effort, rerun it at high first. On the 2026-10 review-seat run one Opus 5.5 high call cost $0.36 (one toolless review seat). The 30-day ledger mean to 2026-10-25 is $0.79 per Opus 5.5 call at session-default effort. Both sit well under a Fable rerun at 2× the per-token price.

If Opus at high still falls short, move to Fable at high. Do not raise Opus to `xhigh` or `max` first. Anthropic's model-choice guide (claude.dev, "Which Claude model should you build with") reports Fable 5.2 at `high` matching Opus 5.5 at `max` on FrontierCode 2.0 for about half the cost per task.

When the test passes, use Fable. Do not ask the user. Say one line before the spawn: what the task is, and which trait makes Opus risky. Spawn with `model: "fable"`. Claude cannot switch its own session model, so Fable work is always a sub-agent.

When the test fails, stay on Opus (or a lower tier). Most tasks fail it.

## Effort

`high` on every surface that takes the flag. `max` vs `high` is unmeasured. On the 2026-10 review-seat run (§ Review-seat measurement table below) the Opus max cell hit the time cap after 3 calls, one of them a 280 s timeout. Opus high was conclusive: H 0.33, $0.356 per call, 47.6 s per call.

- `bin/pr-review-now`: `EFFORT=high`, passed as `--effort`.
- `bin/plan-now`: `--effort high` on the orchestrator.
- `bin/post-plan-now`: `--effort high` on the Sonnet 5.5 skill engine.
- The three architect defs: `effort: high` frontmatter.
- Post-plan harness review seats: no flag. They inherit `effortLevel: medium` from `~/.claude/settings.json`. The 2026-11-04 run found no loss from `medium` on agent A (§ Medium vs high below).

The measurement covers one toolless code-review seat. Planning surfaces (`bin/plan-now`, the architect defs) stay at `high` by judgment, because no planning corpus exists.

Re-tune trigger: a re-run of `python3 -m harness.tier_measure` in which `opus-max` beats `opus-high` under the real-gap test justifies `max` for review seats. A tie keeps `high`.

## Review-seat measurement table

These cells measure one toolless review seat (agent A) on the 8-case code-review subset, at explicit `--effort`. The `fable-high` cell ran Fable 5.1. Fable 5.2 has not been measured here. Production review seats pass no `--effort` and inherit `effortLevel: medium`, so no cell in this 2026-10 table equals current production behavior. § Medium vs high below has those cells. Agents B and D, the security seat and the verifier seats were not measured.

- **R1.** Sonnet high tied Opus high on review (H 0.42 vs 0.33, S 2/6 each) at 2.1× lower cost per call.
- **R6.** Opus 5.5 is 2.0× Sonnet 5.5 per token (claude-api skill); `bin/measure-delegate-cost` measures about 1.63 to 1.7× effective; agent A measured 2.1× per call.

| cell | model | effort | bug runs | H | S | spread | FP | C (USD/call) | T (s/call) | verdict |
|---|---|---|---|---|---|---|---|---|---|---|
| sonnet-high | claude-sonnet-5-5 | high | 12 | 0.42 (5/12) | 2/6 | 0.17 | 0 | 0.168 | 30.6 | ok |
| opus-high | claude-opus-5-5 | high | 12 | 0.33 (4/12) | 2/6 | 0.00 | 0 | 0.356 | 47.6 | ok |
| opus-max | claude-opus-5-5 | max | 2 | 0.50 (1/2) | 1/6 | n/a | 0 | 0.659 | 212.0 | inconclusive: 2 completed bug runs < 8; error rate 33% > 25% |
| fable-high | claude-fable-5-1 | high | 5 | 0.40 (2/5) | 2/6 | 0.50 | 0 | 0.940 | 112.2 | inconclusive: 5 completed bug runs < 8; error rate 33% > 25% |

Total spend $19.51 ($19.13 measured plus $0.38 in probes), under the $25.00 ceiling. Each cell ran in its own driver process with its own budget share. Opus max stopped at the time cap after 3 calls. Fable high stopped after 9 calls with 3 errors. Prior data, not re-measured: [#2996](https://github.com/a-jay85/IBL5/pull/2996) (Sonnet 5.5 vs Opus 5.5 negative-proof A/B) and [#2977](https://github.com/a-jay85/IBL5/pull/2977) (Haiku 5.5 retier).

Reproduce: `python3 -m harness.tier_measure run --repo-root ../.. --out <path> --dry-run` from `tools/postplan-harness/`, then drop `--dry-run`. The $25 ceiling is a module constant.

## Medium vs high

The 2026-11-04 run measured what production runs: `sonnet-medium` is the default depth and `opus-medium` is deep depth. Both `high` cells were re-run the same day as controls. Same seat and corpus as the table above.

| cell | model | effort | bug runs | H | S | spread | FP | C (USD/call) | T (s/call) | verdict |
|---|---|---|---|---|---|---|---|---|---|---|
| sonnet-high | claude-sonnet-5-5 | high | 12 | 0.33 (4/12) | 2/6 | 0.00 | 0 | 0.161 | 28.0 | ok |
| sonnet-medium | claude-sonnet-5-5 | medium | 12 | 0.33 (4/12) | 2/6 | 0.00 | 0 | 0.134 | 11.8 | ok |
| opus-high | claude-opus-5-5 | high | 12 | 0.33 (4/12) | 2/6 | 0.00 | 0 | 0.374 | 63.2 | ok |
| opus-medium | claude-opus-5-5 | medium | 12 | 0.50 (6/12) | 3/6 | 0.00 | 0 | 0.333 | 43.1 | ok |

Total spend $14.03. Sonnet `medium` caught the same cases as Sonnet `high`. Opus `medium` caught one more case (`team-page-missing-pick-team`) in both runs. Six bug cases are too few to call `medium` better, so this shows only that `medium` lost nothing here. The `high` pins above are unchanged. Reproduce with `--drop-cell opus-max --drop-cell fable-high`.

## Pinned agent defs

A def whose frontmatter pins `model: fable` always runs on Fable. Today that is `plan-architect-xhigh` at high effort. The `/plan` architect tier is an Opus judgment that `bin/plan-tier-hint` makes with the test above. The orchestrator owns the final call (`/plan` Step 3).

A human may route any task to either architect. When `bin/plan-now` sees a declared tier that disagrees with the hint and one side is xhigh, it exits 4, and `--tier-ok "<reason>"` records the override. Every verdict lands in `~/.claude/logs/plan-tier-hint.log` with its reason, so a tier pick that went wrong can be traced.

The judge reads the `/plan` task line, the full brief with its tier-routing lines removed, the paths the brief names, and signal lines for a `feat:` title, a stated `auto_merge: false`, and a `bin/plan-now --implement` run. Burndown passes the backlog issue body as the brief. A design the brief already resolves counts as a mitigation.

## Asm-level static RE (JSB engine): Fable is the default

Asm-level static reverse-engineering always passes the test. This covers argument-binding derivations, NaN/FPU-flag paths, and encoded operands (pinning a `FUN_*`/`+0xNNN` operand, or a faithful-vs-divergent port verdict from `objdump` or decompile). Opus has shipped provably false conclusions here. On 2026-07-23 it misread J24 putback-3pt because Ghidra mis-numbered params (`param_6` was a `double` using two stack slots). Undoing that took an ADR, a golden regen, and test rebaselines. On 2026-07-07 a Fable session overturned an Opus-era "requires live debugging" premise on the foul divisor. The 2026-10 measurement corpus holds no RE cases, so it gives no basis to move this default.

- **Use Fable from the start** when the load-bearing step is the asm derivation.
- **If Opus drafted the RE, have a Fable sub-agent verify** the faithful-vs-divergent or NOT-A-LEVER verdict before it lands in the backlog, an ADR, or a port. `advisor()` cannot be pointed at Fable, so this must be a spawned sub-agent.
- **Measured work stays on Opus.** A/B sweeps, corpus statistics, and CI-floor construction are arithmetic. Fable's edge is reading asm. The exception covers only conclusions whose proof is a disassembly.
