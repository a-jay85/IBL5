---
description: scp -r with a trailing slash on the source copies directory contents rather than the named directory — omit the trailing slash. Enforced at review time by Agent E Topic 3.
last_verified: 2026-09-05
paths:
  - ".github/workflows/*.yml"
  - "bin/**"
---

# scp Trailing Slash

## Rule

Never append a trailing slash to the **source** argument of `scp -r`.

- `scp -r dist/ host:/app/` — copies *contents* of `dist/` into `/app/` directly; the named directory `dist` is NOT created at the destination.
- `scp -r dist host:/app/` — copies the directory itself, creating `/app/dist/` at the destination.

The trailing slash is the POSIX "dereference directory" signal. It is silent: scp exits 0 and transfers files without error; the only symptom is that files land one level higher than expected.

## Why this matters

`.github/workflows/main.yml` "Deploy IBLbot dist to server" step previously read:

```
scp -r -P … dist/ USERNAME@HOST:www/ibl5/IBLbot/dist/
```

This created `www/ibl5/IBLbot/dist/` on the first deploy and then silently clobbered the
wrong path on every subsequent run. The fix (shipped in PR #2044) removes the trailing slash
from both source and the redundant `/dist` suffix from the target:

```
scp -r -P … ibl5/IBLbot/dist USERNAME@HOST:www/ibl5/IBLbot/
```

## Prevention checklist

When writing or reviewing an `scp -r` call in any workflow file:

1. Source argument — no trailing slash on a directory: `ibl5/IBLbot/dist` (example) not `ibl5/IBLbot/dist/` (example).
2. Target path — ends at the *parent* directory, not the directory to create: `…:www/ibl5/IBLbot/` not `…:www/ibl5/IBLbot/dist/`.
3. Verify intent: "does the target path already include the directory name, or am I relying on scp to create it?" If the latter, strip the source trailing slash.

## rsync uses the opposite convention

Do not memorize the two tools as one rule — they disagree on purpose:

- `rsync -a src/ dest/` copies the **contents** of `src` into `dest`.
- `rsync -a src dest/` copies the **directory** `src`, creating `dest/src/`.

So a trailing slash on an rsync source is the normal "sync contents into" idiom and is usually
correct, while the same slash on an scp source is the bug above. Read the tool name first, then
the slash.

## Enforcement

Agent E Topic 3 in `.claude/review-shared/_shell-workflow-agent.md` checks this at review time on
every PR that touches a shell script or a workflow file. Agent E is launched by `/post-plan`
Phase 4B and `/pr-review` Step 3.
