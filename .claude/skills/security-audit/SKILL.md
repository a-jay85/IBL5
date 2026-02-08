---
name: security-audit
description: Security vulnerability detection and remediation for XSS and SQL injection in IBL5 PHP code. Use when auditing security, fixing vulnerabilities, or reviewing code for security issues.
---

# IBL5 Security Audit

Identify and fix SQL injection, XSS, and input validation vulnerabilities.

## Primary Vulnerability Checks

### 1. SQL Injection

**Vulnerable patterns:**
```php
// ❌ VULNERABLE - String interpolation
$query = "SELECT * FROM table WHERE id = $id";
$query = "SELECT * FROM table WHERE name = '$name'";
```

**Secure patterns:**
```php
// ✅ SECURE - Prepared statements via BaseMysqliRepository
return $this->fetchOne("SELECT * FROM table WHERE id = ?", "i", $id);
return $this->fetchAll("SELECT * FROM table WHERE name = ?", "s", $name);
return $this->execute("UPDATE table SET col = ? WHERE id = ?", "si", $val, $id);

// ✅ SECURE - Direct prepared statements (when not using BaseMysqliRepository)
$stmt = $db->prepare("SELECT * FROM table WHERE id = ?");
$stmt->bind_param('i', $id);
```

### 2. XSS (Cross-Site Scripting)

**Vulnerable patterns:**
```php
// ❌ VULNERABLE - Direct output
echo $username;
<?= $row['name'] ?>
```

**Secure patterns:**
```php
// ✅ SECURE - Use HtmlSanitizer
echo \Utilities\HtmlSanitizer::safeHtmlOutput($username);
<?= \Utilities\HtmlSanitizer::safeHtmlOutput($row['name']) ?>
```

### 3. Input Validation

**Vulnerable patterns:**
```php
// ❌ VULNERABLE - No validation
$playerId = $_GET['pid'];
$sortColumn = $_GET['sort'];
$query = "ORDER BY $sortColumn";
```

**Secure patterns:**
```php
// ✅ SECURE - Type casting and whitelist
$playerId = filter_input(INPUT_GET, 'pid', FILTER_VALIDATE_INT);

$allowedColumns = ['name', 'age', 'position'];
$sortColumn = in_array($_GET['sort'], $allowedColumns, true) 
    ? $_GET['sort'] : 'name';
```

## Audit Checklist

### Database Operations
- [ ] All queries use prepared statements OR properly escaped values
- [ ] No string interpolation with user input in SQL
- [ ] Dynamic table/column names validated against whitelist
- [ ] LIMIT/OFFSET values are integers

### Output Encoding
- [ ] All user-controlled output uses `HtmlSanitizer::safeHtmlOutput()`
- [ ] HTML attributes properly escaped
- [ ] JavaScript contexts use `json_encode()` for data

### Input Validation
- [ ] Integer inputs validated with `filter_var()` or type casting
- [ ] Enumerated values checked against whitelist
- [ ] String inputs have maximum length limits

## Report Format

```
## [SEVERITY] Vulnerability Type - filename.php:line

**Location:** `ClassName::methodName()`

**Vulnerable Code:**
// Show the problematic code

**Risk:** What an attacker could do

**Recommended Fix:**
// Show the secure version
```

Severity: **CRITICAL** (SQL injection) | **HIGH** (XSS) | **MEDIUM** (validation) | **LOW** (best practice)

## Examples

See [examples/](./examples/) for before/after patterns:
- [xss-before-after.php](./examples/xss-before-after.php)
- [sql-injection-patterns.php](./examples/sql-injection-patterns.php)

## Secured Reference Modules

- `ibl5/classes/PlayerDatabase/` - 15+ injection points fixed
- `ibl5/classes/DepthChartEntry/` - Fully refactored with prepared statements
