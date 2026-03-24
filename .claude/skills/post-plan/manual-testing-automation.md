# Phase 6.5: Manual Testing Automation

Replace manual testing steps with automated verification wherever possible. This maximizes auto-merge eligibility and reduces reviewer burden.

### Step 1: Extract and classify manual testing steps

```bash
gh pr view --json body --jq '.body' | sed -n '/## Manual Testing/,/^## /p'
```

Classify each step into one of three categories:

1. **CLI-executable:** The step is a command or sequence of commands Claude can run directly (e.g., `bin/test --filter`, `composer run analyse`, `curl` requests, `bin/db-query`, PHP scripts). Most manual testing steps fall here.
2. **Test-replaceable:** The step checks behavior that a new Playwright/Vitest/PHPUnit test could verify permanently (page content, API responses, form flows).
3. **Truly manual:** The step requires subjective human judgment (visual aesthetics, "does it look right?", production data comparison against iblhoops.net).

### Step 2: Run CLI-executable steps directly

For each CLI-executable step, run the command(s) in the worktree and verify the output. Check for expected results, correct exit codes, and absence of errors.

```bash
cd <worktree>/ibl5 && <command from the manual testing step>
```

- If the step passes: mark it as verified
- If it fails: fix the code, commit, and re-run
- If it requires a running Docker environment, ensure `bin/wt-up` was run in Phase 6

### Step 3: Write tests for test-replaceable steps

For remaining test-replaceable steps, choose the right test type:

- **E2E (Playwright):** Page navigation, UI interactions, form submissions, content assertions, mobile layout
- **API (Vitest):** REST API endpoint responses, status codes, JSON structure, pagination, caching headers, authentication errors
- **Integration (PHPUnit):** Database queries returning correct results, service methods producing expected output
- **Unit (PHPUnit):** Calculations, validation logic, data transformations

Write the tests. Run them directly from the worktree:
```bash
# PHPUnit
cd <worktree>/ibl5 && vendor/bin/phpunit --filter "TestName"

# Vitest API (tests live in tests/api-e2e/)
cd <worktree>/ibl5 && BASE_URL=http://<slug>.localhost/ibl5 \
  bunx vitest run --config vitest.api.config.ts

# Playwright (must run from worktree to pick up new test files)
cd <worktree>/ibl5 && BASE_URL=http://<slug>.localhost/ibl5 \
  IBL_TEST_USER=<test-user> IBL_TEST_PASS=<test-pass> \
  bunx playwright test --grep "test name"
```

Fix until green. If a test cannot be made green after 2 attempts, reclassify the step as truly manual and move on.

### Step 4: Update PR description

After all CLI-executable steps are verified and new tests pass:

1. Commit and push any new tests
2. Remove all verified/automated steps from the Manual Testing checklist
3. If no manual steps remain, replace the entire section with:
   `No manual testing needed — all changes are covered by automated tests.`
4. Apply: `gh pr edit --body "<updated body>"`
