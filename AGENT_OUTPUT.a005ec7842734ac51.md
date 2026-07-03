# PR #1303 Review — Docs Backlog Reorg / Archive Split

Reviewed as Senior PHP Architect / Staff Engineer. No PHP/SQL in diff — Section 3 (DB performance) skipped per instructions.

Diff source: /tmp/post-plan-diff-5051 (worktree: /Users/ajaynicolas/GitHub/IBL5-worktrees/docs-backlog-reorg-archive-split/ibl5)

## Section 1: Architectural Fitness

1. **Scope is clean for 8 of 10 files.** Commit stats confirm exactly the 10 files claimed: 4 pure renames (`a11y-backlog.md`, `ci-backlog.md`, `e2e-backlog.md`, `a11y-contrast-backlog.md`), 1 new `README.md`, 1 new LIVE `maintenance-backlog.md`, 1 new archive file, plus the two inbound-reference fixes (`plan.md`, ADR-0034). No unrelated files touched. `git show fd1ebd81d` confirms `a11y-backlog.md` was a byte-identical rename (0 insertions/deletions).

2. **Scope leak — e2e-backlog.md carries content-reasoning edits, not just relocation housekeeping** (diff lines 94-97, 106-109 of /tmp/post-plan-diff-5051). Beyond the required `last_verified` bump (forced by the move, line 78), the PR adds a new "Planning note" paragraph about `auto_merge: false` axis-packaging guidance (lines 94-97) and reclusters finding D6 from `D-cluster-1` into `D-cluster-2` with a rationale sentence (lines 106-109). Neither is required by a docs move — this is a drive-by content change riding on a reorg PR. Low risk (prose-only, rationale is well-documented in the commit message and diff itself) but worth flagging against the "clean scope" bar.

3. **Split/relocation design is sound.** LIVE vs. archive split follows one clear invariant: LIVE holds only open work (⬜/◑/📋), archive holds resolved work (✅/🚫). Verified via per-finding `**Status:**` marker sampling (see Section 2 #2) — no open-status finding leaked into archive and no resolved-status finding was left in LIVE.

4. **README.md index is well-formed.** Table rows map 1:1 to the 5 backlog files actually present in `ibl5/docs/backlog/`. The "Archive" section correctly documents that `archive/` is out of scope for `bin/check-docs` "by construction" (glob `ibl5/docs/backlog/*.md` doesn't match `archive/*.md` — confirmed via PHP `glob()` in `bin/check-docs` collectFiles(), non-recursive). "Not part of this directory" section correctly documents the gitignored `security-backlog.md` exclusion.

5. **`bin/check-docs` glob addition is minimal and correct** (added line: `'ibl5/docs/backlog/*.md'`). Brings the 5 relocated LIVE backlogs under doc-freshness/dead-ref governance while leaving `archive/` ungoverned, matching stated intent.

## Section 2: Bug Detection

1. **No dropped or duplicated findings in the split (verified empirically, not assumed).** Diffed full set of `### ` finding headers from the pre-move blob (`git show f529357bf:ibl5/docs/maintenance-backlog.md`, 317 headers) against the union of the new LIVE file + archive file headers (317 headers) — `diff` returned **empty**. Every finding ID from the original appears in exactly one of the two new files: none lost, none duplicated. Line-count delta (2929 original vs. 1167+1817=2984 new, +55 lines) is fully explained by the shared preamble/legend block being duplicated across both files for standalone readability — not corruption.

2. **Status-marker discipline held across the split.** Sampled all `**Status:**` line-openers: LIVE's are exclusively "Partial", "Open", "In progress", "Deferred" (in-progress language); archive's are exclusively "Completed/Implemented/Resolved/Declined/Rule landed/Documented/Gated" (closed language). No resolved finding was missed in LIVE and no open finding leaked into archive.

3. **BUG — dangling inbound references not fixed by this PR**: `tests/e2e/smoke/accessibility.spec.ts` lines 10, 14, 56, 60, 75, 82, 87, 92, 96, 101. Ten comments still point at pre-move paths `ibl5/docs/a11y-backlog.md` and `ibl5/docs/a11y-contrast-backlog.md` (now moved to `ibl5/docs/backlog/`). PR's stated goal is fixing inbound refs to the old path, but only `.claude/commands/plan.md` and ADR-0034 were covered — this file was missed. Repo-wide grep for all 5 old paths across every file type found no other dangling references besides this one file. Not a CI-breaker (`.ts` isn't in `bin/check-docs`'s `IN_SCOPE_GLOBS` → silent doc-rot, not build failure) — but leaves the PR's own stated inbound-reference-fix incomplete. **Recommend adding this file's path updates to this PR (or immediate fast-follow).**

4. **No content corruption in the two large files the filtered diff truncated as "(binary or too large)".** Manually inspected `ibl5/docs/backlog/maintenance-backlog.md` (head/tail) and `ibl5/docs/backlog/archive/maintenance-backlog-archive.md` (head) directly — both have correct, distinct frontmatter (`description`/`last_verified: 2026-07-03`), correct headers, and archive correctly back-links to `../maintenance-backlog.md` for open items.

5. **`bin/check-docs` runs clean against the new tree**: `php bin/check-docs` → `139 docs verified.` (no dead-ref, frontmatter, or staleness failures).

6. **README.md's own relative links resolve** (`maintenance-backlog.md`, `ci-backlog.md`, `e2e-backlog.md`, `a11y-backlog.md`, `a11y-contrast-backlog.md`, `archive/`) — all verified to exist on disk via direct filesystem check. Note: `bin/check-docs`'s `scanReferences()` only flags link/backtick targets starting with `REFERENCE_PREFIXES` (`bin/`, `ibl5/`, `.claude/`, `.github/`, `docs/`, `migrations/`) — bare relative links like `archive/` are outside that check's reach, so this required manual verification, not tool coverage.

## Overall

Split mechanics are sound and provably lossless (finding #1 above is the load-bearing check). The one real defect is the incomplete inbound-reference sweep (#3) — `tests/e2e/smoke/accessibility.spec.ts` should be added to this PR or a fast immediate follow-up to fully satisfy the PR's own stated purpose. The e2e-backlog content edit (#2, Section 1) is a minor scope note, not a blocker.
