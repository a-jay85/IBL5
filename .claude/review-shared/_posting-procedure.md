---
description: Shared posting procedure — re-check eligibility, the never-hand-write rule, dispositioning open threads, and link format rules — used by /pr-review and /security-audit.
last_verified: 2026-09-04
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

## Never compose a review comment by hand

Every review artifact is emitted by `post_review_findings` or `post_review_summary` — never by a
freehand `gh pr comment`. The helper's envelope (`### Code review` / `### Security audit` heading,
`<details>` wrapper, `<!-- score: N -->` markers, `PRF_FOOTER`) is **machine-parsed downstream** by
`.claude/skills/pr-ready/scripts/4b-probe.sh` (matches `^#{1,6} +Code review` to set
`PHASE_4B_RAN`), by the dispositioning calls below, and by the `unresolved-findings-hold` gate.

A hand-written comment performs a real review whose artifact is invisible to all three: `/pr-ready`
reports "structured code review never ran" and recommends a redundant re-review, and the findings
can never be dispositioned. Observed on PRs #1956 and #2001. If neither helper call fits the
situation, stop and say so — do not improvise a comment.

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
