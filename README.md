# IBL5 - Internet Basketball League

A fantasy basketball league website powered by the Jump Shot Basketball simulation engine. Managers draft, trade, and manage rosters of simulated players competing in a structured league season.

## Tech Stack

- **Backend:** PHP 8.3, MariaDB 10.6
- **Local Dev:** MAMP (macOS)
- **Testing:** PHPUnit 12, PHPStan (level max + strict-rules + bleedingEdge)
- **CI/CD:** GitHub Actions
- **Frontend:** Tailwind CSS 4, vanilla JS

## Quick Start

```bash
# 1. Clone
git clone https://github.com/your-org/IBL5.git && cd IBL5

# 2. Install dependencies
cd ibl5 && composer install

# 3. Configure database (MAMP must be running)
cp classes/DatabaseConnection.php.template classes/DatabaseConnection.php
# Edit DatabaseConnection.php with your MAMP credentials from config.php

# 4. Run tests
vendor/bin/phpunit
```

See [DEVELOPMENT_ENVIRONMENT.md](ibl5/docs/DEVELOPMENT_ENVIRONMENT.md) for detailed setup including MAMP configuration and dependency caching.

## Project Structure

```
IBL5/
├── ibl5/
│   ├── classes/              # 30 modules (Repository/Service/View pattern)
│   │   ├── Player/           #   Each module has Contracts/ for interfaces
│   │   ├── FreeAgency/
│   │   ├── Trading/
│   │   └── ...
│   ├── tests/                # PHPUnit test suites
│   ├── docs/                 # Project documentation
│   ├── migrations/           # SQL migration scripts
│   ├── modules/              # Legacy PHP-Nuke entry points
│   ├── db/                   # Database connection setup
│   ├── design/               # CSS source files (Tailwind)
│   └── schema.sql            # Database schema reference
├── .claude/                  # Claude Code rules and skills
├── .github/                  # CI/CD workflows, Copilot instructions
└── CLAUDE.md                 # AI agent instructions
```

## Architecture

All 30 IBL modules use an **interface-driven Repository/Service/View** pattern:

```
Module/
├── Contracts/
│   ├── ModuleRepositoryInterface.php
│   ├── ModuleServiceInterface.php
│   └── ModuleViewInterface.php
├── ModuleRepository.php      # Database queries (prepared statements)
├── ModuleService.php         # Business logic, validation
└── ModuleView.php            # HTML rendering (XSS-protected)
```

See `ibl5/classes/Player/` for a canonical example.

## Testing

```bash
cd ibl5

# Run all tests
vendor/bin/phpunit

# Run a specific module's tests
vendor/bin/phpunit tests/Player/

# Run static analysis
composer run analyse
```

**Current:** 2976 tests, 14387 assertions | PHPStan level max

## Documentation

All project documentation lives in [`ibl5/docs/`](ibl5/docs/README.md):

| Guide | Description |
|-------|-------------|
| [DEVELOPMENT_GUIDE.md](ibl5/docs/DEVELOPMENT_GUIDE.md) | Development standards and priorities |
| [DATABASE_GUIDE.md](ibl5/docs/DATABASE_GUIDE.md) | Schema reference and query patterns |
| [API_GUIDE.md](ibl5/docs/API_GUIDE.md) | REST API design (planned) |
| [REFACTORING_HISTORY.md](ibl5/docs/REFACTORING_HISTORY.md) | Complete module refactoring timeline |
| [STRATEGIC_PRIORITIES.md](ibl5/docs/STRATEGIC_PRIORITIES.md) | Post-refactoring roadmap |
| [DEVELOPMENT_ENVIRONMENT.md](ibl5/docs/DEVELOPMENT_ENVIRONMENT.md) | MAMP setup, dependency caching |

For AI agents, see [CLAUDE.md](CLAUDE.md).

## Current Status

| Metric | Value |
|--------|-------|
| Modules refactored | 30/30 (100%) |
| Tests | 2976 (14387 assertions) |
| Test coverage | ~80% |
| Database | 52 InnoDB tables, 23 views, 84 legacy MyISAM |
| Architecture | Interface-driven Repository/Service/View |
