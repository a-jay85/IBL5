---
description: Operator runbook to enable, verify, and roll back the GitHub native merge queue on master (ADR-0072) — run after the plumbing PR merges, before the rebase-prs.yml retirement PR.
last_verified: 2026-06-28
---

# Merge-queue enablement runbook

Turns the GitHub native merge queue **on** for `master` (ADR-0072). The workflow
plumbing already merged (the three required workflows trigger on `merge_group`);
enabling the queue itself is a repo-settings change, performed here.

## When to run

- **After** the plumbing PR (this PR, "PR 2 of 3") merges to `master`.
- **Before** PR 3 (which retires `.github/workflows/rebase-prs.yml`).
- Requires repo-admin on `a-jay85/IBL5`. Confirm `gh auth status` shows admin scope.

## Why ordering matters (ADR-0072 D5)

The workflow edits are **inert while the queue is off** (`merge_group` triggers
never fire), so the plumbing PR merged harmlessly under the existing known-good
required-check floor. Turning the queue ON is the settings change below.
`.github/workflows/rebase-prs.yml` still exists as the rollback fallback until
PR 3 merges — **do not merge PR 3 until the queue is proven** (Step 3). A clean
rollback at this stage = delete the ruleset, and eager rebase is still present.

## Step 1 — turn off `strict` ("require branches up to date"), superseded by the queue (D6)

The queue does just-in-time rebase, so the per-PR `strict` toggle is redundant.
Record the current state, then PATCH `strict:false`:

```bash
REPO=a-jay85/IBL5
gh api repos/$REPO/branches/master/protection/required_status_checks --jq '{strict, checks}'   # record current
gh api --method PATCH repos/$REPO/branches/master/protection/required_status_checks --input - <<'JSON'
{ "strict": false }
JSON
```

## Step 2 — enable the merge queue via a ruleset

The `merge_queue` rule is only available through rulesets, not classic branch
protection. The three required contexts (`Tests and Analysis`, `E2E Tests`,
`human-signoff`) already live in classic branch protection and the queue gates
the merge group on the branch's required checks, so they are **not** duplicated
here:

```bash
REPO=a-jay85/IBL5
gh api --method POST repos/$REPO/rulesets --input - <<'JSON'
{
  "name": "master merge queue",
  "target": "branch",
  "enforcement": "active",
  "conditions": { "ref_name": { "include": ["refs/heads/master"], "exclude": [] } },
  "rules": [
    {
      "type": "merge_queue",
      "parameters": {
        "merge_method": "SQUASH",
        "grouping_strategy": "ALLGREEN",
        "max_entries_to_build": 5,
        "min_entries_to_merge": 1,
        "max_entries_to_merge": 5,
        "min_entries_to_merge_wait_minutes": 5,
        "check_response_timeout_minutes": 60
      }
    }
  ]
}
JSON
```

Confirm the ruleset is active:

```bash
gh api repos/$REPO/rulesets --jq '.[] | select(.name=="master merge queue") | {id, enforcement}'
```

If a future API version rejects this shape, the UI fallback is **Settings →
Rules → New branch ruleset**, target `master`, add the **Merge queue** rule with
the same parameters; the required-status-checks list stays as the existing three
contexts.

## Step 3 — verify on the live repo

```bash
# Open a trivial no-op PR (e.g. a whitespace/docs change), let its required checks pass, then:
gh pr merge <N> --squash --auto
gh pr view <N> --json state,mergeStateStatus,autoMergeRequest    # expect it to enter the queue
gh run list --event merge_group --limit 5                        # the 3 required workflows re-run on the merge_group
gh pr view <N> --json state                                      # expect MERGED once the merge_group goes green
```

**Assert the merge_group human-signoff actually enumerated the PR** (turns the
`(#N)` parse assumption into a checked one — ADR-0072 D3): open the
`human-signoff` job's `merge_group` run log and confirm it shows
`merge_group: evaluated 1 PR(s)`, **not** the empty-enumeration
`::warning:: no PR numbers parsed`. If you see the warning, the `(#N)` parse is
wrong for this repo's merge method — fix `hs_pr_numbers_in_range` in
`bin/lib/human-signoff-classifier.sh` before relying on the re-check (entry still
protects in the meantime).

Also confirm an **unlabeled `feat:` test PR cannot enter the queue** — its
`human-signoff` is red at entry, so the queue button is unavailable until the
`human-approved` label is applied.

## Step 4 — only now, merge PR 3

Retire `.github/workflows/rebase-prs.yml` (PR 3). Until then leave it in place.

## Rollback

```bash
REPO=a-jay85/IBL5
RULESET_ID=$(gh api repos/$REPO/rulesets --jq '.[] | select(.name=="master merge queue") | .id')
gh api --method DELETE repos/$REPO/rulesets/$RULESET_ID            # disables the merge queue
# (optional) restore strict if you want "require up to date" back:
gh api --method PATCH repos/$REPO/branches/master/protection/required_status_checks --input - <<'JSON'
{ "strict": true }
JSON
```

**Rollback note:** disabling the queue does **not** restore eager rebase — that
lives in `.github/workflows/rebase-prs.yml`, retired only in PR 3. Because PR 3
has not merged at runbook time, `rebase-prs.yml` **still exists** and resumes as
the fallback the moment the queue is off. This is exactly **why PR 3 is sequenced
after queue confirmation**: a clean rollback = delete the ruleset and the old
eager-rebase path is still there. (To roll back *after* PR 3 merges, revert PR 3.)

## "Require branches up to date" note

Enabling the merge queue supersedes the per-PR `strict` toggle (the queue does
just-in-time rebase); Step 1 turns it off.
