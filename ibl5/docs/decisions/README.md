---
description: Index of IBL5 Architecture Decision Records (ADRs). Source of truth for every load-bearing decision and its rationale.
last_verified: 2026-04-11
---

# IBL5 Architecture Decision Records

Every load-bearing decision in IBL5 is captured here as a numbered ADR so that future contributors (human and agent) can reconstruct *why* we chose X over Y, not just *that* we chose X. Rules in `.claude/rules/` and `ibl5/phpstan-rules/` tell you *what* to do; ADRs tell you *why the rule exists*.

## How to Use

- **Reading:** start with the ADR most relevant to the surface you're touching. Each rule file and each PHPStan custom rule links back to the ADR that justifies it.
- **Writing:** when you make a new significant decision, create an ADR first — the CI gate (`bin/adr-check`, workflow `adr-required.yml`) blocks PRs that add mechanical-enforcement surfaces without an accompanying ADR. See the "When an ADR is required" section below.
- **Creating one:** run `bin/next-adr "kebab-title"` from the repo root. It copies `0000-template.md` into the next numbered slot and prints the path. The template is Michael Nygard format, adapted for IBL5's frontmatter schema.

## Index

| # | Title | Status | Summary |
|---|-------|--------|---------|
| [0001](0001-interface-driven-architecture.md) | Interface-driven Repository/Service/View architecture | Accepted | Organizing pattern for every module in `ibl5/classes/`; drove the 30-module refactor. |
| [0002](0002-xss-enforcement-via-phpstan.md) | XSS enforcement via PHPStan `RequireEscapedOutputRule` | Accepted | Shift-left XSS prevention: mechanical CI gate, not runtime convention. |
| [0003](0003-statsformatter-mandate.md) | `StatsFormatter` mandate, `number_format()` banned | Accepted | Single centralized stat formatter enforced by `BanNumberFormatRule`. |
| [0004](0004-docker-only-dev-environment.md) | Docker-only development environment | Accepted | MAMP sunset; reproducible dev + CI parity + worktree port isolation. |
| [0005](0005-strict-types-enforcement.md) | Strict types + typed properties enforcement | Accepted | PHPStan level `max` + `strict-rules` as the floor; type coercion bugs banned mechanically. |

## When an ADR is Required

The CI workflow `adr-required.yml` runs `bin/adr-check` on every PR. An ADR is required if the PR adds any of:

1. A new PHPStan custom rule under `ibl5/phpstan-rules/*.php`.
2. A new always-loaded or path-conditional agent rule under `.claude/rules/*.md`.
3. A new CI workflow under `.github/workflows/*.yml`.
4. A destructive schema migration (`DROP TABLE`, `DROP COLUMN`, or `DROP INDEX` in `ibl5/migrations/*.sql`).
5. A new `bin/` helper script of at least 50 lines.
6. A new dependency in `ibl5/composer.json`'s `require` or `require-dev` block.

**Bypass** for changes that genuinely don't need an ADR: add `<!-- no-adr: reason at least 15 chars long -->` as an HTML comment inside the PR body. The reason is logged in CI output for traceability. Silence is not allowed — you must type a reason.

## Template and Tooling

- [`0000-template.md`](0000-template.md) — the Nygard template. Never edit in place.
- [`bin/next-adr`](../../../bin/next-adr) — creates the next ADR file by number, copying the template and slugging the title.
- [`bin/adr-check`](../../../bin/adr-check) — the CI gate (also usable locally with `--staged`).
- [`bin/check-docs`](../../../bin/check-docs) — enforces frontmatter freshness on every ADR and verifies bidirectional `Supersedes` integrity.
- [`.claude/rules/doc-freshness.md`](../../../.claude/rules/doc-freshness.md) — the frontmatter schema every ADR must satisfy.
