# Development Guide

**Status:** 22/23 IBL modules refactored (96% complete) • 787 tests • ~56% coverage • Goal: 80%

> 📘 **Progressive Loading:** Detailed workflows are in `.claude/rules/` and `.github/skills/`. See [SKILLS_GUIDE.md](.github/SKILLS_GUIDE.md).

---

## Current Priorities

### 🎯 Final Module: Cap_Info (134 lines)

### 🚀 Post-Refactoring Phase

1. **Test Coverage → 80%** - Focus: Voting (0), Schedule (0), DepthChart (2 tests)
2. **API Development** - REST API with JWT, rate limiting, OpenAPI docs
3. **Security Hardening** - XSS audit, CSRF, security headers

---

## Quick Reference

| Action | Command/Location |
|--------|------------------|
| Run tests | `cd ibl5 && vendor/bin/phpunit tests/` |
| Schema reference | `ibl5/schema.sql` |
| CI/CD | `.github/workflows/tests.yml` |
| Interface examples | `classes/Player/Contracts/`, `classes/FreeAgency/Contracts/` |
| Stats formatting | `BasketballStats\StatsFormatter` |
| XSS protection | `Utilities\HtmlSanitizer::safeHtmlOutput()` |

---

## Completed Modules (22/23)

Player • Statistics • Team • Draft • Waivers • Extension • RookieOption • Trading • Negotiation • DepthChart • Voting • Schedule • Season Leaders • Free Agency • Player_Search • Compare_Players • Leaderboards • Standings • League_Stats • Player_Awards • Series_Records • One-on-One

---

## Skills Architecture

**Path-Conditional** (`.claude/rules/`): Auto-load when editing matching files
- `php-classes.md` → `ibl5/classes/**/*.php`
- `phpunit-tests.md` → `ibl5/tests/**/*.php`
- `view-rendering.md` → `**/*View.php`

**Task-Discovery** (`.github/skills/`): Auto-load when task matches
- `refactoring-workflow/` - Module refactoring with templates
- `security-audit/` - XSS/SQL injection patterns
- `phpunit-testing/` - Test patterns and mocking
- `basketball-stats/` - StatsFormatter usage
- `code-review/` - PR validation checklist

---

## Resources

| Document | Purpose |
|----------|---------|
| [DATABASE_GUIDE.md](DATABASE_GUIDE.md) | Schema, indexes, views |
| [API_GUIDE.md](API_GUIDE.md) | REST API development |
| [SKILLS_GUIDE.md](.github/SKILLS_GUIDE.md) | Skills creation guide |
| [ibl5/docs/archive/](ibl5/docs/archive/) | Archived detailed workflows |

---

## FAQs

**Run tests?** `cd ibl5 && vendor/bin/phpunit tests/`  
**Database changes?** Use `ibl5/migrations/` - never modify schema directly  
**XSS protection?** `Utilities\HtmlSanitizer::safeHtmlOutput()` on all output  
**Stats formatting?** `BasketballStats\StatsFormatter` - never `number_format()`
