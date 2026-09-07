---
description: Read-on-demand detail for _architect-contract.md — incident callbacks, counter-examples, procedure elaboration, and taxonomy rationale moved from the rules spine. The plan-architect never reads it; load only when editing the contract.
last_verified: 2026-09-07
---

Read-on-demand companion to `_architect-contract.md` (the plan-architect's output contract). This file holds the incident callbacks, counter-examples, procedure elaboration, and extended rationale for each operative rule in the spine. The plan-architect never reads it — the spine's pointer lines name the specific section to open when editing the contract.

## What the plan MUST produce

### Why edit anchors are quoted, not summarized

The edit anchor rule is a **correctness / disambiguation** aid, not a token optimization. The impl agent already greps-then-slices and never reads a whole file to locate an edit, so anchors reduce ambiguity, not tokens. Do not present them as a token optimization: a framing that says "quote the anchor to save tokens" invites the impl agent to skip it when it believes the edit site is obvious — which is the exact moment a first-try Edit failure is most likely. The mandate is disambiguation, and it holds regardless of how obvious the site looks.

### Why negative-path rows name the mutation they catch

An assertion whose catching mutation you cannot state is vacuous: it survives a rewrite that guts the behavior it claims to pin. A later remediation pass can drop or hollow it without any check going red — the test stays green through the exact regression it was written to catch. Naming the mutation converts the row from a documentation claim into a mechanical predicate: a concrete statement that anyone can independently check remains true after the next refactor.

### Mutation-statement counter-examples

**Compliant:** "asserts the roster count is 25 — replacing `count($roster)` with `0` makes this assertion fail."

**Non-compliant:** "asserts roster totals are correct" — no mutation named, so nothing distinguishes a real behavioral assertion from a static source grep that is green from birth and stays green through the exact regression it was written to catch.

Write the mutation into the row's **How** cell as a clause on the command, so an implementer cannot substitute a text grep without leaving the stated mutation unmet, and a later reviewer inherits a stated predicate instead of having to re-derive the row's intent. If you cannot state the catching mutation, the row is not yet an assertion — rewrite it until you can.

### Critical Files parsing — counter-examples

The `(conditional)` marker exempts an entry from the MISSING-FILE check, but only when `conditional` *opens* the parenthesized group immediately followed by `)` or a separator (`—`/`–`/`-`/`:`/`;`/`,`):

- `(conditional — Phase 4 only)` → EXEMPT
- `(conditional Phase 4 only)` → MUST_APPEAR (write the dash form to exempt)

A keyword in surrounding prose does **not** exempt — only the parenthesized group signals "this is a marker, not a description." Canonical markers: `(reference)`, `(read-only)`, `(read-only reference)`, `(verify)`, `(verification)`, `(template)`, `(no-edit)`, `(no-change)`, `(unchanged)`, `(context)`, `(conditional)`. All tokens match as **whole words** — `(filename references the affected class)` and `(the referenced file is deleted)` are **not** exempt because `references`/`referenced` are not the token `reference`. An explanatory tail inside the same parens is allowed for the non-`conditional` tokens — e.g. `(reference — pattern to mirror)`.

A bare path OR a path you annotate with a change-description is still checked; only the reference marker exempts. The `## Critical Files` section must be in **list form** (`- \`path\` (annotation)`); a markdown-table section is rejected by `bin/check-plan` gate `[F]` because a table parses to zero entries and silently voids the whole conformance check.

### Phase-count guard — HTML comment mechanism

The orchestrator persists the turn-1 outline into the draft as an HTML comment. The numbered `Phase <N>` or `Step <N>` items in it **fix the plan's phase count for the remainder of the run**. A title that omits the `Phase <N>:`/`Step <N>:` prefix is not counted and silently disables the guard. There is no mid-run outline-revision escape hatch: the count is enforced mechanically by the orchestrator via `bin/check-plan --draft` before Step 5 finalize, and an excess heading is deleted or terminates the run.

### Why blockquotes are not a staleness-guard exemption

The staleness guard (`bin/check-plan-staleness`) consults cues for paths already missing on disk. A `>` blockquote is **not** a fourth safe position: quoted prose is scanned exactly like body prose, so a "Path correction" / aside callout naming a path that does not exist still reads as a vanished dependency and skips the run. This is what skipped the `e2e-axis-c-weak-assertions` plan — the plan used a blockquote to reference the path, and the guard read it as a vanished dependency. Inside a quote, use (a) (a fence works: `` > ``` ``), (b), or (c), or reword so the path is not a standalone backtick token.

A path the plan cites only to state it is **absent** — "`x/y.md` does not exist", "never existed" — is exempt via the same same-line cue mechanism as (a): the guard only consults cues for paths already missing on disk, so an absence assertion always matches reality. The `discord-pipeline-1` false positive was a `## Out of Scope` note naming a script a later PR creates — that heading is exempted as a whole section (position (c)), so the path was safe, not a skip signal.

### The seam-address story

Read the seam's address from the **serving code**, never from the shared-context artifact. The URL prefix, file path, or CLI flag your acceptance test targets MUST be confirmed at implementation time against the code that serves it — `ibl5/.htaccess` plus `ibl5/router.php` for an HTTP route, the plist template for a launchd job, the script's own argument parsing for a CLI flag. A canonical-looking table in a shared-context doc is prose, and prose drifts.

Observed incident (`ibl5/IBLbot/src/bug-bot/php-client.test.ts:37`): a shared context described the endpoints as `/api/bug-pipeline/` while the served prefix was `ibl5/api/v1/bug-pipeline/`. A unit test with a mocked `baseUrl` asserted the wrong prefix green — passing test, wrong seam. An acceptance test built from the doc faithfully reproduced the doc's bug.

### Why bin/check-plan is off-limits to the architect

Gates `[13]` (`impl_model` consistency, via `bin/lib/plan-model-consistency`), `[H]` (hold justification), `[T]` (tier binding, via `bin/lib/plan-impl-model`) and `[S]` (Sonnet-recipe completeness) all read the frontmatter at `NR==1`. A draft's first line is always `## <section title>` — never the `---` block, which the architect cannot write (the orchestrator writes line-1 at Step 5 assembly). So every violation those gates report against a draft is unreachable from where the architect sits: no edit permitted to the architect can clear them. Re-running to chase them is an unbounded loop that burns the run without ever converging.

The prohibition is on *invoking the script*, not on the gates themselves: `[C]` (size budget), `[F]` (list-form `## Critical Files`), `[8]` (creation phrasing), and `[T]` (tier lines) remain **authoring constraints you satisfy while writing** — self-verify them by reading what you wrote.

### Pre-prod exercise path — slice elaboration

When a behavior looks untestable pre-prod, **split it by slice before conceding**:

- **Logic slice**: what the artifact does when invoked — almost always exercisable on the worktree stack through a one-shot seam this plan adds if it does not exist. `bin/sim-recap-tick`'s `--dry-run --sim=N` is the shape to copy.
- **Scheduling slice**: launchd/cron actually firing on the prod box at the configured interval — intrinsic.
- **Network / credential boundary**: the real endpoint reached with the real token — intrinsic.

"The daemon can't run on my laptop" is a claim about *registration*, not about the code the daemon runs; a blanket exception spanning a whole tick feature is precisely the failure this bullet exists to stop. A slice that genuinely survives the split is a **recorded exception, not a deletion**: a line-anchored `<!-- pre-prod-exception: <slice + why> -->` marker plus a matching `## Pre-prod Exception Justification` entry naming its category (scheduling / reachability / credential).

Silence is not coverage, and "we'll see once it's live" is not a plan. Dissolving a post-merge-only row means **building the pre-prod exercise path** — never **deleting the row**.

## Conditional sections

### Backlog — table-status format

For a **table-status** backlog (`maintenance-backlog.md`), the status-update step is **not** a glyph swap. The step must:

1. **Remove** the `| <id> | ⬜ Open | … |` row entirely from the per-axis table.
2. Add `<id>` to that axis's `> ✅ resolved (N): …` (or `> 🚫 declined (N): …`) blockquote above the table header and **increment its `(N)`**.
3. Move the row's `Evidence / note` cell **verbatim** into `archive/maintenance-backlog-archive.md` under `### <id>` as `**Table evidence (YYYY-MM-DD):** <cell>`.

Quote both the row and the axis's summary line as edit anchors. A resolved row left in a per-axis table fails `bin/check-docs` (`checkMaintenanceResolved`).

Add the tracking doc and the sibling archive to **Critical Files** as change targets — a bare path or a change-description, **never** a `(reference)` marker, since the doc IS edited.

Quote the exact current table row / status line as the edit anchor — find it by running `bin/backlog-open <doc-path>` and Reading its output (redirect to a temp file first for `maintenance-backlog.md`, ~93 KB filtered); the filter emits table rows byte-identically, so a row quoted from its output matches the source file exactly. Fall back to reading the doc directly if the filter errors or the doc is not one of the 8 LIVE backlogs.

### Post-merge — watcher setup procedure

When the trigger is the merge event itself, write a small trigger script, then arm a detached launchd job (the established pattern — see `bin/post-plan-now`) that polls for the merge and runs the script.

Two correctness traps the watcher MUST handle:

1. **Resolve the PR number once from the branch, then poll by number** (`gh pr view <#> --json state,mergedAt`) — never keep polling `--head <branch>`, because branch-auto-delete-on-merge makes the merge invisible and the watcher waits forever.
2. **Self-teardown on *either* terminal state** — fire the script and unload on `MERGED`, but on `CLOSED` (closed without merging) unload *without* running the script.

Artifacts live **outside** the repo (`~/.claude/…`, launchd plists) so they are worktree-exempt. Name the trigger script, the poll command, and the self-teardown in the plan. The objective is zero reliance on the user's memory/attention to execute a crucial post-merge step.

### Why the UI/UX hold is NOT the pre-prod gate

The Truly-manual UI/UX row plus `auto_merge: false` (Step 4 gate 14d) remains exactly the right output for a look-and-feel judgment. Do **not** collapse it with Step 4 gate 16, which asks a different question — does deploy-dependent *behavior* carry a pre-prod-exercisable row at all — and which never licenses dropping the row 14d forces.

If the judgment can only be rendered after *this PR's own* artifact is live on prod (a CI-deployed file, an applied migration, a registered daemon), the default is to **build the pre-prod exercise path and keep the row gating**: phrase it to run against the worktree Docker stack, CI, or the prod-clone `.github/workflows/deploy-rehearsal.yml` rehearsal. Recording it instead under a `## Post-merge verification` PR-body note plus a mechanized follow-up is a **narrow, marker-recorded exception** — requires `<!-- pre-prod-exception: ... -->` plus `## Pre-prod Exception Justification`, and may cover only the slice that is genuinely intrinsic. Dissolving a post-merge-only row means **building** the pre-prod path, never **deleting** the row — and never as a way to shed the forced UI/UX hold.

### Pre-prod testability — distinct from Verification-gap

**Verification-gap mechanization** fires on a *silent* correctness property needing a mechanical self-check. **Pre-prod testability mechanization** fires on *deploy-dependent* behavior lacking any pre-prod-exercisable row. They overlap often — when both apply, one well-scoped phase can satisfy both; say so rather than writing two phases.

**Stub-only coverage is not a pre-prod exercise path**: a suite that drives only stubs (e.g. `STUB_CREATE_THREAD_FAIL`) proves the *caller's* branch handling, never that the real call shape works. The full worked catalogue — per-artifact-class rows with their reducible/intrinsic disposals — lives in `_plan-verification.md` § Pre-prod exercise paths.

### Schema-safety — guard mechanics

The apply-time fail-closed guard MUST raise a real SQL **error**, not a warning. The runner is `mysqli::multi_query`, which halts the batch (skipping the `ALTER`) only on an erroring statement, and prod `sql_mode` is non-strict — so use a mode-independent idiom:

```sql
SELECT IF(<violation-condition>, (SELECT 1 UNION SELECT 2), 0)
```

The true branch forces ERROR 1242. **Never use** `CAST(... AS SIGNED)` or division-by-zero — these only warn under non-strict mode and do not halt the batch on prod.

The forward-bound assertion (requirement 2) is the *only* thing preventing future violations, since non-strict prod truncates rather than rejects an over-length write at runtime.

The **DatabaseIntegration test** must run under a session `sql_mode` matching prod's:

```sql
SET SESSION sql_mode = ''
```

Local/CI MariaDB is strict-by-default. A test left in strict mode would green-light a strict-only idiom (e.g. `CAST(... AS SIGNED)`) that merely *warns* on non-strict prod — passing test, unguarded prod. Under prod's mode, only the mode-independent erroring idiom from the guard passes. The DatabaseIntegration test is required-blocking on any migration PR, so a red guard-abort test holds the merge.

This applies **only** when the target shape is chosen independently of prod data; a migration whose *shape* depends on unreadable prod data is genuinely design-data-blocked and stays held under gate 14(c).

### Security-surface — dischargeable taxonomy

**Dischargeable examples** — the invariant is a structural property of the code:
- A **state-free extraction**: a collaborator moved out that holds no DB handle and no actor/session identity.
- A **pure-function move**: logic relocated with no new I/O and no authorization decision.
- A **validator that never sees actor identity**: shape/range/enum checks over request parameters only.

**Irreducible examples** — the hold stands:
- A check that genuinely *carries* actor identity (`$loggedInTeamID`, session, current user).
- Moving or rewriting an **authorization decision** — per-site judgment, not a structural property.
- A **new POST/form endpoint** (CSRF surface whose correctness is a live request/response property).
- A **new SQL construction site**.
- **New user-facing output rendering**.

Anything that does not clearly match a dischargeable shape is irreducible: **ambiguity resolves to held.**

**Correctness trap — the test must live in a registered `<testsuite>` directory.** A test file dropped in an unregistered directory never runs, so the "Tests and Analysis" required gate stays green while the invariant is unasserted — a passing PR with an unguarded surface. If the target directory is not registered in `ibl5/phpunit.xml`, registering it is part of the phase.

The **reflection assertion** shape: in the `*Test.php` file, `$r = new \ReflectionClass(Foo::class); self::assertSame([], array_values(array_filter($r->getProperties(), static fn(\ReflectionProperty $p): bool => in_array(strtolower($p->getName()), ['db','mysqli','conn','connection','loggedinteamid','teamid','session','user','userid'], true))), 'stateless collaborator must hold no DB handle and no actor identity');` plus `$c = $r->getConstructor(); self::assertTrue($c === null || $c->getNumberOfParameters() === 0);`.

A named-but-unasserted invariant, a "stateless by inspection" note, or a reviewer's word discharges nothing; there is no prose-only path.

A discharge never replaces a defense — the **Security** bullet (SQL prepared statements, `CsrfGuard`, etc.) is required regardless.

## Mid-design exploration

`plan-architect` and `plan-architect-xhigh` have the `Agent` tool. `plan-architect-sonnet` does not, and must not be given it — a recipe-backed task by definition has no unresolved mid-design question, and the Sonnet architect is chosen precisely when the design is already known.

**Why the grant exists.** Before it, an unknown that surfaced *during* design could not be explored at all: the orchestrator's Step-2 fan-out is spent before the architect starts, so the architect either guessed or the orchestrator had to have guessed the architect's needs in advance. Both produce plans with soft spots the architect could see but not close.

**What it is not.** It is not a licence to delegate reading. A direct `Read`/`Grep` beats a ~3–5K-token spawn; an architect-side spawn must clear `.claude/rules/agent-tiering-detail.md` § Skip the Agent on its own merits, the same bar the orchestrator's Step-2 spawns clear.

**Budget — per actor, not per run.** The `/plan` Step-2 cap (≤2 orchestrator agents; never 3) is unchanged. The architect gets **≤1 `Explore` spawn per architect invocation**, on top of it. Run-wide ceiling: **3** (2 orchestrator + 1 architect). A per-actor cap needs no shared counter and no cross-actor bookkeeping, and 1 rather than 2 keeps the ceiling one above today's rather than doubling it — the grant's justification is *a* question that surfaced mid-design, singular.

**The bound is advisory.** Claude Code agent frontmatter offers `disallowedTools` (a denylist) and no positive allowlist, so "`Explore` only" cannot be expressed in the def. A `PreToolUse` hook on `Agent` sees only `tool_input.subagent_type` and `tool_input.model` — never the spawner's identity — so it cannot scope a rule to architect-initiated spawns. One component *is* mechanically enforced: `~/.claude/hooks/explore-model-gate.sh` pins any `Explore` spawn to `haiku`-or-omitted regardless of spawner. Everything else — `Explore` only, ≤1, foreground — rests on the def body and **is enforced by code review**. Treat a diff that widens it as a security-surface change.

## Delegation packets — delegation economics

For a phase that is **genuinely verbose or parallelizable**, delegate the **whole phase loop including its own verify/regen/fixup** — the sub-agent's tool output then accumulates in *its* context and returns as one summary, keeping the orchestrator's per-turn context flat. The win is **context localization** (the orchestrator stops re-reading a growing transcript every turn) — not a flat cost-percentage. Reserve packets for phases whose moved work clearly exceeds a sub-agent's fixed startup (~15K tokens); a packet for one tiny edit costs more than it saves, so keep small phases inline.

This does **not** regress the ~15K economics: tiny sub-tier phases still stay inline because a sub-agent's fixed startup exceeds the work a one/two-edit phase moves. The rule changes only that such a phase is now *labeled* `(inline — …)` instead of left bare — zero new delegation is forced, only an explicit decision. The force applies solely to below-run-model phases whose moved work *already* clears ~15K, which the doctrine *already* says should be packets; the rule makes that latent "should" mechanically enforced.
