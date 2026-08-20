---
description: Watch a detached launchd run with bin/watch-run, keyed on the job's label disappearing — never `tail -f` its log.
last_verified: 2026-08-20
---

# ADR-0107: A Self-Terminating Watcher for Detached Run Logs

**Status:** Accepted
**Date:** 2026-08-20
**Deciders:** ajaynicolas

## Context

`bin/post-plan-now`, `bin/plan-now`, and `bin/docfix-run` each fire a detached launchd one-shot and print the path of its log. Each of those logs is written by a headless `claude -p` session or by the compiled post-plan harness, and both buffer their output and flush it only at process exit. Every agent that wanted progress reached for the obvious `tail -f <log> | grep -E "…"` — which is silent for the whole run *and* silent forever afterwards, because `tail -f` never exits. "Finished cleanly" and "still working" are then indistinguishable, so the watcher always runs to its timeout. On 2026-08-20 one such watcher sat at 22 minutes reporting "No output available" for a job that had already merged its PR; a 2026-08-11 instance (PR #1842) grepped an alternation that matched *zero* lines of the finished log. A memory note telling agents "never arm a Monitor on this log" was the standing workaround, and it did not hold.

## Decision

Watch a detached run with **`bin/watch-run --log <log> --label <launchd label>`**, and never with `tail -f`. Its completion signal is the **launchd label disappearing** — every one of the three producers ends its launchd command with `launchctl bootout gui/$(id -u)/<label>`, so the job removes itself on *every* branch: success, typed failure, the rc=3 fail-closed sentinel, and hard crash. All three producers now print that exact command in place of their old `tail -f` hint, because an agent copies whatever the launcher prints. Independently, `tools/postplan-harness/runner.py` emits a one-line `RESULT: …` verdict as the **first** line of its output, with a distinct branch for each `TerminalState` plus the rebase-conflict sentinel, each naming its outcome in words a watcher actually greps for (`RESULT`, `complete`, `FAILED`, `ERROR`, `conflict`, `PR #`, `pull/`).

## Alternatives Considered

- **A log-line sentinel as the terminator** — have every producer write a final `RUN-COMPLETE:` token and exit the watcher on it. Rejected because: `bin/post-plan-now`'s job runs the harness and then, on a generic harness failure, escalates to a `/post-plan` skill session in the *same* job writing the *same* log, so the harness's own terminal line lands while a second engine is still working; the watcher would report a failure the job was in the middle of recovering from. (`RESULT:` is still accepted as a terminator when no `--label` is given, since nothing better exists there.)
- **Widen the grep alternation agents write by hand** — teach the words that do appear (`terminal=`, `armed=`). Rejected because: it fixes only the content half. `tail -f` still never exits, so the monitor still hangs after a match — and it leaves the pattern for each agent to re-derive correctly every time.
- **Keep the memory-note prohibition** — "never arm a Monitor on this log; `head -1` it afterwards." Rejected because: it costs context on every session, relies on an agent recalling a rule at exactly the wrong moment, and it demonstrably failed twice. Automate, do not remember.
- **Emit the terminator from the launchd `$CMD` wrapper** — engine-agnostic, covers the skill fallback too. Rejected because: that string crosses both an XML escape and a `/bin/bash -lc` re-parse, a boundary that has already caused two silent-corruption incidents in `bin/post-plan-now`; label-gone termination makes the wrapper edit unnecessary.

## Consequences

- Positive: a watcher on any of the three producers now terminates on its own, on every outcome including a crash that writes nothing recognisable — `bin/watch-run` falls back to printing the log tail rather than exiting silently.
- Positive: `head -1 <log>` on a post-plan run is now a complete verdict, including the PR number and URL, which the log previously never contained.
- Positive: the failure branches are covered explicitly. A verdict line that names only success is the same defect as no verdict line, because silence still reads as "running".
- Negative: one more `bin/` script to maintain. It clears the `meta-tooling-bar.md` extend-before-add bar — `bin/watch-automouse-plan` is a different surface (it polls an automouse queue disposition and delivers by Discord DM), no existing script owns "watch a launchd job's log", and no rule doc can make `tail -f` exit.
- Negative: `bin/watch-run` polls (default 5s) rather than following the file, so output is chunked rather than instantaneous. That is the correct trade here: the producers flush at exit anyway, so there is no stream to follow.

## References

- `bin/watch-run` — the watcher; its header comment carries the full rationale.
- `bin/post-plan-now`, `bin/plan-now`, `bin/docfix-run` — the three producers whose printed hint now names it.
- `tools/postplan-harness/runner.py` — `verdict_line()` and `_pull_url_base()`; the `RESULT:` line is printed first, before the existing stats lines.
- `tools/postplan-harness/tests/test_verdict_line.py` — pins one case per terminal branch, and that every failure branch names a failure word.
- `.claude/rules/work-triage.md` § Execution routing: repeat-polling is a spend bug — "name the completion signal before writing the watcher" is the rule this ADR discharges for these three producers.
- `.claude/rules/meta-tooling-bar.md` — the extend-before-add bar cleared above.
