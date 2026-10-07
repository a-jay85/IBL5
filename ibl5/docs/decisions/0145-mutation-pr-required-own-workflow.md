---
description: The per-PR Infection job moves to its own workflow so it reports on every PR and can be a required check; branch protection gains it by a manual append-only POST with a checked read-back.
last_verified: 2026-09-29
owner: ajaynicolas
---

# ADR-0145: `Infection PHP (per-PR diff)` Required, in Its Own Workflow

**Status:** Accepted
**Date:** 2026-09-29
**Deciders:** ajaynicolas

## Context

ADR-0065 decided in June 2026 that the per-PR mutation job would become a required check on master. It was never activated. Live protection on 2026-09-29 lists four contexts (`Tests and Analysis`, `E2E Tests`, `human-signoff`, `Meta checks`) and none is the mutation job.

The job shared `.github/workflows/mutation.yml` with the label-triggered full suite. That file ran on `labeled` events and used one concurrency group with `cancel-in-progress: true`. A bookkeeping label on a PR therefore cancelled the real per-PR run, and the labeled run added a second, skipped check run with the same name. GitHub documents that a job skipped by a conditional reports success. It does not document which of two same-name check runs on one SHA decides a required context. On 2026-09-29 one bulk label produced eight skipped runs in a minute.

A required check must also start on every PR. A workflow skipped by a `paths` or `branches` filter leaves its check pending and blocks the merge forever.

## Decision

1. The job `mutation-pr` (name `Infection PHP (per-PR diff)`) lives alone in `.github/workflows/mutation-pr.yml`. It triggers on `pull_request` types `opened`, `synchronize`, and `reopened`, with no `paths` or `branches` filter and no job-level `if:`. A PR with no covered PHP changes runs the job, skips the later steps at step level, and reports success.
2. The threshold stays `--min-msi=100` on changed lines. There is no waive label and no bypass. The two sticky-comment steps carry `continue-on-error: true`, so a comment-API failure cannot fail the gate. The Infection step carries no such key.
3. `.github/workflows/mutation.yml` keeps the full suite and its failure alert, and its `pull_request` trigger narrows to `types: [labeled]`.
4. `bin/check-composite-contracts` pins these properties on every PR (M1 to M4). It holds the one machine-checked copy of the context literal and compares it to the workflow and to this ADR.
5. Branch protection gains the context by a manual, append-only admin POST after merge (below). It is not automated. ADR-0120 rejected an unattended protection flipper for the same reason: the protection PUT replaces the whole object.

## Activation (post-merge, manual)

Preconditions, all read-only:

```bash
# 1. The new workflow has reported success at least once.
gh run list --repo a-jay85/IBL5 --workflow mutation-pr.yml \
  --status success --limit 1

# 2. Straggler audit: open PRs whose head SHA has no completed,
#    non-cancelled run of the context. Must print nothing.
gh pr list --repo a-jay85/IBL5 --state open --base master \
  --json number,headRefOid --jq '.[] | "\(.number) \(.headRefOid)"' |
while read -r n sha; do
  c=$(gh api "repos/a-jay85/IBL5/commits/$sha/check-runs" \
    -f check_name='Infection PHP (per-PR diff)' -X GET \
    --jq '[.check_runs[] | select(.status == "completed"
      and .conclusion != "cancelled")] | length')
  [ "$c" -gt 0 ] || echo "STRAGGLER #$n $sha"
done
```

For each straggler, run `gh pr update-branch <n>`. Every PR opened before this ADR merged is behind master, so the update pushes a `synchronize` event and the new workflow runs. Do not close and reopen a PR, because closing clears its auto-merge. Re-run the audit until it prints nothing.

Apply:

```bash
PRE=~/claude-plans/_backups/master-protection-pre-$(date +%F).json
gh api repos/a-jay85/IBL5/branches/master/protection > "$PRE"
echo '["Infection PHP (per-PR diff)"]' | gh api -X POST \
  repos/a-jay85/IBL5/branches/master/protection/required_status_checks/contexts \
  --input -
```

Send the body as a bare JSON array. The `-f 'contexts[]=...'` form sends `{"contexts": [...]}`, and on 2026-09-29 GitHub answered it with an empty HTTP 500 that `gh` reports as `unexpected end of JSON input`. Protection did not change.

Use only the `POST .../required_status_checks/contexts` endpoint, which appends. Never use the protection `PUT`: it replaces the whole object, and the ADR-0065 recipe built on it would now drop `Meta checks`.

## Read-back

```bash
POST=/tmp/master-protection-post.json
gh api repos/a-jay85/IBL5/branches/master/protection > "$POST"
bin/check-composite-contracts --protection-readback="$POST" --pre-image="$PRE"
```

Expected output: `protection-readback: OK — 5 contexts ...`. The mode asserts that the pre-image lacked the context, that the post set equals the pre set plus `Infection PHP (per-PR diff)`, that `strict` is `true`, and that nothing outside `required_status_checks.contexts` and `checks` changed. Any other result means roll back now.

## Rollback

```bash
echo '["Infection PHP (per-PR diff)"]' | gh api -X DELETE \
  repos/a-jay85/IBL5/branches/master/protection/required_status_checks/contexts \
  --input -
diff <(jq -S . "$PRE") \
  <(gh api repos/a-jay85/IBL5/branches/master/protection | jq -S .)
```

The `diff` prints nothing when protection matches the saved pre-image.

## Consequences

- Every PR pays for one mutation job, including docs-only PRs, where the job exits after `Detect covered changes`. This was already true before the move.
- A PR whose changed lines leave a surviving mutant cannot merge until a test kills it.
- `bin/pr-triage` reads required contexts from the live protection API, so it picks up the new context with no code change.

## Alternatives Considered

- **Keep the job in `mutation.yml`.** Relying on the `labeled` skip was rejected: the shared concurrency group cancels the real run, and the winner between two same-name check runs is undocumented.
- **Run the POST from `/post-plan`.** A workflow was also considered and both were rejected: the POST needs an admin token, and a mistaken protection write blocks every PR. The read-back mode makes the manual step checkable.
- **A registry file.** A file listing required checks was rejected: it is one more copy to drift. The constant in `bin/check-composite-contracts` is the only machine-checked copy.

## Lineage

Supersedes the mechanism and the Activation block of ADR-0065. Precedent for the manual flip with a checked read-back: ADR-0120.

## Addendum: activation body format (2026-09-29)

The Activation and Rollback commands first used `-f 'contexts[]=Infection PHP (per-PR diff)'`. The POST returned an empty HTTP 500 twice and left protection unchanged. The same POST with a bare-array body through `--input -` returned 200, and the read-back printed `protection-readback: OK — 5 contexts`. Both blocks now use the bare-array form. The DELETE form is untested because the rollback was never needed.
