---
description: Template for new ADRs. Copy with `bin/next-adr "kebab-title"`; do not fill in place.
last_verified: 2026-04-11
---

# ADR-NNNN: <Title>

**Status:** Accepted
**Date:** YYYY-MM-DD
**Deciders:** (optional — names or roles)

## Context

Two to four sentences. What forces were in play, what hurt, what the team had tried. Be concrete about the problem — an ADR is only useful if a future reader can reconstruct why the decision mattered at the time.

## Decision

What we chose, stated as a directive. One paragraph max. If the decision is mechanically enforced, cite the enforcement mechanism (PHPStan rule identifier, CI job, hook, `bin/` script).

## Alternatives Considered

- **<Alternative 1>** — one-line description. Rejected because: <one-line reason>.
- **<Alternative 2>** — one-line description. Rejected because: <one-line reason>.
- **<Alternative 3>** — one-line description. Rejected because: <one-line reason>.

## Consequences

- Positive: <tradeoff we accepted>.
- Positive: <tradeoff we accepted>.
- Negative: <tradeoff we accepted>.

## Supersedes

(Only present when this ADR replaces an earlier one. Remove this section otherwise.) Reference the superseded ADR by number and summarize what changed.

## References

Bulleted list of source files, rules, commits, or prior docs that enforce or illustrate the decision. Use inline backticks for repo paths so `bin/check-docs` validates them.
