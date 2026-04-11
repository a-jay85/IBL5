---
description: Why XSS is enforced mechanically by a PHPStan custom rule instead of runtime convention or templating.
last_verified: 2026-04-11
---

# ADR-0002: XSS enforcement via PHPStan `RequireEscapedOutputRule`

**Status:** Accepted
**Date:** 2026-04-11

## Context

PHP `echo` and `<?= ?>` output is unsafe by default. Any variable reaching either sink without explicit escaping is an XSS vector. IBL5 has dozens of View classes rendering user-controlled strings (team names, player bios, forum posts). Convention alone — "wrap everything in `HtmlSanitizer::e()`" — does not hold: humans miss it under review, agents miss it when generating new code, and a single unescaped `echo` is enough for a stored XSS. The only robust defense is mechanical: a check that runs before code can merge, refuses to let unescaped output pass, and has zero false negatives on the surface it covers.

## Decision

Every `echo` and `<?= ?>` expression inside any file whose basename ends in `View.php` must pass the `RequireEscapedOutputRule` PHPStan custom rule (identifier `ibl.unescapedOutput`, implemented at `ibl5/phpstan-rules/RequireEscapedOutputRule.php`). Permitted expressions:

- `HtmlSanitizer::e()` or `HtmlSanitizer::safeHtmlOutput()` call.
- A whitelisted safe-HTML helper from the rule's `SAFE_STATIC_CALLS` list.
- An `(int)`, `(float)`, or `(bool)` cast (always HTML-safe).
- A string or numeric literal, or a constant (`true`, `false`, `null`, class constant).
- A ternary, null-coalesce, or concatenation where every operand is safe.

The rule intentionally does **not** walk into variables or arbitrary function calls — those are unsafe unless explicitly whitelisted. New helpers must be added to `SAFE_STATIC_CALLS` explicitly. The rule runs in PostToolUse hooks during editing and in CI (`.github/workflows/tests.yml` PHPStan job), so a PR cannot merge with an unescaped echo.

## Alternatives Considered

- **Runtime auto-escaping in a View base class** — every `echo` routes through a parent method that escapes. Rejected: easy to bypass with direct `echo`; the rule isn't checked until runtime; slow to catch in CI because coverage is incomplete; and agents still write raw `echo` out of habit.
- **Full templating engine (Twig, Blade, Plates)** — migrate every View to a template file with auto-escaping. Rejected: whole-codebase migration cost, loss of inline PHP familiarity for the contributors, two-language mental overhead, and the existing `HtmlSanitizer::e()` infrastructure already covers the narrow surface.
- **Convention + code review** — mandate `HtmlSanitizer::e()` in the style guide, rely on humans to catch misses. Rejected: every previous project that tried this had an XSS in production. Humans and agents both miss it. The convention survives only with mechanical enforcement.
- **htmlspecialchars() direct calls** — allow the built-in instead of the wrapper. Rejected: `htmlspecialchars()` requires developer discretion about flags (`ENT_QUOTES`, encoding) and can be misconfigured silently. The wrapper locks in the flags and gives us one thing to review.

## Consequences

- Positive: XSS is impossible in View files that pass CI, by construction.
- Positive: the whitelist is explicit and grep-able — you can find every safe helper by reading one constant in one file.
- Positive: the rule fires during editing (PostToolUse hook), so agents get immediate feedback instead of merge-time rejection.
- Positive: security audits no longer need to read View files looking for unescaped output — `_review-rubric.md` explicitly tells reviewers not to re-check this.
- Negative: adding a new safe helper requires editing `ibl5/phpstan-rules/RequireEscapedOutputRule.php` and updating the whitelist. Deliberate friction — the point is to force a review when the safe-set grows.
- Negative: the rule only covers files matching `*View.php`. Output from Controllers or other classes is not inspected. Mitigated by the `_review-rubric.md` guidance that echo from non-View files is rare and reviewed manually; expand the rule if a pattern emerges.

## References

- `ibl5/phpstan-rules/RequireEscapedOutputRule.php` — the rule implementation and `SAFE_STATIC_CALLS` whitelist.
- `.claude/rules/view-rendering.md` — the agent-facing rule file (cites this ADR).
- `.claude/commands/_review-rubric.md` — the reviewer guidance that excludes re-checking this category.
- `ibl5/classes/Utilities/HtmlSanitizer.php` — the wrapper that locks in `htmlspecialchars` flags.
