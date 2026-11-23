---
description: Diff-scoped gate bin/check-adr-append-only fails a PR that rewrites or removes frozen ADR text (Context, Decision, Rationale, Alternatives, frontmatter description), with a PR-body bypass marker and a CI split between the harness and the PR-diff run.
last_verified: 2026-11-21
---

# ADR-0272: ADR append-only gate

**Status:** Accepted
**Date:** 2026-11-20
**Deciders:** Repo owner (backlog#1607)

## Context

`.claude/rules/adr-append-only.md` freezes the Context, Decision, Rationale and Alternatives sections of an accepted ADR. Enforcement was prompt-only: the docfix prompt and the rule doc told agents to append an Addendum, and nothing in CI checked it. The rule failed in practice. PR 3367 rewrote ADR-0104 Decision item 2 in place. PR 3373 rewrote the Decision text of ADR-0013 and ADR-0169. PR 3637 rewrote the ADR-0169 frontmatter `description`. A replay of this gate over master history found frozen text changed in 81 of the 279 first-parent commits that modified an ADR file.

## Decision

Add `bin/check-adr-append-only --since=<base-ref> [--head=<rev>]`, a diff-only bash gate. It reads `git diff -U0` against the merge-base, maps each changed old-side line to its nearest preceding `## ` heading on the BASE blob, and fails a rewrite or removal inside the frozen set. A pure insertion always passes, so appending an Addendum never trips it.

The frozen set is the headings Context, Decision, Decisions, Decision N, Rationale, Alternatives Considered, Alternatives Rejected and Alternatives, plus the frontmatter `description`. The gate lowercases a heading and cuts it at the first `(`, `:`, ` - ` or em dash before an exact match, so `## Contextual notes` and `## Decision Drivers` stay free. A heading containing `addendum`, `update` or `correction` is never frozen. `### ` subheadings inherit their parent. A `## ` line inside a code fence is not a heading. The preamble, Consequences, References and every other frontmatter key are free, so a `last_verified` bump passes.

The allowed edits are the rule doc's list and nothing wider: trailing-whitespace changes, a `[CORRECTED ...]` or `[SUPERSEDED ...]` stamp appended to a line with no other new text, and a dead-path repair. In a dead-path repair the old backtick span names a path that no longer exists at the head side, and the new span names a path that does or adds ` (example)`. A span resolves from the repo root or from `ibl5/`. A typo or wording fix is flagged and goes in an Addendum.

A new ADR passes, a deleted ADR fails, a pure rename passes, and a rename with edits is diffed from the old path to the new one. A rename out of the top-level `NNNN-*.md` set fails like a deletion. `--since` is required and never derived from repo state. CI passes the PR base SHA, so a stacked PR is compared with its parent branch head and the parent's ADR edits are not flagged again on the child.

The bypass is `<!-- adr-edit-ok: NNNN <reason of 15+ chars> -->` in the PR body, one marker per ADR number, read from the `PR_BODY` env var. It mirrors the `no-adr:` marker, and `pr-meta-checks.yml` already re-runs on `edited`. A marker kept in the file was rejected because it would exempt every later edit of that ADR.

The gate fails the same-line numeric ADR corrections that `bin/docfix-check-veronly` arms. The rule forbids them, and the docfix prompt already says to append an Addendum, so a red check only stops an edit that ignored it. `bin/docfix-check-veronly` is unchanged.

The harness `bin/test-check-adr-append-only` runs in the `static-guards` job of `tests.yml`. The PR-diff run is a step of `pr-meta-checks.yml`, with `PR_BODY` passed through `env` and never through `${{ }}` inside `run:`. This is the split ADR-0258 set. No new job and no branch-protection change.

## Alternatives Considered

- Extend `bin/check-docs`. Rejected because the file is large and hot, its ADR check only reads status lines, and a hunk-to-section parser is a separate job.
- Extend `bin/adr-check`. Rejected because it answers "does this PR need an ADR", a different question.
- Map changed lines to sections on the new side. Rejected because a rewrite that also renames `## Decision` to `## Addendum` would escape. The cost of the old-side map is that an insertion inside a frozen section passes, which keeps every Addendum append green.
- Flag a description only when it overlaps the Decision text. Rejected because it needs a tuned threshold and misses paraphrased decision text. The `description` is frozen unconditionally and the bypass covers the rare legitimate change.
- A commit-trailer bypass. Rejected because post-plan squashes and rewrites commit messages, and owners edit PR bodies, not commits.
- A whole-tree audit. Rejected because it has no base to compare against and the historical edits would need a grandfather list.

## Consequences

- Positive: the rule is now enforced by a required check instead of an agent prompt.
- Positive: the gate is diff-only, so the historical violations never block a PR.
- Negative: a docfix PR that rewrites frozen numerics now fails `Meta checks` until it appends an Addendum or carries the bypass marker.
- Negative: an insertion inside a frozen section passes, so a new line spliced into the middle of a Decision is not caught.
- Corpus replay: the gate ran once per first-parent master commit that modified a top-level ADR file, 525 commits, with `PR_BODY` unset. 442 pass (exit 0), 83 fail (exit 1), none exit 2. The 158 findings split into 143 rewrites, 14 removals and 1 deleted file, and the 157 rewrite and removal findings split by section: 57 frontmatter `description`, 65 Decision, 18 Context, 17 Alternatives Considered.
- Corpus audit: 66 flagged commits were read, which is all 56 whose only finding is one line plus 10 random others from the 27 with more. 0 were false positives. Every one rewrote or removed frozen text with no allowed shape. The 57 description findings are the largest class, and all are real edits of a line the rule freezes, so they need the bypass marker or an Addendum.

## References

- backlog#1607
- [`.claude/rules/adr-append-only.md`](../../../.claude/rules/adr-append-only.md): the rule this gate enforces.
- [`0258-exit-code-handling-gate.md`](0258-exit-code-handling-gate.md): the harness and PR-diff wiring precedent.
- [`0104-verification-only-doc-refresh-auto-merge.md`](0104-verification-only-doc-refresh-auto-merge.md): the docfix path that arms numeric ADR corrections.
- [`bin/check-adr-append-only`](../../../bin/check-adr-append-only): the gate.
- [`bin/test-check-adr-append-only`](../../../bin/test-check-adr-append-only): the fixture harness.
