---
name: security-audit
description: Security vulnerability patterns for IBL5 PHP code. Use when auditing security, fixing vulnerabilities, or reviewing code for security issues.
---

# IBL5 Security Patterns

Quick reference for secure vs. vulnerable patterns across 5 categories.

## 1. SQL Injection

```php
// ❌ $db->sql_query("SELECT * FROM t WHERE id = $id");
// ✅ $this->fetchOne("SELECT * FROM t WHERE id = ?", "i", $id);
// ✅ $stmt = $db->prepare("...?"); $stmt->bind_param('i', $id);
```

Secure: `BaseMysqliRepository` methods (`fetchOne`, `fetchAll`, `execute`), `prepare()`+`bind_param()`, hardcoded SQL strings, `(int)` casts.

## 2. XSS / Output Encoding

```php
// ❌ echo $player['name'];
// ✅ echo \Utilities\HtmlSanitizer::safeHtmlOutput($player['name']);
```

Secure: `HtmlSanitizer::safeHtmlOutput()`, `json_encode()` for JS context, `(int)` cast values.

## 3. Input Validation

```php
// ❌ $id = $_GET['pid'];
// ✅ $id = filter_input(INPUT_GET, 'pid', FILTER_VALIDATE_INT);
// ✅ in_array($_GET['sort'], $allowedColumns, true)
```

Secure: `filter_input()`, whitelist validation with strict `in_array()`, typed parameters in `strict_types=1`.

## 4. CSRF Protection

```php
// ❌ POST handler modifies state without token validation
// ✅ CsrfGuard::validateSubmittedToken() before processing
// ✅ CsrfGuard::generateToken() in form HTML
```

Exempt: GET-only endpoints, API handlers using `ApiKeyAuthenticator`.

## 5. Authentication & Authorization

```php
// ❌ State-changing endpoint with no auth check
// ✅ if (!is_user($user)) { redirect to login; }
// ✅ ApiKeyAuthenticator for API endpoints
// ✅ is_admin() for admin-only operations
```

Exempt: Read-only public pages (standings, stats, schedules).

## Severity

| Level | Examples |
|-------|---------|
| **CRITICAL** | SQL injection, command injection |
| **HIGH** | XSS, missing auth on state-changing endpoints, open redirect |
| **MEDIUM** | Missing CSRF token, input validation gaps |
| **LOW** | Best practice deviations |

## PR Audits

Use `/security-audit` for automated, token-efficient security audits on pull requests.
