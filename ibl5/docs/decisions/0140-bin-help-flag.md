---
description: Every user-facing script in bin/ and bin/automouse/ answers --help on stdout with exit 0 before any side effect, and bin/test-bin-help enforces it in CI by running each script with stubbed tools.
last_verified: 2026-10-05
---

> This ADR was drafted by the post-plan harness for this PR. A human must review and approve it before merging.

# ADR-0140: Every `bin/` Script Answers `--help` Before Any Side Effect

**Status:** Accepted
**Date:** 2026-09-28
**Deciders:** post-plan harness (auto-draft)

## Context

`bin/adr-check` flagged one decision-trigger surface on this branch: `bin/test-bin-help`, a new developer tool of more than 50 lines. It exists to hold a convention the rest of the diff applies across roughly seventy scripts. Before this change, asking a `bin/` script for help was unsafe to guess at. Many scripts had no `--help` branch at all and treated the flag as a positional argument. Others printed usage only after they had already sourced helpers, called `git`, or resolved the repo root. `bin/plan-index` printed its help on `--help` and then exited 1, and `bin/watch-run --help` exited 3 through its `usage()` function. A reader or an agent who wanted to learn a script's arguments had to open the source. Running it with `--help` could start a DB sync, a launchd job, or a headless `claude` session.

## Decision

Every executable script with a shebang in `bin/` and `bin/automouse/` handles `--help` (and `-h` where that letter is free) as its first action. The check sits at the top of the script, ahead of any `source`, `git`, `cd`, or network call. It prints usage to stdout and exits 0. Scripts whose header comment already documents usage may print that comment with `sed` or `awk` instead of a heredoc. Three scripts accept only `--help`: `bin/regen-schema-dump` (where `-h` means host), `bin/post-plan-fail-dm`, and `bin/vr-changed-coverage`. `bin/plan-index` and `bin/watch-run` now exit 0 on `--help`. `bin/plan-index` still exits 1, with usage on stderr, on a wrong argument count, and `bin/watch-run` still exits 3 on a usage error. The convention is written down in `bin/README.md`. Enforcement is `bin/test-bin-help`, run as a step in `.github/workflows/tests.yml`. It copies `bin/` to a temp dir, sets `HOME` to a temp dir, and puts stubs first on `PATH` for every tool that could reach the network, the repo, or a paid session (`git`, `gh`, `docker`, `mysql`, `ssh`, `launchctl`, `claude`, and others). Each stub logs its name and exits 1. For each script the test asserts exit 0, non-empty stdout, a `Usage` line in stdout (case-insensitive), and an empty stub log. `bin/test-*` harnesses are out of scope.

## Alternatives Considered

- **README note only.** Document the convention in `bin/README.md` and add no check. Rejected because: a norm with no check drifts. New scripts would ship without the branch, and a misplaced branch that runs after a `git` call would look correct in review.
- **Static grep.** Search each script for the string `--help`. Rejected because: the string's presence proves nothing about order. The failure this closes is a help branch that runs after a side effect, and only executing the script with stubbed tools shows that.
- **Shared helper.** Put the check in a file under `bin/lib/` that each script sources. Rejected because: the help text differs per script, so the helper saves almost nothing. Sourcing it also requires resolving the script's own directory first, which moves work ahead of the help check.
- **Extend an existing gate.** Rejected because: no current `bin/check-*` script or test harness iterates every script in `bin/`. Bolting this onto one would give it a second responsibility, which `.claude/rules/meta-tooling-bar.md` asks new tooling to avoid.

## Consequences

- Positive: `bin/<script> --help` is safe to run on any script in scope. Agents and humans can learn a script's arguments without reading its source.
- Positive: a new script without a help branch, or with one placed after a side effect, turns CI red.
- Positive: `bin/plan-index --help` and `bin/watch-run --help` now exit 0, so callers can tell a help request apart from a usage error.
- Negative: about seventy scripts now carry near-identical boilerplate at the top.
- Negative: the test checks only that help prints and exits cleanly. A help text that no longer matches the script's real argument parsing still passes.
- Negative: the stub list is finite. A side effect through a tool the list omits, such as `python3` or `bun`, goes unseen.
- Negative: CI has no `bun`, so the test skips Bun-shebang scripts there. They are covered only on a developer machine.
- Negative: the per-script timeout applies only when `timeout` or `gtimeout` exists on the host.

## References

- `bin/test-bin-help`: the harness flagged by `bin/adr-check`, with its stub list and scope filter.
- `bin/README.md`: the section that records the convention for new scripts.
- `.github/workflows/tests.yml`: the CI step that runs `bin/test-bin-help`.
- `bin/plan-index` and `bin/watch-run`: the two scripts whose exit code on `--help` changed (from 1 and from 3, to 0).
- `bin/regen-schema-dump`: the script where `-h` keeps its host meaning.
- `.claude/rules/bin-help-span-and-secondary-assertions.md`: the earlier rule on help text read from header comments.
- `.claude/rules/meta-tooling-bar.md`: the extend-before-add bar applied to this new tool.
- `ibl5/docs/decisions/README.md`: the decision-record policy `bin/adr-check` enforces.
