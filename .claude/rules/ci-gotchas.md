---
description: GitHub Actions gotchas learned in production — cascade cancels, payload freezing, required check wiring, mutation gate, Dependabot, ssh-keyscan, VR update-baselines label flow.
last_verified: 2026-10-06
paths: ".github/workflows/**"
---

# CI Gotchas

## CI failures are always the PR's fault

Do not escalate to "CI is flaky" or rerun until you have ruled out:

1. A real test failure introduced by the diff.
2. A seed mismatch (local vs. CI seed differ — see `playwright-gotchas.md`).
3. An environment assumption in the diff that does not hold in CI (PHP extension, env var).

Only after confirming those three can you treat a failure as infrastructure noise and rerun.

## Cascade cancel — `cancel-in-progress: true`

`cancel-in-progress: true` + rapid push sequence = every check run cancels before it exits, leaving GitHub with zero status checks on the PR. No red, no green — just a stale "pending" badge. The merge button stays gray forever.

**Fix:** squash all pending commits into one + force-push. That triggers a single clean run with no predecessor to cancel.

## `gh run rerun` replays the original event payload

`gh run rerun <id>` replays the event snapshot captured at trigger time — **PR body edits made after that moment are invisible**. Reruns read the frozen description, not the live one. A rerun that needs the current PR body (e.g. a gate that parses body fields) will behave as if the old body is still there.

**Fix:** push an empty commit to produce a new event with the current payload. Never `gh run rerun` when the gate reads the PR body.

## Config example files are copied verbatim by CI

**`ibl5/config.php.example` → `ibl5/config.php`** (`cp` in `.github/workflows/migration-safety.yml` and `deploy-rehearsal.yml`): every `define()` in the example ships as-is to CI. Use realistic non-empty placeholder values — not `''` or `0` — for any constant CI will evaluate. `ibl5/bin/check-config-example` (in `static-guards`) enforces this; it bans bare empty/zero `define()` literals.

**`ibl5/config/discord.config.example.php`** deliberately has no `testing` key. Without it, `Discord::postToChannel()` resolves `$webhooks['testing'] ?? null` → null → no-op in all non-prod environments. Do NOT add a try/catch guard in the class — it trips the 100% Infection MSI gate on changed lines in `classes/`.

## Required checks: aggregator jobs plus protection contexts

Branch protection on master names job-level contexts. `Tests and Analysis` is the aggregator job in `.github/workflows/tests.yml` and `E2E Tests` is the one in `.github/workflows/e2e-tests.yml`. To gate merge on a new job inside either workflow, add its job id to that aggregator's `needs:`. Leave the protection settings alone for that case.

A new protection context is a separate decision (ADR-0120, ADR-0145). The workflow that emits it must report on every PR: no `paths` or `branches` filter and no job-level `if:`. A context with no matching check run leaves the PR pending forever. Add it with the append-only `POST .../required_status_checks/contexts` and check it with `bin/check-composite-contracts --protection-readback`. Never use the protection `PUT`, which replaces the whole object. Live list: `gh api repos/a-jay85/IBL5/branches/master/protection --jq '.required_status_checks.contexts'`.

`All checks green` (`.github/workflows/all-checks-green.yml`, ADR-0149) is the required cross-workflow aggregator. A red check anywhere on the head blocks the merge, and a pending one holds it until it finishes. After you re-run one flaky job, re-run the aggregator run too: `gh run list --workflow all-checks-green.yml --branch <head-branch> --limit 1 --json databaseId --jq '.[0].databaseId' | xargs gh run rerun`. The excluded names live in the workflow's `--ignore=`. To make a new job advisory, add its name there. Never delete a check to get a merge through.

## Mutation testing: per-PR diff job required, full suite label-gated

`.github/workflows/mutation-pr.yml` runs `Infection PHP (per-PR diff)` on every PR at `--min-msi=100` on changed lines. There is no waive label. It is a required context and blocks merge, so a surviving mutant on a changed line needs a test that kills it. `.github/workflows/mutation.yml` runs the full suite only on the `mutation-test` label, Monday's cron, or `workflow_dispatch`, and never blocks merge. Keep `labeled` out of `mutation-pr.yml`: a label run in the same concurrency group cancels the real run. `bin/check-composite-contracts` (M1 to M4) enforces the trigger, name, `if:`, and threshold.

## `ssh-keyscan` needs `|| true`

Bare `ssh-keyscan host >> ~/.ssh/known_hosts` can exit non-zero on transient network errors, killing the step. Guard every keyscan: `ssh-keyscan -H host >> ~/.ssh/known_hosts || true`.

## Update-baselines label flow

The `update-baselines` label in `.github/workflows/e2e-tests.yml` triggers Visual Regression baseline regeneration and commit back to the PR branch. Three fixed bugs to avoid re-learning:

1. **`conclusion` vs `outcome`**: with `continue-on-error: true`, `conclusion` becomes `'success'` even when the step fails. The regen/commit steps must check `steps.visual-regression.outcome == 'failure'` — `outcome` retains the real result.
2. **Missing `always()` on Commit step**: when Regenerate fails, GitHub Actions skips later steps. The Commit step's `if:` must include `always()`.
3. **Premature label removal**: "Remove update-baselines label" must be gated on `steps.regen-baselines.outcome != 'skipped'`; otherwise a label event where regen is skipped still removes the label, preventing retry.

**CI-storm fix:** label events no longer rerun the full E2E suite. `src` dropped `action == 'labeled'`; a new `baseline` output (`github.event.action == 'labeled' && github.event.label.name == 'update-baselines'`) gates only the Visual Regression job. Skipped shards are safe for auto-merge — the required `E2E Tests` gate (`if: always()`, fails only on `failure`/`cancelled`) treats skipped as passing.

## Dependabot `@rebase` is unreliable

`@dependabot rebase` can no-op with only a comment to show for it. Observed: both PRs replied "Looks like this PR has been edited by someone other than Dependabot" even when `git log` showed a single `dependabot[bot]` commit on a clean master base — the refusal is a false positive.

The completion signal is the PR **head SHA changing**, not the bot's comment. After `@dependabot rebase` posts, read the bot's reply (`gh pr view <n> --json comments`) and confirm `headRefOid` moved.

**On "edited by someone other than Dependabot":** first verify the branch has no real human commits (`git log --format='%an' origin/<branch>`). If none — the common case — `@dependabot recreate` is the actual fix. If recreate also stalls, resolve by hand in a worktree: merge `origin/master` (never rebase), regenerate the lockfile with the ecosystem's tool, then push.
