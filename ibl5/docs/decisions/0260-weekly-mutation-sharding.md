---
description: The weekly Infection run splits into shards from a committed map, shares one coverage run, and enforces one aggregate MSI bar; a module dispatch input runs one directory report-only.
last_verified: 2026-11-16
---

# ADR-0260: Weekly mutation testing runs as shards with one aggregate bar

**Status:** Accepted
**Date:** 2026-11-16
**Deciders:** Repo owner

## Context

The weekly `.github/workflows/mutation.yml` ran Infection over all of `ibl5/classes` in one job. It hit its 60-minute cap on 2026-11-02 and 2026-11-09. The honest run on 2026-11-16 took 52 minutes for 45,639 mutants with 4 threads. A planned change runs database tests per mutant, which adds time to every mutant, so one job will not fit again. Test-only PRs also change no class lines, so `mutation-pr.yml` mutates nothing for them, and there was no way to check one directory's score.

## Decision

Split the weekly run into five jobs. `Plan mutation shards` reads the committed map `ibl5/infection-shards.json` and emits a matrix. Its `ibl5/bin/infection-shards check` step fails the run when an `ibl5/classes` entry sits in no shard or in two. `Mutation coverage` runs PHPUnit with pcov once and shares `ibl5/coverage` (example) through the `mutation-coverage` artifact. Each `Infection PHP sNN` job mutates one shard with `--filter` and `--min-msi=0`. `Aggregate MSI` sums every shard's `infection-summary.log` with `ibl5/bin/infection-shards aggregate` and enforces `MUTATION_MIN_MSI` once. `Notify Mutation Failure` waits on all five. A `workflow_dispatch` run with the `module` input mutates one entry and reports its MSI without enforcing the bar. `bin/test-infection-shards` pins the script and the workflow shape.

## Alternatives Considered

- **A runtime splitter that bin-packs `ls classes`.** Needs no upkeep. Rejected because shards drift whenever line counts change, so a shard's score cannot be compared from week to week.
- **A JSON summary logger added to `ibl5/infection.json5`.** Rejected because that file also configures the required per-PR check, and a wrong logger key would break config validation on every class-touching PR. The text summary log is already written, and the parser exits 3 on an unknown label or a drifting total.
- **Coverage per shard.** Needs no artifact. Rejected because it repeats the full PHPUnit + pcov suite 10 times and adds that time to every shard.
- **Extend `ibl5/bin/check-infection-excludes`.** Rejected because it owns parity between the json5 excludes and the coverage config. The map's check, matrix, and aggregate share one parser, and splitting them across two scripts would duplicate it.

## Consequences

- Positive: each shard finishes far inside its timeout, and the shard count can grow without touching the workflow.
- Positive: one weak directory cannot hide behind strong ones, because the bar applies to summed counts.
- Positive: a `module` dispatch checks a test-writing PR's target directory in minutes.
- Negative: a new `ibl5/classes` entry needs one line in `ibl5/infection-shards.json`, and the weekly run fails until it is added.
- Negative: ten database containers and one artifact transfer per run use more runner minutes than one job did.

## References

- `.github/workflows/mutation.yml`
- `ibl5/infection-shards.json`
- `ibl5/bin/infection-shards`
- `bin/test-infection-shards`
- `.claude/rules/ci-gotchas.md`
