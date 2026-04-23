---
name: post-plan
description: Single orchestrator for the post-plan workflow. Runs diff classification, simplify, commit/push/PR, code review, security audit, verification, CI monitoring, retrospective, and worktree teardown as one uninterrupted sequence.
last_verified: 2026-04-21
---

# Post-Plan Orchestrator

Execute all phases below **sequentially in a single response**. Do NOT stop, ask for input, or return control between phases.

Phase numbers below are local to this skill. The variables computed in Phase 2 (`HAS_PHP`, `NON_CODE_ONLY`, `DOCS_ONLY`, `CSS_ONLY`, `MIGRATION_ONLY`, `HAS_MODIFIED`, `HAS_COMMENTS_IN_DIFF`, `DIFF`, etc.) are consulted by every downstream phase to gate sub-agent launches — never recompute them locally.

---

## Phase 1: Clear Plan Gate

Remove the plan workflow gate so that commits and edits within this skill are not blocked by PreToolUse hooks:

```bash
rm -f /tmp/claude-plan-active-$PPID
```

---

## Phase 2: Classify Diff

Run this bash block once. It computes classification flags and writes the filtered diff to `/tmp/post-plan-diff-$PPID` (same `$PPID` pattern Phase 1 uses — stable across Bash tool calls in the session). Uses `gh pr diff` when a PR exists (correct base for stacked PRs), falls back to `git diff origin/master...HEAD` pre-PR. Every later phase consults these flags and reads the diff file — do not recompute.

```bash
DIFF_FILE=/tmp/post-plan-diff-$PPID

# Detect whether a PR exists for this branch
HAS_PR=false
if gh pr diff --name-only &>/dev/null; then
    HAS_PR=true
fi

# Changed file list (deleted files excluded — nothing to review)
if $HAS_PR; then
    FILES=$(gh pr diff --name-only)
else
    FILES=$(git diff --name-only origin/master...HEAD)
fi

# Per-type counts (grep -cE, default 0 if no match)
COUNT_TOTAL=$(echo "$FILES" | grep -c . || true)
COUNT_PHP=$(echo "$FILES" | grep -cE '\.php$' || true)
COUNT_CSS=$(echo "$FILES" | grep -cE '\.css$|^ibl5/design/' || true)
COUNT_MD=$(echo "$FILES" | grep -cE '\.md$' || true)
COUNT_MIGRATION=$(echo "$FILES" | grep -cE '^ibl5/migrations/.*\.sql$' || true)
COUNT_TEST=$(echo "$FILES" | grep -cE '^ibl5/tests/|\.test\.(ts|js|php)$|\.spec\.(ts|js)$' || true)
COUNT_LOCK=$(echo "$FILES" | grep -cE '(composer|package|bun)\.lock$' || true)
COUNT_SNAPSHOT=$(echo "$FILES" | grep -cE '__snapshots__/|\.snap$' || true)
COUNT_NON_CODE=$(( COUNT_MD + COUNT_LOCK + COUNT_SNAPSHOT ))

# Derived flags (true/false strings for readable gates downstream)
HAS_PHP=$([ "$COUNT_PHP" -gt 0 ] && echo true || echo false)
HAS_CSS=$([ "$COUNT_CSS" -gt 0 ] && echo true || echo false)
HAS_MIGRATION=$([ "$COUNT_MIGRATION" -gt 0 ] && echo true || echo false)
HAS_TEST=$([ "$COUNT_TEST" -gt 0 ] && echo true || echo false)

# "X-only" means every file in $FILES matches that category
DOCS_ONLY=$([ "$COUNT_TOTAL" -gt 0 ] && [ "$COUNT_MD" -eq "$COUNT_TOTAL" ] && echo true || echo false)
CSS_ONLY=$([ "$COUNT_TOTAL" -gt 0 ] && [ "$COUNT_CSS" -eq "$COUNT_TOTAL" ] && echo true || echo false)
MIGRATION_ONLY=$([ "$COUNT_TOTAL" -gt 0 ] && [ "$COUNT_MIGRATION" -eq "$COUNT_TOTAL" ] && echo true || echo false)
TEST_ONLY=$([ "$COUNT_TOTAL" -gt 0 ] && [ "$COUNT_TEST" -eq "$COUNT_TOTAL" ] && echo true || echo false)
NON_CODE_ONLY=$([ "$COUNT_TOTAL" -gt 0 ] && [ "$COUNT_NON_CODE" -eq "$COUNT_TOTAL" ] && echo true || echo false)

# "Has modified (not added) files" — gates Phase 5B Agent 4 (previous PRs)
MODIFIED_COUNT=$(git diff --diff-filter=M --name-only origin/master...HEAD 2>/dev/null | grep -c . || true)
HAS_MODIFIED=$([ "$MODIFIED_COUNT" -gt 0 ] && echo true || echo false)

# Filtered diff -> temp file (single awk pass stripping migrations, lockfiles, snapshots)
DIFF_AWK='
  /^diff --git.*(migrations\/|composer\.lock|package-lock\.json|bun\.lock|__snapshots__\/|\.snap$)/ {skip=1; next}
  /^diff --git/ {skip=0}
  skip==0 {print}
'
if $HAS_PR; then
    gh pr diff | awk "$DIFF_AWK" > "$DIFF_FILE"
else
    git diff origin/master...HEAD | awk "$DIFF_AWK" > "$DIFF_FILE"
fi

# Fallback: if filtered diff is still > 100KB and a PR exists, shrink via gh api
if [ "$(wc -c < "$DIFF_FILE")" -gt 102400 ] && $HAS_PR; then
  PR_NUM=$(gh pr view --json number --jq '.number')
  gh api "repos/a-jay85/IBL5/pulls/$PR_NUM/files" --paginate \
    --jq '.[] | select(.filename | test("migrations/|composer\\.lock|package-lock\\.json|bun\\.lock|__snapshots__/|\\.snap$") | not) | "--- " + .filename + " ---\n" + (.patch // "(binary or too large)")' \
    > "$DIFF_FILE"
fi

# Code-comment detection on added lines only (gates Phase 5B Agent 5)
COMMENT_COUNT=$(grep -cE '^\+[[:space:]]*(//|#|/\*|\*)' "$DIFF_FILE" || true)
HAS_COMMENTS_IN_DIFF=$([ "$COMMENT_COUNT" -gt 0 ] && echo true || echo false)

# PHP lines changed (gates Phase 5B Agents 3-4 size threshold)
LINES_PHP_CHANGED=$(git diff origin/master...HEAD -- '*.php' | grep -cE '^\+[^+]' || true)

# Classification summary for the run log (Claude reads these and remembers them for later phases)
echo "=== Diff classification ==="
echo "  total=$COUNT_TOTAL php=$COUNT_PHP css=$COUNT_CSS md=$COUNT_MD migration=$COUNT_MIGRATION test=$COUNT_TEST lock=$COUNT_LOCK snapshot=$COUNT_SNAPSHOT"
echo "  DOCS_ONLY=$DOCS_ONLY CSS_ONLY=$CSS_ONLY MIGRATION_ONLY=$MIGRATION_ONLY TEST_ONLY=$TEST_ONLY NON_CODE_ONLY=$NON_CODE_ONLY"
echo "  HAS_PHP=$HAS_PHP HAS_CSS=$HAS_CSS HAS_MODIFIED=$HAS_MODIFIED HAS_COMMENTS_IN_DIFF=$HAS_COMMENTS_IN_DIFF LINES_PHP_CHANGED=$LINES_PHP_CHANGED"
echo "  DIFF_FILE=$DIFF_FILE ($(wc -c < "$DIFF_FILE") bytes)"
```

Each Bash tool call runs in a fresh shell, so the classification flags are **not** bash state you can reference later — they're output Claude records from this block's stdout and applies as gates in later phases. The filtered diff is bridged via `$DIFF_FILE` (same `$PPID` across calls).

---

## Phase 3: Simplify

**Skip if** `$NON_CODE_ONLY` or `$MIGRATION_ONLY` (nothing reviewable).

Review changed files (`git diff --name-only HEAD~1` or vs base branch) for reuse opportunities and over-engineering. Mandatory CLAUDE.md rules are enforced by PHPStan custom rules (see `.claude/commands/_review-rubric.md` for the full list) — assume they hold and focus on judgment-level issues like duplication, awkward abstractions, and dead code.

---

## Phase 4: Commit, Push & PR

1. Stage relevant changes, review with `git diff --staged`, commit (CLAUDE.md conventions), push, create PR
2. **Stacked PRs:** If branched from a feature branch (not `master`), use `--base <parent-branch>`
3. **Manual testing in PR description:** Include a "Manual Testing" section. If automated tests fully cover behavior, write: `No manual testing needed — all changes are covered by unit and E2E tests.` Otherwise, list only steps requiring subjective human judgment on new or redesigned UI/UX ("does this look/feel good?", "does this flow work well?"). Production comparison and "does output still match?" are visual-regression-replaceable, not manual. Do NOT list CLI commands or script invocations — Phase 7 executes those.
4. Use Haiku agents for commit message generation if delegating

---

## Phase 5: Code Review + Security Audit

Agent definitions and scoring rubric live in shared include files under `.claude/commands/` so this skill, `/code-review`, and `/security-audit` all share one source of truth. Read them as instructed below — do NOT inline the definitions or duplicate them.

### 5A: Fetch PR data (shared by both)

Run these commands yourself (not via agents):

```bash
gh pr view --json number,headRefOid,headRefName,baseRefName,title,body,author
cat /tmp/post-plan-diff-$PPID   # filtered diff written by Phase 2 (already < 100KB after the fallback)
```

Capture the `cat` output — that is `$DIFF` for every sub-agent prompt below. No sub-agent calls `gh pr diff`.

Read root `CLAUDE.md`. If `! $NON_CODE_ONLY`, also read directory-specific `CLAUDE.md` files for modified directories.

### 5B: Code Review — up to 6 parallel agents (mixed tiers)

**Read** `.claude/commands/_review-agents.md` — the canonical agent definitions (6 agents: architectural fitness, bug detection, git history, previous PRs, code comments, database performance).

Pass each agent: PR metadata, file list, filtered `$DIFF`, CLAUDE.md content(s) from 5A. **No agent calls `gh pr diff`.**

**Model tiers** — agents that must judge whether a finding is semantically relevant need Sonnet; agents that look up facts or match against named patterns use Haiku:

- Agent 1 (Architectural fitness): **Sonnet** — judges R/S/V fit, dependency direction
- Agent 2 (Bug detection): **Sonnet** — connects schema types to PHP comparison operators
- Agent 3 (Git history): **Sonnet** — must judge whether a past commit's collision zone overlaps the current change
- Agent 4 (Previous PRs): **Haiku** — mechanical `gh search prs` + `gh pr view` lookup. Add to prompt: "List EVERY prior review comment that touches these files. Do NOT judge relevance — report all matches."
- Agent 5 (Code comments): **Sonnet** — must judge whether code semantically complies with docstring guidance
- Agent 6 (Database performance): **Sonnet** — interprets query behavior in context

**Launch gates** (consult Phase 2 variables — skip the launch entirely, don't let the agent exit early):

- Agent 1 (Architectural fitness): skip if `$NON_CODE_ONLY`
- Agent 2 (Bug detection): skip if `$NON_CODE_ONLY` or `$MIGRATION_ONLY`
- Agent 3 (Git history): skip if `! $HAS_PHP` or `$LINES_PHP_CHANGED <= 50`
- Agent 4 (Previous PRs): skip if `$NON_CODE_ONLY` or `! $HAS_MODIFIED` or `$LINES_PHP_CHANGED <= 50`
- Agent 5 (Code comments): skip if `$NON_CODE_ONLY` or `! $HAS_COMMENTS_IN_DIFF`
- Agent 6 (Database performance): skip if `! $HAS_PHP`

### 5C: Security Audit — conditional Haiku agents

**Skip entire 5C if** `! $HAS_PHP`. CSS, markdown, migrations, and lockfile bumps cannot introduce SQLi/CSRF/auth vulnerabilities.

**Read** `.claude/commands/_security-agents.md` — the canonical security agent definitions and pattern-detection bash block.

Run the pattern-detection block from that file to get SQL and Forms category counts, then launch only the relevant agents (SQL Injection if SQL > 0; CSRF Protection if Forms > 0; Auth/Authz unconditionally). Pass each agent the PHP-only subset of `$DIFF`.

**All three security agents use Haiku.** Their prompts include explicit vulnerable/secure pattern tables — the agent checks each pattern against the diff and reports matches. Add to each prompt: "Check EACH pattern in the vulnerable and secure lists against the diff. For each pattern, state whether it was found and cite the file:line, or state it was not found."

**XSS and Input Validation are NOT audited here** — they're deterministically enforced by `RequireEscapedOutputRule` and `BanRawSuperglobalsRule` (run in PostToolUse and CI).

### 5D: Score, filter, and post

**Read** `.claude/commands/_review-rubric.md` — the canonical rubric, thresholds (`< 80` for code review, `< 75` for security), Automatic-Zero rule list, and IBL5 false-positive list.

Combine ALL issues from 5B and 5C into one numbered list.

**Skip the scoring agent if the combined list is empty** — jump straight to posting "No issues found." comments in the two `gh pr comment` steps below.

Otherwise launch a **single Haiku agent**, pass it the issues list plus the **Scoring scale and Thresholds sections** from `_review-rubric.md` (not the full Automatic Zero or false-positive lists — review agents have already filtered those). Instruct it to return JSON scores per that rubric. Parse the response and assign scores back to each issue.

**Filter** per the thresholds in `_review-rubric.md`.

**Re-check PR state:** `gh pr view --json state --jq '.state'` — skip posting if not `OPEN`.

**Post two `gh pr comment` entries** (code review + security audit) using full SHA from 5A.

Code review format (issues found): `### Code review\n\nFound N issues:\n\n1. <description> (CLAUDE.md says "<rule>")\n\n<link>`

Code review format (no issues): `### Code review\n\nNo issues found.` followed by a 1-2 sentence evidence summary assembled from agent responses (e.g., "Architecture follows Repository/Service/View split. Native-type comparisons consistent with schema. No bind_param mismatches in modified files.").

Security audit format (issues found): `### Security audit\n\nFound N issue(s):\n\n**[SEVERITY]** Type in \`Class::method()\` — description\n\n<link>` Severity: CRITICAL (SQLi/CMDi), HIGH (missing auth/open redirect), MEDIUM (CSRF/missing auth on non-critical endpoints), LOW (best practice).

Security audit format (no issues): `### Security audit\n\nNo security issues found.` followed by brief evidence per category that launched (e.g., "SQL: all queries use prepared statements. CSRF: token validated on line N. Auth: guard present on state-changing endpoints.") and `(XSS and input validation are enforced by PHPStan custom rules.)`

**Link format:** `https://github.com/a-jay85/IBL5/blob/{FULL_SHA}/path/to/file#L{start}-L{end}` — expand SHA beforehand, never use bash interpolation in the comment. Include 1 line of context before/after.

Both comments end with: `Generated with [Claude Code](https://claude.ai/code)` and `<sub>If this was useful, react with thumbs-up. Otherwise, thumbs-down.</sub>`

---

## Phase 6: Final Verification

Run parallel agents (**Haiku** for both):

**Agent 1 — PHPUnit + PHPStan (Haiku):** **Skip if** `! $HAS_PHP`. The PostToolUse hook already ran both during edits, and a PHP-less diff cannot regress either suite.
```bash
cd <worktree>/ibl5 && vendor/bin/phpunit --no-progress --no-output --testdox-summary | tail -n 3
cd <worktree>/ibl5 && composer run analyse
```

**Agent 2 — E2E (Playwright):**

Steps:
1. Run `bin/wt-down <worktree-name> --volumes` then `bin/wt-up <worktree-name> --seed`
2. Run `bin/e2e-for-pr <worktree-name>` and capture both stdout and exit code
3. Branch on the result:
   - **Exit 0, empty stdout** → print "No E2E tests map to changed files — skipping E2E" and stop
   - **Exit 2** → run full suite: `bin/e2e-wt.sh <worktree-name>`
   - **Exit 0, test file list on stdout** → run targeted: `bin/e2e-wt.sh <worktree-name> <test-files-from-stdout>`

Prompt MUST include: "Run these commands and report the summary output. Do NOT investigate, re-run, or diagnose individual test failures — just report the pass/fail counts and any error output."

Prompt MUST ALSO include this long-run handling rule: "`bin/e2e-wt.sh` can exceed the Bash tool's 600s cap. If it does, invoke Bash with `run_in_background: true` and poll via the **BashOutput** tool — do NOT pipe to a file and shell-loop on `grep`. If you absolutely must shell-poll, the terminator must accept every Playwright outcome (`grep -qE 'passed|failed|did not run|timed out|error'` scanning `tail -10`, not a single last-line match): Playwright's trailing line is often `N did not run` after an early setup failure, which will hang a `passed|failed`-only check forever."

If either fails, fix in worktree, commit, push, and re-run the failing track.

---

## Phase 7: Manual Testing Automation

**Skip if** PR description says "No manual testing needed."

### Step 1: Extract

```bash
EXTRACTED=$(gh pr view --json body --jq '.body' | sed -n '/## Manual Testing/,/^## /p')
echo "$EXTRACTED"
```

**Also skip Phase 7 entirely if `$EXTRACTED` is empty or whitespace-only** — the section is absent or was already cleared. Do not launch the Sonnet review gate on empty input.

### Step 2: Sonnet Review Gate

Launch a **single Sonnet agent** with this prompt (substitute the extracted steps and file list):

> You are a **Senior QA Automation Engineer** reviewing manual testing steps from a PR. Your job: eliminate every step that can be replaced by automated verification. Be aggressive — manual testing is expensive and error-prone. Only steps requiring subjective human judgment on **new or redesigned** UI/UX should survive ("does this look/feel good?", "does this flow work well?").
>
> **PR manual testing steps:**
> {extracted steps from Step 1}
>
> **Changed files:** {file list from Phase 5A}
>
> Classify each step into exactly one category:
>
> | Category | Description | Action |
> |----------|-------------|--------|
> | **CLI-executable** | A command or script Claude can run directly (curl, bin/db-query, grep output) | Opus runs it |
> | **PHPUnit-replaceable** | Unit/integration test can assert the behavior (DB state, service output, calculation) | Opus writes PHPUnit test |
> | **API-test-replaceable** | HTTP request/response can be verified programmatically (endpoint returns correct JSON/HTML, status codes, headers) | Opus writes integration or API test |
> | **E2E-replaceable** | Browser interaction needed (form submit, page navigation, HTMX swap, DOM state) | Opus writes Playwright test |
> | **Visual-regression-replaceable** | "Does output still match?" / production comparison where UI/UX was not intentionally redesigned | Opus writes Playwright visual-regression test or screenshot diff |
> | **Truly manual** | Requires subjective human judgment on **new or redesigned** UI/UX that no automated test can replicate ("does this look/feel good?", "does this new flow work well?") | Stays in PR description |
>
> For each step, return a JSON array:
> ```json
> [
>   {"step": "original step text", "category": "cli-executable|phpunit|api-test|e2e|visual-regression|truly-manual", "rationale": "why this category", "test_hint": "what the test should assert (omit for cli-executable and truly-manual)"}
> ]
> ```
>
> **Bias toward automation.** If a step says "verify X works" or "check that Y returns Z", that is automatable — not manual. "Compare against production" is visual-regression-replaceable (screenshot diff) unless UI/UX was intentionally redesigned — it is NOT truly manual. Only subjective judgment on new/redesigned UI/UX is truly manual.

### Step 3: Execute findings

Using the Sonnet agent's classifications:

1. **CLI-executable:** Run directly in the worktree. Fix failures, commit.
2. **PHPUnit/API-test/E2E-replaceable:** Write the appropriate test type. Fix until green; reclassify as truly manual after 2 failed attempts.
3. **Truly manual:** Keep in PR description.
4. **Update PR:** Remove verified/automated steps. If none remain, replace section with `No manual testing needed — all changes are covered by automated tests.` Apply: `gh pr edit --body "<updated>"`

---

## Phase 8: CI Monitoring

**BLOCKING GATE — loop until CI is green or 3 fix-push-retry cycles exhausted.**

1. **Wait for checks:** Poll `gh pr checks <pr> --json name,state 2>/dev/null | jq 'length'` up to 4 times with 15s waits. If count stays 0, warn user and stop.
2. **Block until complete:** `gh pr checks <pr> --watch` (Bash timeout 600000). Falls back to polling `--json name,state,conclusion` every 30s on timeout.
3. **If all passed** -> Phase 9
4. **If any failed:** Get failed checks (`jq '[.[] | select(.conclusion == "failure")]'`), download logs (`gh run view <id> --log-failed`), run the 3-step CI failure checklist (is file in my diff? is failing line my change? did it fail on parent?), fix, commit, push, loop back to step 1. After 3 iterations, escalate to user.

---

## Phase 9: Auto-Merge

All three conditions must be true: (1) CI passed, (2) PR says "No manual testing needed", (3) no review/audit findings scored >= 80.

If met: `gh pr merge --squash --auto --delete-branch` then `cd <repo-root> && git checkout master && git pull origin master`.

If not: report which condition(s) blocked. User merges manually.

---

## Phase 10: Retrospective

Before saving any memory, ask: **"Can this be a PHPStan rule instead?"** If the mistake is mechanical and deterministic, it belongs in `ibl5/phpstan-rules/` as a new custom rule — open a TODO comment in the plan file rather than a memory entry. Memories are for things a linter cannot express (architectural judgment, environment quirks, incident context).

Save to memory only if something was learned that would **prevent a bug** in a future session AND cannot be mechanized AND isn't already in MEMORY.md, CLAUDE.md, `.claude/rules/`, or an existing PHPStan rule. Read the target memory file first to avoid duplicates. If nothing qualifies, skip silently.

---

## Phase 11: Worktree Preview Environment

**Skip if** worktree was pre-existing, earlier phases left uncommitted fixes, or `$CLAUDE_HEADLESS` is set (nightly autonomous mode — no human present to verify).

1. Tear down and restart with production data:
   ```bash
   bin/wt-down <worktree-name> --volumes
   bin/wt-up <worktree-name> --prod
   ```
2. Print preview URL: `http://<slug>.localhost/ibl5/`
3. Do NOT run `wt-remove` or `git branch -D`
