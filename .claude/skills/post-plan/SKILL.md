---
name: post-plan
description: Single orchestrator for the post-plan workflow. Runs diff classification, simplify, commit/push/PR, code review, security audit, verification, CI monitoring, retrospective, and worktree teardown as one uninterrupted sequence.
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

Run this bash block once. It computes classification flags from `gh pr diff --name-only` and writes the filtered diff to `/tmp/post-plan-diff-$PPID` (same `$PPID` pattern Phase 1 uses — stable across Bash tool calls in the session). Every later phase consults these flags and reads the diff file — do not recompute.

```bash
DIFF_FILE=/tmp/post-plan-diff-$PPID

# Changed file list (deleted files excluded — nothing to review)
FILES=$(gh pr diff --name-only 2>/dev/null)

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
gh pr diff | awk '
  /^diff --git.*(migrations\/|composer\.lock|package-lock\.json|bun\.lock|__snapshots__\/|\.snap$)/ {skip=1; next}
  /^diff --git/ {skip=0}
  skip==0 {print}
' > "$DIFF_FILE"

# Fallback: if filtered diff is still > 100KB, shrink via gh api with the same exclusions
if [ "$(wc -c < "$DIFF_FILE")" -gt 102400 ]; then
  PR_NUM=$(gh pr view --json number --jq '.number')
  gh api "repos/a-jay85/IBL5/pulls/$PR_NUM/files" --paginate \
    --jq '.[] | select(.filename | test("migrations/|composer\\.lock|package-lock\\.json|bun\\.lock|__snapshots__/|\\.snap$") | not) | "--- " + .filename + " ---\n" + (.patch // "(binary or too large)")' \
    > "$DIFF_FILE"
fi

# Code-comment detection on added lines only (gates Phase 5B Agent 5)
COMMENT_COUNT=$(grep -cE '^\+[[:space:]]*(//|#|/\*|\*)' "$DIFF_FILE" || true)
HAS_COMMENTS_IN_DIFF=$([ "$COMMENT_COUNT" -gt 0 ] && echo true || echo false)

# Classification summary for the run log (Claude reads these and remembers them for later phases)
echo "=== Diff classification ==="
echo "  total=$COUNT_TOTAL php=$COUNT_PHP css=$COUNT_CSS md=$COUNT_MD migration=$COUNT_MIGRATION test=$COUNT_TEST lock=$COUNT_LOCK snapshot=$COUNT_SNAPSHOT"
echo "  DOCS_ONLY=$DOCS_ONLY CSS_ONLY=$CSS_ONLY MIGRATION_ONLY=$MIGRATION_ONLY TEST_ONLY=$TEST_ONLY NON_CODE_ONLY=$NON_CODE_ONLY"
echo "  HAS_PHP=$HAS_PHP HAS_CSS=$HAS_CSS HAS_MODIFIED=$HAS_MODIFIED HAS_COMMENTS_IN_DIFF=$HAS_COMMENTS_IN_DIFF"
echo "  DIFF_FILE=$DIFF_FILE ($(wc -c < "$DIFF_FILE") bytes)"
```

Each Bash tool call runs in a fresh shell, so the classification flags are **not** bash state you can reference later — they're output Claude records from this block's stdout and applies as gates in later phases. The filtered diff is bridged via `$DIFF_FILE` (same `$PPID` across calls).

---

## Phase 3: Simplify

**Skip if** `$NON_CODE_ONLY` or `$MIGRATION_ONLY` (nothing reviewable).

Review changed files (`git diff --name-only HEAD~1` or vs base branch) for reuse opportunities, CLAUDE.md mandatory-rule violations, and over-engineering. Fix issues directly before proceeding.

---

## Phase 4: Commit, Push & PR

1. Stage relevant changes, review with `git diff --staged`, commit (CLAUDE.md conventions), push, create PR
2. **Stacked PRs:** If branched from a feature branch (not `master`), use `--base <parent-branch>`
3. **Manual testing in PR description:** Include a "Manual Testing" section. If automated tests fully cover behavior, write: `No manual testing needed — all changes are covered by unit and E2E tests.` Otherwise, list only steps requiring subjective human judgment (visual aesthetics, production comparison). Do NOT list CLI commands or script invocations — Phase 7 executes those.
4. Use Haiku agents for commit message generation if delegating

---

## Phase 5: Code Review + Security Audit

All instructions are self-contained below. Do NOT read `code-review.md` or `security-audit.md`.

### 5A: Fetch PR data (shared by both)

Run these commands yourself (not via agents):

```bash
gh pr view --json number,headRefOid,headRefName,baseRefName,title,body,author
cat /tmp/post-plan-diff-$PPID   # filtered diff written by Phase 2 (already < 100KB after the fallback)
```

Capture the `cat` output — that is `$DIFF` for every sub-agent prompt below. No sub-agent calls `gh pr diff`.

Read root `CLAUDE.md`. If `! $NON_CODE_ONLY`, also read directory-specific `CLAUDE.md` files for modified directories.

### 5B: Code Review — up to 5 parallel Sonnet agents

Pass each agent: PR metadata, file list, filtered `$DIFF`, CLAUDE.md content(s) from 5A. **No agent calls `gh pr diff`.**

**Launch gates** (consult Phase 2 variables — skip the launch entirely, don't let the agent exit early):

- Agent 1: skip if `$NON_CODE_ONLY`
- Agent 2: skip if `$NON_CODE_ONLY` or `$MIGRATION_ONLY`
- Agent 3: skip if `! $HAS_PHP`
- Agent 4: skip if `$NON_CODE_ONLY` or `! $HAS_MODIFIED`
- Agent 5: skip if `$NON_CODE_ONLY` or `! $HAS_COMMENTS_IN_DIFF`

**Agent 1 (CLAUDE.md compliance):** Audit changes against CLAUDE.md rules. Return issues with the specific rule violated.

**Agent 2 (Bug detection):** You are a **Staff Software Engineer** reviewing a PR for correctness. Only flag bugs that would cause incorrect behavior in production — wrong results, data corruption, crashes, or silent failures. Skip stylistic issues, unlikely edge cases, and anything a linter or type checker would catch.

**Agent 3 (Git history):** Check `git log --oneline -10 <file>` for up to 5 PHP files with most lines changed. Stop early on first relevant concern. No `git blame`.

**Agent 4 (Previous PRs):** Use `gh search prs` and `gh pr view` to find prior PRs touching the modified (not added) files. Check for comments that also apply here.

**Agent 5 (Code comments):** Check if changes comply with code comments visible in diff context. Only Read full file if a comment appears truncated at a hunk edge.

### 5C: Security Audit — conditional Sonnet agents

**Skip entire 5C if** `! $HAS_PHP`. CSS, markdown, migrations, and lockfile bumps cannot introduce SQLi/XSS/auth vulnerabilities.

Detect patterns on **added lines in `*.php` files only** (prevents markdown code blocks and deleted lines from triggering false positives):
```bash
PHP_ADDED=$(git diff origin/master...HEAD -- '*.php' | grep -E '^\+' | grep -v '^\+\+\+')
echo "SQL:"          && echo "$PHP_ADDED" | grep -c -E 'sql_query|prepare|fetchOne|fetchAll|query\(' || true
echo "Output:"       && echo "$PHP_ADDED" | grep -c -E 'echo |print |<\?=' || true
echo "Superglobals:" && echo "$PHP_ADDED" | grep -c -E '\$_GET|\$_POST|\$_REQUEST|\$_COOKIE' || true
echo "Forms:"        && echo "$PHP_ADDED" | grep -c -E 'POST|PUT|DELETE|<form|action=' || true
```

Launch only agents whose category count > 0. Agent 5 (Auth/Authz) launches unconditionally once this section runs (gated by `$HAS_PHP` at the top). Pass each agent the PHP-only subset of `$DIFF`. Each security agent receives this shared preamble:

> You are a **Senior Application Security Engineer** auditing a PHP codebase. Focus on exploitable vulnerabilities, not theoretical risks. Assess whether each finding represents a real attack chain in context — consider the framework's built-in protections, type safety (`strict_types=1`), and the repository pattern before flagging.

**Agent 1 (SQL Injection, if SQL > 0):** Flag `sql_query()` with string interpolation, dynamic ORDER BY/LIMIT from user input. Safe: `BaseMysqliRepository` methods, `prepare()+bind_param()`, hardcoded SQL, `(int)` casts.

**Agent 2 (XSS, if Output > 0):** Flag `echo $var` without `HtmlSanitizer::safeHtmlOutput()`. Safe: `json_encode()`, `(int)` casts, CLI scripts, hardcoded strings, HtmlSanitizer on different line.

**Agent 3 (Input Validation, if Superglobals > 0):** Flag direct superglobal use without `filter_input()` or whitelist validation. Safe: typed params in `strict_types=1`.

**Agent 4 (CSRF, if Forms > 0):** Flag POST/PUT/DELETE handlers without `CsrfGuard::validateSubmittedToken()`. Safe: GET-only endpoints, `ApiKeyAuthenticator` handlers.

**Agent 5 (Auth/Authz):** Flag state-changing endpoints without `is_user()`/`is_admin()`/`ApiKeyAuthenticator`. Safe: read-only public pages, already-guarded endpoints. (Launches whenever 5C runs — the `$HAS_PHP` gate at the top of 5C already ensures there's PHP to audit.)

### 5D: Score, filter, and post

Combine ALL issues from 5B and 5C into one numbered list.

**Skip the scoring agent if the combined list is empty** — jump straight to posting "No issues found." comments in the two `gh pr comment` steps below.

Otherwise launch a **single Haiku agent** to score each 0-100:

> **Rubric:** 0=false positive, 25=suspicious but mitigated, 50=real but minor, 75=verified and important, 100=certain and frequent.
>
> **IBL5 false positives (score 0-25):** BaseMysqliRepository variables (already parameterized), test files, typed ints in strict_types, CLI echo, hardcoded sql_query strings, HtmlSanitizer on different line, ApiKeyAuthenticator endpoints (CSRF exempt), GET-only handlers, pre-existing issues, issues a linter/typechecker/CI would catch, changes in functionality that are likely intentional.
>
> For CLAUDE.md issues: verify the rule is actually stated in CLAUDE.md.
>
> Return ONLY valid JSON: `[{"n": 1, "score": 75}, ...]`

**Filter:** Code review issues < 80 are dropped. Security findings < 75 are dropped.

**Re-check PR state:** `gh pr view --json state --jq '.state'` — skip posting if not `OPEN`.

**Post two `gh pr comment` entries** (code review + security audit) using full SHA from 5A.

Code review format: `### Code review\n\nFound N issues:\n\n1. <description> (CLAUDE.md says "<rule>")\n\n<link>` — or `No issues found. Checked for bugs and CLAUDE.md compliance.`

Security audit format: `### Security audit\n\nFound N issue(s):\n\n**[SEVERITY]** Type in \`Class::method()\` — description\n\n<link>` — or `No security issues found.` Severity: CRITICAL (SQLi/CMDi), HIGH (XSS/missing auth/open redirect), MEDIUM (CSRF/input validation), LOW (best practice).

**Link format:** `https://github.com/a-jay85/IBL5/blob/{FULL_SHA}/path/to/file#L{start}-L{end}` — expand SHA beforehand, never use bash interpolation in the comment. Include 1 line of context before/after.

Both comments end with: `Generated with [Claude Code](https://claude.ai/code)` and `<sub>If this was useful, react with thumbs-up. Otherwise, thumbs-down.</sub>`

---

## Phase 6: Final Verification

Run parallel agents (**Sonnet** for PHPUnit+PHPStan, **Haiku** for E2E):

**Agent 1 — PHPUnit + PHPStan:** **Skip if** `! $HAS_PHP`. The PostToolUse hook already ran both during edits, and a PHP-less diff cannot regress either suite.
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

> You are a **Principal Automation Engineering Architect** reviewing manual testing steps from a PR. Your job: eliminate every step that can be replaced by automated verification. Be aggressive — manual testing is expensive and error-prone. Only steps requiring subjective human judgment (visual aesthetics, UX feel, production data comparison) should survive.
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
> | **Truly manual** | Requires subjective human judgment that no automated test can replicate (visual aesthetics, "does this look right", production comparison) | Stays in PR description |
>
> For each step, return a JSON array:
> ```json
> [
>   {"step": "original step text", "category": "cli-executable|phpunit|api-test|e2e|truly-manual", "rationale": "why this category", "test_hint": "what the test should assert (omit for cli-executable and truly-manual)"}
> ]
> ```
>
> **Bias toward automation.** If a step says "verify X works" or "check that Y returns Z", that is automatable — not manual. "Compare against production" is truly manual only if it requires visual judgment; if it's comparing data values, write an assertion.

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

Save to memory only if something was learned that would **prevent a bug** in a future session and isn't already in MEMORY.md, CLAUDE.md, or `.claude/rules/`. Read the target memory file first to avoid duplicates. If nothing qualifies, skip silently.

---

## Phase 11: Worktree Preview Environment

**Skip if** worktree was pre-existing or earlier phases left uncommitted fixes.

1. Tear down and restart with production data:
   ```bash
   bin/wt-down <worktree-name> --volumes
   bin/wt-up <worktree-name> --prod
   ```
2. Print preview URL: `http://<slug>.localhost/ibl5/`
3. Do NOT run `wt-remove` or `git branch -D`
