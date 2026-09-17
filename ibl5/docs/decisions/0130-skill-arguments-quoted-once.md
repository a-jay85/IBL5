---
description: A SKILL.md quotes `$ARGUMENTS` exactly once, in a `<user_request>` block near the top, and refers to it as "the request" elsewhere; enforced by `bin/check-skill-arguments`.
last_verified: 2026-09-17
---

# ADR-0130: SKILL.md quotes `$ARGUMENTS` exactly once

**Status:** Accepted
**Date:** 2026-09-17

## Context

The harness substitutes the caller's full invocation text into *every* `$ARGUMENTS` occurrence in a `SKILL.md` before injecting the body. `.claude/skills/plan/SKILL.md` carried ten occurrences, nine of them purely conceptual back-references such as "a fact asserted in `$ARGUMENTS`". Real transcripts show the result: injected turn-one messages of 216,153 B and 179,097 B against an 86 KB skill body, with the request text present exactly ten times in each. That duplication alone consumed most of the `/plan` orchestrator's context and drove a compaction inside a single run. No existing gate could see it. `bin/check-rules-byte-budget` scopes to `.claude/rules/` and measures the file on disk, so a substitution blowup in `.claude/skills/` is invisible to it.

## Decision

A `SKILL.md` references `$ARGUMENTS` exactly once. The single occurrence is quoted near the top inside a `<user_request>` block that names the convention, and every later reference uses the phrase "the request" instead. An occurrence past the first is legitimate only when the literal text must be interpolated at that point, such as a shell command that consumes the argument; those are recorded one per line in the `ALLOWLIST` of `bin/check-skill-arguments`, with an exact expected count and a reason. The gate runs unconditionally in the `static-guards` job of `.github/workflows/tests.yml`, alongside its own `--self-test`.

## Alternatives Considered

- **Drop the token entirely.** This assumes the harness appends the raw request on its own. Rejected because: the injected block's tail shows no separate copy, so the skill would lose the request entirely.
- **Leave the duplication and rely on compaction.** Rejected because: compaction is lossy and costs a full re-read, and the waste recurs on every invocation of the skill.
- **Extend `bin/check-rules-byte-budget` to cover skills.** Rejected because: it measures on-disk bytes under `.claude/rules/`, a different trigger and a different tree, so hosting this would strain its single responsibility.
- **A convention documented in prose only.** Rejected because: the duplication is invisible in review, and the repo prefers an executable gate over remembered discipline.

## Consequences

- Positive: `/plan` turn one drops from roughly 224,821 B to 99,054 B on a 14 KB request, about 34k tokens saved per invocation.
- Positive: a regression is caught at CI rather than in a token bill, and the gate self-tests both directions on every run.
- Negative: a skill author who genuinely needs literal interpolation must edit the allowlist, which is a small extra step at authoring time.

## References

- `bin/check-skill-arguments` for the gate, its allowlist, and its self-test.
- `.github/workflows/tests.yml`, the `static-guards` job, which runs it on every PR.
- `.claude/skills/plan/SKILL.md` for the reference `<user_request>` anchor.
- `.claude/skills/plan-prompt/SKILL.md` for the same pattern applied to a smaller skill.
- `.claude/skills/pr-wt/SKILL.md`, the one allowlisted file, whose second occurrence feeds `bin/pr-wt-resolve`.
