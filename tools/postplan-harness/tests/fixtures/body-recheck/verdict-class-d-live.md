REVIEW-COVERAGE: CURRENT

3. Revert the out-of-scope debounce rewrite in `.claude/rules/visual-review-prs.md`.
4. Note the debounce rewrite in the PR body's `visual-review-prs.md` bullet.
Passed: 1, 2, 5, 6.

## FINDINGS
- [BLOCKING] Revert the debounce prose rewrite in `.claude/rules/visual-review-prs.md`. The hunk at master lines 52-64 rewrites the `pages-deploy` debounce paragraph, which sits outside § Refreshing stale galleries (check 3).
- [NOTE] Extend the PR body's `visual-review-prs.md` bullet if the debounce rewrite stays. The Summary says the file was "updated to document the dual-shape contract", but it never mentions the debounce paragraph rewrite (check 4).

NOT READY

## DIGEST
**What changed:** The stale-gallery refresh tool now finds visual-review galleries linked by PR number as well as by commit SHA.
**Why:** Galleries move to `pr/<N>/visual-review`, and the old SHA-only parser marked every such PR as not visual and skipped it.
**Watch:** The select-mode JSON field `gallery_sha` is renamed to `gallery_dir`.
**Touches:** ibl5/tests/e2e/vr-refresh.ts, bin/vr-refresh-targets, .claude/rules/visual-review-prs.md
**Machine-authored fixes:** pending. Phase 6.5 has not run.
REVIEWED_TREE=45215479c6f2f6f877af511afdb2f0062618ecee
