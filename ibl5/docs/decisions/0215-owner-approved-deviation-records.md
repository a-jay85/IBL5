---
description: An owner-approved deviation is a trusted PR issue comment record that post-plan snapshots at run start; fixers may not revert its anchored lines, and fidelity review may downgrade only by citing it.
last_verified: 2026-10-24
owner: ajaynicolas
---

# ADR-0215: Owner-approved deviation records

**Status:** Accepted
**Date:** 2026-10-24
**Deciders:** a-jay85

## Context

Post-plan fixers treat the plan file as the only source of intent. Those fixers are Phase 5.5 fidelity remediation, the review-thread fixer, and the gate fixer. Owner decisions made after planning live in chat and PR comments. Three PRs saw a fixer undo an approved change: #3428 (a filter removal), #3514 (a watchdog deletion), and #3520 (Phase 5.5 reverting owner fixes). Amending the plan by hand is a workaround that depends on memory. Fixer sessions run with the owner's `gh` token, so a comment's author login alone cannot prove a human posted it.

## Decision

An owner-approved deviation is a PR issue comment carrying `<!-- postplan-owner-approval -->` and a JSON record with `schema`, `id`, `summary`, and `files[].path/keep/gone`. `bin/approve-deviation` writes it from a commit or a diff. `keep` holds the normalized added lines that must stay present. `gone` holds the normalized removed lines that must stay absent.

Only comments whose REST `user.login` is `a-jay85` and whose `user.type` is `User` count. `Bot` authors, `[bot]` logins, and PR-body blocks have no effect.

The harness snapshots the record set once at run start, before it spawns any fixer. Every tree-editing fixer call gets the snapshot as an off-limits block. Fixers are denied `gh pr comment`, `gh issue comment`, `gh issue edit`, `gh pr review`, and `gh api`. After each fixer call the harness re-lists the records and holds on drift. A fixer commit that removes a `keep` line or reintroduces a `gone` line is dropped, and the run holds on arming condition (20). Fidelity review may downgrade a finding only by citing a snapshot id whose anchors are still at `HEAD`.

Residual risk, accepted: a fixer that reaches `gh` through a path the deny list does not match (a wrapper script, or `curl` with the token) could post a record during its own run. The drift check turns that into a hold for the current run. A record planted for a future run looks the same as the owner's. The mitigation is the owner reading `Owner-approved deviation` comments on the PR before merge, which the hold already forces.

Enforcement lives in `tools/postplan-harness/harness/owner_approvals.py`, the `_commit_with_gate_fix` choke point in `tools/postplan-harness/runner.py`, and condition (20) in `tools/postplan-harness/harness/armable.py`.

## Alternatives Considered

- **PR-body block.** The record lives in the PR description. Rejected because the harness and fixers rewrite the body with `gh pr edit`, and a body has no per-block author.
- **Plan amendment.** The owner edits the plan file after approving. Rejected because it is manual and depends on memory.
- **Reuse the review-thread trust rule.** `_is_trusted` already admits the owner. Rejected because it also admits `Bot` and `[bot]` logins, which is the fixer's identity class.

## Consequences

- Positive: an approval survives rebases, because anchors are normalized lines instead of line ranges or shas.
- Positive: a fixer revert becomes a visible hold that names the line, instead of a silent undo.
- Negative: a fixer that legitimately refactors an approved line holds the PR. The owner reads the hold and decides.
- Negative: the skill-engine fallback holds every PR that carries a record, which is stricter than the harness.

## References

- `bin/approve-deviation`
- `tools/postplan-harness/harness/owner_approvals.py`
- `tools/postplan-harness/harness/approve_deviation.py`
- `tools/postplan-harness/harness/armable.py`
- `bin/lib/pr-armable.sh`
- `.claude/skills/post-plan/_phase-6.5-arm-auto-merge.md`
