---
description: "IBL5 project overview: what it is, the engineering practices behind it, and how to run it."
last_verified: 2026-06-08
---

# IBL5 — Internet Basketball League

A production fantasy-basketball league platform built around the Jump Shot Basketball simulation engine. League managers draft, trade, sign free agents, and set strategy for simulated teams that play out a full season — standings, box scores, playoffs, awards, and a permanent season archive.

The codebase began in 2020 as inherited PHP-Nuke legacy code and has been incrementally modernized into a typed, tested, statically-analyzed PHP 8.5 application — without ever taking the live league offline.

![IBL Season Archive](ibl5/docs/images/season-archive.png)

## What this project demonstrates

This is a solo-maintained, long-running application, so it doubles as a portfolio of sustained engineering discipline on a real system with real users:

- **Strangler-fig modernization** — 80+ feature modules extracted from procedural legacy code into an interface-driven Repository / Service / View pattern, one slice at a time, behind passing tests.
- **Tests as a safety net for legacy refactoring** — hundreds of PHPUnit test files plus a Playwright E2E suite, with characterization tests written *before* extracting each module so behavior is provably preserved.
- **Static analysis at the ceiling** — PHPStan at `level: max` with strict-rules, deprecation-rules, bleedingEdge, and a set of custom project-specific PHPStan rules that ban known footguns (e.g. raw SQL identifier drift).
- **Mutation testing** — Infection enforces test *effectiveness*, not just coverage, on critical modules.
- **Heavy CI/CD** — 20+ GitHub Actions workflows covering unit/integration tests, E2E, static analysis, mutation, CodeQL, secret scanning, Lighthouse performance/a11y budgets, migration safety checks, and deploy rehearsals.
- **Decision records** — dozens of ADRs documenting the *why* behind architectural choices.

## Tech Stack

- **Backend:** PHP 8.5, MariaDB 10.11
- **Frontend:** Tailwind CSS 4, vanilla JS, HTMX
- **Testing:** PHPUnit 13, PHPStan (level max + strict-rules + custom rules), Infection (mutation), Playwright (E2E)
- **Infra:** Docker (Apache/PHP + MariaDB), GitHub Actions CI/CD
- **Database:** versioned SQL migrations from a baseline schema

## Architecture

Every modernized module follows an interface-driven **Repository / Service / View** split:

```
Module/
├── Contracts/                 # Interfaces — Repository, Service, View
├── ModuleRepository.php       # Data access (prepared statements only)
├── ModuleService.php          # Business logic + validation
└── ModuleView.php             # HTML rendering (XSS-escaped)
```

`ibl5/classes/Waivers/` is the canonical, fully-built-out example (Repository, Service, Processor, Validator, View, Controller).

## Quick Start

```bash
git clone git@github.com:a-jay85/IBL5.git
cd IBL5/ibl5 && composer install && cd ..

docker compose up -d          # Apache/PHP + MariaDB
cd ibl5 && vendor/bin/phpunit  # run the test suite
```

See [DOCKER_SETUP.md](ibl5/docs/DOCKER_SETUP.md) for full setup.

## Testing & Quality (run from `ibl5/`)

```bash
vendor/bin/phpunit                 # unit + integration tests
vendor/bin/phpunit --filter Player # one module
composer run analyse               # PHPStan, level max
bun run test:e2e                   # Playwright E2E (requires Docker)
```

## Documentation

Deeper docs live in [`ibl5/docs/`](ibl5/docs/README.md):

| Guide | Description |
|-------|-------------|
| [DEVELOPMENT_GUIDE.md](ibl5/docs/DEVELOPMENT_GUIDE.md) | Standards and conventions |
| [DATABASE_GUIDE.md](ibl5/docs/DATABASE_GUIDE.md) | Schema reference and query patterns |
| [TESTING_STANDARDS.md](ibl5/docs/TESTING_STANDARDS.md) | Testing patterns and gotchas |
| [REFACTORING_HISTORY.md](ibl5/docs/REFACTORING_HISTORY.md) | Module-by-module modernization timeline |
| [decisions/](ibl5/docs/decisions/) | Architecture Decision Records (ADRs) |
