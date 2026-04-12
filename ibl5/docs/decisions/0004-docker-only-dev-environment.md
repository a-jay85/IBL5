---
description: Why IBL5 local dev is Docker-only, with MAMP sunset and native PHP stacks rejected.
last_verified: 2026-04-12
---

# ADR-0004: Docker-only development environment

**Status:** Accepted
**Date:** 2026-04-11

## Context

IBL5's original local dev stack was MAMP: macOS-bundled Apache + PHP + MySQL. As the project adopted PHPStan level max, Playwright E2E tests, and git worktrees for parallel work, MAMP's problems compounded: MAMP's PHP version lagged production, MAMP's MySQL was MySQL 5.7 while production ran MariaDB 10.11, Playwright's test runner assumed `http://main.localhost/ibl5/` which meant MAMP's Apache had to be bound to port 80, and git worktrees couldn't run concurrently because only one MAMP Apache can bind port 80 at a time. Every onboarding session burned hours on "why does it work on yours but not mine" drift between MAMP versions. CI ran on Docker MariaDB 10.11, so local PHPUnit tests frequently passed against MAMP but failed on CI against real production-parity databases.

## Decision

Local development uses Docker Compose only. `docker compose up -d` starts PHP-Apache + MariaDB 10.11 + Mailpit + Adminer at `http://main.localhost/ibl5/`. Worktrees use per-worktree Docker stacks via `bin/wt-new <slug>` + `bin/wt-up <slug>` to get their own isolated MariaDB instance, so multiple branches can run in parallel without port conflicts. MAMP is no longer supported; the last MAMP reference was removed from docs in PR #607 as part of the Living Documentation pass. `config.php` reads `DB_HOST` from the environment with a `127.0.0.1` fallback, so the same code works in Docker and in CI without edits.

## Alternatives Considered

- **Keep MAMP, document the drift points** — add checklists to README for version matching. Rejected: every project that tried "be disciplined about MAMP version matching" drifted anyway; onboarding time never improved; CI parity was never achieved.
- **Native PHP + Homebrew MariaDB** — install PHP 8.4 and MariaDB 10.11 via Homebrew, run without containers. Rejected: each contributor would need the exact same Homebrew formula version; upgrades on one machine break another's tests; port conflicts with worktrees are unsolved; CI still runs Docker, so parity drift returns.
- **Vagrant VM** — a full VirtualBox VM with provisioned IBL5. Rejected: resource-heavy (multi-gigabyte VMs per contributor), slow boot, Vagrant is a declining technology with fewer macOS Silicon users, and Docker Compose gives us the same reproducibility with a fraction of the footprint.
- **Remote dev environment (Gitpod, Codespaces)** — run dev in a cloud VM. Rejected: adds a cost center, requires internet for every keystroke, and the Playwright E2E setup still needs local browsers for visual verification.

## Consequences

- Positive: every contributor runs identical PHP version, MariaDB version, Apache config, and request routing. "Works on my machine" is no longer a legitimate diagnosis.
- Positive: git worktrees can run in parallel — each worktree gets an isolated Docker stack via `bin/wt-up`.
- Positive: CI parity is automatic. The same `docker-compose.yml` that CI uses is what developers use locally, so PHPUnit and Playwright behave the same in both environments.
- Positive: Playwright E2E tests can rely on `http://main.localhost/ibl5/` being the single canonical URL.
- Negative: Docker Desktop is a heavyweight prerequisite for onboarding. Accepted because the alternative was worse.
- Negative: First-time setup requires creating the shared `ibl5-proxy` Docker network manually. Documented in `ibl5/docs/DOCKER_SETUP.md`.
- Negative: Running the full dev stack consumes ~2-3 GB RAM. Accepted for the other properties.

## References

- `ibl5/docs/DOCKER_SETUP.md` — the step-by-step onboarding guide.
- `docker-compose.yml` — the canonical service definition.
- `bin/wt-new`, `bin/wt-up`, `bin/wt-down` — worktree lifecycle commands that assume Docker.
- `.claude/rules/workflow-continuity.md` — the agent-facing worktree rule (cites this ADR).
