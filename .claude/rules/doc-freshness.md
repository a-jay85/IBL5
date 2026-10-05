---
description: Operative rules for the frontmatter schema (including `paths:` residency semantics, repo-relative only, never glob an always-loaded rule), 60-day staleness policy, on-touch verification rule, dead-reference rule, and retired-figure rule, and the engine lint-pin rule enforced by bin/check-docs. Rationale, history, and gate mechanics live in doc-freshness-detail.md (Read on demand). Decision-record append-only rule moved to .claude/rules/adr-append-only.md.
last_verified: 2026-10-05
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

### `paths:` residency semantics (`.claude/rules/*` only)

`paths:` is the residency selector. **No `paths:` ⇒ always-loaded** into every system prompt; **with `paths:` ⇒ lazy**, attaching only when a matching file is touched. Two constraints:

- **Repo-relative only.** A `~/`- or `/`-prefixed entry does not match, so it is not a trigger at all.
- **Never glob an always-loaded rule's own path.** Point the glob at the **source surface** the doc explains instead. A deliberately broad glob aimed at a real surface (this doc's `**/*.md`) is fine.

A companion with no live trigger is **Read-on-demand only**. That is legitimate when the always-loaded parent cites it by name at each decision point. Say so in its `description`. Rationale and evidence: `doc-freshness-detail.md` § `paths:` residency rationale.

## On-Touch Rule

When editing any in-scope `.md` file, verify its content still matches reality, confirm the `description` still reflects the content, and bump `last_verified` to today, all in the same edit. A `last_verified` over **60 days** old is stale (the PR gate runs `--no-staleness`; the nightly audit owns repo-wide staleness).

Enforced in CI by `bin/check-docs --since=<base-ref>`, which fails any PR that changes an in-scope `.md` body without bumping `last_verified`. The comparison is base-vs-head, never date-equality-to-today.

Never set a future date. The local `date +%F` (never `date -u`) is always the right value, even when master already carries it. A strictly-newer value is rejected as `` `last_verified` in the future ``. Run `bin/check-docs --since=master --no-staleness` on the dirty tree before shipping. `--since` sees uncommitted edits (base...HEAD, unstaged, and staged); untracked files are excluded. Detail: `doc-freshness-detail.md` § On-touch gate mechanics.

## Dead-Reference Rule

`bin/check-docs` scans doc bodies for repo-path tokens (`bin/<name>`, `ibl5/<path>`, `.claude/<path>`, `.github/<path>`) and fails on any token that does not resolve to an existing file or directory. Shell variables like `$FOO/bar` and paths with glob characters (`*`, `?`, `[`, `]`) are skipped. For intentional non-resolving literal paths, append `(example)` immediately after the closing backtick, e.g. `` `bin/some-path` (example) ``, and the reference is skipped.

Source-file comments under `bin/`, `bin/lib/`, `ibl5/classes/`, `ibl5/phpstan-rules/`, and `ibl5/migrations/` are in scope too (full-scan only, never `--since`); test harnesses are excluded. Detail: `doc-freshness-detail.md` § Source-comment scanning.

## Decision Records Are Append-Only

Moved to `.claude/rules/adr-append-only.md` (lazy, attaches on `ibl5/docs/decisions/**`).

## Retired-Figure Rule

When a dated correction retires a *figure*, the correction must propagate to every doc that cites it. `bin/check-docs`'s full scan fails on any in-scope doc line matching a `RETIRED_FIGURES` pattern with no correction marker nearby.

- **Markers:** `[CORRECTED …]`, `[SUPERSEDED …]`, `**Superseded by:**`, or `RETIRED-OK` (usually in an HTML comment, for a line that *is* the correction).
- **Scope of an exemption:** the marker must be on the line or within 3 lines of it. A heading whose text names a correction (`## Addendum …`, `## Correction …`) exempts its whole section, up to the next heading. Frontmatter is exempt wholesale.
- **`⚠` is not a marker.**

Adding an entry is a high bar: only when the retired form is distinctive enough that a legitimate live use is implausible. When a figure fails the bar, correct the docs and skip the gate. Run `bin/check-docs --self-test` to exercise the exemption logic. Rationale and scope limit: `doc-freshness-detail.md` § Retired-figure rationale.

## Engine Lint-Pin Rule

`.github/workflows/engine.yml` is the source of truth for the two golangci-lint pins: the action ref (`golangci-lint-action@<sha> # vX.Y.Z`) and the linter binary (`with: version:`). The full scan of `bin/check-docs` fails with `FAIL engine lint pin drift` when a doc listed in its `ENGINE_LINT_PIN_DOCS` constant restates a different value. A doc's abbreviated SHA must be a prefix of the workflow SHA and its action tag must match. Every other three-part `v` semver in the doc must equal the binary version. A pin bump edits the workflow and every tracked doc in one PR. A tracked doc that no longer restates the pins also fails, so drop it from the constant when its prose changes.
