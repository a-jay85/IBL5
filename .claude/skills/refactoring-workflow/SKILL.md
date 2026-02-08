---
name: refactoring-workflow
description: IBL5 module refactoring using interface-driven architecture pattern with Repository/Service/View separation. Use when refactoring legacy PHP-Nuke modules to modern PHP.
---

# IBL5 Module Refactoring Workflow

Refactor PHP-Nuke legacy modules to modern PHP with interface-driven architecture.

## Architecture Pattern

Extract each module into `ibl5/classes/ModuleName/`:

```
Module/
├── Contracts/
│   ├── ModuleRepositoryInterface.php
│   ├── ModuleValidatorInterface.php
│   └── ModuleServiceInterface.php
├── ModuleRepository.php    # implements ModuleRepositoryInterface
├── ModuleValidator.php     # implements ModuleValidatorInterface
├── ModuleService.php       # implements ModuleServiceInterface
└── ModuleView.php          # HTML rendering with output buffering
```

## Refactoring Steps

1. **Analyze** - Identify responsibilities in existing module
2. **Design interfaces** - Define contracts in `Contracts/` subdirectory
3. **Extract Repository** - Database operations extending `BaseMysqliRepository`
4. **Extract Validator** - Input validation with whitelist patterns
5. **Extract Service** - Business logic and orchestration
6. **Extract View** - HTML rendering with output buffering
7. **Update module index.php** - Thin controller calling service classes
8. **Security & Standards Audit** - XSS protection, HTML modernization
9. **Production Validation** - Compare localhost against iblhoops.net

## Interface Standards

Each interface MUST contain comprehensive PHPDoc:
- Method signatures with parameter types and return types
- Behavioral documentation (what and why)
- Parameter constraints and valid ranges

## Implementation Rules

1. **Use `implements InterfaceName`** on all classes
2. **Add `@see InterfaceName::methodName()`** instead of duplicating docblocks
3. **Class constants for domain values** (not function arguments)
4. **Strict types**: `declare(strict_types=1);` in every file

## Database Access

All repositories extend `BaseMysqliRepository` and use prepared statements:

```php
// Use BaseMysqliRepository helper methods
return $this->fetchOne("SELECT * FROM table WHERE col = ?", "s", $value);
return $this->fetchAll("SELECT * FROM table WHERE tid = ?", "i", $teamId);
return $this->execute("UPDATE table SET col = ? WHERE id = ?", "si", $value, $id);
```

## Production Validation

After refactoring, compare localhost against iblhoops.net:
- Page rendering and layout must match
- Data values (stats, names, calculations) must be identical
- List ordering and sorting must match
- If output doesn't match exactly, refactoring is incomplete

## Templates

See [templates/](./templates/) for starter files:
- [ModuleInterface.php](./templates/ModuleInterface.php) - Interface template
- [ModuleRepository.php](./templates/ModuleRepository.php) - Repository template
- [ModuleService.php](./templates/ModuleService.php) - Service template
- [ModuleView.php](./templates/ModuleView.php) - View template

## Reference Implementations

- `ibl5/classes/PlayerDatabase/` - 4 interfaces, 4 classes, 48 tests
- `ibl5/classes/FreeAgency/` - 11 interfaces, 12 classes, 94 tests
- `ibl5/classes/Player/` - 30 interfaces, 35 classes, 205 tests
