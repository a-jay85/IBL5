---
description: Frontmatter schema (including `paths:` residency semantics — repo-relative only, never glob an always-loaded rule), 60-day staleness policy, on-touch verification rule, dead-reference rule, and the retired-figure rule enforced by bin/check-docs
last_verified: 2026-08-09
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

`paths:` is the residency selector. **No `paths:` ⇒ always-loaded** into every system prompt; **with `paths:` ⇒ lazy**, attaching only when a matching file is touched. Two constraints on writing one:

- **Repo-relative only.** A `~/`- or `/`-prefixed entry does not match in practice, so it is not a trigger at all — even when the doc exists to explain that out-of-repo file (verified 2026-07-28 across session transcripts: `work-triage-detail.md` attached zero times in sessions that edited the hook its `paths:` named).
- **Never glob an always-loaded rule's own path.** Compaction restore re-materializes the resident set as file attachments, and that re-materialization is itself a *touch* — so a companion globbing its parent re-attaches every window, self-sustaining, with no tool call touching the trigger (three companions did this until PR #1730). The per-window cost varies by session, since the restore set is "whatever was resident" — measure it on your own transcript rather than trusting a quoted figure. Point the glob at the **source surface** the doc explains instead.

This is not an argument for narrow globs generally. A deliberately broad glob aimed at a real surface — this doc's `**/*.md`, `meta-tooling-bar.md`'s `.claude/**` — re-attaches on restores too, and that is intended: both docs govern the surface being restored. The defect is a glob whose *only* purpose is to ride its parent's residency.

A companion left with no live trigger is **Read-on-demand only**. That is legitimate when the always-loaded parent cites it by name at each decision point — but say so in the `description`, so the next reader doesn't assume it auto-attaches.

## On-Touch Rule

When editing any in-scope `.md` file, verify its content still matches reality, confirm the `description` field accurately reflects the content, and bump `last_verified` to today — all in the same edit. A `last_verified` over **60 days** old is stale (the PR gate runs `--no-staleness`, so an untouched stale doc never blocks an unrelated change; the nightly audit owns repo-wide staleness).

Enforced in CI by `bin/check-docs --since=<base-ref>`, which fails any PR that changes an in-scope `.md` body without bumping `last_verified`. The comparison is base-vs-head, never date-equality-to-today, so a PR opened one day and merged later does not false-fail; and an unchanged value still passes when it equals the edit's **git commit date**, so a same-UTC-day re-edit of a doc verified earlier that day need not wait for UTC rollover.

## Dead-Reference Rule

`bin/check-docs` scans doc bodies for repo-path tokens (`bin/<name>`, `ibl5/<path>`, `.claude/<path>`, `.github/<path>`) and fails on any token that does not resolve to an existing file or directory. Shell variables like `$FOO/bar` are ignored, and paths with glob characters (`*`, `?`, `[`, `]`) are skipped automatically. For intentional non-resolving literal paths, append `(example)` immediately after the closing backtick — e.g. `` `bin/some-path` (example) `` — and `bin/check-docs` will skip the reference.

**Source-file comments are in scope too (full-scan mode only).** The same resolution rules apply to leading comment bodies in `bin/`, `bin/lib/`, `ibl5/classes/`, `ibl5/phpstan-rules/`, and `ibl5/migrations/` — PHP `//`, `#`, `/* */` and `*` docblock continuations; shell and TypeScript `#` / `//`. Test harnesses (`bin/test-` (example) prefixed scripts, `ibl5/tests/`) are excluded because their non-resolving paths are deliberate negative fixtures. A trailing ` (example)` marker suppresses a comment reference too, but the comment-side match is deliberately looser than markdown's: it tolerates any run of closing delimiters (backtick, quote, bracket, punctuation) between the path and the marker, so the backticked form `` `path` `` (example) works there as well. Five named false-positive suppressions run before resolution — trailing punctuation, directory-shaped tokens, ADR-slug placeholders, runtime-generated artifacts, and the `ibl5/bin/<x>` working-directory ambiguity; read them in `checkSourceCommentReferences()` before trusting a suppressed finding. This is whole-tree only, never `--since`: source files carry no frontmatter and so never participate in the on-touch bump rule.

## Retired-Figure Rule

When a dated correction retires a *figure* (not just a claim), the correction has to propagate to every doc that cites it — the observed failure mode (PRs #1620, #1622, #1626) is a superseded number re-surfacing in a doc the sweep missed. So `bin/check-docs`'s full scan fails on any in-scope doc line matching a `RETIRED_FIGURES` pattern with no correction marker nearby.

- **Markers:** `[CORRECTED …]`, `[SUPERSEDED …]`, `**Superseded by:**`, or `RETIRED-OK` (the last usually in an HTML comment, for a line that *is* the correction and quotes the old value only to replace it).
- **Scope of an exemption:** the marker must be on the line or within 3 lines of it. A heading whose text names a correction (`## Addendum …`, `## Correction …`) exempts its whole section, up to the next heading. Frontmatter is exempt wholesale — a `description:` is a summary, not a claim site.
- **`⚠` is deliberately not a marker.** It's a generic emphasis glyph, so accepting it would let an unrelated nearby warning silently exempt a genuinely stale figure, and a false negative here is invisible.

**Adding an entry is a high bar.** A figure qualifies only when its retired form is distinctive enough that a legitimate live use is implausible. `~100× smaller` was rejected on exactly that test: ADR-0094 uses the phrase for a different, still-correct comparison, so gating it would force stamping correct prose as retired. When a figure fails the bar, correct the docs and skip the gate.

**Known scope limit:** the check reads both the in-scope markdown globs and source-file comment bodies (PHP, shell, TypeScript under `bin/`, `bin/lib/`, `ibl5/classes/`, `ibl5/phpstan-rules/`, `ibl5/migrations/`). It would not have caught the PR #1622 miss, which lived in a JSON test artifact field. Run `bin/check-docs --self-test` to exercise the exemption logic (fixtures cover exemptions only — a wrong pattern fails loudly on the next PR, a wrong exemption does not).
