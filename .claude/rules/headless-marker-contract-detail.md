---
description: Companion to headless-marker-contract.md — attaches only when bin/plan-now or bin/test-plan-now is touched, never on a general bin/ edit. Contains the historical rationale for the headless marker rule and bin/plan-now draft-recovery fallback internals (Leg A and Leg B). Read when debugging a missing-marker failure or modifying bin/plan-now recovery logic.
last_verified: 2026-08-09
paths:
  - "bin/plan-now"
  - "bin/test-plan-now"
---

Companion to `.claude/rules/headless-marker-contract.md`. Contains the historical rationale and `bin/plan-now` draft-recovery internals. Scoped to `bin/plan-now` and `bin/test-plan-now` — the parent stub carries everything a new-runner author needs.
## Why it's worth a rule

`bin/plan-now` shipped with exactly that instruction. Every run it ever made logged
`RESULT unconfirmed` and never reached its `check-plan` gate — including a 2026-07-28
run that took 2855s, exited 0, and wrote a perfectly good plan, on a 1294-byte
transcript containing only its closing message. The failure is fail-safe (never a wrong
green) and therefore easy to mistake for the model misbehaving.

The other runners are already correct, by different routes — check the one you are
editing against the table above rather than assuming:

- `bin/bug-pipeline-tick` — `--output-format json`, reads `.result` (that field *is*
  the final message); the hunter uses `stream-json` and has its agent write results to
  a file rather than stdout.
- `bin/automouse/run` — `stream-json --verbose` through `bin/lib/automouse-stream-filter.sh`;
  it *passes* `PLAN_FILE=` into the prompt as input and parses nothing back out.
- `bin/post-plan-now` — passes an optional `--plan <abs-path>` through to the harness (which
  owns variant resolution in `harness/planfile.py`) and into the fallback prompt; `bin/docfix-run`
  derives the plan path from the branch slug in shell. Neither parses model output.

`bin/test-plan-now` pins all of this for `plan-now` — the decorated shapes it accepts,
and the three ways a marker still lands `unconfirmed`. It stubs the model call through
`PLAN_NOW_CLAUDE` / `PLAN_NOW_PLANS_DIR` and runs the runner directly, so it costs no
tokens and spawns no launchd job. Copy that shape if a second runner ever parses stdout.

## Draft recovery fallback

A missing marker is fail-safe but not free: the run's work still exists. Since
2026-07-29 `bin/plan-now` tries to recover it before reporting `unconfirmed`, along two
independent legs, because the arm is reached two distinct ways:

- **Leg A — the architect was killed at the background-wait ceiling.** A complete draft
  is orphaned in `.drafts/` and no plan file was ever written.
- **Leg B — the run succeeded and the model omitted the line.** Plan written, draft
  consumed, full completion report printed. There is no output-side fix: text-mode `-p`
  discards everything before the final message, so a marker left out of *that* message
  is unrecoverable from stdout. It is a compliance failure, not truncation, and no coda
  wording closes it.

Leg A, in detail. When the model exits 0 and no usable `PLAN_FILE:` line was printed, the runner reads
this run's slug back out of its own prompt (the `- Branch slug: ` line the drafting
session writes) and looks for exactly one file: `<plans-dir>/.drafts/<slug>.draft.md`,
modified after the run started. Both conditions are required — the slug match keeps a
concurrent `plan-now` run's draft out, and the mtime keeps an abandoned draft from a
previous run of the *same* slug out. If either fails, nothing is adopted; the run
prints the unchanged `unconfirmed` verdict, plus a pointer if some unattributable
fresh draft is sitting there.

A recovered draft is promoted the way `.claude/skills/plan/SKILL.md` Step 5 would have
promoted it: the `<!-- PLAN OUTLINE ... -->` scaffold is dropped, and a frontmatter
block the scaffold shape stranded below the title is hoisted to line 1 — required,
because `bin/check-plan` parses frontmatter anchored at line 1 and would otherwise
reject every recovered plan for a missing `impl_model`. An existing plan file is never
overwritten. `bin/check-plan` then runs on the result exactly as it would on an
identified plan, and only a pass reaches the queue.

Leg B runs only when leg A adopted nothing. It never reads model output. Its identity
test is **membership in this run's own before/after diff of the plans directory** —
not an mtime comparison, because a file newer than the run's start may equally be a
*concurrent* run's plan, which is exactly the wrong attribution recorded in the
rejected-alternatives block at the top of `bin/plan-now`. Within that set the basename
must be this run's prompt slug as `<slug>.md` or `<slug>-<n>.md` (`/plan`'s own loop
writes `-2`, `-3`, … when `check-plan` rejects a draft; a bare `<slug>*` glob would
also match a peer slug that merely prefixes this one), and the **newest** match wins.
Newest-wins is load-bearing rather than a tiebreaker: a real run left a superseded
5-phase `<slug>.md` beside the live 7-phase `<slug>-3.md`, and the superseded file
*passes* `check-plan` — the gate cannot break that tie, only recency can. Nothing is
promoted or deleted on this leg; the file is adopted where it already sits.

The verdict says `RESULT degraded (recovered)` on success and
`RESULT degraded (recovery attempted)` when the promoted plan fails `check-plan` —
both distinct from `RESULT ok` and from `RESULT unconfirmed`, so a log line still
tells you which of the three happened without reading the transcript. Recovery
supplies only the *identification* leg of the queue gate; clean exit and a passing
`bin/check-plan` are still required and still unchanged.

This is a net, not a licence. The prompt coda tells the run never to background a
sub-agent in the first place, and deliberately does not mention that this net exists.
`bin/test-plan-now` pins all eleven branches — five on leg A, five on leg B, plus the
regression that a valid marker still wins outright and never enters recovery at all
(that one belongs to neither leg, which is the point of it). Five of the eleven are
negatives, and the most important is a slugless prompt: both legs no-op for it, that is
the *majority* of real prompts, and its verdict must stay byte-identical to the
pre-recovery one.
