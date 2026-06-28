---
description: How to derive the correct Docker hostname for the current worktree or main repo. Prevents using stale slugs.
last_verified: 2026-06-27
---

# Worktree Hostname

## Main repo

The main checkout (`/Users/ajaynicolas/GitHub/IBL5/ibl5/`) is reference/read-only (ADR-0062 — see `workflow-continuity.md`). Its Docker hostname is `main.localhost`, used for reading/serving the canonical `master` stack and main-stack DB tooling, not for developing a change.

## Worktrees

Worktrees live OUTSIDE the repo at `IBL5-worktrees/<slug>/ibl5/` — a canonical-case
sibling of the main checkout (see `ibl5/docs/decisions/0046-worktrees-outside-repo.md`
for why). The Docker hostname is `<slug>.localhost`.

To derive the slug at runtime from anywhere inside a worktree:

```bash
basename "$(git rev-parse --show-toplevel)"
```

This returns the worktree directory name, which matches the Traefik route configured by `bin/wt-up`.

## URL paths

Always navigate to `/ibl5/` paths — never the bare root (`/`). The application lives under `/ibl5/`, and the root directory returns a redirect (or 403 if the Docker image hasn't been rebuilt). Going to root wastes a round-trip; go directly to `/ibl5/` or a deeper path.

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
