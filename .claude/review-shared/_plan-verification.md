---
description: Requires plans to classify every verification step into the test-type taxonomy at plan-write time, preventing manual-testing items from deferring to post-plan cleanup, and grounds seed/DOM-dependent E2E assertions in real fixtures.
last_verified: 2026-09-05
---

# Plan Verification Matrix

Shared include — read by `/plan` (Step 1), `/post-plan`, and `_test-spec-corpus`. Not an
always-loaded rule; `/plan` reads it on invocation.

Every plan must include a **Verification Matrix** — a table classifying each verification item at plan-write time. No verification step may be deferred as "manual" or left unclassified.

## Required format

Each implementation phase that changes behavior must have a corresponding row (or rows) in the matrix. Place the matrix after the implementation phases, before any "Out of Scope" section.

```
| # | What to verify | Test type | Timing | Test file / location |
|---|---------------|-----------|--------|---------------------|
| 1 | Example: salary cap calculation rejects over-cap trades | PHPUnit | pre-impl (characterization) | tests/Trade/TradeValidatorTest.php |
| 2 | Example: form submits and redirects | E2E | post-impl | e2e/trades/submit-trade.spec.ts |
```

### Test type — exactly one of:

| Test type | When to use |
|-----------|-------------|
| **PHPUnit** | DB state, service output, calculation, validation logic |
| **API-test** | HTTP request/response (endpoint returns correct JSON/HTML, status codes, headers) |
| **E2E** | Browser interaction (form submit, page navigation, HTMX swap, DOM state) |
| **Visual-regression** | "Does output still match?" / production comparison where UI/UX was NOT intentionally redesigned |
| **CLI-executable** | A command Claude can run directly during implementation (curl, bin/db-query) — not a test, but a one-shot verification |
| **Truly-manual** | Requires subjective human judgment on **new or redesigned** UI/UX ("does this look/feel good?", "does this new flow work well?"). **Forced** — see § Forced manual-verification trigger — whenever the plan introduces new/redesigned UI/UX; not optional. |

### Timing — exactly one of:

| Timing | When to use |
|--------|-------------|
| **pre-impl** | Characterization test locking in current behavior before modifying it (refactors, behavior changes, signature changes, shared infrastructure) |
| **post-impl** | New method/class, new UI, additive behavior |

### Classification rules

- "Verify X returns Y", "check that Z happens", "confirm the redirect works" → automatable. Never classify as truly-manual.
- "Compare against production" / "does output still match iblhoops.net?" → **visual-regression** (screenshot diff), not truly-manual — unless UI/UX was intentionally redesigned.
- If nothing in UI/UX changed, visual regression covers it. Do not classify as truly-manual.
- The **only** truly-manual items are subjective judgment on **new or redesigned** UI/UX.
- "I can't tell mechanically whether it works" (a silent/integration-only failure mode, an observe-in-prod property) is a **verification gap, not a truly-manual item** → build the self-asserting check (`/plan` autonomy lever 3 / Step 3 § Verification-gap mechanization), do not classify as truly-manual or hold the merge.
- **A truly-manual row must be performable on the *open PR*, before it merges** (orthogonal to the Timing column — pre-/post-impl is when a test is *written*, not when it can be *performed*). The test is *"can a reviewer render this row's judgment now, against the worktree/local stack?"* — never "does a command exit 0." A UI/UX taste judgment **always** qualifies (bring the worktree up and look), so **never** cite "can't do it pre-merge" to drop a forced UI/UX row — that would defeat the § Forced manual-verification trigger + `/plan` Step 4 gate 14a. When a judgment *appears* to depend on **this PR's own artifact being live on prod** — a file CI deploys on merge, a migration having run on prod, a registered daemon/cron (e.g. a recap whose quality can't be judged until the queue script this PR adds is deployed and reachable) — that is **not** a licence to move the row out of the merge gate. It is the trigger for § Pre-prod exercise paths below: **design an exercise path on one of the three reachable pre-prod environments, and keep the row.** Only an *intrinsic* deploy-dependency that survives that challenge may be recorded as a non-gating `## Post-merge verification` note in the PR body plus a follow-up — and that disposal is a **narrow, marker-recorded exception, never the default**: `/plan` Step 4 gate 16 and `bin/check-plan` gate `[P]` require a `pre-prod-exception:` marker plus a matching `## Pre-prod Exception Justification` entry naming the intrinsic category. Emptying `## Manual Testing` this way can arm auto-merge, so the removal is a deliberate call — valid only for an intrinsic deploy-dependency, never to shed a subjective UI/UX hold.
- **Deploy-dependent behavior needs a row at all — an absent row is the violation.** Silence is not coverage. If a plan introduces behavior that only manifests once something is deployed (a file CI ships on merge, an applied migration, a registered daemon/cron, a scheduled workflow), it must carry at least one Verification Matrix row for that behavior, and the row must name which pre-prod environment exercises it — see § Pre-prod exercise paths.
- If a plan has zero truly-manual items, state: `All verification is automated — no manual testing needed.`

### Pre-prod exercise paths

**Operative definition — "pre-prod" is not an abstraction here.** There is no staging web environment in this repo. Pre-prod is exactly these three reachable environments, and a verification item is **pre-prod-exercisable** if and only if it can be run on at least one of them:

| # | Environment | How to reach it | What it exercises |
|---|-------------|-----------------|-------------------|
| 1 | **Worktree Docker stack** | `<slug>.localhost` under `/ibl5/`; slug = `basename "$(git rev-parse --show-toplevel)"` — see `.claude/rules/worktree-hostname.md` | Anything the running app does: rendered pages, HTMX swaps, endpoints, DB state — and any script's body invoked by hand rather than on its schedule |
| 2 | **CI** | ubuntu runners on `pull_request`, seeded from `ibl5/tests/e2e/fixtures/ci-seed.sql` | PHPUnit, API-tests, E2E, every `bin/check-*` gate — and, via `workflow_dispatch` / `workflow_call`, a new workflow's own body run from the PR branch before it is ever a merge-triggered job |
| 3 | **`.github/workflows/deploy-rehearsal.yml`** | already runs on `pull_request` (ADR-0059) | Pending migrations dry-run against a **clone of production** — the worked precedent that "needs prod" is usually "needs prod-*shaped* data" |

"Exercisable on one of these three" is checkable prose; "testable pre-prod" is not. Cite the number.

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

### Weave tests inline

Pre-implementation tests go **before** their corresponding implementation step. Post-implementation tests go **immediately after**. Never collect all tests into a separate appendix at the bottom.

## Required Test Methods

A plan whose Verification Matrix carries **≥1 PHPUnit row** MUST also carry a `## Required Test Methods` section — a markdown list of the exact test-method names the implementation must ship, one bare name per list item:

```
## Required Test Methods
- `testTradedPlayerAttributedToFromTeamId`
- `test_required_methods_ignores_fenced_example`
```

The matrix's "Test file / location" column pins only a **path**, and `/post-plan` Phase 5.0 greps `git diff --name-only` for that path — so an implementation that writes the right *file* with different, weaker tests satisfies every row. That is how PR #1753 shipped five substituted tests with an all-green matrix. This section is what makes conformance **row-level**.

- **The name must match the shipped declaration exactly.** Phase 5.0 greps the diff **body** for `function <name>` or `def <name>` and otherwise emits `MISSING-METHOD: <name>`. Write the bare method name — no class prefix, no `()`, no `::`.
- **Fenced examples do not count.** Both parsers strip fenced blocks width-aware before reading the section (`bin/lib/critical-files.sh` for bash, `harness/planfile.py`'s `_strip_fenced` for python), so an illustrative list inside a fence yields zero entries. A fence-blind parse would instead harvest example names and strand the PR on an unresolvable `MISSING-METHOD:`.
- **Escape hatch.** When every PHPUnit row genuinely names no new method (e.g. it re-runs an existing suite as a characterization check), write `<!-- no-test-methods: <reason ≥15 chars> -->` instead. `bin/check-plan` gate `[M]` accepts the section or the marker.

## Forced E2E triggers

These patterns **require** at least one E2E row in the verification matrix, even when PHPUnit covers the underlying logic. Unit tests verify isolated behavior; only E2E confirms the behavior composes into a rendered page.

| Trigger pattern | Why PHPUnit is insufficient |
|-----------------|----------------------------|
| New POST/form endpoint | Form submission, CSRF, redirect, and resulting page state are browser-only |
| New conditional UI gated by session, cookie, or user identity | Session/cookie hydration and DOM presence depend on the full request lifecycle |
| New navigation entry or menu item | Rendering is composed through `NavigationMenuBuilder` → theme → DOM; unit-testing the builder alone misses integration |
| New HTML route (module `index.php`) | The route may render, redirect, or error — only a browser visit confirms which |
| New `<details>`, modal, toggle, or expandable section | Expand/collapse, visibility toggling, and content rendering are DOM interactions |
| New indicator or status element that changes with state | Visual state feedback (dots, badges, labels) must be verified in-browser across both states |
| Plan adds or modifies `htmx:beforeRequest` / `htmx:afterRequest` handlers that mutate DOM state (element disabled/enabled, text changed) | These mutations are serialized into the htmx history cache between the before-request event and the swap; browser Back restores the request-time snapshot, making the mutation permanent unless a `historyRestore` handler repairs it — only a real browser navigation catches this (see `.claude/rules/htmx-history-cache.md`) |

When a plan introduces any of these patterns, the planner must add a corresponding E2E row — one row per distinct user-visible state. For example, a toggle that shows/hides UI needs two E2E rows: one verifying the ON state, one verifying OFF.

If E2E coverage is blocked by a missing test fixture (e.g., no CI seed user for an admin-gated feature), the plan must include a phase that creates the fixture. "No fixture exists" is not a reason to downgrade to PHPUnit.

### Seed- and DOM-grounded E2E assertions

Any E2E verification-matrix row that asserts a **seed-** or **DOM-dependent** value — a row count, a dropdown/option value, sort order or direction, filter results, or "control X exists / is a `<select>` vs. radio" — must **cite its source**, never an assumed value. An assertion grounded in an imagined value is how PR #887 shipped: it expected a display-cap count (~500, full-league) while the CI seed has only ~24 career rows, so the test was deterministically red the moment it ran.

The source must be one of:

- A specific row or count from `ibl5/tests/e2e/fixtures/ci-seed.sql` (cite the table and the rows that produce the expected value), or
- The rendered form DOM, fetched live from the worktree stack: `curl --cookie "_auto_login=1" http://<slug>.localhost/ibl5/modules.php?name=X` (cite the element the assertion targets). The `_auto_login=1` cookie opts into dev auto-login — localhost is logged-out by default, so an auth-gated form returns the login page without it (see `.claude/rules/browser-login.md`).

Two gotchas this rule exists to catch (cross-referenced from memory):

- **Sort direction is not "ascending by default."** `ibl5/jslib/sorttable.js` sorts **descending** on first click. An assertion on first-click sort order must match that, not an assumed ascending order. See memory `reference_sorttable_descending_first`.
- **Seed cardinality is small.** The CI seed is a fixture, not production — counts, option lists, and "is the list non-empty" assertions must be grounded in what the seed actually contains. See memory `feedback_e2e_seed_grounding`.

## Forced manual-verification trigger (new or redesigned UI/UX)

When a plan introduces **new or redesigned user-visible UI/UX**, the matrix MUST include at least one **Truly-manual** row for the subjective look-and-feel + flow check — *in addition to* (never instead of) any E2E and Visual-regression rows. E2E asserts that elements are present and behave; Visual-regression pins pixels against an **existing** baseline. Neither can judge whether a *newly introduced* design looks right or a *new* multi-step flow feels right — that is the gap PR #1067 shipped through (a new notification bell, unread badge, CSS component, and mark-read flow, all classified E2E/visual with zero manual rows, then auto-merged).

A plan trips this trigger when it adds or restyles any of:

| Trigger | Example surface |
|---------|----------------|
| New or restyled CSS component / stylesheet | a file under `ibl5/design/`, a new `*.css`, a new component class |
| New rendered page or module | a new `ibl5/modules/*/index.php` route a user navigates to |
| New nav/menu entry, indicator, or badge | the nav bell + unread badge from #1067 |
| New multi-step or stateful user flow | mark-read / mark-all-read, a what-if sandbox, a wizard |

**Does NOT trip** (keep automouse autonomy for safe mechanical work): a non-visual refactor, a one-line CSS bugfix with no design change, a JSON/POST endpoint with no visual surface, or any change where **nothing the user sees is new or redesigned**. An *unchanged* UI is covered by Visual-regression alone — see the taxonomy's "If nothing in UI/UX changed" rule; this trigger fires only on genuinely new or redesigned surfaces.

### Phrasing the forced row (gate-3 safe)

The Truly-manual row is a **subjective** judgment, so phrase it as a question of taste — never with an automatable verb (`verify` / `check that` / `confirm` / `ensure`), which `bin/check-plan` gate 3 rejects as a mislabeled-automatable row. Copy this shape:

```
| # | What to verify | Test type | Timing | Test file / location |
|---|---------------|-----------|--------|---------------------|
| N | Does the new notification bell + inbox look right, and does the mark-read flow feel right? | Truly-manual | post-impl | manual (reviewer walkthrough) |
```

A plan that trips this trigger therefore **cannot** carry the "All verification is automated — no manual testing needed" line, and per `/plan` Step 4 gate 14 must set `auto_merge: false`.

## Forced integration-verification trigger

The taxonomy above classifies *how* a thing is verified; this table names five shapes of change whose verification is routinely written too weakly. If a plan matches a left-hand row, its Verification Matrix must carry a row asserting the right-hand property.

| If the plan... | it MUST carry a row that... |
|---|---|
| ships a component that calls another component over HTTP | exercises the **real URL**, with the path pinned to `file:line` in the **serving** codebase — never to the sibling plan |
| adds or changes a launchd plist, cron entry, or systemd unit | asserts against the **generated** unit, not a live install — a `--print-schedule`-style emitter (`bin/sim-recap-cron-setup:11,114`) piped to a grep for the supervisor-environment properties: PATH contains every binary the job invokes, no `$HOME`/`$VAR` left unexpanded, no `<string></string>` empty value |
| adds a CLI flag or a flag-parsing branch | asserts the **rejected** form fails loudly (unknown flag; space-form when `=` is required) |
| reads a new environment variable | asserts **unset** and **empty** separately, and states which is legal. An unset-with-a-safe-default var needs only the empty case; a required var must fail on both |
| writes a file or directory inside the repo tree | asserts it is gitignored, or explicitly declares it committed |
| adds or modifies an updater or importer that reads a generated file from a repo-relative path | asserts the source path is NOT git-tracked: `git ls-files --error-unmatch <path>` exits nonzero (untracked — expected); a negation line in .gitignore force-tracks the file so deploy git-reset clobbers live content with stale committed data |
| adds or modifies an escape path in a CI check gate that calls a git-range helper (`git log base..HEAD` or similar) | asserts the **empty-range** case: when no commits exist in the range (first commit on branch), the gate passes or fails gracefully without the escape path becoming permanently unreachable |
| adds or modifies an enqueue or requeue path in bin/automouse/queue | asserts that a plan file with an ancient mtime is placed AFTER all incumbents — ordering must be by insertion time, not by the plan file's authoring mtime; both symlink lstat mtime (GNU ls sort key) and target mtime (BSD ls sort key) must be strictly newer than the newest incumbent |
| removes or modifies an importer or upsert path that was writing an incorrect value to a column (fixing ongoing data corruption) | asserts that either (a) a compensating backfill migration ships in the same PR and is verified post-impl, or (b) the plan's Scope section explicitly states that already-corrupted rows are out of scope and names a follow-up plan or ticket |
| introduces or modifies a loop whose upper bound is sourced from user-controlled input (`$_GET`, request params) | asserts that an over-horizon input is **rejected before the loop begins** — a PHPUnit test showing that a maliciously large bound (e.g., `999999`) returns an empty or neutral result without iterating |
| adds or modifies salary-comparison or cap-enforcement logic in a service | asserts **both** the in-season path (`Season::advancesContractYears()=false`, `current_salary` basis) and the offseason path (`advancesContractYears()=true`, `next_year_salary` basis): verify the salary-lookup column actually changes between paths and that the cap outcome (accept/reject) is correct on each |
| relaxes a fail-closed guard on a store or import path (changes an error or rejection into a warning or no-op) | asserts that either (a) the compensating resolution path that prevents orphaned rows ships in the same PR and is verified post-impl, or (b) the plan's Scope section explicitly states that orphan accumulation is accepted and names a follow-up plan to address it |
| adds or modifies an importer that writes records to a secondary/audit table (e.g., a junction or tracking table alongside a primary entity table) | carries an integration test that reads the canonical flag column in the primary table after a complete import cycle — checking the secondary table alone does not prove the flag propagated; the primary column must be verified to have changed |
| adds or modifies a detection check in an audit class that has a fail-open guard conditioned on data availability (e.g., `$isEnabled = $dataIndex !== []`) | asserts the check fires even when the guard-controlling condition is false — a check nested inside a fail-open guard silently skips when data is unavailable, which must be verified to not affect unconditional detection paths |
| adds or modifies a `proc_open` call site in a PHP CLI script or class | asserts that `proc_close` is called and its return value checked (non-zero exit → error path), that any stderr pipe descriptor is fully drained before `proc_close` (unread pipe deadlocks the child once the OS buffer fills), and that command-line delimiters (e.g., `-z` NUL for `git check-ignore`) match the parser on the receiving end |
| adds or modifies a worktree batch-sync script that executes git operations on working branches | asserts at least one scenario where the target branch has already-published commits (`git push` has occurred), and verifies that the script does not rewrite local commit history — the existing SHA survives as an ancestor after reconcile, or the strategy is explicitly merge-safe |

The header intentionally differs from the two tables above (`| Trigger pattern | Why PHPUnit is insufficient |`, `| Trigger | Example surface |`): those map a trigger to a rationale or an example, while this one mandates a **row shape**. Do not harmonize the headers.

Rows in this table are almost always `CLI-executable` — a one-shot command, not a test file. `bin/check-plan` gate `[V]` enforces that such a row's "Test file / location" cell is a single runnable shell command (escape any pipe as `\|`), and `/post-plan` Phase 5.0 executes every `post-impl` one, so keep each cell fast and read-only.

## Hot-file thresholds

Files over **500 LOC** in `classes/` are considered hot. The current list is generated by `bin/check-hot-files`.

When a plan adds **> 100 LOC** to a hot file, the plan must EITHER:

- Propose an extraction (Service/Repository/Helper) in the implementation steps, OR
- Justify the addition inline: state why extraction is premature (single-purpose
  growth, no natural seam, etc.).

If the plan proposes no extraction and no justification, `bin/check-hot-files`
flags the PR (advisory comment, non-blocking).

This rule is advisory — gated growth is acceptable when justified. The goal is
to force a structural conversation at plan-write time, not to enforce a hard
ceiling.

## Decision-trigger pre-classification

`bin/adr-check` fires on PRs that add files matching specific trigger patterns. When a plan phase adds any of these, the plan must pre-decide the resolution:

| Trigger | File pattern |
|---------|-------------|
| PHPStan rule | `ibl5/phpstan-rules/*.php` |
| Agent rule | `.claude/rules/*.md` |
| CI workflow | `.github/workflows/*.yml` |
| Destructive migration | Migration SQL containing `DROP TABLE`, `DROP COLUMN`, or `DROP INDEX` |
| Tool script | `bin/*` (new file, ≥50 lines) |
| New dependency | New entry in `ibl5/composer.json` `require` or `require-dev` |

**Resolution — exactly one of:**

- **ADR:** Add an implementation step to write an ADR under `ibl5/docs/decisions/`. Use when the change introduces a genuinely new architectural constraint not covered by an existing ADR.
- **Bypass:** Add an implementation step to include `<!-- no-adr: reason at least 15 characters -->` in the PR body. Use when the decision is already captured in an existing ADR (e.g., new PHPStan rules enforcing ADR-0001's architecture split).

If the plan has no phases adding trigger-pattern files, no action is needed.

## What the plan must NOT do

- List "verify manually" or "check by hand" for any item that can be asserted by PHPUnit, an API test, E2E, or visual-regression.
- Defer test classification to post-plan Phase 6. Phase 6 is a safety net, not the primary classification point.
- Add a standalone "Testing" or "Verification" section with prose descriptions instead of the matrix.
- Use "run X and check Y" without specifying the test type and file path.
