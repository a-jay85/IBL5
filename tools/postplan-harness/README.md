# postplan-harness — compiled `/post-plan`

A specialized, deterministic harness for the IBL5 `/post-plan` ship pipeline —
the most frequently repeated workflow in this machine's Claude Code history
(323 full runs in 30 days, ~9M gross tokens and ~108 model turns per run).
Historically, a general-purpose agent re-read the 11-phase skill and re-decided
every mechanical step turn-by-turn. This harness **compiles** the stable
procedure into code and keeps the LLM only where judgment is irreducible.

**Status: INSTALLED (2026-07-16, explicit approval).** `./run isolated
<worktree> --live` is the live mode: it pushes to origin, executes the six
allowlisted `gh` mutations (each still audited to `out/actions.jsonl` with
`executed: true`), and watches CI. `bin/post-plan-now` in IBL5 invokes it,
falling back to the Sonnet `/post-plan` skill session if the harness fails.
Without `--live`, every would-be side effect remains a typed intent record.

## What is code vs. what is still a model

| Owned by code (deterministic) | Retained LLM calls (bounded, typed, validated) |
|---|---|
| Phase sequencing + terminal states | `pr-copy` — commit/PR title + summary (haiku) |
| Phase 3 diff classification (all flags) | `review-agent-a/b/d` — code review judgment (sonnet) |
| Phase 5 verify aggregation | `security-audit` — security judgment (haiku) |
| Phase 5.0 plan→test/file conformance | `score-findings` — rubric confidence scoring (haiku) |
| All ten Phase 6.5 arming conditions | `safety-verdict` — condition (9), **add-only** holds (haiku) |
| CI-watch interpretation | `manual-classify` — plan-blind manual-step triage (haiku) |
| Side-effect gating + audit log | `retrospective` — save-a-lesson-or-not (haiku) |

Every retained call: single turn, no tools, byte-capped input packet, JSON
output validated by `harness/schemas.py`, one bounded retry, usage recorded.
Invalid output is a typed failure — never silently accepted.

## Layout

```
runner.py                 phase sequencer (CLI)
run                       entry wrapper: replay | demo | isolated | test
harness/
  classify.py             Phase 3 port (flags, filtered diff, module extraction)
  planfile.py             plan location + frontmatter/matrix/Critical-Files parsing
  conformance.py          Phase 5.0 MISSING/MISSING-FILE detection
  armable.py              ten arming conditions (pure functions, fail-closed)
  review.py               Phase 4 launch gates + bounded review/security/scoring calls
  ciwatch.py              Phase 7 outcome interpretation
  llm_calls.py            prompt builders for the non-review retained calls
  schemas.py              typed validation for every LLM output
  state.py                Classification / ArmDecision / RunResult / UsageLedger
  adapters/
    llm.py                claude -p adapter (single-turn, --tools "", usage-recording)
    ghad.py               RecordingGh — mutations become out/actions.jsonl intents
    gitad.py              LiveGit (push disabled by default) / ReplayGit (fixtures)
    verify.py             LiveVerify (phpunit/phpstan/go) / ReplayVerify (recorded outputs)
tests/                    27 tests (pure logic + live-git tempdir + full replay runs)
fixtures/scenarios/<slug>/fixture.json   point-in-time inputs from 8 historical runs
bench/benchmark.py        Method-A replay benchmark + parity gates
report/report.html        self-contained benchmark report
out/                      per-run result.json, audit.log, actions.jsonl
```

## Run it

```bash
./run test                          # offline test suite (no LLM calls)
./run demo                          # offline demo: bundled fixture + canned LLM, $0
./run replay fixtures/scenarios/request-event-logging/fixture.json
                                    # historical replay with LIVE bounded LLM calls (~$0.10-0.25)
./run isolated <worktree-path>      # live git + live verify on a real worktree;
                                    # gh mutations record-only; push disabled
./run isolated <worktree-path> --live
                                    # INSTALLED mode: push origin, execute
                                    # allowlisted gh mutations, watch CI
python3 bench/benchmark.py all      # full benchmark (see report/report.html)
```

`replay` is the **benchmark/development** command (point-in-time historical
inputs). `isolated` is the closest thing to normal use: point it at a real
worktree and it classifies, reviews, verifies, decides arming, and writes the
intent log — without touching GitHub.

## Safety model

- **No mutation escape hatch.** `RecordingGh` has no code path that executes a
  mutating `gh` command; `LiveGit.push()` raises `push-disabled` unless an
  explicit remote is injected. `LiveGh` (live mode) can only reach the six
  fixed mutation commands its methods construct, and audits each execution.
- **Fail-closed arming.** Indeterminate inputs (UNKNOWN dep state, missing
  clearance section, degraded fixtures) block arming; the LLM safety verdict
  can only ADD holds, never release one.
- **Human gates preserved.** `feat:` floor, `auto_merge: false`, golden-file
  and manual-testing holds all land in `SHIPPED_HELD` — exactly the PRs a
  human had to merge before.

## Installation (executed 2026-07-16 with explicit approval)

1. ✅ `LiveGh` (`harness/adapters/ghad.py`) executes the six allowlisted
   mutations behind `--live` — the allowlist is the method set; there is no
   generic gh escape hatch. Merge uses `--squash --auto` (no `--delete-branch`:
   benign-error in multi-worktree clones, and it permanently closes stacked
   child PRs).
2. ✅ `LiveGit(push_remote="origin")` enabled in `--live` only.
3. ✅ IBL5 `bin/post-plan-now` invokes `./run isolated <worktree> --live`
   under launchd, falling back to the Sonnet skill session on harness failure
   (or when the harness is absent — other machines keep working). Escape
   hatch: `POST_PLAN_SKILL=1 bin/post-plan-now` forces the skill path.
4. ✅ Phase 10 (preview) and Phase 9 memory WRITES stay interactive-side; the
   harness records a Phase 9 intent only.

Known scope reductions vs the full skill (accepted at install): no backlog
housekeeping, no worktree teardown, no E2E verify track (Docker stack), no
review Agent C (prior-PR feedback). The skill fallback retains all of them.
