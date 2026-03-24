# Phase 6.5: Manual Testing Automation

Replace manual testing steps with automated tests wherever possible. This maximizes auto-merge eligibility and reduces reviewer burden.

### Step 1: Extract and review manual testing steps

```bash
gh pr view --json body --jq '.body' | sed -n '/## Manual Testing/,/^## /p'
```

For each manual testing step, determine whether it can be **sufficiently replaced** by a combination of Playwright E2E tests, PHPUnit integration tests, or unit tests. A step is replaceable if automated tests can verify the same behavior the manual step is checking. A step must stay manual only if it requires subjective human judgment (visual aesthetics, "does it look right?", production data comparison against iblhoops.net).

### Step 2: Write tests for replaceable steps

For each replaceable step, choose the right test type:

- **E2E (Playwright):** Page navigation, UI interactions, form submissions, content assertions, mobile layout
- **Integration (PHPUnit):** Database queries returning correct results, service methods producing expected output
- **Unit (PHPUnit):** Calculations, validation logic, data transformations

Write the tests. Run them directly from the worktree:
```bash
# PHPUnit
cd <worktree>/ibl5 && vendor/bin/phpunit --filter "TestName"

# Playwright (must run from worktree to pick up new test files)
cd <worktree>/ibl5 && BASE_URL=http://<slug>.localhost/ibl5 \
  IBL_TEST_USER=<test-user> IBL_TEST_PASS=<test-pass> \
  bunx playwright test --grep "test name"
```

Fix until green. If a test cannot be made green after 2 attempts, reclassify the step as manual and move on.

### Step 3: Update PR description

After all new tests pass:

1. Commit and push the new tests
2. Remove the now-automated steps from the Manual Testing checklist
3. If no manual steps remain, replace the entire section with:
   `No manual testing needed — all changes are covered by unit and E2E tests.`
4. Apply: `gh pr edit --body "<updated body>"`
