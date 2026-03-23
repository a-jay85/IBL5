---
name: post-plan
description: Single orchestrator for post-plan Phases 3-9. Runs simplify, commit/push/PR, code review, security audit, verification, CI monitoring, retrospective, and worktree teardown as one uninterrupted sequence.
---

# Post-Plan Orchestrator (Phases 3-9)

Execute all phases below **sequentially in a single response**. Do NOT stop, ask for input, or return control between phases. Each phase flows directly into the next.

---

## Phase 3: Simplify

Review all changed files (use `git diff --name-only HEAD~1` or compare against the base branch) for:

1. **Reuse** — Is there existing code that does the same thing? Check for duplicate logic in `classes/`, utility methods in `Utilities\`, and common repository helpers in `CommonMysqliRepository`.
2. **Quality** — Are there PHPStan violations, missing strict_types, missing type declarations, loose comparisons (`==`/`!=`), or missing `HtmlSanitizer::e()` on output?
3. **Efficiency** — Unnecessary loops, redundant queries, over-engineering, or abstractions for one-time operations?

Fix any issues found directly in the worktree files before proceeding.

---

## Phase 4: Commit, Push & PR

1. Stage all relevant changes: `git add -A` (or selectively stage only task-related files)
2. Review staged changes: `git diff --staged`
3. Create a commit with conventional format: `<type>: <short summary>` followed by `## Section` headers with bullet points
4. Push the branch: `git push -u origin <branch-name>`
5. Create a PR: `gh pr create --fill` (or with explicit `--title` and `--body`)

**Stacked PRs:** If the current branch was created from another feature branch (not `master`), set `--base <parent-branch>` so the PR targets the parent branch instead of master.

**Manual testing in PR description:** Always include a "Manual Testing" section. If automated tests (PHPUnit + Playwright) fully cover the changed behavior, write: `No manual testing needed — all changes are covered by unit and E2E tests.` Otherwise, list only steps that automated tests don't cover as a markdown checklist (`- [ ]`) — minimal, no redundancy with automated checks.

**Model guidance:** Use Haiku agents for the commit message generation if delegating.

---

## Phase 5: Code Review + Security Audit

Run both the code review and security audit processes below. They can share the PR data fetched once.

### 5A: Fetch PR data (shared by both review and audit)

Run these commands yourself (not via agents):

```bash
# PR metadata
gh pr view --json number,headRefOid,headRefName,baseRefName,title,body,author

# File list
gh pr diff --name-only

# Filtered diff (excluding migrations)
DIFF=$(gh pr diff | awk '/^diff --git.*migrations\//{skip=1} /^diff --git/{skip=0} skip==0{print}')
echo "$DIFF"
```

Read the root `CLAUDE.md` and any directory-specific `CLAUDE.md` files for modified directories.

### 5B: Code Review

**Step 1 — Eligibility:** Use a Haiku agent to check if the PR is closed, draft, trivially simple, or already reviewed. Skip if any apply.

**Step 2 — Five parallel Sonnet agents.** Each receives the filtered diff from 5A. No agent should call `gh pr diff`.

| Agent | Task | Extra input |
|-------|------|-------------|
| 1. CLAUDE.md compliance | Audit changes against CLAUDE.md rules | CLAUDE.md content(s) |
| 2. Bug detection | Shallow scan for obvious bugs (large bugs only, skip nitpicks) | Diff only |
| 3. Git history | Check `git log --oneline -10 <file>` for up to 5 PHP files; stop early if concern found; skip non-PHP files | File list + diff |
| 4. Previous PRs | Search `gh search prs` + `gh pr view` for related PR comments | File list only (NOT diff) |
| 5. Code comments | Check compliance with guidance in code comments; only Read full files if comment is truncated at hunk edge | File list + diff |

**Step 3 — Confidence scoring:** Collect all issues into a numbered list. Launch a single Haiku agent with the rubric:
- **0:** False positive / pre-existing
- **25:** Might be real, unverified; stylistic issue not in CLAUDE.md
- **50:** Real but nitpicky or unlikely in practice
- **75:** Very likely real, important, directly impacts functionality or called out in CLAUDE.md
- **100:** Definitely real, frequent impact, evidence confirms

Agent returns JSON: `[{"n": 1, "score": 75}, ...]`

**Step 4 — Filter:** Keep only issues scoring >= 80.

**Step 5 — Re-check:** `gh pr view --json state --jq '.state'` — skip comment if not OPEN.

**Step 6 — Post comment:** Use `gh pr comment` with format:

```
### Code review

Found N issues:

1. <description> (CLAUDE.md says "<...>")
<link to file with full SHA + line range>

Generated with [Claude Code](https://claude.ai/code)
<sub>If this code review was useful, please react with thumbs-up. Otherwise, react with thumbs-down.</sub>
```

Or if no issues: `No issues found. Checked for bugs and CLAUDE.md compliance.`

**Link format:** `https://github.com/a-jay85/IBL5/blob/{FULL_SHA}/path/to/file#L{start}-L{end}` — always use the full SHA from PR metadata, never bash interpolation.

**False positive examples (filter these out):** Pre-existing issues, linter/typecheck catches, pedantic nitpicks, general quality issues not in CLAUDE.md, silenced lint issues, intentional functionality changes, issues on unmodified lines.

### 5C: Security Audit

**Step 1 — Eligibility:** Use a Haiku agent to check if the PR is closed, draft, has zero PHP files, or already has a `### Security audit` comment. Skip if any apply.

**Step 2 — Pattern detection** on the PHP-only diff:

```bash
echo "SQL patterns:" && echo "$DIFF" | grep -c -E 'sql_query|prepare|fetchOne|fetchAll|query\(' || true
echo "Superglobals:" && echo "$DIFF" | grep -c -E '\$_GET|\$_POST|\$_REQUEST|\$_COOKIE' || true
echo "Output:" && echo "$DIFF" | grep -c -E 'echo |print |<\?=' || true
echo "Forms:" && echo "$DIFF" | grep -c -E 'POST|PUT|DELETE|<form|action=' || true
```

Launch only relevant agents (count > 0), plus Agent 5 (Auth) always:

| Agent | Category | Trigger |
|-------|----------|---------|
| 1 | SQL Injection | SQL patterns > 0 |
| 2 | XSS / Output Encoding | Output > 0 |
| 3 | Input Validation | Superglobals > 0 |
| 4 | CSRF Protection | Forms > 0 |
| 5 | Auth & Authorization | Always |

Each Sonnet agent receives the PHP diff and returns findings with file, line, and description. See the project's `.claude/commands/security-audit.md` for the full vulnerable/secure pattern lists per category.

**Step 3 — Confidence scoring:** Single Haiku agent scores 0-100 per finding:
- **0:** False positive — code is secure
- **25:** Suspicious but likely mitigated
- **50:** Pattern present but requires specific conditions
- **75:** Clearly present, no visible mitigation
- **100:** Direct user input to SQL/HTML/state-change with zero sanitization

IBL5-specific false positives to downgrade: `BaseMysqliRepository` methods, test files, typed params in strict_types, CLI echo, hardcoded SQL, `HtmlSanitizer` on different line, `ApiKeyAuthenticator` endpoints, GET-only handlers.

**Step 4 — Filter:** Keep only findings scoring >= 75.

**Step 5 — Post comment:** Use `gh pr comment` with format:

```
### Security audit

Found N issue(s):

**[SEVERITY]** Vulnerability type in `Class::method()` -- description
<link with full SHA + line range>

Generated with [Claude Code](https://claude.ai/code)
<sub>If this security audit was useful, please react with thumbs-up. Otherwise, react with thumbs-down.</sub>
```

Severity: CRITICAL (SQLi, command injection), HIGH (XSS, missing auth, open redirect), MEDIUM (missing CSRF, input validation), LOW (best practice).

Or if no issues: `No security issues found. Scanned for SQL injection, XSS, input validation, CSRF, and auth/authz vulnerabilities.`

---

## Phase 6: Final Verification

Run three verification tracks concurrently using **two parallel Sonnet agents**:

**Agent 1 — PHPUnit + PHPStan:**
```bash
cd <worktree>/ibl5 && vendor/bin/phpunit --no-progress --no-output --testdox-summary | tail -n 3
cd <worktree>/ibl5 && composer run analyse
```

**Agent 2 — E2E tests (Playwright):**
```bash
# From the repo root (not the worktree):
bin/wt-down <worktree-name> --volumes   # tear down if already running
bin/wt-up <worktree-name> --seed        # start with CI seed data for E2E
bin/e2e-wt.sh <worktree-name>           # run Playwright against worktree
```

After both agents complete:
- If either fails, fix the issues in the worktree, commit, and push the fix. Then re-run the failing verification.
- Once all pass, proceed to Phase 6.5.

---

## Phase 6.5: Manual Testing Automation

Re-evaluate the PR's manual testing checklist and convert automatable steps into Playwright E2E tests. This maximizes auto-merge eligibility and reduces reviewer burden.

**Skip this phase if** the PR description already says "No manual testing needed."

### Step 1: Extract manual testing steps

```bash
gh pr view --json body --jq '.body' | sed -n '/## Manual Testing/,/^## /p'
```

Parse the checklist items into a numbered list.

### Step 2: Classify each step

For each manual testing step, determine whether it can be automated with Playwright:

| Automatable — write an E2E test | Must stay manual |
|---|---|
| Navigate to page, assert element/content exists | Subjective visual aesthetics ("does it look right?") |
| Click/interact → assert state change | Cross-browser rendering judgment |
| Form submission → assert result page | Production data comparison (iblhoops.net) |
| Table data presence and correctness | Performance "feel" (perceived speed) |
| Mobile viewport layout (via `setViewportSize`) | Accessibility beyond ARIA/semantic checks |
| Error states and edge-case flows | Auth flows requiring real user credentials |
| URL routing and redirects | Anything requiring human sensory judgment |

### Step 3: Write E2E tests for automatable steps

For each automatable step:

1. Write a Playwright test in the appropriate `e2e/tests/` file (or create a new one if no suitable file exists)
2. Follow existing E2E patterns — use `appState` fixtures, CI seed data, no `test.skip()`
3. Run the new test directly from the worktree (not via `bin/e2e-wt.sh`, which runs from the main repo and won't pick up new test files in the worktree):
   ```bash
   cd <worktree>/ibl5 && BASE_URL=http://<slug>.localhost/ibl5 \
     IBL_TEST_USER=<test-user> IBL_TEST_PASS=<test-pass> \
     bunx playwright test --grep "test name"
   ```
4. Fix until green. If a test cannot be made green after 2 attempts, reclassify the step as manual and move on — do not burn cycles on flaky automation.

### Step 4: Update PR description

After all new E2E tests pass:

1. Commit and push the new E2E tests
2. Build the updated PR body — remove automated steps from the Manual Testing checklist
3. If no manual steps remain, replace the entire Manual Testing section with:
   `No manual testing needed — all changes are covered by unit and E2E tests.`
4. Apply the update:
   ```bash
   gh pr edit --body "<updated body>"
   ```

### Step 5: Re-evaluate (loop)

Re-read the updated Manual Testing section. If any remaining steps are now indirectly covered by the tests just written (e.g., a step was "check X and Y" — X is now tested, and Y is also testable as a side effect), go back to Step 2.

**Exit the loop when** every remaining step genuinely requires human judgment, or the section says "No manual testing needed."

---

## Phase 7: CI Monitoring

**BLOCKING GATE — do NOT proceed to Phase 7.5 until every CI check shows success. This phase loops until CI is green or you exhaust retries.**

Track iteration count starting at 1. Maximum 3 fix-push-retry cycles before escalating to the user.

### Step 7.1: Wait for checks to register

After the most recent push, CI checks may take 10-30 seconds to register. Poll `gh pr checks` until checks appear:

```bash
gh pr checks <pr-number> --json name,state 2>/dev/null | jq 'length'
```

Repeat up to 4 times with 15-second waits (60s total). If count stays 0, warn the user and **stop the workflow** — do not proceed.

**Why `gh pr checks` instead of `gh run list`:** A single push triggers multiple workflow runs (Tests & Analysis, E2E Tests, Lighthouse CI, etc.). `gh run list --limit 1` only returns one run. `gh pr checks` aggregates ALL checks across ALL workflow runs for the PR head commit.

### Step 7.2: Block until ALL checks complete

```bash
gh pr checks <pr-number> --watch
```

Use Bash timeout of 600000 (10 minutes). This blocks until every check reaches a terminal state. Exit code 0 means all checks passed; non-zero means at least one failed.

Do NOT use `gh run watch <single-run-id>` — that only monitors one workflow run and will miss failures in other runs.

If the command times out, fall back to polling `gh pr checks <pr-number> --json name,state,conclusion` every 30 seconds until all states are terminal.

### Step 7.3: Evaluate result

**If all checks passed → proceed to Phase 7.5.**

**If any check failed:**

1. Identify failed checks: `gh pr checks <pr-number> --json name,state,conclusion | jq '[.[] | select(.conclusion == "failure")]'`
2. For each failed check, find its run ID and download logs: `gh run view <run-id> --log-failed`
3. Run the 3-step CI failure checklist from `memory/feedback_ci_failures.md`:
   - Is the failing file in my PR diff?
   - Is the failing line/assertion one I changed?
   - Did this test fail on the parent commit?
4. Diagnose the failure from the logs
5. Fix the code in the worktree
6. Commit and push the fix
7. Increment iteration count
8. **If iteration count > 3:** Stop and report unresolved failures to the user. List what failed, what you tried, and the remaining error. Do NOT proceed to Phase 7.5.
9. **Otherwise: GO BACK TO Step 7.1** — wait for the new checks triggered by the push

---

## Phase 7.5: Auto-Merge

After CI passes, check whether the PR can be auto-merged without user intervention.

**All three conditions must be true:**
1. All CI jobs passed (confirmed in Phase 7)
2. The PR description contains "No manual testing needed" (generated by Phase 4 when automated tests fully cover the changes)
3. No code review or security audit findings scored >= 80 (from Phase 5)

**If all conditions are met:**
```bash
gh pr merge --squash --auto --delete-branch
```
This enables GitHub's native auto-merge queue. GitHub waits for all required status checks to pass, then squash-merges and deletes the branch automatically. If checks have already passed, the merge happens immediately.

Then pull master in the main repo so Phase 9's teardown has the merged code:
```bash
cd <repo-root> && git checkout master && git pull origin master
```

**If any condition fails, skip the merge and report which condition(s) blocked it.** The user will merge manually after reviewing.

---

## Phase 8: Retrospective

Check if anything was learned during this implementation that would help future sessions:
- Gotchas or surprises not already in MEMORY.md, CLAUDE.md, or `.claude/rules/`
- Patterns that worked well or failed
- Codebase assumptions that turned out wrong

**Do NOT save any of these — they create token waste without preventing errors:**
- Workarounds for issues already fixed (in code, hooks, or CI)
- Facts derivable by reading the code (type casts, return types, enum values)
- Platform/tool implementation details (Claude Code internals, IDE quirks)
- Niche domain knowledge for inactive workstreams
- Developer workflow tips that don't prevent bugs (counting tricks, tool preferences)
- One-time debugging notes for problems already resolved

**Litmus test:** "If I delete this note and hit the same situation next month, would I introduce a bug or just spend 30 seconds re-discovering it?" Only save if the answer is "introduce a bug."

Before writing, read the target memory file to avoid duplicating existing entries. Context hierarchy:
- `memory/MEMORY.md` — experiential learnings
- Project `CLAUDE.md` — canonical rules
- `.claude/rules/` — always-loaded coding patterns
- `~/.claude/CLAUDE.md` — user preferences

If nothing new was learned, skip silently. Do not announce "nothing to record."

---

## Phase 9: Worktree Preview Environment

After all phases complete successfully, ensure the worktree's Docker environment is running so the user can visually verify the changes in the browser. The worktree persists until the PR merges to master, when a git hook cleans up automatically.

1. `cd` to the repo root (not the worktree)
2. Check if Docker env is already running:
   `docker ps --format '{{.Names}}' | grep -q "^ibl5-php-<slug>$"`
3. If NOT running: start it with `bin/wt-up <worktree-name> --prod`
   - If `ibl5/fixtures/prod-seed.sql` doesn't exist, use `--seed` instead
   - If `wt-up` fails, warn but do not fail the workflow
4. Print the preview URL: `http://<slug>.localhost/ibl5/`
5. Do NOT run `wt-down`, `wt-remove`, or `git branch -D`

**Skip this phase if:**
- The worktree was not created by Phase 1 (e.g., pre-existing worktree the user asked you to work in)
- Any earlier phase failed and there are uncommitted fixes in the worktree
