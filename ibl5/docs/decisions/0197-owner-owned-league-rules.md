---
description: The league owner owns ibl5/docs/LEAGUE_RULES.md. Agents check game-logic changes against it and stop to ask before changing a rule.
last_verified: 2026-10-15
---

# ADR-0197: Owner-owned league rules file

**Status:** Accepted
**Date:** 2026-10-15
**Deciders:** a-jay85

## Context

PRs merge faster than the owner can read them. The rules the site enforces lived only in code spread across many classes. A PR could change a rule, such as the roster limit or a cap check, and nobody would notice until a GM did.

## Decision

`ibl5/docs/LEAGUE_RULES.md` lists the rules the site must enforce, and the owner owns it. The lazy rule `.claude/rules/league-rules.md` loads when an agent edits `ibl5/classes/`. It tells the agent to check the change against the file and to stop and ask before changing a rule. A PR that changes a rule with approval updates the file and says `League rule changed:` in its body. The weekly comprehension digest reads the file and marks PRs that look like rule changes with `RULE CHANGE?`.

## Alternatives Considered

- A CI gate that fails PRs touching rule code. Rejected because a path match cannot tell a rule change from a refactor, so it would mostly fire on noise.
- Keep the rules only in code and tests. Rejected because the owner cannot read them at a glance or see when they drift.

## Consequences

- Positive: the owner has one short page to check the site against.
- Positive: rule changes surface in the weekly digest and in PR bodies.
- Negative: the file can drift from the code. Agents report a mismatch and leave both sides alone until the owner decides.

## References

- `ibl5/docs/LEAGUE_RULES.md`
- `.claude/rules/league-rules.md`
- `bin/automouse/comprehension-digest`
