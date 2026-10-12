---
description: Worktree Docker hostnames, URL paths, and slug-derivation rules — path-scoped, loads only for E2E tests and worktree Docker scripts.
last_verified: 2026-09-30
paths:
  - "ibl5/tests/e2e/**"
  - "ibl5/playwright*.config.ts"
  - "bin/wt-up"
  - "bin/e2e-wt"
  - "bin/dev-up"
  - "bin/wt-list"
  - ".github/workflows/e2e-tests.yml"
---

# Worktree Hostname

## Main repo

The main checkout (`/Users/ajaynicolas/GitHub/IBL5/ibl5/`) is reference/read-only (ADR-0062 — see `workflow-continuity.md`). Its Docker hostname `main.localhost` serves the canonical `master` stack and main-stack DB tooling, not development of a change.

## Worktrees

Worktrees live OUTSIDE the repo at `IBL5-worktrees/<slug>/ibl5/` — a canonical-case sibling of the main checkout (see `ibl5/docs/decisions/0046-worktrees-outside-repo.md`). Docker hostname: `<slug>.localhost`.

Derive the slug at runtime from anywhere inside a worktree:

```bash
basename "$(git rev-parse --show-toplevel)"
```

This returns the worktree directory name, matching the Traefik route `bin/wt-up` configures.

## URL paths

Always navigate to `/ibl5/` paths — never the bare root (`/`). The app lives under `/ibl5/`; root returns a redirect (or 403 if the Docker image hasn't been rebuilt) and wastes a round-trip.

```
# Wrong
curl http://main.localhost/
# Right
curl http://main.localhost/ibl5/
```

## Rules

- Use the derived hostname for all browser checks, curl, and E2E in that worktree.
- Never hardcode a slug from a previous worktree.
- Never use `main.localhost` when working in a worktree (it hits the main repo's Docker stack).
