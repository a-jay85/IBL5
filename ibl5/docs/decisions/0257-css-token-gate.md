---
description: bin/check-design-system fails a PR when component CSS skips the token aliases or a UI class has no row in ibl5/classes/UI/README.md.
last_verified: 2026-11-16
---

# ADR-0257: Gate Component CSS Tokens and the UI Class Catalog

**Status:** Accepted
**Date:** 2026-11-16
**Deciders:** a-jay85

## Context

`.claude/rules/css-architecture.md` § Token Naming tells agents to write the bare aliases from `ibl5/design/tokens/tokens.css` (`var(--accent-500)`), never `var(--color-*)`, and to write `var(--z-*)` and `var(--font-weight-*)` tokens in place of numbers. Nothing enforced it. When this ADR was written, 19 lines in 4 component files used `var(--color-*)`. Numeric `z-index` and `font-weight` stood at 0 after an earlier sweep, with nothing to keep them there. `ibl5/classes/UI/README.md`, the catalog an agent reads before building UI, had drifted too: 4 of its 12 classes had no row. The idea came from a LogRocket article on teaching AI assistants a design system. It pairs the written rules with a regex scanner and a docs checker in CI.

## Decision

`bin/check-design-system` scans every `ibl5/design/components/*.css` file. It fails on `var(--color-...)`, on a numeric `z-index`, and on a numeric `font-weight`. Before it matches, it strips CSS block comments, including ones that span lines. A line that carries `/* design-ok: <reason> */` is skipped. The same script fails when a `.php` file under `ibl5/classes/UI/` outside `Contracts/` has no backticked `Name` or `Sub\Name` in the README. The `Meta checks` job in `.github/workflows/pr-meta-checks.yml` runs `bin/test-check-design-system` and then the gate on every pull request. The scan covers the whole tree, so the job needs no base ref.

## Alternatives Considered

- **Stylelint with a custom rule set.** Rejected because the repo has no Stylelint, and adding it brings an npm dependency and a config for three regexes.
- **Extend `bin/check-orphan-css`.** Rejected because that script finds unused selectors by crawling pages. Token use is a separate job.
- **Extend `bin/check-docs` for the catalog half.** Rejected because `bin/check-docs` checks frontmatter, freshness, and dead links across every doc. A per-directory class list would add a second job to it.
- **Leave it as a rule doc only.** Rejected because the rule already existed and 19 lines broke it.

## Consequences

- Positive: an agent that writes `var(--color-accent-500)` or `z-index: 50` fails CI with a fix hint that names the right token.
- Positive: the UI catalog lists every class, so an agent reading it finds `PageTabs` before it builds a new tab bar.
- Negative: one more `bin/check-*` gate and one more `test-*` harness to maintain.
- Negative: the CSS check reads text. A raw value built through a custom property, such as `--x: 50; z-index: var(--x)`, passes it.

## References

- `bin/check-design-system`
- `bin/test-check-design-system`
- `.github/workflows/pr-meta-checks.yml`
- `.claude/rules/css-architecture.md`
- `ibl5/design/tokens/tokens.css`
- `ibl5/classes/UI/README.md`
