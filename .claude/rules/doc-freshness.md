---
description: Frontmatter schema, 60-day staleness policy, on-touch verification rule, dead-reference rule, and the retired-figure rule enforced by bin/check-docs
last_verified: 2026-07-24
paths: "**/*.md"
---

# Doc Freshness Rule

## Frontmatter Schema

Every in-scope `.md` file (README.md, `.claude/rules/`, `.claude/skills/**/SKILL.md`, `ibl5/docs/`, `ibl5/docs/decisions/`) must open with:

```yaml
---
description: One-line hook describing what this doc teaches.
last_verified: 2026-04-11
owner: optional-team-or-person
paths: "glob-or-list"  # only meaningful for .claude/rules/*
---
```

`description` and `last_verified` are required. `owner` and `paths` are optional.

## On-Touch Rule

When editing any in-scope `.md` file, verify its content still matches reality, confirm the `description` field accurately reflects the content, and bump `last_verified` to today — all in the same edit.

This is enforced in CI by `bin/check-docs --since=<base-ref>`, which fails any PR that changes an in-scope `.md` body without bumping `last_verified` (a base-vs-head value comparison, never date-equality-to-today, so a PR opened one day and merged later does not false-fail). Exception: if the doc was already verified earlier the **same day**, the value cannot bump higher without going into the future — so an unchanged value still passes when it equals the edit's git commit date. That escape keys off the immutable commit date, not the CI clock, so it does not reintroduce a midnight false-fail. The practical effect: a same-UTC-day re-edit of a doc verified earlier that day does not have to wait for UTC rollover. Trade-off: at one-day granularity the escape cannot tell a genuine same-day re-verification from a same-day edit that skipped re-checking — an accepted loosening, since the alternative (a hard same-day deadlock) was worse.

## Dead-Reference Rule

`bin/check-docs` scans doc bodies for repo-path tokens (`bin/<name>`, `ibl5/<path>`, `.claude/<path>`, `.github/<path>`) and fails on any token that does not resolve to an existing file or directory. Shell variables like `$FOO/bar` are ignored. Paths with glob characters (`*`, `?`, `[`, `]`) are also skipped automatically. For intentional non-resolving literal paths, append `(example)` immediately after the closing backtick — e.g. `` `bin/some-path` (example) `` — and `bin/check-docs` will skip the reference.

## Retired-Figure Rule

When a dated correction retires a *figure* (not just a claim), the correction has to propagate to every doc that cites it. The observed failure mode (PRs #1620, #1622, #1626) is a superseded number re-surfacing in a doc the sweep missed — so `bin/check-docs`'s full scan fails on any in-scope doc line matching a `RETIRED_FIGURES` pattern with no correction marker nearby.

- **Markers:** `[CORRECTED …]`, `[SUPERSEDED …]`, `**Superseded by:**`, or `RETIRED-OK` (the last usually in an HTML comment, for a line that *is* the correction and quotes the old value only to replace it).
- **Scope of an exemption:** the marker must be on the line or within 3 lines of it. A heading whose text names a correction (`## Addendum …`, `## Correction …`) exempts its whole section, up to the next heading. Frontmatter is exempt wholesale — a `description:` is a summary, not a claim site.
- **`⚠` is deliberately not a marker.** It's a generic emphasis glyph, so accepting it would let an unrelated nearby warning silently exempt a genuinely stale figure, and a false negative here is invisible.

**Adding an entry is a high bar.** A figure qualifies only when its retired form is distinctive enough that a legitimate live use is implausible. `~100× smaller` was considered and rejected: ADR-0094 uses that exact phrase for a different, still-correct comparison, so gating it would force stamping correct prose as if it were retired. When a figure fails that bar, correct the docs and skip the gate.

**Known scope limit:** the check reads the in-scope markdown globs only. It would not have caught the PR #1622 miss, which lived in a JSON test artifact field. Run `bin/check-docs --self-test` to exercise the exemption logic (fixtures cover exemptions only — a wrong pattern fails loudly on the next PR, a wrong exemption does not).
