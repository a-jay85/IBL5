---
name: code-review
description: Final validation checklist for IBL5 pull requests before merge. Use when reviewing PRs, validating refactored code, or preparing for merge.
---

# IBL5 Code Review Checklist

Validate code quality, test coverage, and documentation before PR merge.

## 1. Code Quality

### Class Autoloading
- [ ] No `require()` or `require_once()` for classes
- [ ] All classes in `ibl5/classes/` directory
- [ ] Class filename matches class name

### Type Safety
- [ ] `declare(strict_types=1);` in every PHP file
- [ ] Complete type hints on ALL methods
- [ ] Return types specified (including `void`, `?Type`)

### Interface Compliance
- [ ] All classes `implement` their interface
- [ ] Method signatures match interface exactly
- [ ] `@see InterfaceName::methodName()` docblocks present

### Constructor Verification
**CRITICAL:** Before confirming any class is correct:
1. Find the `__construct()` method signature
2. Count required vs optional parameters
3. Search for all usages: `grep -r "new ClassName"`
4. Verify every instantiation passes correct arguments

## 2. Database Operations

### Dual Implementation Support
- [ ] `method_exists($db, 'sql_escape_string')` check present
- [ ] Legacy path uses `DatabaseService::escapeString()`
- [ ] Modern path uses prepared statements

### Security
- [ ] No string interpolation with user input in SQL
- [ ] All output uses `HtmlSanitizer::safeHtmlOutput()`
- [ ] Whitelist validation for enumerated values

## 3. Testing

### Test Registration
- [ ] Test directory exists in `ibl5/tests/ModuleName/`
- [ ] Tests registered in `ibl5/phpunit.xml`

### Test Quality
- [ ] No `markTestSkipped()` calls
- [ ] No `ReflectionClass` for private methods
- [ ] Descriptive test method names
- [ ] One behavior per test

### Test Results
```bash
cd ibl5 && vendor/bin/phpunit tests/ModuleName/
```
- [ ] All tests pass
- [ ] No warnings
- [ ] No failures

## 4. Documentation

- [ ] `STRATEGIC_PRIORITIES.md` - Module marked complete
- [ ] `REFACTORING_HISTORY.md` - Entry added
- [ ] `ibl5/classes/ModuleName/README.md` - Created
- [ ] `DEVELOPMENT_GUIDE.md` - Counts updated

## 5. Code Cleanup

- [ ] No unused method parameters
- [ ] No commented-out code blocks
- [ ] Domain values are class constants
- [ ] No magic numbers/strings

## Report Format

```markdown
## PR Review: ModuleName Module

### ✅ Passed Checks
- [List items that passed]

### ⚠️ Issues Found
**File:** `path/to/file.php:line`
**Issue:** Description
**Recommendation:** How to fix

### 📊 Summary
| Category | Status |
|----------|--------|
| Code Quality | ✅/⚠️/❌ |
| Database Security | ✅/⚠️/❌ |
| Test Coverage | ✅/⚠️/❌ |
| Documentation | ✅/⚠️/❌ |

### Recommendation
- [ ] Ready for merge
- [ ] Needs minor fixes
- [ ] Needs significant work
```

## Final Verification

```bash
cd ibl5 && vendor/bin/phpunit  # Full suite - zero warnings/failures
```

PR ready for merge only when ALL checks pass.
