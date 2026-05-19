---
description: Post-refactoring roadmap and priority queue.
last_verified: 2026-05-19
---

# Strategic Development Priorities for IBL5

## Current State

IBL5 has completed its full-stack modernization from a PHP-Nuke monolith to an interface-driven architecture with Repository/Service/View separation, a REST API layer, HTMX-powered frontend, and comprehensive CI/CD. The codebase is in **maturation mode** — the architecture is established, and work focuses on extending capabilities, hardening quality gates, and retiring legacy debt.

### What's Built

- **All IBL modules** follow Repository/Service/View pattern with `Contracts/` interfaces
- **REST API** with 17 controllers, API key auth, rate limiting, ETag caching, pagination, CSV export
- **Security stack**: CSP + HSTS + X-Frame-Options headers, CSRF on all forms, `HtmlSanitizer::e()` on all output, prepared statements everywhere
- **HTMX frontend**: boosted navigation (SPA-like), tab switching via `hx-get`, partial-page loading, form boost
- **Test pyramid**: PHPUnit (unit + integration + module entry point), Playwright E2E (functional + visual regression), Infection mutation testing at 100% MSI
- **CI/CD**: PHPUnit + PHPStan + Playwright + Lighthouse + CodeQL + migration safety + mutation testing + production smoke tests + auto-rebase
- **Docker dev environment**: multi-worktree with Traefik routing, isolated DBs, automated migration runner
- **IBL6 SvelteKit frontend** in early development (`IBL6/`)

### Quality Gates (enforced by CI)

- PHPStan level `max` with `strict-rules` and `bleedingEdge` — zero errors
- PHPUnit — full suite green, zero skips
- Coverage threshold — 70% (ratcheted; actual ~80%)
- Mutation testing — 100% MSI / 100% Covered MSI (weekly + on-demand)
- Playwright E2E — 4-shard parallel, visual regression baselines
- Lighthouse — performance audits on every PR

---

## Active Priorities

### 1. PHP-Nuke Legacy Retirement

10 `nuke_*` tables remain (`nuke_blocks`, `nuke_config`, `nuke_counter`, `nuke_stats_date`, `nuke_stats_hour`, `nuke_stats_month`, `nuke_stats_year`, `nuke_stories`, `nuke_stories_cat`, `nuke_topics`). Over 20 others have been dropped across migrations 050–102. Each drop requires auditing code references, migrating any live reads to IBL-native tables (`ibl_settings`, `auth_users`), and removing dead writes.

**Completed drops:** `nuke_users` (migration 102, PRs #599/#600), `nuke_session` (070), `nuke_comments` (073), `nuke_modules` (074), `nuke_authors`/`nuke_pages`/`nuke_poll_desc` (071), `nuke_headlines`/`nuke_main`/`nuke_queue` (050), `nuke_autonews`/`nuke_groups`/`nuke_message` (051), `nuke_referer` (052), plus others in 035/072.

**Remaining work:**
- Audit and drop remaining 10 `nuke_*` tables where code references are dead
- Migrate live `nuke_stories` reads to IBL-native equivalents
- Remove PHP-Nuke framework functions still called from bootstrap (`mainfile.php`)
- Goal: eliminate `nuke_*` dependency entirely so the schema only contains `ibl_*` and `auth_*` tables

### 2. IBL6 Frontend (SvelteKit)

A SvelteKit frontend (`IBL6/`) is under early development, deployed at `ibl6.iblhoops.net`. It consumes the REST API built in the IBL5 backend.

**Remaining work:**
- Build out routes for core pages (teams, players, standings, schedule)
- Replace PHP-rendered pages with SvelteKit equivalents as they mature
- The REST API continues to expand to serve IBL6's needs

### 3. HTMX Expansion

HTMX is partially adopted — boosted navigation, tab switching, and form boost are in place. Several modules still do full page reloads for form submissions.

**Remaining work:**
- Convert remaining full-reload forms to HTMX inline rendering where appropriate
- Add `hx-get` partial loading to more data-heavy pages (leaderboards, player database)
- Evaluate `HX-Location` for SPA-preserving form redirects (currently uses `HX-Redirect` which triggers full page reload)

### 4. Test Coverage Expansion

Coverage is at ~80% with the 70% CI threshold. The test suite is mature (PHPUnit, Playwright, mutation testing all enforced), but there are still gaps in integration test coverage for some modules.

**Remaining work:**
- Ratchet coverage threshold as new tests are added (next target: 75%)
- Add module entry point tests for untested modules (using `ModuleEntryPointTestCase`)
- Expand E2E coverage for form submission flows that currently only have read-only tests

### 5. API Maturation

The REST API has 17 controllers covering players, teams, games, standings, schedule, leaders, injuries, trade actions, and CSV export. Auth (API keys), rate limiting, ETag caching, and pagination are all in place.

**Remaining work:**
- OpenAPI/Swagger documentation generation
- Additional endpoints as IBL6 frontend needs arise
- Consider JWT auth alongside API keys for user-scoped operations

---

## Lower Priority / Future

- **Performance optimization**: Query analysis, Redis caching layer, page fragment caching. Not urgent — current page loads are acceptable.
- **PowerRankings module**: The only display module without `Contracts/` interfaces. Low impact, refactor opportunistically.
- **Generic PHP-Nuke modules** (Web_Links, Your_Account, News, Content, etc.): Will be replaced by IBL6 SvelteKit equivalents rather than refactored in PHP.

---

## Completed Milestones

- **Oct 2025 – Mar 2026**: All IBL modules refactored to Repository/Service/View with interfaces
- **Jan 2026**: 80% test coverage target achieved; all display modules refactored
- **Feb 2026**: REST API launched with auth, rate limiting, caching
- **Mar 2026**: HTMX frontend phases 1-3 shipped; mutation testing at 100% MSI; legacy `nuke_*` tables reduced to 10 (from 30+); TradingRepository god class split; CSV player export; production deploy smoke tests; worktree Docker simplification
