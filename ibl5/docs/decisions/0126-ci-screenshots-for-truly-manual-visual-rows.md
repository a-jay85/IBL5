---
description: Truly-manual look-and-feel rows carry a `vr:` cell; CI reaches the state via test-state.php, publishes the PNG to the per-SHA Pages tree, and splices it under the matching Manual Testing bullet.
last_verified: 2026-09-14
---

# ADR-0126: CI screenshots for truly-manual visual rows

**Status:** Accepted
**Date:** 2026-09-14

## Context

The visual-regression pipeline only covers screens already registered as VrRows in
`ibl5/tests/e2e/vr-manifest.ts` and rendered by `ibl5/playwright.visual.config.ts`. A screen this
PR *newly builds or redesigns* is not in that manifest, so it reaches the reviewer as a bare text
checkbox — `- [ ] **Row N** — <text>`, emitted by `tools/postplan-harness/harness/manual_rows.py` —
with no image attached. To answer "does this look right?" the reviewer must stand the worktree
Docker stack up by hand, which is exactly the friction that makes look-and-feel rows go unchecked.

One structural constraint shapes every decision below: the plan file lives at
`~/claude-plans/<branch>.md`, **outside the repo**, so CI can never read it. The only CI-reachable
carrier of a Truly-manual row is the PR body's `## Manual Testing` section, read via
`gh pr view --json body` — already how `bin/lib/pr-armable.sh` and `bin/check-pr-manual-testing`
reach those rows.

## Decision

1. **A `vr:` cell is the source of truth.** A Truly-manual Verification Matrix row that needs a
   screenshot carries a `vr:` token in its *Test file / location* cell — the cell `bin/check-plan`'s
   `matrix_split` exposes as `MC_LOC`. The token is self-describing: it names the label, the auth
   role, the setup request(s), and the path to visit, so no lookup table anywhere can drift out of
   sync with it.

2. **"Look-and-feel" is a lexicon match on the *What to verify* cell**, not a new column and not a
   human judgement at gate time. Gate `[3]` already forbids `verify|check that|confirm|ensure` in
   that cell on a truly-manual row, so the lexicon is tuned to the vocabulary that actually
   survives there: `look`, `feel`, `visual`, `appearance`, `layout`, `spacing`, `legible`,
   `readable`, `UI`, `UX`, `design`, `renders`, `styling`. A single documented escape —
   `no-vr: <reason>` in the location cell — is the only opt-out.

3. **Screenshots are published as Pages content, never as workflow artifacts.**
   `actions/upload-artifact` URLs are not renderable in markdown, so an artifact link can never
   back an inline `![](...)`. The capture step writes into the `manual-rows/` subdirectory of the gallery build tree, and the
   existing "Deploy VR gallery to per-SHA GitHub Pages" step publishes that directory unchanged.
   The stable name is `<pages-url>manual-rows/<label>.png`, where `<label>` is the slug from the
   `vr:` cell — identical across re-runs of the same SHA, and addressable before the image exists.

4. **The comment is a new sticky PR comment, not a PR-body splice.** A second body splicer would
   contend with `/post-plan` Phase 6's `gh pr edit --body` rewrite and with the existing
   `--update-pr-body` new-screens block. `marocchino/sticky-pull-request-comment@v3` with a
   distinct `header:` key gives idempotency by construction — the same mechanism the existing
   `header: visual-review` comment uses.

5. **The builder extends `bin/vr-review-comment`; no new `bin/` script is created.**
   `.claude/rules/meta-tooling-bar.md` sets extend-before-add, and the script is already a
   multi-mode dispatcher (`--gallery`, `--coverage`, `--copy-new-screens`, `--update-pr-body`) that
   owns the `gh pr view` / `gh pr edit --body-file` and `--pages-url` plumbing. Two modes are
   added; the pure logic lands in the new `vr-manual-rows.ts` module beside
   `ibl5/tests/e2e/vr-pr-body.ts`, unit-tested exactly as `ibl5/tests/ts-unit/vr-pr-body.test.ts`
   tests its sibling.

Mechanical enforcement: `bin/check-plan` gate `[Q]` fails any plan whose Truly-manual row matches
the look-and-feel lexicon and carries neither a `vr:` cell nor a `no-vr:` reason.

## Alternatives Considered

- **`actions/upload-artifact` instead of Pages** — attach the PNGs as a workflow artifact. Rejected
  because: artifact URLs are not markdown-renderable, so they can never back an inline `![](...)`.
- **A new dedicated `vr-manual-comment` builder script** — a standalone builder beside `bin/vr-review-comment`.
  Rejected because: `.claude/rules/meta-tooling-bar.md` sets extend-before-add, and it would
  duplicate the `gh`/Pages plumbing and re-trigger the ≥50-line `bin/` ADR clause.
- **Splicing the images into the PR body** — a second body splicer. Rejected because: it contends
  with `/post-plan` Phase 6's `gh pr edit --body` rewrite and with the ADR-0076 new-screens block.
- **A new `vr` column in the Verification Matrix** — a sixth matrix column. Rejected because:
  `bin/check-plan`'s `matrix_split` reads a fixed 5-column shape and counts pipes, so a sixth
  column would invalidate every existing plan at once — the opposite of additive.

## Consequences

- Positive: a reviewer judges a newly built screen from the PR page itself, with no local Docker
  stack, closing the gap that made look-and-feel rows go unchecked.
- Positive: the gate is additive — every pre-existing plan and every non-visual truly-manual row
  passes unmodified; only a newly authored look-and-feel row owes a `vr:` cell.
- Positive: a wrong `vr:` cell degrades to a missing image plus a named failure line in the
  comment, never a red Visual Regression job — the capture and comment steps carry
  `continue-on-error: true`, mirroring the two new-screens steps.
- Negative: a plan author who writes a look-and-feel row now owes a `vr:` cell or a typed
  `no-vr:` reason, and the capture step lengthens the Visual Regression job.
- Negative: the screenshots inherit the Pages retention and public-visibility properties that
  ADR-0074 and ADR-0076 already accepted for the gallery.

## References

- `ibl5/docs/decisions/0074-vr-change-driven-review.md` — the change-driven gallery
  selection this decision extends, unchanged.
- `ibl5/docs/decisions/0076-vr-new-screens-in-pr-body.md` — the per-SHA Pages tree this decision
  reuses as the publishing channel, unchanged.
- `.claude/rules/visual-review-prs.md` — names the VR selection surface and the PR-body
  new-screens splice as mechanical-enforcement surfaces requiring an ADR.
- `.claude/rules/meta-tooling-bar.md` — the extend-before-add bar behind decision 5.
- `bin/check-plan` — gate `[Q]`, the authoring-time enforcement of decision 2.
- `bin/vr-review-comment` — the multi-mode dispatcher the two new modes extend.
- `tools/postplan-harness/harness/manual_rows.py` — renders the `## Manual Testing` bullets the
  `vr:` cell must survive into.
- `ibl5/test-state.php` — the action dispatcher the capture step drives to reach each UI state.

## Addendum — Phases 5, 7, and 8 deferred to follow-up PRs (2026-09-14)

The PR that shipped Phases 1–4 and 6 of the backing plan did not include Phases 5, 7, or 8. Phase 5.5 plan-intent fidelity review identified this as an undeclared omission.

**What shipped (Phases 1, 2, 3, 4, 6):** gate `[Q]` in `bin/check-plan`; `bin/vr-review-comment` builder modes `--manual-rows-from-pr` and `--manual-gallery`; `ibl5/tests/e2e/vr-manual-rows.ts` and `ibl5/tests/ts-unit/vr-manual-rows.test.ts`; `tools/postplan-harness/harness/manual_rows.py` and `tools/postplan-harness/tests/test_manual_rows.py`.

**What is deferred:**
- **Phase 5** — Playwright config `ibl5/playwright.manual-rows.config.ts` (example) and E2E spec `ibl5/tests/e2e/manual-rows.spec.ts` (example); `ibl5/test-state.php` unknown-action 400 guard.
- **Phase 7** — CI workflow steps in `.github/workflows/e2e-tests.yml` for capture, Pages publish, and sticky-comment invocation (the `bin/vr-review-comment --manual-gallery` caller).
- **Phase 8** — Tooling-doc updates in `.claude/rules/visual-review-prs.md` (example) and plan-skill docs; end-to-end local rehearsal.

**Consequence while deferred:** Gate `[Q]` is live and requires `vr:` cells from plan authors, but no CI step captures or publishes the screenshots those cells describe. The `## Consequences` bullet "a reviewer judges a newly built screen from the PR page itself" describes the end-state of the full pipeline; it does not yet hold. The References entry naming `ibl5/test-state.php` as "the action dispatcher the capture step drives" is accurate in intent but the capture step does not yet exist.
