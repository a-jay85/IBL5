REVIEW-COVERAGE: CURRENT

4. The PR body Summary says `skip_note` "consolidates all eleven SKIP paths", but the diff converts twelve. This is blocking under check 4, because the diff contradicts a hand-written count. Change "eleven" to "twelve" in the body.
Passed: 1, 2, 3, 5, 6.

## FINDINGS
- [BLOCKING] Check 4: fix the SKIP-path count in the PR body. The Summary bullet for `bin/cleanup` says "all eleven SKIP paths", but the diff routes twelve `check_worktree` SKIP sites through `skip_note`.

NOT READY

## DIGEST
**What changed:** Asking the cleanup script to remove one named worktree now fails with exit code 3 and a stated reason when it refuses.
**Why:** The doc-fix poller logged "reaped" for branches that cleanup had quietly refused to remove, because a skipped named target still exited 0.
**Watch:** The comment at `bin/docfix-poll:272` still says "bin/cleanup exits 0 on a SKIP too".
**Touches:** bin/cleanup, bin/docfix-poll, bin/test-cleanup-safety, bin/test-docfix-poll
**Machine-authored fixes:** pending. Phase 6.5 has not run.
REVIEWED_TREE=45215479c6f2f6f877af511afdb2f0062618ecee
