---
name: post-plan
description: Single orchestrator for post-plan Phases 3-9. Runs simplify, commit/push/PR, code review, security audit, verification, CI monitoring, retrospective, and worktree teardown as one uninterrupted sequence.
---

# Post-Plan Orchestrator (Phases 3-9)

Execute all phases below **sequentially in a single response**. Do NOT stop, ask for input, or return control between phases. Each phase flows directly into the next.

---

## Phase 3: Simplify

Review changed files (`git diff --name-only HEAD~1` or vs base branch) for reuse opportunities, CLAUDE.md mandatory-rule violations, and over-engineering. Fix issues directly before proceeding.

---

## Phase 4: Commit, Push & PR

1. Stage relevant changes, review with `git diff --staged`, commit (CLAUDE.md conventions), push, create PR
2. **Stacked PRs:** If branched from a feature branch (not `master`), use `--base <parent-branch>`
3. **Manual testing in PR description:** Include a "Manual Testing" section. If automated tests fully cover behavior, write: `No manual testing needed — all changes are covered by unit and E2E tests.` Otherwise, list only steps that require subjective human judgment (visual aesthetics, production comparison). Do NOT list CLI commands, curl requests, or script invocations as manual steps — Phase 6.5 will execute those directly.
4. Use Haiku agents for commit message generation if delegating

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

Execute the code review process defined in `.claude/commands/code-review.md` with these modifications:
- **Skip Steps 1 and 2** — eligibility was checked and PR data was already fetched in 5A above
- Pass the PR metadata, file list, filtered diff, and CLAUDE.md content(s) from 5A to each agent
- No agent should call `gh pr diff`

### 5C: Security Audit

Execute the security audit process defined in `.claude/commands/security-audit.md` with these modifications:
- **Skip Steps 1 and 2** — eligibility was checked and PR data was already fetched in 5A above
- Use the PHP-only subset of the diff from 5A (filter with `awk` if not already PHP-only)
- No agent should call `gh pr diff`

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

**Skip this phase if** the PR description already says "No manual testing needed."

Otherwise, read and follow the instructions in `manual-testing-automation.md` (same directory as this skill).

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
