---
description: Shared posting procedure — re-check eligibility, dispositioning open threads, and link format rules — used by /pr-review and /security-audit.
last_verified: 2026-08-29
---

# Review Posting Procedure (shared)

Source of truth for the posting mechanics that surround `post_review_findings`. Used by `/pr-review` Steps 6–7 and `/security-audit` Steps 6–7. Do not edit without updating both callers.

Callers bind `$PR_NUMBER` before running anything here. `/post-plan` Phase 4 carries a compressed restatement of the same procedure under its own variable names — keep it in sync with this file.

## Re-check eligibility

Run this command directly (no agent needed):

```bash
gh pr view --json state --jq '.state'
```

If the result is not `"OPEN"`, do not post a comment. Tell the user the PR is no longer open.

## Dispositioning open threads

A finding posted as an inline thread stays open until something replies *in-thread* and resolves it. Never use `gh pr comment` to announce that a finding is fixed or to close a thread — a top-level comment cannot associate with a review thread. To disposition a finding:

```bash
source "$(git rev-parse --show-toplevel)/bin/lib/post-review-findings.sh"
list_open_review_findings "$PR_NUMBER"                      # TSV: COMMENT_ID, score, path:line, excerpt
resolve_review_finding "$PR_NUMBER" <COMMENT_ID> "Fixed in <sha> — <what changed>"
```

The same call applies when declining a finding — the body says why, and the thread still closes. A finding is dispositioned when it is fixed *or* explicitly declined; silence is not a disposition.

## Link format rules

- Must use the full git SHA (from Step 2a's `headRefOid`)
- Format: `https://github.com/a-jay85/IBL5/blob/{FULL_SHA}/path/to/file#L{start}-L{end}`
- Provide at least 1 line of context before and after the line you are commenting about
- Do NOT use `$(git rev-parse HEAD)` or any bash interpolation in the body string — expand the SHA beforehand
