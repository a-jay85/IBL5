---
description: Shared posting procedure — re-check eligibility, the never-hand-write rule, dispositioning open threads, remediating trusted threads, and link format rules — used by /pr-review and /security-audit.
last_verified: 2026-10-21
---

# Review Posting Procedure (shared)

Source of truth for the posting mechanics that surround `post_review_findings`. Used by `/pr-review` Steps 6–7 and `/security-audit` Steps 6–7. Do not edit without updating both callers.

Callers bind `$PR_NUMBER` before running anything here. The compiled harness (`tools/postplan-harness/harness/review.py`) runs the same procedure under its own variable names. Keep it in sync with this file.

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
`.claude/review-shared/scripts/4b-probe.sh` (matches `^#{1,6} +Code review` to set
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

## Remediating trusted threads

A run that posts findings also inherits the threads already open on the PR. `list_trusted_open_threads "$PR_NUMBER"` lists the subset a run may act on: still open, still anchored to a live diff hunk, root comment authored by `a-jay85`, a GitHub `Bot`, or a `[bot]` login. Snapshot `prf_review_threads` ids before posting. Ids in the snapshot are pre-existing threads. The compiled harness also dispositions this run's own findings in a later pass (Phase 4.5b), with one round only: after a fix commit the review runs once more over the fix delta, any new finding is posted and left open, and no second disposition pass runs. Each candidate is fixed (commit, push, then `resolve_review_finding` with `Fixed in <sha> — <what changed>`) or declined (`resolve_review_finding` with `Declined: <reason>`). A decline with no reason leaves the thread open. Threads outside the trusted subset are never touched; the Phase 6.5 unresolved-thread hold keeps them for a human. The runnable blocks live in `tools/postplan-harness/harness/thread_ingestion.py`.

Each verdict line also carries a `category` for a DECLINE: `wrong`, `intentional`, `already-fixed` or `not-worth-it`. A FIX ignores it. Each resolved thread's outcome is appended to the main checkout's `tools/postplan-harness/out/state/review-outcomes.jsonl`. A deterministic sweep also logs findings that went outdated or were resolved without a reply. A logging failure never fails the run. Each event also records `origin`, `pre-existing` or `same-run`. `bin/review-outcomes report` does not group by it yet.

## Link format rules

- Must use the full git SHA (from Step 2a's `headRefOid`)
- Format: `https://github.com/a-jay85/IBL5/blob/{FULL_SHA}/path/to/file#L{start}-L{end}`
- Provide at least 1 line of context before and after the line you are commenting about
- Do NOT use `$(git rev-parse HEAD)` or any bash interpolation in the body string — expand the SHA beforehand
