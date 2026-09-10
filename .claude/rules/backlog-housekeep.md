---
description: Backlogs live in GitHub Issues, not in-repo markdown. Read on demand when filing or closing a tracked finding; no glob fires at the moment this matters.
paths: ibl5/docs/**/*.md
last_verified: 2026-09-08
---

# Backlog Housekeeping

The markdown backlog corpus is retired (ADR-0121). Findings live as GitHub Issues in the
private repo `a-jay85/IBL5-backlog`.

**File one:** `gh issue create --repo a-jay85/IBL5-backlog --label <area> --title "<title>"`
**Close one:** `gh issue close <n> --repo a-jay85/IBL5-backlog -c "<what closed it>"`

Area is a label (`ci`, `dev-efficiency`, `e2e`, `maintenance`, `token-spend`, `a11y`, `a11y-contrast`,
`jsb-native`, `security`, `loop-engineering`). Legacy markdown IDs survive as title prefixes.
`/backlog` and `bin/backlog` are the entry points; search before filing. There is no
in-repo mirror of Issue state — do not create one.
