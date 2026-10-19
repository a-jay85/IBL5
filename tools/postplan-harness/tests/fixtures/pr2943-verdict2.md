**NOT READY** (re-review, round 1) — 2026-10-19 04:00:15 PDT · auto-merge held

Auto-merge held:
- (1) manual-testing-clearance — state=HELD
- (7) plan-auto-merge-hold — plan declares auto_merge: false
- (12) plan-fidelity-verdict — fidelity verdict='NOT READY' (source: verdict-2); need READY or READY WITH NOTES
- (14) conflict-auto-resolved — this branch carries a /post-plan auto-resolved rebase conflict — conflict-review verdict=ABSENT; need CONFLICT-REVIEW=CLEAN
- (18) merge-danger — state=MISSING

**Code review:** Phase 4B ran in this run, and a /code-review comment was posted at 2026-10-15T18:35:22Z ([comment](<https://github.com/a-jay85/IBL5/pull/2943#issuecomment-6199477991>)): No issues found.

### Still open (blocking)
- [ ] Restore the stuck-run sentence in visual-review-prs.md step 3. Verdict 1 flagged this under check 2. The remediation was body-only, so `.claude/rules/visual-review-prs.md` still drops "A not-started run older than an hour counts as stuck" (diff lines 34-35). The plan says "Keep the debounce sentences unchanged." Both dispatch steps in `vr-pr-screens.yml` still cancel runs older than 3600 seconds (diff lines 995-1005 and 1120-1130), so the rule now leaves out something the code does. This matches check 2's blocking clause. Next step: put the sentence back in step 3 with a commit, because a body edit cannot fix a repo file.
- [ ] Phase 7 keeps master's `VRCTX_PUBLISH`/`VRCTX_SAME_REPO` guard in `e2e-tests.yml` where the plan named a `pull_request` guard. This is the better route, because refresh-mode runs can publish to `pr/<N>/visual-review/`. (not filed)
- [ ] Phase 4: `fetch_tip` in `bin/vr-pages-publish` does a full fetch when the clone is not shallow. This is the better route, because a shallow fetch would damage a full clone. (not filed)
- [ ] `.gitleaks.toml` adds two allowlist regexes. Each matches one fixed test string, and the PR body says so. (not filed)
- [ ] `ibl5/tests/ts-unit/vr-pr-screens-cli.test.ts` is an extra test file that covers the `bin/vr-review-comment` screens modes. (not filed)
- [ ] `vr-pr-screens.yml` adds a zero-cell path. It serves the plan's goal of a fast run for PRs with no `vr:` rows. (not filed)
- [ ] The render job in `vr-pr-screens.yml` gets `packages: read` to pull the ghcr image. The plan did not list this permission. (not filed)
- [ ] Manual Testing Row 40 in the PR body is a CLI prose check and is still unticked. Next step: run `bin/check-prose` on `ibl5/docs/decisions/0179-vr-pr-keyed-screens.md` and the other touched docs, then tick Row 40. (not filed)
- [ ] Manual Testing Row 42 in the PR body is Truly-manual and unticked, so the PR does not claim a person checked it. (not filed)
- [ ] The sticky merge-digest **Watch:** line in the PR body still says the `vr-pr-screens.yml` dispatch steps "never cancel a stuck pages-deploy run". The diff shows they do cancel. This block is runner-written, so the runner should replace it with the digest below. (not filed)

<details><summary>Reviewer verdict</summary>

Prior blocker is still open; writing the re-review verdict now.

Phase 4B did not run in this run. The packet sets `PHASE_4B_RAN: no`, and that value is authoritative. Verdict 1 gave a 4B review date, but this run's probe did not report it.

2. Phase 9c deleted the stuck-run sentence from visual-review-prs.md, and the body-only fix left it out.
3. Five changes the plan did not ask for. None touch a blocking surface.
5. Row 40 is unticked. Row 42 is Truly-manual and is not claimed as done.
Passed: 1, 4, 6.

NOT READY

## FINDINGS
- [BLOCKING] Restore the stuck-run sentence in visual-review-prs.md step 3. Verdict 1 flagged this under check 2. The remediation was body-only, so `.claude/rules/visual-review-prs.md` still drops "A not-started run older than an hour counts as stuck" (diff lines 34-35). The plan says "Keep the debounce sentences unchanged." Both dispatch steps in `vr-pr-screens.yml` still cancel runs older than 3600 seconds (diff lines 995-1005 and 1120-1130), so the rule now leaves out something the code does. This matches check 2's blocking clause. Next step: put the sentence back in step 3 with a commit, because a body edit cannot fix a repo file.
- [NOTE] Phase 7 keeps master's `VRCTX_PUBLISH`/`VRCTX_SAME_REPO` guard in `e2e-tests.yml` where the plan named a `pull_request` guard. This is the better route, because refresh-mode runs can publish to `pr/<N>/visual-review/`.
- [NOTE] Phase 4: `fetch_tip` in `bin/vr-pages-publish` does a full fetch when the clone is not shallow. This is the better route, because a shallow fetch would damage a full clone.
- [NOTE] `.gitleaks.toml` adds two allowlist regexes. Each matches one fixed test string, and the PR body says so.
- [NOTE] `ibl5/tests/ts-unit/vr-pr-screens-cli.test.ts` is an extra test file that covers the `bin/vr-review-comment` screens modes.
- [NOTE] `vr-pr-screens.yml` adds a zero-cell path. It serves the plan's goal of a fast run for PRs with no `vr:` rows.
- [NOTE] The render job in `vr-pr-screens.yml` gets `packages: read` to pull the ghcr image. The plan did not list this permission.
- [NOTE] Manual Testing Row 40 in the PR body is a CLI prose check and is still unticked. Next step: run `bin/check-prose` on `ibl5/docs/decisions/0179-vr-pr-keyed-screens.md` and the other touched docs, then tick Row 40.
- [NOTE] Manual Testing Row 42 in the PR body is Truly-manual and unticked, so the PR does not claim a person checked it.
