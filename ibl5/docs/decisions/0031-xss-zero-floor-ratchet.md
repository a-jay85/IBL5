---
description: Zero-floor ratchet mechanism for RequireEscapedOutputRule prevents XSS regression in cleaned files.
last_verified: 2026-07-22
---

# ADR-0031: XSS Zero-Floor Ratchet

## Status

Accepted

## Context

The `RequireEscapedOutputRule` PHPStan rule enforces XSS escaping in View classes, but violations can be suppressed via `phpstan-baseline.neon`. Once a file is cleaned to zero violations, nothing prevents new unescaped output from being added and immediately baselined — silently undoing the cleanup work.

Plans B and C (SeasonLeaderboards, CareerLeaderboards) depend on a mechanism that locks cleaned files at zero violations permanently.

## Decision

Add a `ZERO_FLOOR_FILES` constant to `RequireEscapedOutputRule`. Files listed in this constant emit errors with `RuleErrorBuilder::nonIgnorable()`, which PHPStan refuses to suppress via baseline. Any new violation in a zero-floor file blocks CI unconditionally.

The initial zero-floor list covers the 5 Navigation view files (58 violations eliminated in this PR). Future cleanup PRs add their files to the list.

## Consequences

- **Positive:** Cleaned files cannot regress — new violations are compile-time errors.
- **Positive:** Incremental adoption �� files are added to zero-floor only after they reach zero violations.
- **Negative:** Contributors editing zero-floor files must use `HtmlSanitizer::e()`, `trusted()`, casts, or whitelisted helpers for every echo expression. This is intentional.
- **Maintenance:** The list grows monotonically. There is no mechanism to remove a file from it (by design).
