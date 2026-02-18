# IBL5 Documentation Index

This directory is the primary home for all project documentation.

## For New Contributors

1. Start with the [Main README](../../README.md) for project overview
2. Read [DEVELOPMENT_GUIDE.md](DEVELOPMENT_GUIDE.md) for coding standards and workflow
3. Review [REFACTORING_HISTORY.md](REFACTORING_HISTORY.md) for what's been done
4. Check [STRATEGIC_PRIORITIES.md](STRATEGIC_PRIORITIES.md) for what to work on next

## Guides

| Document | Description |
|----------|-------------|
| [DEVELOPMENT_GUIDE.md](DEVELOPMENT_GUIDE.md) | Development standards, priorities, and workflow |
| [DATABASE_GUIDE.md](DATABASE_GUIDE.md) | Schema reference and query patterns |
| [API_GUIDE.md](API_GUIDE.md) | REST API design and development (planned) |
| [DEVELOPMENT_ENVIRONMENT.md](DEVELOPMENT_ENVIRONMENT.md) | MAMP setup, dependency caching, database connection |
| [DOCUMENTATION_STANDARDS.md](DOCUMENTATION_STANDARDS.md) | Documentation organization and lifecycle |
| [TESTING_STANDARDS.md](TESTING_STANDARDS.md) | Testing philosophy and conventions |

## Project Status

| Document | Description |
|----------|-------------|
| [REFACTORING_HISTORY.md](REFACTORING_HISTORY.md) | Complete timeline of all 30 module refactorings |
| [STRATEGIC_PRIORITIES.md](STRATEGIC_PRIORITIES.md) | Post-refactoring roadmap and next priorities |
| [STATISTICS_FORMATTING_GUIDE.md](STATISTICS_FORMATTING_GUIDE.md) | StatsFormatter and StatsSanitizer usage |

## Component Documentation

Component-specific docs live next to their code in `ibl5/classes/`:

- [Player/README.md](../classes/Player/README.md) - Player module architecture
- [Statistics/README.md](../classes/Statistics/README.md) - StatsFormatter usage
- [DepthChartEntry/README.md](../classes/DepthChartEntry/README.md) - DepthChartEntry architecture
- [DepthChartEntry/SECURITY.md](../classes/DepthChartEntry/SECURITY.md) - Security patterns
- [Draft/README.md](../classes/Draft/README.md) - Draft module
- [Negotiation/README.md](../classes/Negotiation/README.md) - Negotiation module
- [ComparePlayers/README.md](../classes/ComparePlayers/README.md) - Compare module
- [Standings/README.md](../classes/Standings/README.md) - Standings module

Database migrations: [ibl5/migrations/README.md](../migrations/README.md)

## Archive

Historical documents (completed work summaries, superseded guides) are in [`archive/`](archive/):

- `DATABASE_OPTIMIZATION_GUIDE.md` - Completed optimization phases (all 5 phases done)
- `PRODUCTION_DEPLOYMENT_GUIDE.md` - One-time UTF-8 migration guide
- `DATABASE_VIEW_AUDIT.md` - Database view technical audit
- `COMPARE_PLAYERS_REFACTORING.md` - Compare Players module refactoring details
- `TEST_REFACTORING_SUMMARY.md` - Testing philosophy (absorbed into TESTING_STANDARDS.md)

Root-level `.archive/` contains 40+ older historical documents.

## Document Lifecycle

| Location | Purpose |
|----------|---------|
| `ibl5/docs/` | Active guides and project tracking |
| `ibl5/classes/Module/` | Component-specific architecture docs |
| `ibl5/docs/archive/` | Recently completed/superseded docs |
| `.archive/` | Older historical documents |

---

**Last Updated:** February 16, 2026
