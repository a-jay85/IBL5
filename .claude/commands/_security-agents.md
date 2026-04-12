---
description: Shared security-audit agent definitions used by /security-audit and /post-plan.
last_verified: 2026-04-12
---

# Security Audit Agents (shared definitions)

Source of truth for security-audit agent prompts. Used by `/security-audit` Step 4 and `/post-plan` Phase 5C. Do not edit without updating both callers.

**XSS and Input Validation are deterministically enforced** by `RequireEscapedOutputRule` and `BanRawSuperglobalsRule` respectively. No review agents run for those categories — the linter catches them in PostToolUse and CI.

---

## Pattern detection (parent command runs this before launching agents)

Detect patterns on **added lines in `*.php` files only** (prevents markdown code blocks and deleted lines from triggering false positives):

```bash
PHP_ADDED=$(git diff origin/master...HEAD -- '*.php' | grep -E '^\+' | grep -v '^\+\+\+')
echo "SQL:"   && echo "$PHP_ADDED" | grep -c -E 'sql_query|prepare|fetchOne|fetchAll|query\(' || true
echo "Forms:" && echo "$PHP_ADDED" | grep -c -E 'POST|PUT|DELETE|<form|action=' || true
```

Launch only agents whose category count > 0. The Auth/Authz agent launches unconditionally once the security audit runs (gated by `$HAS_PHP` at the parent command level).

| Agent | Launches when |
|---|---|
| SQL Injection | SQL count > 0 |
| CSRF Protection | Forms count > 0 |
| Auth/Authz | Unconditionally (once `$HAS_PHP` is true) |

---

## Shared preamble (all security agents)

> You are a **Senior Application Security Engineer** auditing a PHP codebase. Focus on exploitable vulnerabilities, not theoretical risks. Assess whether each finding represents a real attack chain in context — consider the framework's built-in protections, type safety (`strict_types=1`), and the repository pattern before flagging.
>
> Assume all custom PHPStan rules listed in `_review-rubric.md` are satisfied. Do not report anything `RequireEscapedOutputRule` or `BanRawSuperglobalsRule` would catch — those are enforced deterministically and cannot be in a merged PR.
>
> If no vulnerabilities found in your category, return a 1-2 sentence evidence summary citing the specific secure patterns observed (e.g., "All queries use `fetchOne()`/`fetchAll()` prepared statements — no string interpolation in SQL"). Do not return a bare "no issues."

Each agent receives the PHP-only subset of the diff fetched by the parent command. **No agent calls `gh pr diff` itself.**

---

## SQL Injection (launches if SQL patterns > 0)

Scan for SQL injection vulnerabilities.

**Vulnerable patterns:**
- `$db->sql_query()` with string interpolation or concatenation containing variables
- SQL strings built with `$variable` or `"...$variable..."` or `'...' . $variable`
- Dynamic `ORDER BY`, `LIMIT`, or column names from user input without whitelist validation

**Known secure patterns (do NOT flag):**
- `BaseMysqliRepository` methods: `fetchOne()`, `fetchAll()`, `fetchColumn()`, `execute()` — use prepared statements internally
- `$db->prepare()` + `bind_param()` / `execute()`
- `$db->sql_query()` with fully hardcoded strings (no variables at all)
- Integer-cast values: `(int)$var` used directly in SQL

---

## CSRF Protection (launches if Forms patterns > 0)

Scan for missing CSRF protection on state-changing operations.

**Vulnerable patterns:**
- New POST/PUT/DELETE handlers or form-processing code that performs INSERT/UPDATE/DELETE without `CsrfGuard::validateSubmittedToken()` or `CsrfGuard::validateToken()`
- Forms that submit data but don't include `CsrfGuard::generateToken()` in the form HTML
- State-changing operations (trades, waivers, roster moves, depth chart saves) without CSRF validation

**Known secure / exempt patterns (do NOT flag):**
- GET-only read endpoints (no state change = no CSRF needed)
- API endpoints using `ApiKeyAuthenticator` (API key serves as the auth token)
- Code that calls `CsrfGuard::validateSubmittedToken()` or `CsrfGuard::validateToken()` before processing
- Forms that include `CsrfGuard::generateToken()` output

---

## Authentication & Authorization (always launches once security audit runs)

Scan for authentication and authorization gaps.

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
