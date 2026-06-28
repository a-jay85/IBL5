---
description: Conventional-commit type rubric for PR titles — when a change is feat: (and trips the human-signoff hold) vs. fix/refactor/chore/docs/etc. Single source of truth for /post-plan Phase 2 titling. Read-on-demand at title time (the one-line decision test is mirrored in auto-commit.md); auto-loads only when editing the gate definitions.
last_verified: 2026-06-27
paths:
  - ".github/workflows/human-signoff.yml"
  - ".claude/skills/post-plan/SKILL.md"
---

# Commit-Type Rubric (PR titles)

This file is the **single source of truth** for choosing a conventional-commit
type when titling a PR or commit. `/post-plan` Phase 2 points here. Get the type
right because the title-only required GitHub check (`.github/workflows/human-signoff.yml`)
grades the PR **title** alone — it never reads the diff.

## The decision test

> **"Would a league GM notice a new ability they didn't have before?"**

- **Yes** → `feat:`
- **Invisible to a GM** (dev tooling, a new slash command, an internal refactor,
  a doc, a dependency bump) → **not** `feat:` (`chore:` / `refactor:` / `docs:` / …).

## `feat:` definition

`feat:` is a **NEW user-facing capability a league GM or admin can notice and
use that did not exist before** — a new page, a new endpoint, a command they
invoke in the app, a new in-app ability. A genuine new GM-facing page, endpoint,
or in-app command is `feat:`.

## NOT `feat:`

| Type | Use for |
|------|---------|
| `fix:` | A bug — the same intended behavior is restored. |
| `refactor:` / `perf:` | Same behavior, internal change only. |
| `test:` | Test-only changes. |
| `docs:` | Documentation. |
| `build:` / `ci:` | Build system, CI config. |
| `chore:` | Dependencies, internal/dev-workflow tooling, repo housekeeping. |

## Standing convention (a written rule, not per-PR judgment)

**Dev-workflow tooling and new slash commands are `chore:`, not `feat:`.**

Canonical example: the `/ship` slash command. A league GM never invokes `/ship`,
so by the decision test it is **not** `feat:` — it is `chore:`. `/ship` historically
shipped under a `feat(commands):` title; that is exactly the mis-titling this
standing convention closes, **not** a model `feat:` example.

## Anti-gaming clause

Classify by what the diff **IS**, never by the desired merge outcome. `feat:`
tripping a human-merge hold is the gate **working**, not a cost to route around.
Under-titling a feature as `chore:` / `refactor:` to dodge the hold defeats the
only gate (`human-signoff`) that is blind to diff content.

## Intent-alignment with the consumer regex

The human-signoff hold trips on all four conventional-commit feature forms —
`feat:`, `feat(scope):`, `feat!:`, and `feat(scope)!:` — matching the
case-insensitive regex `^feat(\([^)]*\))?!?:` used by
`.github/workflows/human-signoff.yml` and `/post-plan` Phase 6.5. Keep titles
consistent with that regex so the gate reads the type accurately.
