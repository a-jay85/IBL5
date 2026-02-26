---
allowed-tools: Bash(gh pr diff:*), Bash(gh pr view:*), Bash(gh pr comment:*),
  Bash(gh api:*), Bash(git rev-parse:*)
description: Token-efficient security audit for pull requests
---

Perform a security audit on the given pull request. This command optimizes token usage by fetching the diff once and distributing it to specialized security agents.

## Step 1: Eligibility check

Use a **Haiku** agent to check if the pull request:
(a) is closed, (b) is a draft, (c) has zero PHP files changed, or (d) already has a `### Security audit` comment from you.

If any of these are true, do not proceed. Tell the user why.

## Step 2: Fetch all data once (parent context — do NOT delegate this to agents)

Run these commands yourself (not via agents) and store the results:

### 2a. Get PR metadata
```bash
gh pr view --json number,headRefOid,headRefName,baseRefName
```

### 2b. Get the PHP file list
```bash
gh pr diff --name-only | grep '\.php$'
```

### 2c. Get the PHP-only diff
```bash
gh pr diff | awk '/^diff --git/{found=0} /^diff --git.*\.php/{found=1} found{print}'
```

### 2d. Measure the PHP diff size
```bash
gh pr diff | awk '/^diff --git/{found=0} /^diff --git.*\.php/{found=1} found{print}' | wc -c
```

**If the PHP diff is larger than 100,000 bytes:** Instead of the full diff, use the GitHub API to get per-file patches (excluding test files):
```bash
gh api "repos/a-jay85/IBL5/pulls/{N}/files" --paginate --jq '.[] | select(.filename | test("\\.php$")) | select(.filename | test("tests/") | not) | "--- " + .filename + " ---\n" + (.patch // "(binary or too large)")'
```

Store all of these results — they will be passed as context to agents below.

## Step 3: PR summary

Use a **Haiku** agent. Pass it the PR metadata and PHP file list from Step 2. Ask it to return a 2-3 sentence summary of the change.

## Step 4: Five parallel Sonnet agents

Launch **5 parallel Sonnet agents**. Each agent receives the PHP diff from Step 2c and must return a list of findings with file, line number(s), and description. If the agent finds no issues, it returns an empty list.

**CRITICAL: No agent should call `gh pr diff`. The diff was already fetched in Step 2.**

### Agent 1: SQL Injection
Pass this agent the PHP diff from Step 2c.

Task: Scan for SQL injection vulnerabilities.

**Vulnerable patterns:**
- `$db->sql_query()` with string interpolation or concatenation containing variables
- SQL strings built with `$variable` or `"...$variable..."` or `'...' . $variable`
- Dynamic `ORDER BY`, `LIMIT`, or column names from user input without whitelist validation

**Known secure patterns (do NOT flag):**
- `BaseMysqliRepository` methods: `fetchOne()`, `fetchAll()`, `fetchColumn()`, `execute()` — these use prepared statements internally
- `$db->prepare()` + `bind_param()` / `execute()`
- `$db->sql_query()` with fully hardcoded strings (no variables at all)
- Integer-cast values: `(int)$var` used directly in SQL

### Agent 2: XSS / Output Encoding
Pass this agent the PHP diff from Step 2c.

Task: Scan for cross-site scripting vulnerabilities.

**Vulnerable patterns:**
- `echo $var` without `HtmlSanitizer::safeHtmlOutput()` wrapping
- `<?= $var ?>` without `HtmlSanitizer::safeHtmlOutput()`
- HTML string concatenation with raw variables: `'<td>' . $name . '</td>'`
- `sprintf()` producing HTML where `%s` substitutions are unsanitized

**Known secure patterns (do NOT flag):**
- `\Utilities\HtmlSanitizer::safeHtmlOutput($var)` — even if on a different line than the echo
- `json_encode()` for JavaScript contexts
- `(int)` cast values in HTML output
- `echo` in CLI scripts (no web context)
- Hardcoded string literals with no variables

### Agent 3: Input Validation
Pass this agent the PHP diff from Step 2c.

Task: Scan for input validation gaps.

**Vulnerable patterns:**
- Direct use of `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE` without `filter_input()` or explicit validation
- Superglobal values used directly in SQL queries or file paths
- `$_GET['sort']` or similar column/field names without whitelist validation

**Known secure patterns (do NOT flag):**
- `filter_input(INPUT_GET, ..., FILTER_VALIDATE_INT)` and similar
- `in_array($val, $whitelist, true)` validation
- Typed parameters in `strict_types=1` files (PHP enforces the type)
- Integer type hints on function parameters receiving superglobal values

### Agent 4: CSRF Protection
Pass this agent the PHP diff from Step 2c.

Task: Scan for missing CSRF protection on state-changing operations.

**Vulnerable patterns:**
- New POST/PUT/DELETE handlers or form-processing code that performs INSERT/UPDATE/DELETE without `CsrfGuard::validateSubmittedToken()` or `CsrfGuard::validateToken()`
- Forms that submit data but don't include `CsrfGuard::generateToken()` in the form HTML
- State-changing operations (trades, waivers, roster moves, depth chart saves) without CSRF validation

**Known secure / exempt patterns (do NOT flag):**
- GET-only read endpoints (no state change = no CSRF needed)
- API endpoints using `ApiKeyAuthenticator` (API key serves as the auth token)
- Code that calls `CsrfGuard::validateSubmittedToken()` or `CsrfGuard::validateToken()` before processing
- Forms that include `CsrfGuard::generateToken()` output

### Agent 5: Authentication & Authorization
Pass this agent the PHP diff from Step 2c AND the PHP file list from Step 2b.

Task: Scan for authentication and authorization gaps.

**Vulnerable patterns:**
- New endpoints/modules performing state-changing operations without `is_user()` or `$authService->isAuthenticated()` checks
- New API handlers missing `ApiKeyAuthenticator` middleware
- Admin-only operations (user management, league settings, simulation controls) missing `is_admin()` or `$authService->isAdmin()` checks
- `header('Location: ' . $userInput)` — open redirect with user-controlled destination
- Session handling without `session_regenerate_id()` after authentication state changes

**Known secure / exempt patterns (do NOT flag):**
- Read-only public pages (standings, stats, schedules, player profiles) — no auth needed
- Endpoints already guarded by `is_user()` / `is_admin()` / `$authService->isAuthenticated()`
- API handlers using `ApiKeyAuthenticator`

## Step 5: Confidence scoring

For each finding from Step 4, launch a parallel **Haiku** agent that takes the PR summary, finding description, and the relevant code context. Each scoring agent returns a confidence score from 0-100 using this rubric:

- **0:** False positive — code is secure. Variable is validated upstream, parameter is type-hinted int in strict_types, string is hardcoded, API endpoint uses ApiKeyAuthenticator, etc.
- **25:** Suspicious but likely mitigated. Variable is constrained elsewhere, or pattern is technically present but unexploitable in context.
- **50:** Pattern present but exploitation requires specific conditions that may not apply.
- **75:** Clearly present vulnerability with no visible mitigation in the diff.
- **100:** Direct user input flows to SQL/HTML/file/state-change with zero sanitization or validation.

**IBL5-specific false positives to downgrade to 0-25:**
- Variables from `BaseMysqliRepository` methods (already parameterized)
- Test files (`tests/` directory — not production attack surface)
- Integers in `strict_types=1` files with typed parameters
- `echo` in CLI scripts (no web context)
- `$db->sql_query()` with fully hardcoded strings (no variables)
- `HtmlSanitizer` wrapping on a different line than the `echo` (still protected)
- API handlers that already use `ApiKeyAuthenticator` (CSRF exempt)
- Read-only GET handlers (CSRF exempt)

## Step 6: Filter

Filter out any findings with a score less than **75**. If no findings meet this threshold, skip to Step 8 with "no issues found".

## Step 7: Re-check eligibility

Use a **Haiku** agent to repeat the eligibility check from Step 1, to make sure the PR is still open and hasn't been merged while the audit was running.

## Step 8: Post comment

Use `gh pr comment` to post the audit results. Use the PR number and head SHA from Step 2a for links.

### Comment format (if issues found):

---

### Security audit

Found N issue(s):

**[SEVERITY]** Vulnerability type in `Class::method()` — description

<link to file and line with full SHA + line range>

**[SEVERITY]** Vulnerability type in `Class::method()` — description

<link to file and line with full SHA + line range>

Generated with [Claude Code](https://claude.ai/code)

<sub>If this security audit was useful, please react with thumbs-up. Otherwise, react with thumbs-down.</sub>

---

### Severity mapping:
- **CRITICAL** — SQL injection, command injection
- **HIGH** — XSS, missing auth on state-changing endpoints, open redirect
- **MEDIUM** — Missing CSRF token, input validation gaps, missing auth on non-critical endpoints
- **LOW** — Best practice deviations

### Comment format (if no issues):

---

### Security audit

No security issues found. Scanned for SQL injection, XSS, input validation, CSRF, and auth/authz vulnerabilities.

Generated with [Claude Code](https://claude.ai/code)

---

### Link format rules:
- Must use the full git SHA (from Step 2a's `headRefOid`)
- Format: `https://github.com/a-jay85/IBL5/blob/{FULL_SHA}/path/to/file#L{start}-L{end}`
- Provide at least 1 line of context before and after the line you are commenting about
- Do NOT use `$(git rev-parse HEAD)` or any bash interpolation in the comment — expand the SHA beforehand

### Notes:
- Do not check build signal or attempt to build or typecheck the app. These will run separately.
- Use `gh` to interact with GitHub, not web fetch.
- Make a todo list first.
