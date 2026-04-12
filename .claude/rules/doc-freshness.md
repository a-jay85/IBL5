---
description: Frontmatter schema, 60-day staleness policy, on-touch verification rule, and dead-reference rules enforced by bin/check-docs
last_verified: 2026-04-12
paths: "**/*.md"
---

# Doc Freshness Rule

Agent-facing docs are operational instructions, not passive reference. Stale docs poison agent output — the agent faithfully follows whatever the doc says, even when the codebase has moved on. This file defines the schema and the policy that `bin/check-docs` and the `doc-freshness.yml` CI workflow enforce.

## In-Scope Files

CI validates every `.md` under these roots:

- `CLAUDE.md`
- `README.md`
- `.claude/rules/*.md`
- `.claude/skills/**/SKILL.md`
- `.claude/commands/*.md`
- `ibl5/docs/*.md` (non-archive)
- `ibl5/docs/decisions/*.md` (ADRs — also integrity-checked for bidirectional `Supersedes` / `Superseded by` links across files)

**Out of scope:** `.archive/**`, `ibl5/docs/archive/**`, `worktrees/**`, and any `.md` inside `vendor/` or `node_modules/`. User memory (`~/.claude/projects/.../memory/*.md`) is not repo-tracked; `bin/check-docs --include-memory` sweeps it locally but CI never does.

## Frontmatter Schema

Every in-scope file must open with a YAML block:

```yaml
---
description: One-line hook describing what this doc teaches.
last_verified: 2026-04-11
owner: optional-team-or-person
paths: "glob-or-list"  # only meaningful for .claude/rules/*
---
```

### Field rules

| Field | Required | Format | Notes |
|-------|----------|--------|-------|
| `description` | yes | non-empty string | Shown when the doc is auto-loaded; must describe the doc's role in one line |
| `last_verified` | yes | `YYYY-MM-DD` | ISO date, re-stamped whenever a human re-reads the doc and confirms it still matches code |
| `owner` | no | string | Defaults to `unowned`; the person responsible for re-verifying the doc |
| `paths` | no | string or list | Glob(s) that trigger path-conditional rule loading — preserve where already present |

Fields not listed here are tolerated but ignored. Do not add fields without updating this schema and `bin/check-docs` together.

## Staleness Policy — 60 Days

`bin/check-docs` fails with exit code 1 if any in-scope doc has a `last_verified` date older than **60 days** from today. The fix is one of two commits:

1. **Verification commit** — re-read the doc, confirm it still matches reality, bump `last_verified` to today. No content change. This is the expected outcome for most stale docs.
2. **Update commit** — fix the content, bump `last_verified` to today. This is the outcome when re-reading surfaces drift.

Bumping `last_verified` without actually re-reading the doc defeats the entire point of the policy. Treat it as an affirmation, not a checkbox.

Use `bin/check-docs --fix-dates` only when doing a deliberate, reviewed batch re-verification — never as a silencing shortcut.

### On-Touch Rule

When editing any in-scope `.md` file, verify its content still matches reality, confirm the `description` field accurately reflects the content, and bump `last_verified` to today — all in the same edit.

## Dead-Reference Rule

`bin/check-docs` also scans doc bodies for tokens that look like repo paths (`bin/<name>`, `ibl5/<path>`, `.claude/<path>`, `docs/<path>`, `.github/<path>`) and fails on any token that does not resolve to an existing file or directory. This catches the most common form of drift — a doc naming a script, class, or rule file that has been renamed or deleted.

Code fences with language tags other than paths (e.g. `bash`, `php`, `sql`) are scanned line-by-line along with prose; shell variables like `$FOO/bar` are ignored.

If a reference is intentional but unverifiable (e.g. an example path that should not resolve), inline-escape it with a trailing `(example)` marker or move it outside a fenced reference.

## How to Run

```bash
# Validate all in-scope docs (what CI runs)
bin/check-docs

# Include user memory files in the sweep (local only)
bin/check-docs --include-memory

# Bump last_verified on every in-scope doc to today (reviewed batch re-verification only)
bin/check-docs --fix-dates

# Verbose per-file output
bin/check-docs --verbose
```

The CI workflow `doc-freshness.yml` runs `bin/check-docs` on every push and PR. Merge is blocked on failure.

## Rationale

Few-shot context: every code example and phrased rule in these docs is a demonstration the model pattern-matches against. Three-day debugging sessions have happened elsewhere because an agent followed a stale preference buried in a forgotten convention file. Structured frontmatter plus CI-enforced freshness makes staleness visible before it causes damage — it trades a small, automated friction for the large, manual cost of correcting an agent session that followed a ghost rule.
