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

**Manual testing in PR description:** Only include a "Manual Testing" section if the PR has functionality not already covered by existing E2E (Playwright) and unit (PHPUnit) tests. If automated tests fully cover the changed behavior, omit the section entirely. When included, use a markdown checklist (`- [ ]`) — minimal, no redundancy with automated checks.

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
- Once all pass, proceed to Phase 7.

---

## Phase 7: CI Monitoring

1. Find CI runs: `gh run list --branch <branch-name> --limit 5`
2. Monitor: `gh run watch <run-id>` or poll with `gh run view <run-id>`
3. If a job fails: `gh run view <run-id> --log-failed` to get the error
4. Fix the issue in the worktree, commit, and push
5. Repeat until all CI jobs pass
6. Report final CI status

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

## Phase 9: Worktree & Docker Teardown

After all phases complete successfully, tear down the worktree and its Docker environment. The branch and PR are already pushed — the worktree is no longer needed. The user will check out the branch in the main repo if they need to verify or make further changes.

1. `cd` to the repo root (not the worktree)
2. Tear down Docker: `bin/wt-down <worktree-name> --volumes --force`
3. Remove the worktree: `git worktree remove --force worktrees/<worktree-name>`
4. Switch back to master: `git checkout master`

**Skip this phase if:**
- The worktree was not created by Phase 1 (e.g., pre-existing worktree the user asked you to work in)
- Any earlier phase failed and there are uncommitted fixes in the worktree
- The user explicitly asked to keep the worktree
