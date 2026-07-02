---
description: Brand-new VR views (gallery.newCells) are published inline at the top of the PR body — not only inside the sticky visual-review comment — via a marker-delimited, offset-0, idempotent managed block spliced in with `gh pr edit --body-file`, after a bounded readiness poll against the first new-screen image URL.
last_verified: 2026-07-02
---

# ADR-0076: Publish first-render screenshots of new VR views in the PR body

**Status:** Accepted
**Date:** 2026-07-02

## Lineage

Extends **ADR-0074** (change-driven gallery, the source of `gallery.json`'s pre-classified
`newCells`) — does NOT supersede it. ADR-0074 decided *how* new-vs-changed cells are classified;
this ADR decides an *additional surface* those already-classified new cells are published to.

## Context

A brand-new VR view (`gallery.newCells`) has no prior baseline, so there is nothing to diff — the
only useful review artifact is the first render itself. Today that render is reachable only via the
sticky `visual-review` comment and the per-SHA Pages gallery link, both a click away. A reviewer
scanning the PR body (the default landing view on GitHub, and what most reviewers glance at first)
sees no visual signal at all that a new screen was added, let alone what it looks like — they must
already know to open the comment.

Publishing the first-render images directly at the top of the PR body removes that extra click and
surfaces new-screen additions where reviewers are already looking. This is additive to, not a
replacement for, the sticky comment (which still carries the full changed/flake breakdown).

This adds a new bin-script surface (`--copy-new-screens`, `--update-pr-body` on
`bin/vr-review-comment`), a new pure module (`ibl5/tests/e2e/vr-pr-body.ts`), and a workflow rewire
in `.github/workflows/e2e-tests.yml` — a mechanical-enforcement surface per
`.claude/rules/visual-review-prs.md` — so an ADR is required. This ADR resolves the `bin/adr-check`
decision-trigger for those files; no `no-adr` bypass is needed.

## Decision

- **Selection**: only `gallery.newCells` (already pre-classified by `bin/vr-build-gallery` per
  ADR-0074) are published to the PR body. Changed and flake cells are not — they remain
  comment-only, since a changed/flake cell needs the before/after/diff triptych the gallery page
  provides, not a single image.
- **Managed block, offset 0, idempotent**: the new-screens section is wrapped in
  `<!-- vr-new-screens:begin -->` / `<!-- vr-new-screens:end -->` markers and always spliced at
  offset 0 (the very top of the body). `spliceBody` (`ibl5/tests/e2e/vr-pr-body.ts`) replaces an
  existing block found at offset 0, leaves a marker found elsewhere in the body untouched (treated as
  human prose, not the managed block), and removes the block entirely (restoring bare prose) when
  `newCells` is empty on a later run — so a screen that stops being "new" (baseline committed via
  `update-baselines`) cleans up after itself instead of leaving a stale block behind.
- **Deploy-tree copy**: `--copy-new-screens=DEST` copies each new cell's `<title>.after.png` from the
  gallery render tree into a `new-screens/` subdirectory of the Pages deploy tree, so the PR-body
  image URL (`https://a-jay85.github.io/IBL5/<sha>/visual-review/new-screens/<title>.png`) resolves
  once Pages finishes deploying.
- **Bounded readiness poll**: because the Pages deploy and the PR-body edit are separate workflow
  steps racing the same `peaceiris/actions-gh-pages` publish, `--update-pr-body` first polls the
  first new-screen image URL (HEAD request, up to 6 attempts, 5s backoff, ~30s cap) before editing
  the PR body. On timeout it edits anyway (stderr-warns "not live; editing anyway") rather than
  silently dropping the block — a briefly-404ing image that resolves moments later is preferable to
  never surfacing the new screen at all.
- **`--dry-run`**: prints the spliced body to stdout with no network calls (no poll, no `gh`
  invocation) — the only way to safely exercise the splice/strip logic against a real PR number
  without mutating it.
- **Both steps `continue-on-error: true`**: a copy or splice failure (e.g. a transient `gh` API
  error) must not fail the VR job or block the sticky comment step, since the PR-body block is a
  convenience surface, not the primary review artifact.

## Alternatives Considered

- **Only link to the gallery from the PR body (no inline image).** Rejected: reintroduces the
  extra-click problem this ADR exists to remove — a link doesn't let a reviewer see the render
  without navigating away from the PR body.
- **Publish all cells (changed + new) to the PR body, not just new ones.** Rejected: changed/flake
  cells need before/after/diff, which doesn't fit a single inline image; the gallery page already
  serves that comparison well. Scope creep into a redundant, worse-rendered copy of the sticky
  comment.
- **Append the block at the bottom of the body instead of offset 0.** Rejected: a reviewer's first
  glance is the top of the body; appending at the bottom reintroduces the "you have to already know
  to scroll" friction this ADR is meant to fix.
- **Overwrite the whole PR body unconditionally instead of a managed, offset-0 block.** Rejected:
  would clobber the PR author's own description text every run. The marker-delimited splice
  preserves everything the author wrote.

## Consequences

- **Positive:** New-screen renders are visible on the PR body itself, with zero extra clicks —
  reviewers see what changed as they read the description.
- **Positive:** The block is self-cleaning — once a new screen's baseline is committed
  (`update-baselines`), the next run's empty `newCells` strips the block automatically, so the PR
  body never accumulates stale first-render images.
- **Positive:** Splice/strip logic (`spliceBody`, `normalizeBody`, `buildNewScreensSection`,
  `buildCopyPlan`, `newScreenUrl`) is pure and unit-tested
  (`ibl5/tests/ts-unit/vr-pr-body.test.ts`, 18 cases) — the only I/O (`gh pr view`/`gh pr edit`,
  `fetch`, `fs` copy) lives in the `bin/vr-review-comment` glue layer.
- **Negative:** The readiness poll adds up to ~30s to the workflow's critical path when Pages is
  slow to publish (bounded, and `continue-on-error` means a timeout degrades to a possibly-404
  image rather than failing the job).
- **Negative:** A second I/O surface (`gh pr edit`) against the PR object itself, distinct from the
  existing sticky-comment surface — a bug here mutates the PR body, not just a comment, which is
  harder for an author to simply delete and ignore. Mitigated by the idempotent, marker-scoped
  splice (never touches text outside the managed block) and by `--dry-run` for safe local testing.

## References

- `bin/vr-review-comment` — `--copy-new-screens`/`--renders-dir` (copy mode) and
  `--update-pr-body`/`--dry-run`/`--body-file-in` (update-body mode).
- `ibl5/tests/e2e/vr-pr-body.ts` — pure `buildCopyPlan`, `buildNewScreensSection`, `newScreenUrl`,
  `normalizeBody`, `spliceBody`.
- `ibl5/tests/ts-unit/vr-pr-body.test.ts` — unit coverage for the pure module.
- `.github/workflows/e2e-tests.yml` — "Copy new-screen renders into gallery deploy tree" and
  "Splice new-screen images into PR body" steps.
- `ibl5/docs/decisions/0074-vr-change-driven-review.md` — source of `gallery.json`'s `newCells`.
