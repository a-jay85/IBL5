# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

IBL5 is an Internet Basketball League fantasy basketball site powered by Jump Shot Basketball simulation engine. PHP 8+ with MariaDB 10.6, using interface-driven architecture.

## Commands

```bash
# Run all tests
cd ibl5 && vendor/bin/phpunit

# Run single test file
cd ibl5 && vendor/bin/phpunit tests/Player/PlayerRepositoryTest.php

# Run single test method
cd ibl5 && vendor/bin/phpunit --filter testMethodName

# Run specific test suite
cd ibl5 && vendor/bin/phpunit --testsuite "Player Module Tests"
```

## Architecture

### Interface-Driven Modules
All 30 modules follow Repository/Service/View pattern with interfaces in `Contracts/` subdirectories:
```
ibl5/classes/
├── Player/
│   ├── Contracts/           # Interfaces (PlayerRepositoryInterface, etc.)
│   ├── PlayerRepository.php # Database operations
│   ├── PlayerService.php    # Business logic
│   └── PlayerView.php       # HTML rendering
├── FreeAgency/
├── Trading/
└── ... (30 modules total)
```

### Key Patterns
- **Repository:** Database queries via prepared statements
- **Service:** Business logic, validation, calculations
- **View:** HTML generation with XSS protection
- **Controller:** Request handling (where applicable)

### Class Autoloading
Classes autoload from `ibl5/classes/`. Never use `require_once`.

### Database
- Schema: `ibl5/schema.sql` - **always verify table/column names here**
- Use `$mysqli_db` (modern MySQLi) over legacy `$db`
- 52 InnoDB tables with foreign keys, 84 legacy MyISAM tables

## Mandatory Rules

### XSS Protection
Use `Utilities\HtmlSanitizer::safeHtmlOutput()` on ALL output (database results, user input, error messages).

### Type Hints
`declare(strict_types=1);` in every file. Full type hints on all methods.

### Statistics Formatting
Use `BasketballStats\StatsFormatter` for all stats - never `number_format()` directly.

### HTML Modernization
Replace deprecated tags: `<b>` → `<strong>`, `<font>` → `<span>`, `<center>` → `<div style="text-align: center;">`.

### Production Validation
After refactoring, compare output against iblhoops.net. Results must match exactly.

## Progressive Loading

Context-aware rules auto-load when relevant:

**Path-Conditional** (`.claude/rules/`):
- `php-classes.md` → editing `ibl5/classes/**/*.php`
- `phpunit-tests.md` → editing `ibl5/tests/**/*.php`
- `view-rendering.md` → editing `**/*View.php`

**Task-Discovery** (`.github/skills/`):
- `refactoring-workflow/` - Module refactoring with templates
- `security-audit/` - XSS/SQL injection patterns
- `phpunit-testing/` - Test patterns and mocking
- `basketball-stats/` - StatsFormatter usage

## Key References

| Resource | Location |
|----------|----------|
| Schema | `ibl5/schema.sql` |
| Development status | `DEVELOPMENT_GUIDE.md` |
| Database guide | `DATABASE_GUIDE.md` |
| API patterns | `API_GUIDE.md` |
| Interface examples | `classes/Player/Contracts/`, `classes/FreeAgency/Contracts/` |
