---
description: CI runs the shell harness scripts in parallel through bin/run-shell-harnesses, with each harness still named on its own line in tests.yml.
last_verified: 2026-10-03
---

> This ADR was drafted by the post-plan harness for this PR. A human must review and approve it before merging.

# ADR-0150: Run CI Shell Harnesses in Parallel Through One Runner Script

**Status:** Accepted
**Date:** 2026-09-30
**Deciders:** post-plan harness (auto-draft)

## Context

The `Shell harness regression tests` job in `.github/workflows/tests.yml` ran each `bin/test-*` harness as its own workflow step. The list had grown to about 90 steps, and they ran one after another. The job's wall time was the sum of every harness, so one slow harness delayed every PR. Each new harness also cost two YAML lines of step boilerplate.

`bin/adr-check` flagged this PR because it adds a new developer tool of at least 50 lines under `bin/`: `bin/run-shell-harnesses`.

## Decision

Add `bin/run-shell-harnesses`, which takes a list of harness scripts and runs them with `xargs -P`. The worker count defaults to the CPU count and `-j N` overrides it. Each script writes to its own log under a `mktemp` directory. When a script finishes, the runner prints its log as one `::group::` block with PASS or FAIL and its duration. A `mkdir` lock keeps each block whole when two workers finish at once. After all scripts finish, the runner prints a table sorted by duration and exits 1 if any script failed or if fewer scripts reported than were passed in.

The workflow replaces the per-harness steps with one step that calls the runner. Every harness still appears on its own line in that step, so `bin/check-plan` gate [G] can still find each harness named in a workflow file. The list starts with the slowest harnesses so the workers pack well. A harness that cannot share the runner keeps its own serial step. Three harnesses do this today. `bin/test-bug-pipeline-e2e` exercises a real `lsof` port guard. `bin/test-burndown` and `bin/test-burndown-loop` each need a scrubbed environment from `env -i`.

`bin/test-pr-cycle` and `bin/test-digest-dm-build` each assert that they are wired into CI exactly once. Both now match the harness name as an indented line in the runner's argument list. The order check in `bin/test-pr-cycle` is gone, because parallel runs have no fixed order.

## Alternatives Considered

- **Keep one serial step per harness.** Rejected because the job's wall time keeps growing with each new harness.
- **Split the harnesses across a matrix of jobs.** Rejected because every job pays for its own checkout and Bun setup, and each extra job takes one more runner from the queue.
- **Glob `bin/test-*` inside the runner.** Rejected because gate [G] looks for each harness name in a workflow file, and a glob would hide them. A glob would also pick up harnesses that cannot run beside others.
- **Use GNU parallel.** Rejected because it adds an install step, and `xargs -P` with a small shell function covers the need.

## Consequences

- Positive: the job's wall time tracks the slowest harnesses plus scheduling, and stops growing with every new harness.
- Positive: adding a harness is one line in the list.
- Positive: each harness's output stays in one collapsed log group, and the summary table shows which harnesses are slow.
- Negative: every harness in the list must build its own state under `mktemp` and hold no fixed port or other global resource. A harness that breaks this rule can fail at random when a neighbor touches the same resource.
- Negative: a harness now shows up as a log group inside one step. The GitHub step list no longer names it.
- Negative: one more `bin/` script to maintain under the meta-tooling bar.

## References

- `bin/run-shell-harnesses`
- `.github/workflows/tests.yml` (the `Shell harness regression tests` job)
- `bin/check-plan` (gate [G], which needs each harness named in a workflow)
- `bin/test-bug-pipeline-e2e`, `bin/test-burndown`, and `bin/test-burndown-loop` (the harnesses that keep their own serial steps)
- `bin/test-pr-cycle` and `bin/test-digest-dm-build` (CI wiring assertions)
- `.claude/rules/meta-tooling-bar.md`

## Addendum: three serial harnesses (2026-10-03)

Original figure: the Decision named `bin/test-burndown` as the one harness that keeps its own serial step. Today's figure: three harnesses keep their own serial steps in `.github/workflows/tests.yml`. `bin/test-bug-pipeline-e2e` exercises a real `lsof` port guard, so it cannot share the runner. `bin/test-burndown` and `bin/test-burndown-loop` each need a scrubbed environment from `env -i`.

What changed: the original text named one harness. Two more were already running as serial steps. The decision itself is unchanged: a harness that cannot share the runner keeps its own serial step. The References list above names all three.
