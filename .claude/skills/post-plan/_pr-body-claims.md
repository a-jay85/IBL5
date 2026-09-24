---
description: "PR body authoring rules: version/baseline citations must name their source file; negative-claim bullets must be re-read after every commit; backlog closing keywords come from the plan via the shared normalizer snippet."
last_verified: 2026-09-23
---

# PR Body Claims

## Citation rule

When a PR body states a **version string** (`v2 → v3`, `@v3`), a **numeric baseline**
(`147 call sites`), or an **`X → Y` figure**, name the **authoritative source file**
inline — e.g. ``147 → 134 call sites (per `ibl5/phpstan-baseline.neon`)``.

Uncited claims silently stale. Trigger: L43 (2026-09-02, PR #2059).

| Claim type | Citation form |
|---|---|
| Version string | ``(per `.github/workflows/security.yml` (example) line N)`` |
| Numeric baseline | ``(per `ibl5/phpstan-baseline.neon`)`` |
| `X → Y` figure | ``(per `ibl5/phpstan-baseline.neon` — N sites)`` |
| ADR frozen section rewritten | See `.claude/rules/adr-append-only.md` |

Governs the **claim** (citation present), not whether the figure is correct.
Applies to human-authored and autonomous-loop PR bodies alike.
**Headless:** applies — automouse/`/post-plan` PR bodies must include inline citations.

## Negative-claim re-check rule

A "What is NOT in this PR" bullet is a claim about the **final** diff, not about the diff as
it stood when you wrote the body. Any commit that lands after the body is written can
falsify it silently.

**Before opening or updating a PR, and again after every commit pushed to an open PR, re-read
every bullet under `## What is NOT in this PR` (or any equivalent residual / out-of-scope /
follow-up list) and confirm the diff still does not contain it.**

The trigger is the **commit**, not PR-open. The failure mode is ordered: body written →
remediation commit lands a plan deliverable → body is now false, and nothing re-reads it.
A `/post-plan` remediation commit is the highest-risk moment, because remediation exists
precisely to close a gap the body may have already described as still open.

Delete or rewrite any bullet the diff has overtaken. Do not leave it standing with a
softening qualifier — a residual entry that is no longer residual misreports scope to
reviewers and poisons the post-merge audit trail.

Trigger: E46, PR #2077.

| What just happened | What to do with the negative-claim list |
|---|---|
| Remediation commit landed a plan deliverable | Re-read every bullet; delete each one the deliverable satisfies |
| Any commit pushed to an open PR | Re-read the list against the new diff before considering the push done |
| Body written and no commit since | Nothing to do — the list still describes the diff it was written against |
| A bullet is now only *partly* true | Rewrite it to name the residual precisely; never leave the original wording |

This rule governs the **claim**, not the scope. Deliberately leaving work out of a PR is
fine and normal — say so accurately. The defect is a stale absence assertion, not a real one.

Applies to any residual / out-of-scope / follow-up list under any heading wording, not only
the literal string "What is NOT in this PR".

**Headless:** applies — an automouse or `/post-plan` run authoring a PR body performs the same
re-read; there is no human in the loop to catch the stale bullet later.

## Backlog issue references

A bare `#N` autolinks to IBL5's own PR or issue N. Backlog issues live in a different repo,
so cite them as `a-jay85/IBL5-backlog#N`. In prose, write `backlog issue a-jay85/IBL5-backlog#160`.
Use bare `#N` only for IBL5 PRs and issues.

### Closing keywords

The plan's `## Backlog issues` section decides which backlog issues this PR closes. A `closes`
bullet becomes a `Closes a-jay85/IBL5-backlog#N` line in the PR body, and GitHub closes that
issue when the PR merges into `master`. A `refs` bullet gets a plain `a-jay85/IBL5-backlog#N`
link with no closing keyword.

Do not hand-write these lines. Write the composed body to a file, then run the snippet below
before every `gh pr create --body-file` and every `gh pr edit --body-file`. It calls the same
functions the post-plan harness runs (`parse_backlog_issues` and `normalize_backlog_closes`
under `tools/postplan-harness/harness/`), so both engines emit identical lines.

<!-- backlog-closes-snippet:start -->
```bash
BODY_FILE="${BODY_FILE:?set BODY_FILE to the composed PR body file}"
PLAN="${PLAN:-$HOME/claude-plans/$(git rev-parse --abbrev-ref HEAD).md}"
PYTHONPATH="$(git rev-parse --show-toplevel)/tools/postplan-harness" \
  python3 - "$PLAN" "$BODY_FILE" <<'PY'
import os, sys
from harness.planfile import parse_backlog_issues
from harness.classify import normalize_backlog_closes
plan, body_path = sys.argv[1], sys.argv[2]
content = open(plan).read() if os.path.exists(plan) else ""
issues = parse_backlog_issues(content)
with open(body_path) as fh:
    body = fh.read()
out = normalize_backlog_closes(body, [n for k, n in issues if k == "closes"],
                               [n for k, n in issues if k == "refs"])
with open(body_path, "w") as fh:
    fh.write(out)
PY
```
<!-- backlog-closes-snippet:end -->

What the snippet guarantees:

- **No plan record.** With no plan file, or a plan without the section, the body stays byte-for-byte
  as written. A `Closes a-jay85/IBL5-backlog#N` line already in the body (carried from a commit
  message) stays, and nothing new is added. Close an issue only when something names it.
- **Partial work.** A closing keyword (`Closes`, `Fixes`, `Resolves`, in any tense) in front of a
  `refs` issue is removed, leaving the plain link.
- **Full repo path.** Every line it writes carries `a-jay85/IBL5-backlog`. A hand-written `Closes #N`
  would target IBL5 issue N, so never write one.
- **Stacked PRs.** GitHub ignores closing keywords while a PR's base is a parent branch. When the
  parent merges, GitHub retargets the child to `master`, and the keywords fire when the child
  merges. Keep the lines as they are.

## Declared scope

A `## Declared scope` section lists `.claude/` paths this PR edits on purpose that the plan's `## Critical Files` section does not name. Phase 5.0's diff→plan conformance check reads this section and dismisses any path it finds there. One path per bullet, backticked or bare, repo-root-relative:

```markdown
## Declared scope

- `.claude/rules/doc-freshness.md` (frontmatter bump forced by the on-touch rule)
- `.claude/agents/sonnet-4-6.md` (tool list corrected while adjacent)
```

The extraction is section-bounded. It starts at the `## Declared scope` heading and stops at the next `## ` heading, so a `.claude/` path mentioned elsewhere in the PR body dismisses nothing. Write a reason on each bullet for the reviewer; the check reads only the path.
