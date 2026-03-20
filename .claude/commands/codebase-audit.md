---
allowed-tools: Bash(bin/codebase-audit), Bash(bin/codebase-audit:*), Bash(cat:*), Bash(head:*), Bash(tail:*), Bash(wc:*), Bash(grep:*), Bash(touch:*)
description: Run codebase hygiene audit and triage findings
---

Run the codebase audit and triage findings into actionable buckets.

## Steps

### 1. Run the audit

```bash
cd /Users/ajaynicolas/Documents/GitHub/IBL5 && bin/codebase-audit
```

### 2. Read the report

Read the report file printed in the output above (`~/.claude/audit-results/YYYY-MM-DD.md`).

### 3. Triage findings

Organize findings into three buckets:

**Act Now** (CRITICAL and HIGH severity):
- These need immediate attention. List each with a brief recommended fix.

**Track & Plan** (MEDIUM severity):
- Group related findings (e.g., all god classes together, all `!important` together).
- Note which could be bundled into a single PR.

**Backlog** (LOW and INFO):
- Summarize counts by category. Don't list individually unless surprising.

### 4. Present the triage

Show a concise summary like:

```
## Audit Triage — YYYY-MM-DD

### Act Now (N items)
1. [finding] — recommended action
2. ...

### Track & Plan (N items)
- Category: N findings — suggested approach
- ...

### Backlog
- N architecture items, N test coverage gaps, N documentation items
```

### 5. Mark reviewed

After the user has seen the triage:

```bash
touch ~/.claude/audit-results/YYYY-MM-DD.reviewed
```

This prevents the SessionStart hook from re-surfacing the same findings.
