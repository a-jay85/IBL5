Phase 4B did not run in this run. No structured code review covers this head.
REVIEW-COVERAGE: NONE

3. [GATE-EDIT] The PR changes when `/pr-burndown` rebases, in a `.claude/skills` file no plan asked for.
Not assessable — plan-blind run: 1, 2, 5. Passed: 4, 6.

## FINDINGS
- [BLOCKING] [GATE-EDIT] [unplanned-scope] Send the pr-burndown rebase-rule change to a second reviewer. The diff edits `.claude/skills/pr-burndown/SKILL.md` at lines 86-89, 198-200, 221-245 and 309-310, and no plan covers it. The fix was body-only, and a body edit cannot clear this finding. Check 3 treats a path under `## Declared scope` as in scope, but it still applies the blocking surfaces, and a `.claude/skills` path is one of them. The change looks safe. It adds or removes no merge gate and leaves `_ARMING_PATHS` alone. Verdict 1 confirmed that `bin/merge-line-select` skips `DIRTY` PRs, orders hotfix PRs first and then oldest armed first, and that `.github/workflows/update-behind-prs.yml` runs on every push to master. Next action: a second reviewer confirms the new rebase policy.

## SHADOW-ARMING
```json
{
  "schema": "fidelity-shadow/1",
  "planned_landed": {"hold": false, "missing": []},
  "merge_danger":   {"door": "two-way", "hold": false, "why": "only prose in .claude/skills/pr-burndown/SKILL.md changes; reverting the merge commit restores it"},
  "body_numbers":   {"consistent": true, "mismatches": []}
}
```

NOT READY

## DIGEST
**What changed:** `/pr-burndown` now rebases only PRs with a merge conflict and leaves PRs that are just behind master to the merge line.
**Why:** Rebasing several approved PRs at once wasted CI runs, because master takes one PR and the rest fall behind again.
**Watch:** A `BEHIND` PR that post-plan never arms now stays behind master until someone rebases it, because `.github/workflows/update-behind-prs.yml` updates only armed PRs.
**Touches:** `.claude/skills/pr-burndown/SKILL.md`
**Machine-authored fixes:** Phase 6.5 edited only the PR body, adding a `## Declared scope` section and rewording its owner-request clause to "owner request not recorded in this PR".
REVIEWED_TREE=dfec8a90c1f1cbb3212978d88ea7b2f5b56e75b1