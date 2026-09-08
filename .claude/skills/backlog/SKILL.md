---
name: backlog
description: "Backlog findings live as GitHub Issues in a-jay85/IBL5-backlog, not in repo markdown. Use when filing, searching, or closing a tracked finding — search before filing so a duplicate Issue is not opened."
last_verified: 2026-09-08
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

Labels are the areas: `ci`, `dev-efficiency`, `e2e`, `maintenance`, `token-spend`,
`a11y`, `jsb-native`, `security`, `loop-engineering`. Legacy markdown IDs survive as
title prefixes.
