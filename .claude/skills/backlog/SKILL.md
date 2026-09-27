---
name: backlog
description: "Backlog findings live as GitHub Issues in a-jay85/IBL5-backlog, not in repo markdown. Use when filing, searching, or closing a tracked finding — search before filing so a duplicate Issue is not opened."
last_verified: 2026-09-26
---

# Backlog

Findings live as GitHub Issues in the private repo `a-jay85/IBL5-backlog` (ADR-0121).
There is no in-repo markdown backlog and no mirror of Issue state. Do not create one.

**Search before filing.** A finding that already has an Issue gets a comment.

| Do | Command |
|---|---|
| Search | `bin/backlog search "<term>"` |
| List open in an area | `bin/backlog open <label>` |
| List issues filed from one PR | `bin/backlog for-pr <pr-url>` |
| File | `bin/backlog new <label> "<title>" "<body>"` |
| Close | `bin/backlog close <n> "<what closed it>"` |

The body's first line is the origin PR URL, it cites a `file:line`, and it states
a failure scenario in over 100 characters total. `bin/backlog new` exits 2 and
names the failed rule otherwise. When filing from a PR, run `bin/backlog for-pr`
before `bin/backlog search`.

A plan-driven PR closes its issues on merge through a `Closes a-jay85/IBL5-backlog#N` line in the
PR body, generated from the plan's `## Backlog issues` section. Use `bin/backlog close` for an
issue resolved outside a plan-driven PR.

Labels are the areas: `ci`, `dev-efficiency`, `e2e`, `maintenance`, `token-spend`,
`a11y`, `a11y-contrast`, `jsb-native`, `security`, `loop-engineering`. Legacy markdown IDs survive as
title prefixes.
