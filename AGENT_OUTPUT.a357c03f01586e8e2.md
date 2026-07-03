# PR #1303 review — Section 2 (code-comment compliance) findings

Task: review PR #1303 (docs-reorg: backlog split/archive) for compliance with
in-code guidance found in comments visible in the diff. Section 1 (git
history) explicitly skipped per instructions — no PHP files touched. Only
Section 2 (code comments) run.

## Verdict

No compliance violations found. Full pass — both by reading the guidance and
by running the gate live.

## Evidence

1. **`bin/check-docs` IN_SCOPE_GLOBS convention followed.**
   `bin/check-docs:13-22` documents non-recursive, single-directory globs
   (e.g. `ibl5/docs/*.md`, `.claude/rules/*.md`). The new entry added in this
   PR, `'ibl5/docs/backlog/*.md'` (bin/check-docs line 20), follows the exact
   same convention and — confirmed empirically via a live `glob()` call in the
   worktree — correctly does **not** match
   `ibl5/docs/backlog/archive/maintenance-backlog-archive.md`. This matches
   the claim made in the new `ibl5/docs/backlog/README.md` ("Not part of this
   directory... Not governed by bin/check-docs") and in the archive file's
   own header ("Not governed by bin/check-docs (historical dead refs
   tolerated)"). Code and docs agree.

2. **Frontmatter / staleness rules satisfied.**
   Every touched or renamed doc (README.md, a11y-backlog.md,
   a11y-contrast-backlog.md, ci-backlog.md, e2e-backlog.md,
   maintenance-backlog.md, archive/maintenance-backlog-archive.md,
   0034-secret-scanning-gate.md, .claude/commands/plan.md) carries the
   required `description` + `last_verified` frontmatter fields per
   `.claude/rules/doc-freshness.md`, all bumped to 2026-07-03 (the PR edit
   date). Verified by running directly in the worktree:
   - `php bin/check-docs` → `139 docs verified.` (0 failures)
   - `php bin/check-docs --since=f529357bf4d76e7f764137735138ca9be23465c0`
     (merge-base) → `On-touch doc check passed`

3. **ADR-0034 path rewrite is compliant, not a "rewriting history" violation.**
   `ibl5/docs/decisions/0034-secret-scanning-gate.md` describes a *historical*
   incident ("password committed in the tree at
   `ibl5/docs/maintenance-backlog.md`") and the PR rewrites that path to the
   new location `ibl5/docs/backlog/maintenance-backlog.md`. This is directed
   by `bin/check-docs`'s own `REFERENCE_ALLOWLIST` comment
   (`bin/check-docs:41-42`): "Keep this list tiny — prefer fixing references
   over extending the allowlist." Leaving the stale path would have failed
   the dead-reference scan; allowlisting it would have violated that same
   comment's spirit. Fixing the reference is the correct, comment-directed
   choice.

4. **No relocation-guard comments violated.**
   Checked the pre-move blob of `ibl5/docs/maintenance-backlog.md` at the
   merge-base commit (`f529357bf`) for a "do not move this file" or similar
   header comment — none exists. No moved `.md` file in this PR carries such
   a guard.

## Scope note

Per instructions, did not expand into a repo-wide grep for stale references
to the old `ibl5/docs/maintenance-backlog.md` path outside doc-comment
context windows (e.g. PHP comments, workflow YAML) — that is outside the
"comments visible in the diff's context windows" charter for this review.
The full `bin/check-docs` scan already covers every in-scope doc file
(CLAUDE.md, rules, commands, ADRs, backlog docs), which is the governed
surface for this check.

## Files inspected

- /Users/ajaynicolas/GitHub/IBL5-worktrees/docs-backlog-reorg-archive-split/bin/check-docs
- /Users/ajaynicolas/GitHub/IBL5-worktrees/docs-backlog-reorg-archive-split/.claude/commands/plan.md
- /Users/ajaynicolas/GitHub/IBL5-worktrees/docs-backlog-reorg-archive-split/ibl5/docs/backlog/README.md
- /Users/ajaynicolas/GitHub/IBL5-worktrees/docs-backlog-reorg-archive-split/ibl5/docs/backlog/{a11y-backlog,a11y-contrast-backlog,ci-backlog,e2e-backlog,maintenance-backlog}.md
- /Users/ajaynicolas/GitHub/IBL5-worktrees/docs-backlog-reorg-archive-split/ibl5/docs/backlog/archive/maintenance-backlog-archive.md
- /Users/ajaynicolas/GitHub/IBL5-worktrees/docs-backlog-reorg-archive-split/ibl5/docs/decisions/0034-secret-scanning-gate.md
- /tmp/post-plan-diff-5051 (filtered diff, read in full)
