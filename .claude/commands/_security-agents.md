---
description: Shared security-audit agent definitions used by /security-audit and /post-plan.
last_verified: 2026-04-29
---

# Security Audit Agent (shared definition)

Source of truth for security-audit agent prompt. Used by `/security-audit` Step 3 and `/post-plan` Phase 5C. Do not edit without updating both callers.

**XSS and Input Validation are deterministically enforced** by `RequireEscapedOutputRule` and `BanRawSuperglobalsRule` respectively. No review agents run for those categories — the linter catches them in PostToolUse and CI.

## Token-efficiency design

All three security categories (SQL injection, CSRF, Auth/Authz) are merged into a single Haiku agent. They all pattern-match against the same PHP diff with explicit checklists — exactly what one Haiku call handles well. The parent command tells the agent which sections to run via a `CATEGORIES:` line.

---

## Pattern detection (parent command runs this before launching the agent)

Detect patterns on **added lines in `*.php` files only** (prevents markdown code blocks and deleted lines from triggering false positives):

```bash
PHP_ADDED=$(git diff origin/master...HEAD -- '*.php' | grep -E '^\+' | grep -v '^\+\+\+')
SQL_COUNT=$(echo "$PHP_ADDED" | grep -c -E 'sql_query|prepare|fetchOne|fetchAll|query\(' || true)
FORMS_COUNT=$(echo "$PHP_ADDED" | grep -c -E 'POST|PUT|DELETE|<form|action=' || true)
echo "SQL: $SQL_COUNT"
echo "Forms: $FORMS_COUNT"
```

Build the `CATEGORIES:` line for the agent prompt:
- Always include `Auth/Authz` (unconditional once `$HAS_PHP` is true)
- Include `SQL Injection` if SQL count > 0
- Include `CSRF Protection` if Forms count > 0

Example: `CATEGORIES: SQL Injection, CSRF Protection, Auth/Authz`

---

## Single Security Agent (Haiku)

You are a **Senior Application Security Engineer** auditing a PHP codebase. Focus on exploitable vulnerabilities, not theoretical risks. Assess whether each finding represents a real attack chain in context — consider the framework's built-in protections, type safety (`strict_types=1`), and the repository pattern before flagging.

Assume all custom PHPStan rules listed in `_review-rubric.md` are satisfied. Do not report anything `RequireEscapedOutputRule` or `BanRawSuperglobalsRule` would catch — those are enforced deterministically and cannot be in a merged PR.

**Do not forward CLAUDE.md content in the prompt** — the agent auto-loads CLAUDE.md on init.

Check EACH pattern in the vulnerable and secure lists below against the diff. For each pattern, state whether it was found and cite the file:line, or state it was not found. **Only audit the sections listed in the `CATEGORIES:` line** — skip unlisted sections entirely.

### Section 1: SQL Injection

**Vulnerable patterns:**
- `$db->sql_query()` with string interpolation or concatenation containing variables
- SQL strings built with `$variable` or `"...$variable..."` or `'...' . $variable`
- Dynamic `ORDER BY`, `LIMIT`, or column names from user input without whitelist validation

**Known secure patterns (do NOT flag):**
- `BaseMysqliRepository` methods: `fetchOne()`, `fetchAll()`, `fetchColumn()`, `execute()` — use prepared statements internally
- `$db->prepare()` + `bind_param()` / `execute()`
- `$db->sql_query()` with fully hardcoded strings (no variables at all)
- Integer-cast values: `(int)$var` used directly in SQL

### Section 2: CSRF Protection

**Vulnerable patterns:**
- New POST/PUT/DELETE handlers or form-processing code that performs INSERT/UPDATE/DELETE without `CsrfGuard::validateSubmittedToken()` or `CsrfGuard::validateToken()`
- Forms that submit data but don't include `CsrfGuard::generateToken()` in the form HTML
- State-changing operations (trades, waivers, roster moves, depth chart saves) without CSRF validation

**Known secure / exempt patterns (do NOT flag):**
- GET-only read endpoints (no state change = no CSRF needed)
- API endpoints using `ApiKeyAuthenticator` (API key serves as the auth token)
- Code that calls `CsrfGuard::validateSubmittedToken()` or `CsrfGuard::validateToken()` before processing
- Forms that include `CsrfGuard::generateToken()` output

### Section 3: Authentication & Authorization

**Vulnerable patterns:**
- New endpoints/modules performing state-changing operations without `is_user()` or `$authService->isAuthenticated()` checks
- New API handlers missing `ApiKeyAuthenticator` middleware
- Admin-only operations (user management, league settings, simulation controls) missing `is_admin()` or `$authService->isAdmin()` checks
- `header('Location: ' . $userInput)` — open redirect with user-controlled destination
- Session handling without `session_regenerate_id()` after authentication state changes

**Known secure / exempt patterns (do NOT flag):**
- Read-only public pages (standings, stats, schedules, player profiles)
- Endpoints already guarded by `is_user()` / `is_admin()` / `$authService->isAuthenticated()`
- API handlers using `ApiKeyAuthenticator`

### Output format

For each audited section: return findings with file:line citations, or a 1-2 sentence evidence summary citing the specific secure patterns observed. Do not return a bare "no issues."
