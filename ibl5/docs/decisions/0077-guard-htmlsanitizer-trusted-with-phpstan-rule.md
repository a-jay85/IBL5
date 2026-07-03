---
description: PHPStan rule guarding the HtmlSanitizer::trusted() XSS escape hatch; native @phpstan-ignore as the acknowledgment mechanism.
last_verified: 2026-07-03
---

# ADR-0077: Guard `HtmlSanitizer::trusted()` with `RequireTrustedAnnotationRule` (PHPStan)

**Status:** Accepted
**Date:** 2026-07-03
**Deciders:** A-Jay

## Context

`RequireEscapedOutputRule` (ADR-0002) whitelists `HtmlSanitizer::trusted()` as safe echo
output — it trusts that whatever `trusted()` returns is already safe HTML, so it bypasses
XSS escaping at the call site. But nothing checks `trusted()`'s *input*. Existing call sites
pass a mix of genuinely safe values (`$this->renderRow()` helpers, literals) and raw values
that were never sanitized, distinguishable only by manual code review. A refactor can
silently extract `trusted($rawVar)` from a previously-safe context with no signal that the
XSS guarantee just broke (maintenance-backlog finding 10.10).

## Decision

Add `RequireTrustedAnnotationRule`, firing identifier `ibl.trustedVariable`, when the first
argument to `HtmlSanitizer::trusted()` / `Security\HtmlSanitizer::trusted()` is not a
string/numeric literal, an `(int)`/`(float)`/`(bool)` cast, or a `$this->...()` method call.
Existing firing sites (147 call sites across 16 files) are captured in
`ibl5/phpstan-baseline.neon` with zero code churn. A genuinely-safe new site is acknowledged
with a native `// @phpstan-ignore ibl.trustedVariable` comment plus a justifying note, not a
bespoke marker.

## Alternatives Considered

- **Bespoke `// @trusted` marker comment** — a custom comment convention parsed by the rule
  itself. Rejected: reinvents suppression PHPStan already provides, needs custom parsing
  logic in the rule, and is invisible to `ibl5/bin/check-baseline-drift` (which only understands
  native baseline/ignore mechanisms).
- **Refactor all existing call sites to safe forms in this PR** — Rejected: large blast
  radius on runtime View code, out of scope for a tooling change. The baseline freezes the
  debt visibly instead of touching ~16 files of production rendering code.

## Consequences

- Positive: every future `trusted()` call is checked automatically; a refactor that
  introduces an unsafe argument now fails `composer run analyse`.
- Positive: the existing debt is frozen, not growing — `ibl5/bin/check-baseline-drift` blocks any
  *new* firing site from silently landing in the baseline.
- Negative: acknowledging a genuinely-safe new site costs one reviewed
  `// @phpstan-ignore ibl.trustedVariable` comment per call site.

## Supersedes

None.

## References

- `ibl5/phpstan-rules/RequireTrustedAnnotationRule.php`
- `ibl5/docs/decisions/0002-xss-enforcement-via-phpstan.md`
- `ibl5/phpstan-baseline.neon`
- `ibl5/phpstan-rules/RequireEscapedOutputRule.php`
