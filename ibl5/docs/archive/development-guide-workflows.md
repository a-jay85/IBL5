# Archived Development Workflows

**Archive Date:** January 2025  
**Reason:** Content migrated to `.github/skills/` and `.claude/rules/` for progressive loading.

---

## Original Mandatory Code Review Section

### Pull Request Implementation (Non-Negotiable)
**When implementing pull request feedback:**
- Read through ALL comments on the PR to understand the complete feedback scope
- For each comment, search the entire PR's changed files for similar patterns
- Fix all instances of the identified issue, not just the lines with comments
- Example: If a review comment flags an XSS vulnerability on line 45, search the same file and related files for other similar unprotected outputs
- Use grep or semantic search to ensure you catch all variations of the pattern
- This prevents follow-up comments on the same implementation

### Security Audit (Non-Negotiable)
1. **XSS Protection**
   - [ ] All database-sourced content wrapped in `Utilities\HtmlSanitizer::safeHtmlOutput()`
   - [ ] All user inputs sanitized before output (player names, game text, form data)
   - [ ] Play-by-play text, error messages, and dynamic content properly escaped
   - [ ] HTML generated in business logic classes sanitized before embedding in output
   - **Detection:** Search for database queries, `$_POST`, `$_GET`, or string interpolation in HTML context
   - **Action:** Fix immediately - do not defer or mark as "future work"

2. **SQL Injection Protection**
   - [ ] All database queries use prepared statements via `BaseMysqliRepository`
   - [ ] No raw SQL string concatenation with variables
   - [ ] User inputs validated before database operations

### Standards Compliance (Non-Negotiable)
3. **HTML/CSS Modernization**
   - [ ] No deprecated tags: `<b>`, `<i>`, `<u>`, `<font>`, `<center>`
   - [ ] Replace with semantic HTML: `<strong style="font-weight: bold;">`, `<em style="font-style: italic;">`
   - [ ] No `border=` attributes - use `style="border: 1px solid #000; border-collapse: collapse;"`
   - [ ] Table cells with borders need `style="border: 1px solid #000; padding: 4px;"`
   - [ ] Extract repeated inline styles (2+ uses) to `<style>` blocks with CSS classes
   - **Detection:** Grep for `<b>`, `<font`, `border=`, or inspect HTML output in view classes
   - **Action:** Fix immediately - modernization is mandatory, not optional

---

## Original Refactoring Steps

1. Analyze - Identify responsibilities
2. Design - Plan class structure & interfaces
3. Create Interfaces - Document contracts with PHPDoc
4. Extract - Repository → Validator → Processor → View → Controller
5. Implement Interfaces - Add interface implementations and @see docblocks
6. Test - Unit + integration tests
7. **Security & Standards Audit** (MANDATORY)
   - [ ] XSS: All output wrapped in `HtmlSanitizer::safeHtmlOutput()` (scan database results, form data, play-by-play text)
   - [ ] SQL: All queries use prepared statements
   - [ ] HTML: No deprecated tags (`<b>`, `<font>`, `border=`) - convert to semantic HTML + inline CSS
   - [ ] CSS: Extract repeated styles (2+ uses) to classes
   - **Must be 100% compliant before proceeding** - no exceptions
8. **Production Validation** - Compare localhost against iblhoops.net
   - Verify all output (text, data, ordering, formatting) matches exactly
   - If mismatches found, debug and iterate until perfect match
   - This is the final verification gate before merge
9. Review - Code review, performance

---

## Original Class Pattern with Interface Architecture

```
Module/
├── Contracts/
│   ├── ModuleInterface.php
│   ├── ModuleRepositoryInterface.php
│   ├── ModuleValidatorInterface.php
│   └── ...more interfaces as needed
├── Module.php                    # implements ModuleInterface
├── ModuleRepository.php          # implements ModuleRepositoryInterface
├── ModuleValidator.php           # implements ModuleValidatorInterface
├── ModuleProcessor.php           # Business logic
├── ModuleView.php                # View rendering
└── ModuleService.php             # Service layer
```

---

## Original Testing Standards

**Coverage:** Current ~52% → Phase 1: 60% → Phase 2: 75% → Goal: 80%

**Test Pyramid:** Few E2E tests → Some integration → Many unit tests

**CI/CD:** ✅ GitHub Actions workflow implemented
- Automated PHPUnit tests on push/PR
- Composer dependency caching
- See `.github/workflows/tests.yml`

**Required:**
- All public methods tested
- Edge cases & error conditions
- Business rule validation
- Database operations
- Security (SQL injection, XSS)
- **Mock objects**: Use PHPDoc annotations for IDE support:
  ```php
  /** @var InterfaceName&\PHPUnit\Framework\MockObject\MockObject */
  private InterfaceName $mockRepository;
  ```

**No Unused Convenience Methods:**
- ❌ DO NOT create "helper" or "utility" methods that aren't immediately used
- ✅ Only implement methods that are **actively called** in the refactored code
- Each method must have:
  - At least one direct caller
  - Unit tests
  - Clear, documented purpose
- If a method seems "useful later", add it later with tests when it's actually needed
- Dead code confuses developers and increases maintenance burden

---

## Original Code Quality Section

**Type Hints Required:**
```php
// ✅ Good
public function getPlayer(int $playerId): ?Player
public function calculateAverage(array $values): float

// ❌ Bad
public function getPlayer($playerId)
```

**Class Autoloader:**
- Place classes in `ibl5/classes/`
- Filename = class name
- Never use `require()` for classes
- Reference: `$player = new Player($db);`

**Database Object Preference:**
- **Always use the global `$mysqli_db` object** (modern MySQLi with prepared statements)
- **Avoid the legacy `$db` object** whenever possible
- Example: `global $mysqli_db;` then use prepared statements with `$mysqli_db->prepare()`, `bind_param()`, and `execute()`
- Only use legacy `$db` when refactoring legacy code that hasn't yet been updated

**Statistics Formatting:**
- [ ] Use `BasketballStats\StatsFormatter` for ALL statistics (never `number_format()`)
  - `formatPercentage()` for shooting/field goal percentages
  - `formatPerGameAverage()` for per-game stats (PPG, APG, RPG, etc.)
  - `formatPer36Stat()` for per-36-minute stats
  - `formatTotal()` for counting stats with comma separators
  - `formatAverage()` for general 2-decimal averages
- [ ] Use `BasketballStats\StatsSanitizer` for input validation

**HTML & CSS Standards:**
- [ ] Convert deprecated styling tags (`<font>`, `<center>`, `<b>`, `<i>`, `<u>`) to semantic HTML + inline CSS
- [ ] Extract repeated inline styles (2+ occurrences) into `<style>` blocks with CSS classes
- [ ] Use semantic HTML (`<strong>`, `<em>`, `<div>`) instead of presentation tags
- [ ] Keep `<style>` blocks at top of file for maintainability

**Security Checklist:**
- [ ] Prepared statements (SQL injection)
- [ ] HTML escaping (XSS) - Use `Utilities\HtmlSanitizer::safeHtmlOutput()` instead of `htmlspecialchars()`
- [ ] Input validation
- [ ] Authorization checks
- [ ] CSRF protection

---

## Original Performance Section

**Database:**
- Use prepared statements
- Leverage indexes (see DATABASE_GUIDE.md)
- Use database views for complex queries
- Batch operations when possible

**Code:**
- Reuse repositories
- **Use `BasketballStats\StatsFormatter` and `BasketballStats\StatsSanitizer` for all statistics** (never `number_format()`)
- Avoid N+1 queries
- Cache expensive operations

---

## Migration Notes

This content has been migrated to:

| Original Section | New Location |
|-----------------|--------------|
| Refactoring Steps | `.github/skills/refactoring-workflow/` |
| Security Audit | `.github/skills/security-audit/` |
| Testing Standards | `.github/skills/phpunit-testing/` |
| Code Quality | `.claude/rules/php-classes.md` |
| PR Implementation | `.github/skills/code-review/` |
| Stats Formatting | `.github/skills/basketball-stats/` |

See [SKILLS_GUIDE.md](/.github/SKILLS_GUIDE.md) for the progressive loading architecture.
