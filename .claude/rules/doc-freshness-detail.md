---
description: Read-on-demand detail for doc-freshness. NO auto-attach trigger (its `paths:` entry is out-of-repo and never matches); Read it when doc-freshness.md cites it. Covers `paths:` residency rationale and evidence, on-touch gate mechanics (future-date history, --since working-tree view), source-comment scanning, and retired-figure rationale and scope limit.
last_verified: 2026-10-01
paths:
  - "~/.claude/hooks/plan-gate-edit.sh"
---

# Doc Freshness Detail

Operative rules live in `doc-freshness.md`. This file holds the rationale behind them.

## `paths:` residency rationale

- **Repo-relative only.** Verified 2026-07-28 across session transcripts: `work-triage-detail.md` attached zero times in sessions that edited the hook its `paths:` named. An out-of-repo entry is not a trigger, even when the doc exists to explain that out-of-repo file.
- **Never glob an always-loaded rule's own path.** Compaction restore re-materializes the resident set as file attachments, and that re-materialization is itself a *touch*. A companion globbing its parent therefore re-attaches every window, self-sustaining, with no tool call touching the trigger (three companions did this until PR #1730). The per-window cost varies by session, since the restore set is "whatever was resident". Measure it on your own transcript rather than trusting a quoted figure.
- **Broad globs are not the defect.** A deliberately broad glob aimed at a real surface (this doc's `**/*.md`, `meta-tooling-bar.md`'s `.claude/**`) re-attaches on restores too, and that is intended: both docs govern the surface being restored. The defect is a glob whose *only* purpose is to ride its parent's residency.
- **Read-on-demand companions.** Legitimate when the always-loaded parent cites the companion by name at each decision point. Say so in the `description`, so the next reader doesn't assume it auto-attaches.

## On-touch gate mechanics

- **Staleness.** The PR gate runs `--no-staleness`, so an untouched stale doc never blocks an unrelated change. The nightly audit owns repo-wide staleness.
- **Base-vs-head.** The comparison is base-vs-head, never date-equality-to-today, so a PR opened one day and merged later does not false-fail. An unchanged value still passes when it equals the edit's commit date, so a same-UTC-day re-edit of a doc verified earlier that day need not wait for UTC rollover.
- **Future dates.** The on-touch rule passes when `last_verified` is `>=` the edit's commit date (`editCommitDate()`, or `localToday()` when `base..HEAD` is still empty). A value equal to master's therefore still passes on the day you edit, and a strictly-newer value can only be a future date, which `checkFile()` rejects separately with `` `last_verified` in the future ``. The older advice to "bump one day higher when master already has today's date" predates the 2026-09-07 local-date fallback and now fails the gate outright (re-verified 2026-09-16). The real `body changed but last_verified not bumped (still <date>)` failure (PR #1206) fires when the edit's commit date has moved past the value, a next-day commit for example. Re-run `bin/check-docs --since=master --no-staleness` on the dirty tree before shipping; it gives the same verdict the pre-commit hook does.
- **Local date.** `editCommitDate()` reads `%aI` in the author's timezone and the no-commit-yet fallback has been local since 2026-09-07, so a UTC evening date buys nothing and reads as tomorrow.
- **`--since` sees an uncommitted edit.** The changed set is the union of `<base>...HEAD`, unstaged (`git diff HEAD`), and staged (`--cached`) `.md` changes, so running the check *before* committing gives the same verdict CI gives after. Skills that self-verify run it on a dirty tree. With the old HEAD-anchored set alone they were structurally blind to their own edits and printed a false green (PR #1878 merged a stale `last_verified` that way on 2026-08-14). CI checks out a clean tree, so the two working-tree views are empty there and CI behavior is unchanged. Untracked files stay out: with no base blob the on-touch predicate cannot fire, and the full scan already checks their frontmatter.

## Source-comment scanning

The same resolution rules as doc bodies apply to leading comment bodies in `bin/`, `bin/lib/`, `ibl5/classes/`, `ibl5/phpstan-rules/`, and `ibl5/migrations/`. That covers PHP `//`, `#`, `/* */` and `*` docblock continuations, and shell and TypeScript `#` / `//`. Test harnesses (`bin/test-` (example) prefixed scripts, `ibl5/tests/`) are excluded because their non-resolving paths are deliberate negative fixtures. A trailing ` (example)` marker suppresses a comment reference too, but the comment-side match is deliberately looser than markdown's: it tolerates any run of closing delimiters (backtick, quote, bracket, punctuation) between the path and the marker, so the backticked form `` `path` `` (example) works there as well. Five named false-positive suppressions run before resolution: trailing punctuation, directory-shaped tokens, ADR-slug placeholders, runtime-generated artifacts, and the `ibl5/bin/<x>` working-directory ambiguity. Read them in `checkSourceCommentReferences()` before trusting a suppressed finding. This is whole-tree only, never `--since`: source files carry no frontmatter and so never participate in the on-touch bump rule.

## Retired-figure rationale

- **Why propagate.** The observed failure mode (PRs #1620, #1622, #1626) is a superseded number re-surfacing in a doc the sweep missed.
- **Why frontmatter is exempt.** A `description:` is a summary and never a claim site.
- **Why `⚠` is not a marker.** It's a generic emphasis glyph, so accepting it would let an unrelated nearby warning silently exempt a genuinely stale figure, and a false negative here is invisible.
- **Why the entry bar is high.** `~100× smaller` was rejected on exactly that test: ADR-0094 uses the phrase for a different, still-correct comparison, so gating it would force stamping correct prose as retired.
- **Known scope limit.** The check reads both the in-scope markdown globs and source-file comment bodies (PHP, shell, TypeScript under `bin/`, `bin/lib/`, `ibl5/classes/`, `ibl5/phpstan-rules/`, `ibl5/migrations/`). It would not have caught the PR #1622 miss, which lived in a JSON test artifact field. `bin/check-docs --self-test` fixtures cover exemptions only. A wrong pattern fails loudly on the next PR; a wrong exemption does not.
