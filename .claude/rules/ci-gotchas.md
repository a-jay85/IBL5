---
description: GitHub Actions gotchas learned in production; cascade cancels, payload freezing, required check wiring, mutation gate, Dependabot, ssh-keyscan, VR update-baselines label flow, conversation resolution, runner image pin; per-mutant DB tests run on per-thread databases.
last_verified: 2026-11-18
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

A new protection context is a separate decision (ADR-0120, ADR-0145). The workflow that emits it must report on every PR: no `paths` or `branches` filter, and no job-level `if:` except a payload-provable skip (ADR-0210): the skipped events cannot change the verdict, a title that could fail never skips, and the predicate is duplicated into a per-run `concurrency.group` so a skipped run never cancels a real one. `human-signoff` carries one; `Meta checks` deliberately does not. A context with no matching check run leaves the PR pending forever. Add it with the append-only `POST .../required_status_checks/contexts` and check it with `bin/check-composite-contracts --protection-readback`. Never use the protection `PUT`, which replaces the whole object. Live list: `gh api repos/a-jay85/IBL5/branches/master/protection --jq '.required_status_checks.contexts'`.

`All checks settled` (`.github/workflows/all-checks-settled.yml`, ADR-0149 addendum) is the required cross-workflow aggregator. It is a commit status on the PR head, posted from master each time a PR workflow completes, and it never waits. A red check anywhere on the head turns it `failure`, and a pending one keeps it `pending`. Re-running one flaky job re-settles it. If the status is stuck, settle it by hand: `gh workflow run all-checks-settled.yml -f pr_number=N`. To make a job advisory, add its name to that workflow's `--ignore=`. A new workflow with a `pull_request` trigger must also join its `workflow_run.workflows:` list, or its completion never settles the status (`bin/check-composite-contracts` A6). Never delete a check to get a merge through. On a stacked PR (base not master or develop), the status goes `failure` with `FAIL: stacked on <base>`. After the parent merges and GitHub retargets it, push (merge or rebase onto `origin/master`). `E2E Tests` and `Tests and Analysis` run on stacked PRs (no `pull_request` `branches` filter, pinned in `bin/test-plan-checks-block`), so prefire-verify skips bare `bin/e2e-wt` and `bin/db-test-up` rows.

`Plan checks` (`.github/workflows/plan-checks.yml`, ADR-0244) runs plan matrix and mutation rows in CI. It starts only the stacks (DB, web, bun, actionlint) its rows need and maps `localhost` URLs to port 8080 (ADR-0255). Post-plan puts a base64 block in the PR body and adds the `plan-checks` label. `bin/lib/plan-checks-block ci` decodes it. The job fails when the PR has the label but no block, the block is corrupt, a CI row fails, or a mutation row is HELD. A PR with no label and no block passes. The job re-runs on `edited`, so an edit that breaks the block turns it red.

### Conversation resolution

The protection toggle `required_conversation_resolution` makes GitHub refuse any merge, auto-merge included, while a review thread on the PR is unresolved. It counts every thread: any label, any author, outdated ones too. It adds no status context. The owner flips it by hand. Read its live value with `gh api repos/a-jay85/IBL5/branches/master/protection --jq '.required_conversation_resolution.enabled'`.

Master is meant to run with it on because arming condition (11) in `bin/lib/pr-armable.sh` reads threads only at arm time. A thread posted after arming by a standalone `/pr-review`, a `/security-audit`, or a burndown pass would otherwise merge unanswered once checks go green. Auto-merge has no bypass for the toggle, so the tooling keeps open threads near zero and names any that remain:

- Post-plan Phase 4.5 and 4.5b (`tools/postplan-harness/harness/thread_ingestion.py`) reply to and resolve every trusted thread they can: Important, nit, and pre-existing alike. A reply reads `Fixed in <sha> - <reason>` or `Declined: <reason>`. A nit or pre-existing decline closes the thread only for a category in `WONT_FIX_CATEGORIES`. A gate-path decline, a failed fixer call, and an untrusted author's thread stay open for a human.
- `bin/pr-triage` puts an armed PR with any open thread, and an unarmed PR held only by an open nit or pre-existing thread, in the `OPEN-THREAD` bucket, which `--arm` never arms. GitHub shows such a PR as `BLOCKED`, the same state as one waiting on checks, so the bucket is where it gets named.
- `bin/pr-cycle-tick` lists armed PRs with open threads in its stuck DM every tick. `bin/pr-cycle` ranks `OPEN-THREAD` first in its your-turn DM.

`enforce_admins` stays `false` (`ibl5/docs/decisions/0062-human-signoff-gate-for-feature-prs.md`), so a hand merge with `gh pr merge --admin <N>` still lands a PR with an open thread. Read the thread before using it. On an armed PR, resolving the last open thread lets auto-merge proceed with no other step.

## Mutation testing: per-PR diff job required, full suite label-gated

`.github/workflows/mutation-pr.yml` runs `Infection PHP (per-PR diff)` on every PR at `--min-msi=100` on changed lines. There is no waive label. It is a required context and blocks merge, so a surviving mutant on a changed line needs a test that kills it. `.github/workflows/mutation.yml` runs the full suite only on the `mutation-test` label, the Sunday dispatch from `mutation-weekly-dispatch.yml`, or `workflow_dispatch`, and never blocks merge. `Plan mutation shards` builds the full-suite matrix from `ibl5/infection-shards.json` and fails when an `ibl5/classes` entry sits in no shard or in two. `Mutation coverage` runs PHPUnit once and shares the coverage with every shard as an artifact. Each `Infection PHP sNN` job runs one shard at `--min-msi=0`. `Aggregate MSI` sums the shards and enforces `MUTATION_MIN_MSI` once. A `workflow_dispatch` run with the `module` input mutates one `ibl5/classes` entry and reports its MSI without enforcing the bar. Keep `labeled` out of `mutation-pr.yml`: a label run in the same concurrency group cancels the real run. `bin/check-composite-contracts` (M1 to M5) enforces the trigger, name, `if:`, threshold, and diff resolution. Never set `git config diff.relative` in that workflow. Infection then finds no source file and passes with zero mutants ([#3443](https://github.com/a-jay85/IBL5/pull/3443)). The Infection step tees its output to a log and then runs `ibl5/bin/check-infection-ran`, which exits 5 when Infection matched no source but a changed class sits outside `source.excludes`. A diff that touches only excluded classes still passes. A second step in both mutation workflows, `Check mutation kills are real`, runs `ibl5/bin/check-infection-ran --kills infection-report.html`. It exits 6 when a killed mutant's PHPUnit output shows a runner warning, an unreadable baseline, an unset env var, a failed DB connection, or no tests. It also exits 6 when warning-only and fatal-only kills pass 10% of at least 50 kills. A relative `baseline=` path once made every mutant exit 1, so both gates passed on fake kills ([#3625](https://github.com/a-jay85/IBL5/pull/3625)).

**Per-mutant DB tests.** Infection builds each per-mutant config from `<phpUnit.configDir>/phpunit.xml` and ignores `--configuration` in `testFrameworkOptions`. Both Infection steps therefore run `cp phpunit-mutation.xml phpunit.xml` first, so mutant runs execute the `database` group and skip `discord-live` and `mutation-unsafe`; `ibl5/bin/check-xml-class-refs` fails if those excludes go missing or if `database` gets excluded. The steps also set the four `DB_*` keys to the `setup-php-env` defaults. Without them every DB test fails on a missing env var and Infection counts that failure as a kill. Infection runs 4 threads and exports `TEST_TOKEN=1..4`; the `Clone per-thread test databases` step copies the seeded `ibl5` into `ibl5_t1` to `ibl5_t4`, and `INFECTION_PER_THREAD_DATABASE: '1'` makes `ibl5/tests/DatabaseIntegration/DatabaseNameResolver.php` pick `ibl5_t<token>`. The step's `DB_NAME` stays `ibl5` so D1 passes. One thread reruns each test once per mutant in one database, so a test that commits must carry `#[Group('mutation-unsafe')]`: `DatabaseTestCase` fails any untagged test that ends with no open transaction, and `MutationUnsafeScannerTest` fails any untagged test that commits or opens its own connection. Check M6 in `bin/check-composite-contracts` pins the copy, flag, clone step and no `--threads`.

## `ssh-keyscan` needs `|| true`

Bare `ssh-keyscan host >> ~/.ssh/known_hosts` can exit non-zero on transient network errors, killing the step. Guard every keyscan: `ssh-keyscan -H host >> ~/.ssh/known_hosts || true`.

## Update-baselines label flow

The `update-baselines` label starts `.github/workflows/update-baselines.yml`, which calls the `Visual Regression` job of `.github/workflows/e2e-tests.yml` through `workflow_call`. That run rewrites only the baselines the gallery's strict pass flags, then commits them back to the PR branch. Three fixed bugs to avoid re-learning:

1. **`conclusion` vs `outcome`**: with `continue-on-error: true`, `conclusion` becomes `'success'` even when the step fails. The regen step must read `steps.visual-regression.outcome`, which keeps the real result. It runs on `success` or `failure`, because a sub-gate drift that the strict pass flags leaves VR green (ADR-0214).
2. **Missing `always()` on Commit step**: when Regenerate fails, GitHub Actions skips later steps. The Commit step's `if:` must include `always()`, plus `steps.regen-baselines.outcome != 'skipped'` so a skipped regen commits nothing.
3. **Premature label removal**: "Remove update-baselines label" must be gated on `steps.regen-baselines.outcome != 'skipped'`; otherwise a label event where regen is skipped still removes the label, preventing retry. It must also run before "Commit and push updated baselines". The push's synchronize event snapshots the labels, so a push made while the label is still on starts a second regen run and a second bot commit (`bin/test-vr-apply-baselines` pins the order).

**No label trigger on the required workflow.** `e2e-tests.yml` lists no `labeled` type. The required `E2E Tests` gate fails only on `failure` or `cancelled` needs, so a label-only run that skipped every job re-posted the check as success and turned a red gate green ([backlog#1707](https://github.com/a-jay85/IBL5-backlog/issues/1707)). `bin/check-composite-contracts` L1 rejects a `labeled` or `unlabeled` type in that `on:` block. Inside the called workflow, `github.event.action == 'labeled'` marks regen mode: the `baseline` output gates only the Visual Regression job, and the `gate` job skips. Regen checks report as `Update baselines / <job>`.

## Dependabot `@rebase` is unreliable

`@dependabot rebase` can no-op with only a comment to show for it. Its "edited by someone other than Dependabot" reply can be a false positive.

The completion signal is the PR **head SHA changing**, not the bot's comment. After `@dependabot rebase` posts, read the bot's reply (`gh pr view <n> --json comments`) and confirm `headRefOid` moved.

**On "edited by someone other than Dependabot":** first verify the branch has no real human commits (`git log --format='%an' origin/<branch>`). If none — the common case — `@dependabot recreate` is the actual fix. If recreate also stalls, resolve by hand in a worktree: merge `origin/master` (never rebase), regenerate the lockfile with the ecosystem's tool, then push.

## Runner image is pinned to `ubuntu-26.04`

Every job in `.github/workflows/` runs on `ubuntu-26.04`. GitHub moves the `ubuntu-latest` label to a new Ubuntu release in a staged rollout. [runner-images#14748](https://github.com/actions/runner-images/issues/14748) moved it to 26.04 between 2026-10-19 and 2026-11-19, with systemd 255 to 259 and kernel 6.17 to 7.0. During a rollout some jobs land on the old image and some on the new one. A check can then fail on one PR and pass on another with no related diff. On 2026-11-03 `bin/test-cleanup-safety` failed that way on jobs that ran on 26.04, until [#3706](https://github.com/a-jay85/IBL5/pull/3706) taught it the Node 24 thread name.

`bin/check-composite-contracts` check U1 fails any workflow file that names `ubuntu-latest`, comments included. All jobs share one literal, so an upgrade is one PR.

**Support window.** As of 2026-11-04 the [runner-images README](https://github.com/actions/runner-images#available-images) lists `ubuntu-26.04` as a GA image and publishes no retirement date for it. Review this pin by 2027-05-01. That is a review date chosen in the pinning PR. GitHub did not announce it.

**Upgrade recipe.** Do all of it in one worktree PR.

1. Swap the literal in every workflow with `sed -i '' 's/runs-on: ubuntu-26.04/runs-on: ubuntu-NN.04/' .github/workflows/*.yml` on macOS. On Linux, drop the `''`.
2. Swap the same literal in the fixtures of `bin/test-check-composite-contracts`, in `.github/actionlint.yaml`, and in this section, and set a new review date. Delete the `.github/actionlint.yaml` entry if the actionlint pin in `.github/workflows/tests.yml` already lists the new image.
3. Run `bin/check-composite-contracts` and `bin/test-check-composite-contracts`, then let the PR's full CI run on the new image. A check that fails only on the new image is part of the upgrade. Fix it in the same PR, or keep the old pin and name the failing check in the PR body.
