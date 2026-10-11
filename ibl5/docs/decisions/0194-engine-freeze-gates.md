---
description: ADR-0194 — engine/ is frozen and bin/check-engine-freeze gates new decompile tokens on every PR.
last_verified: 2026-10-10
---

# ADR-0194: Freeze engine/ and gate new decompile tokens

**Status:** Accepted
**Date:** 2026-10-10

## Context

The shadow-only Go engine under `engine/` (ADR-0035, ADR-0037) is scheduled for replacement by an independently built engine. Edits to the current tree before then would be discarded and would complicate its retirement. Some files under `engine/` and about twenty files elsewhere cite disassembly artifacts (generated symbol names, struct offsets, artifact file names), and new citations should not spread.

## Decision

`bin/check-engine-freeze` runs in the required meta-checks job of `pr-meta-checks.yml` on every PR. It fails a PR that adds or modifies content under `engine/` (whole-file and line deletions pass, so the cutover PR can delete the tree), and fails any added line in any path that matches the decompile-token table. `bin/check-docs` drops `engine/` from its nightly staleness report, and Dependabot ignores the engine-only lint action, so no automation opens a PR the freeze must reject. The cutover PR retires all four together.

## Alternatives Considered

- **A job in engine.yml** — a separate path-filtered job. Rejected because: it is path-filtered and not a required check, so it would not hold auto-merge.
- **An exemption directory for the gate's own fixtures** — a place where tokens are allowed. Rejected because: it is a standing leak path. Fixtures are built at runtime instead.
- **Removing engine docs from the check-docs scope** — stops the nightly staleness noise. Rejected because: it also silences PR-time dead-reference checks on them.

## Consequences

- Positive: new citations cannot spread, and the freeze cannot be dodged by moving a file out of `engine/`.
- Negative: existing citations stay until their files change, and editing a line that already carries a token fails, so the editor drops the token from that line.
- Negative: direct pushes to master stay ungated.
- Negative: a verbatim copy out of `engine/` that keeps the source and carries no token is not detected, and binary file content is not scanned for tokens.

## References

- [ADR-0149](0149-required-ci-aggregator-all-checks-green.md) — the aggregator that anchors on the meta-checks job.
- `.claude/rules/engine-go.md`
- `bin/check-engine-freeze`
- `bin/test-check-engine-freeze`
