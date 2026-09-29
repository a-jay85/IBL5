---
description: "PR body authoring rules: version/baseline citations must name their source file; external-state claims must carry a link or command output; negative-claim bullets must be re-read after every commit; coordinate citations (file:line, backlog row IDs) must be re-verified after every commit; backlog closing keywords come from the plan via the shared normalizer snippet."
last_verified: 2026-09-29
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

**Headless.** Applies: an automouse or `/post-plan` run authoring a PR body performs the same
re-read; there is no human in the loop to catch the stale bullet later.

## Coordinate-citation re-check rule

A PR body that cites a coordinate points at a spot that later commits can move. Coordinates are a `path:line` or `path:start-end` reference and a backlog row or entry ID such as `(see L51)`. A commit that adds or removes lines above the cited spot shifts the line numbers. A commit that renumbers backlog rows changes the IDs. Nothing recomputes the body, so it keeps the stale number.

After every commit pushed to an open PR, re-verify each coordinate the body cites in a file that commit touched. Open the file at the PR head and confirm the cited line still holds what the prose says it holds. Update the number, or replace it with a symbol or heading anchor that does not drift.

| What the commit did | What to do |
|---|---|
| Touched a file the body cites by line | Re-read each cited line at the new head; fix any that moved |
| Renumbered or renamed backlog rows | Re-check every row ID the body and any archive cross-reference cite |
| Touched no cited file | Nothing to do |

Prefer a function name or heading over a line number when the prose allows it. A symbol survives a rebase and a line number does not.

This rule has no mechanical check. Prose numbers are free-form, and a scan for `:<N>` would flag too many honest lines. Trigger: L59, PR #2083 (row IDs `L51`/`L52` and `push.sh:55` cited after a later commit moved them).

**Headless.** Applies: an automouse or `/post-plan` remediation commit re-verifies the body's coordinates before the push is done.

## External-state evidence rule

Some PR-body claims describe the world outside the diff. A service is running. A launchd job is registered. A cron entry is scheduled. A GitHub Actions run passed. A migration is applied on prod. The diff cannot prove any of these, so the reviewer has only your word for them.

Every such claim carries its evidence inline, in one of two forms:

- **Link.** A URL the reviewer can open: the Actions run, the deploy log, the PR check.
- **Command output.** The command you ran and the output line that shows the state, in a code span or fenced block. For example, `launchctl list | grep <label>` followed by the line it printed.

If you cannot produce the evidence, drop the claim. Describe what the PR changes, and name the command a reviewer runs after merge to confirm the state: "After merge, `launchctl list | grep <label>` shows the job." A present-tense external-state claim with no evidence is a fabricated claim, and the reviewer treats it as one.

This rule has no mechanical check. The claims it covers are free-form prose, and the same phrases appear in design descriptions and quoted plans, so a pattern match would flag too many honest lines. Facts derivable from the diff are generated for you: the `**Files changed**` and `**Tests changed**` blocks come from `git diff`, so never restate them by hand.

## Backlog issue references

A bare `#N` autolinks to IBL5's own PR or issue N. Backlog issues live in a different repo,
so cite them as `a-jay85/IBL5-backlog#N`. In prose, write `backlog issue a-jay85/IBL5-backlog#160`.
Use bare `#N` only for IBL5 PRs and issues.

### Closing keywords

The plan's backlog bullets decide which backlog issues this PR closes. Post-plan collects them
from the whole plan outside fenced code blocks, so a bullet counts in the `## Backlog issues`
section or in any phase body, including a bookkeeping-only phase. Only a bullet that starts with
`closes` or `refs` and names the full `a-jay85/IBL5-backlog` path counts. A mention in prose, an
inline code span, or a blockquote closes nothing. A `closes` bullet becomes a
`Closes a-jay85/IBL5-backlog#N` line in the PR body, and GitHub closes that issue when the PR
merges into `master`. A `refs` bullet gets a plain `a-jay85/IBL5-backlog#N` link with no closing
keyword. When one issue appears as both kinds, `closes` wins.

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

- **No plan record.** With no plan file, or a plan with no backlog bullets, the body stays byte-for-byte
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

A `## Declared scope` section lists paths this PR edits on purpose that the plan names nowhere: in neither its `## Critical Files` section nor a Verification Matrix test path. Any directory counts. Phase 5.0's diff→plan conformance check (`bin/lib/plan-scope-conformance`) reads this section and dismisses every path it finds there. One path per bullet, backticked or bare, repo-root-relative. A token of two or more segments ending in `/` (for example `ibl5/classes/Foo/` (example)) declares every path below it; a one-segment token such as `ibl5/` declares nothing.

```markdown
## Declared scope

- `.claude/rules/doc-freshness.md` (frontmatter bump forced by the on-touch rule)
- `.claude/agents/sonnet-5-5.md` (tool list corrected while adjacent)
- `ibl5/classes/Updater/ScheduleUpdater.php` (Playoffs-phase guard added while fixing the schedule import)
```

The extraction is section-bounded. It starts at the `## Declared scope` heading and stops at the next `## ` heading, so a path mentioned elsewhere in the PR body dismisses nothing. Generated marker spans (`<!-- files-changed:begin -->` through `<!-- files-changed:end -->`, and every other `<!-- name:begin -->` / `<!-- name:end -->` pair) are stripped before the section is read, so the generated block never declares anything, even when it sits under this heading. Write a reason on each bullet for the reviewer; the check reads only the path.

## Plan gaps

A `## Plan gaps` section lists must-appear `## Critical Files` paths the diff does not touch. Write one bullet per path with the reason: cut from scope, deferred to a named follow-up, or already shipped before the branch was cut (cite the PR). A bullet clears only the `UNEXPLAINED-GAP:` item for that path. The `MISSING-FILE:` item keeps its own resolution rule in `_phase-5-final-verification.md`.

```markdown
## Plan gaps

- `ibl5/docs/decisions/0074-example.md` (already refreshed by #2036 before this branch was cut)
```
