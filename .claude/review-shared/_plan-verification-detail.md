---
description: Read-on-demand detail for _plan-verification.md — why each forced trigger exists, the incidents behind them, the worked pre-prod exercise-path catalogue, and the non-compliant counter-examples. Read only when editing the verification rules; the plan-architect never reads it.
last_verified: 2026-09-16
---

# _plan-verification Detail

Supporting rationale for `.claude/review-shared/_plan-verification.md`. Not loaded at plan-write time.

### Why the matrix format is closed

The matrix format — one row per verification item, columns pinned to test-type + timing + file — is closed because every deviation produces a gap the gates cannot close. Free-form prose cannot be machine-parsed; a "Testing" appendix separates the claim from the implementation step that owns it; and a "verify manually" placeholder defers the classification decision past the only moment when the planner has full context.

### Why each integration trigger exists

Each row in the `## Forced integration-verification trigger` table was added from a post-merge finding — a shape of change that was repeatedly mis-verified by developers who understood the feature but wrote coverage at the wrong abstraction level. The rows name the right-hand assertion type (the actual network URL, the generated unit file) rather than repeating the wrong-level mistake.

**Per-row rationale.** The "why" clauses moved out of the rules-file table, whose operative assertions stand alone without them:

- **Importer reading a generated repo-relative path** — a negation line in `.gitignore` force-tracks the file, so the deploy `git reset` clobbers live content with stale committed data.
- **Importer writing to a secondary/audit table** — checking the secondary table alone does not prove the flag propagated; the primary table's flag column is the canonical one.
- **Detection check behind a fail-open guard** — a check nested inside a fail-open guard silently skips when data is unavailable, so unconditional detection paths must be shown unaffected.
- **`proc_open` call site** — an unread stderr pipe deadlocks the child once the OS buffer fills.
- **`echo`/log string pinned by a numeric-token `grep`** — a token-only pin cannot detect a behavioral lie in the wording (a-jay85/IBL5-backlog#144, PR #2135).
- **Port guard piping `lsof` to a pattern filter** — the `lsof` column header never matches a process name, so it always trips the "occupied by other process" branch when header-stripping is absent.
- **`bin/test-*` awk region delimiter** — the mutation run is what proves the awk region is scanning the correct code region.
- **htmx `beforeRequest`/`afterRequest` DOM mutation** (§ Forced E2E triggers, not this table) — the mutation is serialized into the htmx history cache between the before-request event and the swap; browser Back restores the request-time snapshot, making it permanent unless a `historyRestore` handler repairs it. Full mechanism: `.claude/rules/htmx-history-cache.md`.

### Why E2E assertions must be seed- and DOM-grounded

An assertion grounded in an imagined value is how PR #887 shipped: it expected a display-cap count (~500, full-league) while the CI seed has only ~24 career rows, so the test was deterministically red the moment it ran. Grounding on the CI seed or a live DOM curl prevents that class.

### Why the test-type boundaries fall where they do

A truly-manual row must be performable on the *open PR*, before it merges (orthogonal to the Timing column — pre-/post-impl is when a test is *written*, not when it can be *performed*). The test is *"can a reviewer render this row's judgment now, against the worktree/local stack?"* — never "does a command exit 0."

A UI/UX taste judgment **always** qualifies (bring the worktree up and look), so **never** cite "can't do it pre-merge" to drop a forced UI/UX row — that would defeat the § Forced manual-verification trigger + `/plan` Step 4 gate 14a.

When a judgment *appears* to depend on **this PR's own artifact being live on prod** — a file CI deploys on merge, a migration having run on prod, a registered daemon/cron (e.g. a recap whose quality can't be judged until the queue script this PR adds is deployed and reachable) — that is **not** a licence to move the row out of the merge gate. It is the trigger for § Pre-prod exercise paths: design an exercise path on one of the three reachable pre-prod environments, and keep the row.

Only an *intrinsic* deploy-dependency that survives that challenge may be recorded as a non-gating `## Post-merge verification` note in the PR body plus a follow-up — and that disposal is a **narrow, marker-recorded exception, never the default**: `/plan` Step 4 gate 16 and `bin/check-plan` gate `[P]` require a `pre-prod-exception:` marker plus a matching `## Pre-prod Exception Justification` entry naming the intrinsic category. Emptying `## Manual Testing` this way can arm auto-merge, so the removal is a deliberate call — valid only for an intrinsic deploy-dependency, never to shed a subjective UI/UX hold.

### Pre-prod exercise paths — worked catalogue

**Dissolving a deploy-dependency means BUILDING THE EXERCISE PATH — never deleting the row.** The organizing question is not *"is this feature deployable?"* but **"which slice of it is actually deploy-bound?"** For this repo's recurring shape — a `bin/<name>-tick` script driving a live external or cross-process service (`bin/bug-pipeline-tick`, `bin/sim-recap-tick`) — the answer splits cleanly, and the split runs through the middle of a single feature.

**The LOGIC slice is almost always reducible. Build the path.**

| Move | Worked example |
|---|---|
| One-shot local invocation of the script's body | Run the tick by hand on environment 1 rather than waiting for its schedule; only *registration* is deploy-bound, the *behavior* is not |
| A `--dry-run` / `--once` flag | `bin/sim-recap-tick` already ships `--dry-run --sim=N`, which touches no queue row and performs no DB write — copy that shape instead of inventing one |
| Record-and-replay over a genuinely captured response | Replaces a hand-written double with a real payload |
| Scoped live smoke against a **test** channel / endpoint | Crosses the real boundary without touching prod traffic |
| `workflow_dispatch` / `workflow_call` on the PR branch | Environment 2 — `.github/workflows/pr-canary.yml` and `.github/workflows/deploy-rehearsal.yml` both already do this |
| Prod-clone dry-run via `.github/workflows/deploy-rehearsal.yml` | Environment 3 — for the migration half |
| DatabaseIntegration test | For the schema/state effects the tick produces |

**Only the SCHEDULING slice and the NETWORK/CREDENTIAL boundary are intrinsic.** Two things genuinely resist every row above: launchd/cron **actually firing on the prod box on its schedule** (`bin/sim-recap-cron-setup`, `bin/bug-pipeline-cron-setup`), and a boundary reachable only from prod (a prod-tailnet-only endpoint, a secret that exists only in the prod environment).

**A plan may claim `pre-prod-exception:` for the scheduling / reachability slice ONLY, and must still build the pre-prod path for the logic slice.** "The daemon can't run on my laptop" is a claim about *registration*, not about the code the daemon runs; a blanket exception over a whole tick feature is exactly the failure this section exists to stop. A surviving intrinsic slice is a **recorded exception** — `/plan` Step 4 gate 16 requires the `pre-prod-exception:` marker plus a `## Pre-prod Exception Justification` entry naming its category (scheduling / reachability / credential) — never a silent move to a non-gating note.

**Stub-only coverage is NOT a pre-prod exercise path.** A stub, mock, fake, or hand-written double asserts that *your caller called the stub*. It cannot assert that the real service is reachable, authenticated, or shaped as assumed. The worked negative example is in-repo: `bin/lib/bug-pipeline-test-stubs.sh` and its `STUB_CREATE_THREAD_FAIL` toggle give thorough, genuinely valuable coverage of the pipeline's branching — and prove nothing about whether a Discord thread is actually created. For this rule, behavior covered only that way is **UNCOVERED**, and the matrix will look green while the gap ships. The real path for that class is the scoped live smoke against a test channel/endpoint, or record-and-replay over a captured response — rows 3 and 4 of the LOGIC table above.

**Split the PR** when even that is awkward: land the exercisable mechanism with its rows now, land the registration separately.

**A CI-run check must be run by a job THIS PR's own diff actually triggers.** Naming a `bin/check-*` / `bin/test-*` script in a matrix row proves the script exists, not that CI runs it: `.github/workflows/tests.yml`'s `changes` job path-filters split producer from consumer — `harness-tests` is gated on `shell` (`bin/**`), `db-integration` on `src` (`**.php`) — so a PHP-only PR runs zero harness tests and a `bin/`-only PR runs zero DB tests. When a plan's matrix cites a CI-run check, the plan must confirm its own changed-file set matches the `changes:` filter of the job that runs it, and otherwise add a phase that adds the path to that filter or moves the check to an always-run job. This is `/plan` Step 4 gate 16 failure shape (d): Opus judgment, deliberately not mechanized, because `bin/check-plan` parses no workflow YAML.

**The anti-abuse guard takes precedence over all of the above.** A subjective UI/UX judgment is *always* exercisable on environment 1 — bring the worktree up and look. It therefore can never qualify as intrinsic, and "can't do it pre-merge" is never a route out of a forced UI/UX row under § Forced manual-verification trigger and `/plan` Step 4 gate 14a/14d.

### Counter-examples

Minimal section — specific counter-examples are embedded inline in the rules themselves (PR #887 for seed grounding, PR #1067 for forced manual rows, PR #1753 for required test methods).

### HTTP response shape changes

When an endpoint was already covered by E2E response-body assertions, those assertions become stale on the new shape; PHPUnit does not test the browser-observed response chain. The plan must audit all existing E2E tests asserting against that endpoint's response body and update any whose expected value depended on the old shape.

### Required Test Methods — parser mechanics

The `## Required Test Methods` requirement is enforced by a name-match, not a semantic one:
`/post-plan` Phase 5.0 greps the diff body for `function <name>` or `def <name>`, so a
declared method whose shipped name differs by a character reads as absent.

Fenced examples are stripped before the grep runs — `bin/lib/critical-files.sh` performs the
strip for bash callers, `harness/planfile.py`'s `_strip_fenced` for python callers. A method
named only inside a fence therefore never counts as declared.

A phase that ships no new test method needs no entry — for example, one that re-runs an
existing suite as a characterization check.
