---
description: Headless `claude -p` runners — where a machine-parsed marker must be printed, because text-mode `-p` emits only the model's final message. Path-scoped to bin/**, loads only when authoring or editing a runner.
last_verified: 2026-07-28
paths: "bin/**"
---

# Headless Marker Contract

Several `bin/` scripts fire a headless `claude -p` run and then read a machine-readable
marker back out of what the run printed — `bin/plan-now` reads `PLAN_FILE: <abs path>`
to attribute its `bin/check-plan` verdict to the right plan.

## The invariant

**`claude -p` with the default `--output-format text` emits ONLY the model's final
assistant message.** Everything the model prints earlier in the run — however loudly,
however early — never reaches the runner's stdout.

So a runner that scrapes a marker out of that stdout has exactly two correct shapes:

| Shape | Where the marker goes |
|-------|----------------------|
| Default text mode | The marker MUST be on the **last line of the model's final message**. Say so in the prompt, in those words. |
| `--output-format stream-json --verbose` (or `json`) | Anywhere — the full event stream is captured. Costs a JSON-parsing step and a non-human-readable transcript. |

Anything else is a silent failure. Asking for the marker "as soon as you decide" or
"before you start writing" reads as helpful and is structurally unreachable: the model
complies, text mode discards it, and the runner reports a false negative forever.

**Instruct strictly, parse tolerantly.** Tell the model to print the line bare, then
accept it wrapped in bold, backticks, or a list marker anyway — the one real transcript
we have closed with `**Plan:** ` around a backticked path. "Print it bare" is prose
compliance, and prose compliance is what failed in the first place; do not make it the
only thing standing between a 45-minute run and `unconfirmed`. Tolerance belongs in the
*decoration*, never in the *validation*: `bin/plan-now` still requires the parsed path
to sit inside `$PLANS_DIR` and to exist, so a garbled marker costs a false negative and
never a false green.

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
- `bin/automouse-run` — `stream-json --verbose` through `bin/lib/automouse-stream-filter`;
  it *passes* `PLAN_FILE=` into the prompt as input and parses nothing back out.
- `bin/post-plan-now`, `bin/docfix-run` — derive the plan path from the branch slug in
  shell; no model output is parsed.

`bin/test-plan-now` pins all of this for `plan-now` — the decorated shapes it accepts,
and the three ways a marker still lands `unconfirmed`. It stubs the model call through
`PLAN_NOW_CLAUDE` / `PLAN_NOW_PLANS_DIR` and runs the runner directly, so it costs no
tokens and spawns no launchd job. Copy that shape if a second runner ever parses stdout.

## When you add a runner

Choosing text mode is usually right — the transcript stays readable and the log can
`cat` it. Just put the marker requirement in the final message, and make the prompt say
*why*, so the next editor does not "improve" it back to an early print.
