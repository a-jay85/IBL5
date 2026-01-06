# IBL5 Universal Coding Rules

**Use custom agents for task-specific workflows:** See `.github/agents/` for specialized agents (refactoring, testing, security, documentation, review) with handoffs between workflow stages.

---

## ⚠️ CRITICAL RULES (Always Apply)

### 1. Class Autoloading - NO Manual Requires
```php
// ✅ CORRECT - Just use the class name
$player = new Player($db);

// ❌ WRONG - NEVER do this
require_once 'classes/Player.php';
```
- All classes in `ibl5/classes/`
- Filename = class name (e.g., `Player.php`)

### 2. Type Hints Required
```php
public function getPlayer(int $playerId): ?Player
public function getTeamRoster(int $teamId): array
```
- `declare(strict_types=1);` in every file

### 3. Database Dual-Implementation
```php
if (method_exists($this->db, 'sql_escape_string')) {
    // LEGACY: sql_* methods + DatabaseService::escapeString()
} else {
    // MODERN: prepared statements (preferred)
}
```

### 4. Security
- **SQL**: Prepared statements or escaped strings
- **XSS**: Use `Utilities\HtmlSanitizer::safeHtmlOutput()` on ALL output (handles multiple types, removes SQL escaping)
- **Validation**: Whitelist for enumerated values

### 5. Testing
- PHPUnit 12.4+ in `ibl5/tests/`
- Register in `ibl5/phpunit.xml`
- No `markTestSkipped()` - delete instead
- Zero warnings or failures
- **Mock objects**: Use PHPDoc annotations for IDE support
  ```php
  /** @var InterfaceName&\PHPUnit\Framework\MockObject\MockObject */
  private InterfaceName $mockObject;
  ```
- Use @see instead of {@inheritdoc} in PHPDoc

---

## Quick Reference

| Task | Command |
|------|---------|
| Run tests | `cd ibl5 && vendor/bin/phpunit` |
| Run specific | `vendor/bin/phpunit tests/Module/` |
| Schema | `ibl5/schema.sql` |
| Stats formatting | `Statistics\StatsFormatter` |

---

## Custom Agents Workflow

Select agents from the agent picker for task-specific workflows:

1. **IBL5 Refactoring** → Extract modules to Repository/Service/View
2. **IBL5 Testing** → Write PHPUnit behavior-focused tests  
3. **IBL5 Security** → Audit for SQL injection/XSS
4. **IBL5 Documentation** → Update docs during PRs
5. **IBL5 Review** → Final validation before merge

Each agent has **handoff buttons** to transition to the next workflow stage.

---

## Resources

- [DEVELOPMENT_GUIDE.md](../DEVELOPMENT_GUIDE.md) - Priorities & status
- [DATABASE_GUIDE.md](../DATABASE_GUIDE.md) - Schema reference
- [ibl5/docs/](../ibl5/docs/) - Architecture, testing, environment guides
