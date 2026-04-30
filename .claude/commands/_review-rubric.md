---
description: Shared review rubric and false-positive filter used by /code-review, /security-audit, and /post-plan.
last_verified: 2026-04-29
---

# Review Rubric and False-Positive Filter (shared)

Source of truth for confidence scoring. Used by `/code-review` Step 4, `/security-audit` Step 4, and `/post-plan` Phase 5D. Do not edit without updating all three callers.

## Thresholds

| Review type | Drop findings scored below |
|---|---|
| Code review | **80** |
| Security audit | **75** |

## Scoring scale (0-100)

When the parent command passes issues to the Haiku scoring agent, pass **this section and the Thresholds table above** — not the full Automatic Zero or false-positive lists below. Review agents have already filtered those categories; the scoring agent only needs the scale.

Score each finding 0-100 using the rubric below:

- **0** — False positive that doesn't survive light scrutiny, or a pre-existing issue, or caught by the **Automatic Zero** list below.
- **25** — Suspicious but likely mitigated. Variable constrained elsewhere; pattern present but unexploitable in context.
- **50** — Moderately confident. Pattern present but exploitation requires specific conditions that may not apply. Stylistic issue not explicitly called out in CLAUDE.md.
- **75** — Highly confident. Double-checked and verified as a real issue that will happen in practice. Directly mentioned in CLAUDE.md or clearly present with no visible mitigation.
- **100** — Absolutely certain. Direct user input flows to SQL / HTML / file / state-change with zero sanitization or validation.

For CLAUDE.md-cited issues: **verify the rule is actually stated in CLAUDE.md**. Do not invent rules.

Return ONLY valid JSON: `[{"n": 1, "score": 75}, {"n": 2, "score": 0}, ...]`

---

## Automatic Zero (apply before any other scoring)

If the finding would be caught by any of the rules listed below, score 0 immediately. These rules run in PostToolUse and CI — a merged PR cannot violate them, so by definition every "finding" in these categories is a false positive.

**PHPStan level max + strict-rules + bleedingEdge:**
Catches type errors, loose comparisons (`==`/`!=`), unused imports, unreachable code, PHPDoc/return type mismatches, missing declarations, structural errors.

**IBL5 custom PHPStan rules:**

| Rule identifier | What it catches |
|---|---|
| `ibl.missingStrictTypes` | Missing `declare(strict_types=1)` |
| `ibl.bannedNumberFormat` | `number_format()` calls outside `StatsFormatter` |
| `ibl.bannedNukeGlobal` | Direct `is_user`/`is_admin`/`cookiedecode`/etc. outside `NukeCompat`/`LegacyFunctions`/`PageLayout` |
| `ibl.bannedBeginTransaction` | Direct `begin_transaction()` in repository subclasses (use `$this->transactional()`) |
| `ibl.unescapedOutput` | `echo`/`<?=` in Views without `HtmlSanitizer::e()` wrapping |
| `ibl.rawSuperglobal` | `$_GET`/`$_POST`/`$_REQUEST`/`$_COOKIE` outside `*Controller.php`, `*ApiHandler.php`, `Utilities/CsrfGuard.php` |
| `ibl.inlineCss` | `<style>` blocks or `style="..."` attributes in PHP string literals (except `style="--` CSS custom properties) |
| `ibl.deprecatedHtmlTag` | `<b>`, `<i>`, `<center>`, `<font>`, `<u>` in PHP string literals |
| `ibl.requireOnce` | `require_once`/`require`/`include_once`/`include` in `classes/**` |
| `ibl.meaninglessAssertion` | Empty test bodies, `assertTrue(true)`, mocks without `->expects()`/`->method()` |
| `ibl.cookieBeforeHeader` | `$cookie[...]` access before `PageLayout::header()` in the same function |

---

## IBL5-specific false positives (score 0-25)

Apply after the Automatic Zero list.

- `BaseMysqliRepository` variables — already parameterized via prepared statements
- Test files (`tests/` directory) — not production attack surface
- Integers in `strict_types=1` files with typed parameters — PHP enforces the type
- `echo` in CLI scripts — no web context, no XSS
- `$db->sql_query()` with fully hardcoded strings (no variables)
- API handlers already using `ApiKeyAuthenticator` — CSRF exempt
- GET-only read handlers — CSRF exempt
- Pre-existing issues on lines the PR did not modify
- Changes in functionality that are likely intentional or directly related to the broader change
- Issues called out in CLAUDE.md but silenced by explicit opt-out comments (e.g. `// phpcs:ignore`, `@phpstan-ignore-next-line`)
