---
description: Any PR body version string, numeric baseline, or X → Y figure must name its authoritative source file inline.
last_verified: 2026-09-05
---

# PR Body Claims

## Rule

When a PR body states a **version string** (`v2 → v3`, `@v3`), a **numeric baseline**
(`147 call sites`), or an **`X → Y` figure**, name the **authoritative source file**
inline — e.g. `147 → 134 call sites (per `ibl5/phpstan-baseline.neon`)`.

Uncited claims silently stale. Trigger: L43 (2026-09-02, PR #2059).

## Application

| Claim type | Citation form |
|---|---|
| Version string | `(per `.github/workflows/security.yml` (example) line N)` |
| Numeric baseline | `(per `ibl5/phpstan-baseline.neon`)` |
| `X → Y` figure | `(per `ibl5/phpstan-baseline.neon` — N sites)` |
| Stale negative-claim bullet | See `.claude/rules/pr-body-negative-claim-recheck.md` |
| ADR frozen section rewritten | See `.claude/rules/doc-freshness.md` § Decision Records Are Append-Only |

## Calibration

Governs the **claim** (citation present), not whether the figure is correct.
Applies to human-authored and autonomous-loop PR bodies alike.
**Headless:** applies — automouse/`/post-plan` PR bodies must include inline citations.
