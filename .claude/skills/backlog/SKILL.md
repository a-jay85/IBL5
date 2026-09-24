---
name: backlog
description: "Backlog findings live as GitHub Issues in a-jay85/IBL5-backlog, not in repo markdown. Use when filing, searching, or closing a tracked finding — search before filing so a duplicate Issue is not opened."
last_verified: 2026-09-23
---

# Backlog

Findings live as GitHub Issues in the private repo `a-jay85/IBL5-backlog` (ADR-0121).
There is no in-repo markdown backlog and no mirror of Issue state — do not create one.

**Search before filing.** A finding that already has an Issue gets a comment, not a
second Issue.

| Do | Command |
|---|---|
| Search | `bin/backlog search "<term>"` |
| List open in an area | `bin/backlog open <label>` |
| File | `bin/backlog new <label> "<title>"` |
| Close | `bin/backlog close <n> "<what closed it>"` |

A plan-driven PR closes its issues on merge through a `Closes a-jay85/IBL5-backlog#N` line in the
PR body, generated from the plan's `## Backlog issues` section. Use `bin/backlog close` for an
issue resolved outside a plan-driven PR.

Labels are the areas: `ci`, `dev-efficiency`, `e2e`, `maintenance`, `token-spend`,
`a11y`, `a11y-contrast`, `jsb-native`, `security`, `loop-engineering`. Legacy markdown IDs survive as
title prefixes.
