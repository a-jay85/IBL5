---
description: Frontmatter schema, 60-day staleness policy, on-touch verification rule, and dead-reference rules enforced by bin/check-docs
last_verified: 2026-05-31
paths: "**/*.md"
---

# Doc Freshness Rule

## Frontmatter Schema

Every in-scope `.md` file (README.md, `.claude/rules/`, `.claude/skills/**/SKILL.md`, `.claude/commands/`, `ibl5/docs/`, `ibl5/docs/decisions/`) must open with:

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

This is enforced in CI by `bin/check-docs --since=<base-ref>`, which fails any PR that changes an in-scope `.md` body without bumping `last_verified` (a base-vs-head value comparison, never date-equality-to-today, so a PR opened one day and merged later does not false-fail).

## Dead-Reference Rule

`bin/check-docs` scans doc bodies for repo-path tokens (`bin/<name>`, `ibl5/<path>`, `.claude/<path>`, `.github/<path>`) and fails on any token that does not resolve to an existing file or directory. Shell variables like `$FOO/bar` are ignored. Use a trailing `(example)` marker for intentional non-resolving paths.
