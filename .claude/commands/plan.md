---
description: "Plan an implementation task: enforces a verification matrix, directs code reuse, flags security surfaces, and requires negative-path tests so plans drive clean, secure, well-tested implementations."
disallowed-tools:
  - EnterPlanMode
  - ExitPlanMode
last_verified: 2026-06-27

---

# /plan — Implementation Planning with Verification Matrix

You are planning an implementation task. The user's request follows this skill's instructions as `$ARGUMENTS`.

**Do NOT write or edit any code files.** This skill produces a plan only. The output is one plan file per PR.

**One plan = one PR.** A single plan file must be implementable and mergeable as exactly one pull request. If the work naturally spans multiple PRs (independent concerns, separate review surfaces, a refactor that should land before the feature that uses it, or a change too large to review in one sitting), do NOT bundle them. Split the work into PR-sized units and produce a separate, fully self-contained plan — its own implementation steps and its own Verification Matrix — for each one. The user should never have to ask for this split.

**Maximize automouse autonomy.** A plan is most valuable when automouse can implement *and merge* it with no human in the loop. Three levers turn a would-be-supervised plan autonomous — apply them by default instead of reaching for `auto_merge: false`:

1. **Pin, don't supervise.** Uncovered code is not a reason to hold the merge — it's a reason to add a **Phase-1 characterization pin** (Step 4 gate 11). A behavior-preserving refactor with a pin is green-green; the pin *is* the coverage.
2. **Decide at plan-time, not merge-time.** A single discrete design fork doesn't have to wait for a human at merge — surface it to the user *now* (Step 3.5) and record the answer in the plan; automouse then executes a fully-specified decision.
3. **Mechanize the check, don't watch it.** When the *reason* to want a human at merge is "we can't tell mechanically whether it works" — a silent or integration-only failure mode, an "observe in prod" property, or a reflex to have a human confirm it behaves — that is a missing-verification problem, not irreducible judgment. Design the self-asserting check as an implementation phase: a required CI job that fails *loudly* on the bad outcome, a loud-failure signal replacing a silent fallback, an independent invariant assertion on the real artifact, or a shadow/burn-in rollout that enforces the invariant before flipping. This extends to a **reversible schema tightening** (a `varchar` narrowing, a length / `NOT NULL` constraint): an apply-time fail-closed guard that runs on the *target* DB self-gates the migration on prod at deploy, so "needs prod data unreachable from CI" becomes a guard to build — which *releases* the gate-14(c) hold once present and test-proven — rather than a permanent hold (see Step 3 § Schema-safety mechanization). Let auto-merge arm once the check exists; reach for `auto_merge: false` only after the check genuinely can't be built (see Step 3 § Verification-gap mechanization, Step 4 gate 15).

Fall back to `auto_merge: false` only when the judgment is **irreducible**: *distributed* across the implementation (per-site security verdicts, per-test correctness on untested code) or *data-blocked* (needs production data unreachable from CI). "A decision exists" is not a hold reason; "the decision can't be made once, up-front" is; and "I can't verify it works without watching it" is a verification gap to close with lever 3, not a hold reason. The flag holds only the **merge** — post-plan still runs and opens the PR for review either way.

## Step 1: Verification rule

Read `.claude/commands/_plan-verification.md` and use its full content as `$VERIFICATION_RULE` for injection into the Plan agent prompt in Step 3. Do not summarize or paraphrase the rule.

## Step 2: Orient on the codebase

**Prefer direct tool calls over Explore agents.** Most orientation can be done without spawning an agent:

1. Read `.claude/rules/codebase-map.md` to identify affected modules and their file locations
2. Run targeted `grep`/`find` via Bash for specific symbols, callers, or file paths
3. Read key files directly (migrations, interfaces, existing tests)

**Only spawn an Explore agent when** direct lookups leave unanswered questions. Tier per `.claude/rules/agent-tiering.md`:

- Single-module change → 0 agents (direct tools suffice) or 1 Haiku for enumeration
- Spans 2+ modules → up to 2 agents (Sonnet for cross-module traces, Haiku for file/grep lookups)
- Never spawn 3 agents

Provide each agent a single concrete question, pre-resolved paths, and a response cap (under 150 lines).

Collect: file paths, existing patterns, dependencies, blast radius, existing test coverage for affected code, **the specific existing helpers/services/repositories the implementation should reuse** (name the exact methods — e.g. `SalaryCapRepository::getTeamTotalSalary()` — so the plan directs reuse instead of leaving the impl agent to rediscover them), and **which security surfaces the change touches** (SQL queries, POST/form endpoints, auth/authz-gated routes, user-facing output rendering). If none of these surfaces are touched, record that explicitly. Also record **whether the task resolves a finding tracked in a status/tracking doc** (e.g. `ibl5/docs/maintenance-backlog.md`, an a11y/security backlog, a roadmap with per-item status markers) — if so, the doc path, the finding id, and its current status marker — so Step 3 can scope the status-flip edit into the **same** PR. Separately, record **whether the work leaves a follow-up that can only run *after* the PR merges** — something the merge event itself unblocks: a stale memory/doc/plan that stays valid until the change lands (e.g. a `MEMORY.md` "delete this entry when #N merges" pointer), a temporary compat shim that can be removed once its consumer deploys, a feature flag to retire post-rollout. Note each one so Step 3 can **mechanize** it as a merge-triggered watcher rather than leaving it to the user's memory.

## Step 2.5: Scope into PRs

Using the blast radius from Step 2, decide how many PRs the work requires. Default to **one** — most tasks are a single PR. Split into multiple only when a boundary is real:

- Independent concerns that can land and be reviewed separately
- A refactor/extraction that should merge before the feature that depends on it (stacked PRs)
- A change large enough that one reviewer cannot reasonably review it in a single sitting
- Distinct migrations or schema changes that each warrant their own rollback boundary

If the work is **one PR**, proceed to Step 3 once.

If the work is **multiple PRs**, list the PR-sized units in dependency order (what must merge first), then run Steps 3–5 **once per unit** — each producing its own plan file. Plans for stacked PRs should note their base branch in the implementation steps (`bin/wt-new --base <branch>`). Do not collapse the units back into one plan to "save effort" — the split is the deliverable.

## Step 3: Design the plan

Run this step once per PR-sized unit identified in Step 2.5. Each run plans exactly one PR.

The Plan agent auto-loads CLAUDE.md, all always-loaded rules (agent-tiering, core-coding, etc.), and user memory. Do NOT re-inject any of these into the prompt — only supply what the agent cannot get on its own.

Launch a **single Plan agent** (`subagent_type: "plan-architect"` — its definition carries `model: opus` and `effort: xhigh`; do NOT pass an inline `model` override) with a prompt containing ALL of these:

**Run this step inline — never delegate `/plan` itself.** The orchestrating session owns Steps 1–5 directly and spawns exactly **one** `plan-architect` per PR-sized unit. Do NOT hand the whole `/plan` invocation to a `general-purpose`/`claude` sub-agent (or fan it out across several), and do NOT have any such agent fire `/plan` on your behalf. Those agent types carry `Tools: *` — they *can* spawn further agents, so delegating `/plan` to them produces a `general-purpose → plan-architect` nest, exactly the multi-level `plan-architect` tree the flat-fan-out rule forbids (`agent-tiering.md` § Flat fan-out). `plan-architect`/`Plan`/`Explore` cannot cause this themselves — they lack the `Agent` tool — so the only way the nest appears is an orchestrator delegating `/plan` outward. Keep planning one level deep: this session → one `plan-architect`.

1. **Task description** from `$ARGUMENTS` — when the work was split in Step 2.5, scope this to the single PR being planned and state which PR it is and what it depends on
2. **Exploration results** from Step 2 — file paths, code traces, existing patterns, test coverage findings
3. **The full `$VERIFICATION_RULE`** from Step 1, prefixed with: `MANDATORY — you must follow this rule exactly:`
4. **Agent-tiering guidance for plan phases** — the "In Plans / Mechanical recipe agents / Bulk-sweep pattern" block (in *Agent-tiering guidance to inject* below), so the Plan agent labels each implementation phase's tier (Sonnet / Haiku / self).
5. **Draft output path** — the absolute path `$HOME/.claude/plans/.drafts/<slug>.draft.md` (using this PR's Step-5 slug) that the agent must persist the finished plan to *before* returning (see **Persist before returning** below). Pre-resolve `$HOME` and the slug yourself; pass a literal path.

The Plan agent MUST produce:
- Implementation steps with tests woven inline (pre-impl before their step, post-impl after)
- A full Verification Matrix in the exact format specified by `$VERIFICATION_RULE`
- File paths for every test to be written or modified
- A **Reuse** note in each implementation step that should call existing code: name the exact helper/service/repository method to use (from Step 2 findings) so the impl agent reuses rather than reinvents. Omit only when the step genuinely introduces new infrastructure.
- An **exact edit anchor** for every step that modifies an existing file: quote the unique surrounding snippet (the exact line(s) the edit lands on or next to) so the impl agent's first `Edit` matches unambiguously. This is a **correctness / disambiguation** aid — it secures a first-try Edit match and avoids a failed-edit→re-read retry. It is **not** a token optimization and must not be presented as one: the impl agent already greps-then-slices and never reads a whole file to locate an edit, so anchors reduce ambiguity, not tokens.
- For every behavior-changing step, at least one **negative-path, boundary, or failure-case** matrix row — not only the happy path (e.g. "rejects over-cap trade", "returns null for unknown player", "empty roster"). Happy-path-only coverage is insufficient.
- When the plan emits a `## Critical Files` section, **mark every entry that will NOT be changed** (references, templates, files read for context) with an explicit reference marker — use `` `path` (reference) `` or `` `path` (read-only reference) ``. post-plan's Phase 5.0 file-conformance check treats every Critical File as a **must-appear** change target *by default* and blocks auto-merge if it never lands in the diff — it exempts an entry **only** when the annotation carries a reference marker (`reference`/`read-only`/`verify`/`template`/etc.). A bare path OR a path you annotate with a change-*description* (e.g. `` `path` — add the foo helper ``) is still checked, so describing your change-targets is safe; only the reference marker exempts. Mark the non-changed entries and the gate stays false-positive-free.

**Persist before returning (timeout durability).** The `plan-architect` agent has no `Write`/`Edit` tool, so its only delivery channel is its streamed final message — and a long final message can be lost to an `API Error: Stream idle timeout`, which is **unrecoverable** (this harness has no resume / `SendMessage`, and the agent cannot self-persist a draft once the message drops). Defend against this in the prompt: instruct the agent that **before** it emits its final message it MUST write the complete plan markdown to the **Draft output path** (item 5) via Bash, then return only a **one-line pointer** (`Plan written to <draft path>`) — never the plan body. Give it this exact recipe:

```bash
mkdir -p "$HOME/.claude/plans/.drafts"
cat > "$HOME/.claude/plans/.drafts/<slug>.draft.md" <<'PLAN_EOF'
<the full plan markdown>
PLAN_EOF
```

This makes the artifact durable the moment the Bash call returns (tool calls complete and return even when the trailing assistant message idles out) and shrinks the timeout-prone final message to a single line. The orchestrator reads the draft in Step 5. (`API_FORCE_IDLE_TIMEOUT=0` in `~/.claude/settings.json` separately disables the 5-minute idle-gap abort; this Bash-persist defends even if that env var is unset or the run hits the total `API_TIMEOUT_MS` ceiling.)

Conditionally — include a section **only when it applies**; never emit an empty header:
- **Backlog / tracking-doc status update** (only when Step 2 recorded that the task resolves — fully or partially — a finding tracked in a status/tracking doc with per-item status markers, e.g. `ibl5/docs/maintenance-backlog.md`): the plan MUST include an implementation step that updates that finding's status **in the same PR**, so the bookkeeping ships with the work and no separate follow-up "mark-done" PR is ever needed. The step must: (1) flip the finding's marker — `⬜ Open → ✅ Implemented`, or `→ ◑ Partial` with the residual work named, for a partial resolution downgrade rather than close; (2) add a one-line `**Status:**` note citing what shipped; and (3) bump the doc's `last_verified` frontmatter per `doc-freshness.md` (same edit). Add the tracking doc to **Critical Files** as a change target — a bare path or a change-description, **never** a `(reference)` marker, since the doc IS edited (post-plan Phase 5.0 then verifies the status edit actually landed). Quote the exact current table row / status line as the edit anchor. Tier this step **Haiku** (mechanical marker swap from a provided old→new mapping).
- **Post-merge mechanization** (only when Step 2 recorded a follow-up that genuinely *cannot* run until the PR merges — stale-once-landed memory/doc/plan deletion, a temporary shim removable after its consumer deploys, a flag to retire post-rollout): the plan MUST NOT leave it as a prose note for the user to remember — it MUST either fold it into the PR or **mechanize** it. Prefer folding: if the cleanup can ship *with* the change, scope it as a normal implementation step (use the **Backlog / tracking-doc status update** bullet for in-PR bookkeeping). Only when the trigger is the merge event *itself* does the plan add a **merge-triggered watcher** phase: write the follow-up as a small script, then arm a detached launchd job (the established pattern — see `bin/post-plan-now`) that polls for the merge and runs the script. Two correctness traps the watcher MUST handle — they are why a reusable, tested watcher helper is the clean target over a per-plan plist that re-derives buggy poll logic: (1) **resolve the PR number once from the branch, then poll by number** (`gh pr view <#> --json state,mergedAt`) — never keep polling `--head <branch>`, because branch-auto-delete-on-merge makes the merge invisible and the watcher waits forever; (2) **self-teardown on *either* terminal state** — fire the script and unload on `MERGED`, but on `CLOSED` (closed without merging) unload *without* running the script. Artifacts live **outside** the repo (`~/.claude/…`, launchd plists) so they are worktree-exempt. Name the trigger script, the poll command, and the self-teardown; tier the setup **Haiku** (write script + plist from the provided recipe). The objective is zero reliance on the user's memory/attention to execute a crucial post-merge step.
- **Manual UI/UX check** (only when the plan introduces new or redesigned user-visible UI/UX — see `_plan-verification.md` § Forced manual-verification trigger): add one **Truly-manual** matrix row for the subjective look-and-feel + flow judgment (phrase it as a question of taste — no `verify`/`check that`/`confirm`/`ensure`, which `bin/check-plan` gate 3 rejects), do NOT emit the "All verification is automated" line, and set `auto_merge: false` in the line-1 frontmatter (Step 4 gate 14d).
- **Verification-gap mechanization** (only when the change has a correctness property that is **silent**, **integration-only**, observable-only-in-prod, or would otherwise need a human to confirm it works — autonomy lever 3): the plan MUST include an implementation phase that **builds the mechanical self-check** rather than defaulting to a `Truly-manual` row or `auto_merge: false`. Name the **invariant** the check asserts, **how it fails loudly** (a required CI job that errors on the bad outcome, a loud-failure signal replacing a silent fallback, an independent invariant assertion on the real artifact, or a shadow/burn-in rollout that enforces the invariant before flipping), and add matrix rows for it. This is distinct from a `Truly-manual` UI/UX judgment (genuinely subjective) and from an `irreducible` hold (distributed per-site judgment, or data-blocked) — a verification gap is *reducible*: it is missing automation, not missing judgment. Only when no such check can be built does the gap justify a hold, and the plan must then state concretely why mechanization is infeasible.
- **Schema-safety mechanization** (only for a *reversible* schema-tightening migration — a `varchar` narrowing, or a length / `NOT NULL` constraint add — that would otherwise stay held under Step 4 gate 14(c)): rather than defaulting to `auto_merge: false`, the plan MUST include a migration phase that **mechanically neutralizes the truncation/violation blast-radius** with all of: (1) an **apply-time fail-closed guard** that aborts the migration if any live row would violate the new constraint — written to run against the *target* DB so it self-gates on prod at deploy (this is why "needs production `MAX(LENGTH)`, prod unreachable from CI" is a guard to build, not a data-blocked hold). The guard MUST raise a real SQL **error**, not a warning: the runner is `mysqli::multi_query`, which halts the batch (skipping the `ALTER`) only on an erroring statement, and prod `sql_mode` is non-strict — so use a mode-independent idiom such as `SELECT IF(<violation-condition>, (SELECT 1 UNION SELECT 2), 0)` (the true branch forces ERROR 1242), never `CAST(... AS SIGNED)` or division-by-zero, which only warn under non-strict mode. (2) a **forward-bound assertion** that the writer's source column cannot produce a violating value (cite the source column and its width) — this is the *only* thing preventing future violations, since non-strict prod truncates rather than rejects an over-length write at runtime, so it must be a real invariant; (3) **idempotency** (skip the `ALTER` when `information_schema` shows the column already at the target shape); (4) a **documented lossless rollback** (the widening / loosening `ALTER`); and (5) a **DatabaseIntegration test** that — running under a session `sql_mode` matching prod's (non-strict: `SET SESSION sql_mode = ''` in setup) — inserts a violating row and asserts the migration **aborts**, plus a conforming row asserting it succeeds. Running under prod's mode is what makes this test the *mechanical* proof the guard fail-closes: local/CI MariaDB is strict-by-default, so a test left in strict mode would green-light a strict-only idiom (e.g. `CAST(... AS SIGNED)`) that merely *warns* on non-strict prod — passing test, unguarded prod. Under prod's mode, only the mode-independent erroring idiom from (1) passes. This DatabaseIntegration test is required-blocking on any migration PR (the `db-integration` job feeds the required "Tests and Analysis" gate), so a red guard-abort test holds the merge. Add matrix rows for the guard-abort test and the post-migration column shape. This applies **only** when the target shape is chosen independently of prod data; a migration whose *shape* depends on unreadable prod data is genuinely design-data-blocked and stays held under gate 14(c).
- **Design decisions** (only when the design has a genuine fork): list each fork and classify it — **self-resolved** (conventional seam; state the choice + reason), **needs-user-input** (a single discrete choice the codebase can't reveal — preserve-vs-fix a known latent bug, ban-rule scope, module/admin-split boundary), or **irreducible** (distributed per-site/per-test judgment, or data-blocked). Phrase each `needs-user-input` fork as one crisp question with 2–4 concrete options, for the orchestrator to surface in Step 3.5.
- **Approach** (non-trivial changes only): one short paragraph naming the chosen design and the main alternative rejected, with the reason. Skip for trivial single-file edits.
- **Security** (only when Step 2 flagged a touched surface): for each surface, an implementation step encoding the defense AND a matching matrix row —
  - SQL → prepared statement / `bind_param` (mind native-type binding); row asserts the query is parameterized.
  - POST/form endpoint → `CsrfGuard` token validation (share one raw token across forms when a page has ≥10, per `MAX_TOKENS=10`); E2E or API-test row asserts a missing/invalid token is rejected.
  - Auth/authz-gated route → guard present on the state-changing endpoint; row asserts an unauthorized request is refused.
  - Output rendering → escaped output (enforced by `RequireEscapedOutputRule`); note it so the impl agent doesn't fight the PHPStan rule.
  XSS and input validation are deterministically enforced by PHPStan custom rules — note which apply, do not write redundant manual checks.

**Self-apply the Automouse Hold Challenge.** Before you classify any design decision `irreducible`, recommend a hold, or let a verification gap / reversible schema tightening default to a supervised verdict, ask yourself the question that breaks false holds in practice: *"What would I add to this plan to make it safe for automouse to merge unattended?"* If the honest answer is a buildable mechanical check — a lever-3 self-check (Verification-gap mechanization above) or the Step 3 § Schema-safety guard — **add that phase and its matrix rows** instead of leaning toward a hold. Carry a hold forward **only** when it is *intrinsic* (subjective UI/UX taste, a touched security surface, an irreversibly-destructive / design-data-blocked change, or a self-gating change to the merge-gate machinery itself) or you can state concretely *why no mechanical check is buildable*. Do **not** pressure an intrinsic hold into arming — that is a safety regression; just name its category. The orchestrator re-runs this challenge in Step 4.5, so do not leave it a reducible hold you could have dissolved.

#### Agent-tiering guidance to inject (item 4 above)

This guidance was relocated from `agent-tiering.md` (it is plan-authoring-only). Inject it verbatim into the Plan-agent prompt:

> **In Plans.** Explicitly label which implementation phases go to Sonnet / Haiku / self. The tiering decision belongs in the plan, not deferred to execution time.
>
> **Mechanical recipe agents.** When a plan phase writes out every action as literal commands (`git mv`, explicit find/replace mappings, `git rm`, config line swaps), the executing agent is Haiku. The prompt already contains the recipe — the agent executes it. Sonnet is only needed when the prompt asks the agent to decide *what* to do, not just *how* to do it.
> - **Haiku:** `git mv` file renames with explicit source→target, namespace find/replace from a provided mapping, `git rm` + config updates, multi-step recipe execution
> - **Sonnet:** call-site sweeps where the agent must judge whether a match is a column vs. table name, test-writing, code authoring, debugging failures
>
> **Bulk-sweep pattern.**
> - Migration authoring, PHPStan rules, ADRs → Opus (self).
> - Per-module PHP call-site sweeps that require judgment (e.g., distinguishing column refs from table refs in backtick-quoted SQL) → Sonnet.
> - Per-module sweeps with an explicit old→new mapping and no ambiguity → Haiku.
> - Running tests, migrations, schema verification → direct Bash (short output); Haiku only if multi-step or output is unpredictably large.
> - Interpreting failing tests, deciding when to update baselines → Opus (self).

#### Delegation packets for verbose phases

For a phase that is **genuinely verbose or parallelizable** — a multi-step run→inspect→fix→regen loop, or a bulk sweep of roughly **three or more** file-edits — emit a self-contained **delegation packet** the impl agent hands to a single sub-agent. Delegate the **whole phase loop including its own verify/regen/fixup** (not just the edits): the sub-agent's tool output then accumulates in *its* context and returns as one summary, keeping the orchestrator's per-turn context flat. The win is **context localization** (the orchestrator stops re-reading a growing transcript every turn) — not a flat cost-percentage. Reserve packets for phases whose moved work clearly exceeds a sub-agent's fixed startup (~15K tokens); a packet for one tiny edit costs more than it saves, so keep small phases inline.

Format each packet as a fenced block within the plan:

````
### Delegate — <phase name>
- **Tier:** Haiku | Sonnet  (per the agent-tiering guidance above)
- **Scope:** which files, what change
- **Recipe:** the exact commands / edits to run
- **Self-verify:** the command the sub-agent runs *before returning* (e.g. `composer run analyse`, expected test count, green-green) — the packet owns its own verification
- **Report back:** a one-line summary only
````

## Step 3.5: Front-load design decisions

The Plan agent runs in a sub-context and **cannot ask the user**. For each `needs-user-input` fork it flagged in its **Design decisions** section, you (the orchestrator) surface it now with `AskUserQuestion` — one question, 2–4 concrete options, recommendation first; use the `preview` field to show a proposed module layout or code shape for structural choices. Record each answer + a one-line rationale into the plan's **Approach** section as a fixed constraint, then patch the affected implementation steps so the decision is fully specified.

A recorded decision is **no longer a fork**: it does not trip Step 4 gate 7 (unresolved decision) and does not, by itself, force `auto_merge: false` (gate 14) — the human judgment already happened at plan-time.

Do **not** ask when: the fork is conventional (let the Plan agent's self-resolution stand), the judgment is `irreducible` (distributed per-site/per-test, or data-blocked — that legitimately keeps the plan supervised), or asking is ceremony. `AskUserQuestion` is for forks where the answer actually changes the implementation.

## Step 4: Validate the matrix

After receiving the Plan agent's output, check these gates yourself — do NOT delegate validation.

**The deterministic gates are scripted, not hand-run.** `bin/check-plan` (invoked in Step 5, once the plan is on disk) mechanically enforces the false-positive-free subset: gate 1 (matrix exists), gate 3 (no false manuals), the `DECIDE`/`TBD`/`subject to validation`/`subject to review` tokens of gate 7, gate 8 (decision-trigger ADR — flags a declared new trigger-surface file lacking an ADR step or `no-adr:` marker), **and** reuse-target existence (a PHP `Class::method` named in a **Reuse** note whose class exists in `ibl5/` but whose method is absent — a likely typo). Do **not** hand-scan for those; fix whatever the script reports. The gates below are the ones that need judgment a script cannot do:

1. *(scripted — see above)*
2. **No unclassified items** — every row's test type is a real classification. *Not scripted on purpose:* the type column is open-ended in practice (`Go-archive-diagnostic`, `Documented (domain rule)`, `read-before-cut` are legitimate), so a closed-set check would false-positive — judge membership yourself.
3. *(scripted — see above)*
4. **Tests woven inline** — pre-impl tests appear before their implementation step, not collected in a bottom appendix
5. **Production comparison classified correctly** — any "compare against production" or "match iblhoops.net" row must be Visual-regression, not Truly-manual
6. **Test file paths present** — every PHPUnit/API-test/E2E/Visual-regression row names a concrete test file path, not just a category
7. **No unresolved decisions** — the literal tokens are scripted (see above). You still hand-resolve an unresolved **`(or `** fork (e.g. "STAY (or move)") — `bin/check-plan` skips that token because the corpus showed it is overwhelmingly a benign aside (`≤5 (or 0 ideally)`, `(or extend existing)`), and telling a real fork from an aside needs reading the alternative. Resolve any genuine fork in-place; the automouse agent cannot make judgment calls.
8. *(scripted — `bin/check-plan` gate `[8]`)* **Decision-trigger pre-classified** — gate `[8]` flags any declared NEW file matching a `bin/adr-check` trigger surface (the pattern table lives in `_plan-verification.md` § Decision-trigger pre-classification — the single source of truth; do not duplicate it) that lacks a resolution. When it fires, do **not** merely "add an ADR step": pre-name the ADR slug and pre-fill the ADR's Context and Decision text directly into the plan body, so the spec carries the ADR draft. The conservative flags (any new `bin/` script; a new migration only when the plan text mentions `DROP`; a `composer.json` `require`/`require-dev` add) cannot read LOC/content at plan time, so they over-include slightly — clear a false flag with a `no-adr:` marker when no real decision is introduced.
9. **Negative-path coverage** — every behavior-changing step has at least one matrix row asserting a failure, boundary, or rejection case, not only happy-path. If a step has only happy-path rows, add the missing negative-path row.
10. **Hot-file extraction** — if any step adds > 100 LOC to a file `bin/check-hot-files` lists as hot (> 500 LOC under `classes/`), the plan must either propose an extraction step or carry an inline justification (per `_plan-verification.md` § Hot-file thresholds). If neither is present, add one.
11. **Refactor characterization** — if any step under `ibl5/classes/**` carries a refactor signal (file rename, method signature change, visibility narrowing, class removal, or > 30-line deletion per `refactor-flag.md`), the matrix must include a pre-impl characterization row for the affected code. If missing, add it. A correct **Phase-1** characterization pin makes a behavior-preserving refactor green-green — the pin *is* the coverage, so "this code was untested" is not on its own grounds for `auto_merge: false` (gate 14).
12. **Security surface resolved** — if Step 2 flagged a touched security surface, the plan contains a Security section with a defense step and matching matrix row for each. If a flagged surface has no resolution, add it.
13. **impl_model criterion** — if the plan declares `impl_model: sonnet` frontmatter (see Step 5), scan the Verification Matrix; if ANY row is classified `Truly-manual`, strip the marker so the plan runs at the Opus default. Sonnet may drive a plan only when every behavior-changing step has an objectively machine-checkable row that fails on a wrong edit.
14. **auto-merge hold criterion** — post-plan **always** runs and opens the PR (with code review, security audit, and CI) the moment an implementation session verifies complete; what you decide here is only whether **auto-merge arms** or the PR waits for a human to merge. Decide yourself — do NOT delegate — whether this plan wants a human at merge, and if so declare `auto_merge: false` (see Step 5; `/post-plan` Phase 6.5 condition (7) reads it and refuses to arm). Hold the merge when **any** hold: (a) the Verification Matrix carries a `Truly-manual` (or otherwise subjective) row — post-plan's machine gates can't validate it; (b) Step 2 flagged a touched security surface; (c) the plan is a high-blast-radius data/schema change — an *irreversibly destructive* migration (DROP / lossy backfill / in-place data mutation), a column-rename sweep, or an FK-ordering migration. A **reversible schema tightening** (a `varchar` narrowing, a length or `NOT NULL` constraint) is **held under this gate by default**; the hold is *released* (auto-merge may arm) **only** when the plan carries the complete, test-proven Step 3 § Schema-safety mechanization phase (apply-time fail-closed guard + forward-bound assertion + idempotency + documented lossless rollback + a guard-abort test) — absent or incomplete mechanization, it stays held. See gate 15; (d) the plan introduces **new or redesigned user-visible UI/UX** — the forced manual-verification trigger in `_plan-verification.md` (new/restyled CSS component, new rendered page/module, new nav entry/indicator/badge, or a new multi-step user flow). Set `auto_merge: false` directly when any of these hold — it is the single authoritative hold, so you do not need to keep it in sync with the matrix: Phase 6.5 condition (1) *independently* holds a UI/UX plan (its Truly-manual row lands in the PR's Manual-Testing section, which blocks arming), so neither lever re-arms if the other is dropped. This composes with gate 13: a `Truly-manual` row both strips `impl_model: sonnet` **and** sets `auto_merge: false`. A genuine design fork is **not** a hold trigger once it has been front-loaded and recorded via Step 3.5 — the judgment already happened at plan-time; hold only when the judgment is irreducible (distributed per-site/per-test, or data-blocked). Omitting the flag leaves the PR eligible to auto-merge, still subject to every Phase-6.5 condition — including the PR-time safety verdict (9) on the realized diff and the `feat:` floor (8).
15. **verification-gap mechanization (autonomy lever 3)** — when the *motivation* for `auto_merge: false` is a **verification gap** — the change's correctness is silent, integration-only, observable-only-in-prod, or would otherwise need a human to confirm it works — rather than one of gate 14's intrinsic triggers (a)–(d), the hold does NOT stand on that basis alone. First require lever 3: the plan must carry the mechanical self-check (a required CI job that fails loudly on the bad outcome, a loud-failure signal replacing a silent fallback, an independent invariant assertion, or a shadow/burn-in rollout) — see Step 3 § Verification-gap mechanization — or state concretely why one cannot be built. A hold whose only justification is an un-mechanized verification gap **fails this gate**: add the self-check phase (and its matrix rows) and leave auto-merge armed, unless an intrinsic 14(a)–(d) trigger independently applies. Note gate 14(c) holds an *irreversibly destructive* schema change (DROP / lossy backfill / in-place data mutation), a column-rename sweep, an FK-ordering migration, or one whose **target shape itself depends on production data you cannot read** (genuinely design-data-blocked). It does **not** *permanently* hold (i) a CI/config/tooling change ("I can't verify this" is mechanizable — lever-3 territory), nor (ii) a **reversible schema tightening** (a `varchar` narrowing, a length or `NOT NULL` constraint): each stays held only until its mechanization phase exists. A tightening's release condition is the complete Step 3 § Schema-safety mechanization phase, **proven by its guard-abort test**. The guard runs on the *target* DB at deploy, so prod gates its own migration — "needs prod `MAX(LENGTH)`, prod unreachable from CI" is a guard to build, not a data-blocked hold. Two correctness traps the plan must respect: the runner is `mysqli::multi_query` (`MigrationRepository`), which halts the batch only on a statement that raises a real **error** — so the guard must use a `sql_mode`-independent erroring idiom (e.g. `SELECT IF(<violation>, (SELECT 1 UNION SELECT 2), 0)` → ERROR 1242), never one that merely *warns* under non-strict mode; and prod `sql_mode` is **non-strict** while local/CI MariaDB is strict-by-default, so a strict-only idiom passes the local guard-abort test yet leaves prod unguarded. Existing rows are then mechanically protected; future over-length writes are bounded only by the forward-bound assertion (a static plan-time argument — non-strict prod truncates rather than rejects). With the phase present and its guard-abort test green, leave auto-merge armed.

If validation fails on any gate, fix the matrix yourself rather than re-running the Plan agent.

## Step 4.5: Challenge the auto-merge hold

If you did **not** set `auto_merge: false` (Step 4 gate 14), skip this step — there is nothing to challenge.

When you *are* about to hold the merge, run an adversarial second pass on that verdict yourself (Opus — irreducibility is Opus-tier judgment, never delegated). The forcing question, lifted from the manual re-prompt that breaks false holds in practice, is: **"What would I add to this plan to make it safe for automouse to merge unattended?"** Apply it according to the hold's *type* — the standard differs, and conflating them regresses safety in one direction or autonomy in the other.

**Reducible holds — dissolve or prove infeasible.** The hold is *reducible* when its only basis is one of:
- a **verification gap** (gate 15) — correctness is silent, integration-only, observable-only-in-prod, or "I can't tell mechanically whether it works";
- a **reversible schema tightening** (gate 14c-reducible) — a `varchar` narrowing, or a length / `NOT NULL` add;
- a **CI/config/tooling** change you "can't verify."

You may **not** keep the hold on that basis alone. Resolve it one of two ways — **both are passing outcomes**, this is not a one-way push to arm:
- (a) **Dissolve it.** Add the mechanization phase — a lever-3 self-check (Step 3 § Verification-gap mechanization) or the Step 3 § Schema-safety guard — and its matrix rows, then **remove `auto_merge: false`**. The check now does the watching; the hold is gone.
- (b) **Confirm it, with cause.** Keep the hold only if you can state concretely *why no mechanical check can be built* for this specific gap. "It would be effort" is not a reason; "the only signal is subjective human perception of X" or "the asserting artifact does not exist until prod" is.

**Intrinsic holds — name the category and stand.** Do **not** apply pressure to these; an intrinsic hold *should* wait for a human, and challenging it into arming is the safety regression this step exists to prevent. A hold stands as-is when it rests on:
- **subjective UI/UX taste** (gate 14a/d) — a genuine look-and-feel / flow judgment;
- a **touched security surface** (gate 14b);
- an **irreversibly-destructive or design-data-blocked** change (gate 14c) — DROP / lossy backfill / in-place data mutation, a column-rename sweep, an FK-ordering migration, or a migration whose *target shape* depends on prod data unreadable from CI;
- a **self-gating / bootstrap hazard** — a change to the auto-merge, merge-gate, or `/post-plan` machinery **itself**, where arming would let the half-built or just-rewritten mechanism gate its *own* change. No self-run check can be trusted here, because the thing under change *is* the verifier; a human must merge it under the old, known-good floor. (This is why it is not a mere verification gap — lever 3 can't mechanize a check whose own validity the PR is rewriting.)

**Record the outcome.** Every plan that *keeps* `auto_merge: false` must carry an `## Automouse Hold Justification` section (its presence is enforced by `bin/check-plan`; validity is your judgment). State in it: the hold **category** (reducible-confirmed or intrinsic) and which gate-14/15 trigger it rests on, plus one line — for a reducible-confirmed hold, *why no mechanical check is buildable*; for an intrinsic hold, *why the judgment is irreducible*. A reducible hold you **dissolved** carries no section, because the plan no longer holds.

## Step 5: Write the plan file

Derive a kebab-case slug from the task description (max 50 chars, lowercase, alphanumeric and hyphens only).

```bash
PLAN_PATH="$HOME/.claude/plans/<slug>.md"
```

If a plan file already exists at that path, create a new one with a numeric suffix rather than overwriting.

**Source the plan from the persisted draft.** The Step-3 agent wrote the finished plan to `$HOME/.claude/plans/.drafts/<slug>.draft.md` before returning (see Step 3 § Persist before returning), and returned only a one-line pointer. Read **that draft file** as the source of truth — the streamed final message may have been truncated by a stream-idle timeout, so never reconstruct the plan from the message when a draft exists. Fall back to the streamed message only if the draft is missing (e.g. the agent errored before persisting); if neither is usable, re-run the Step-3 agent. After writing the final `$PLAN_PATH` and passing `bin/check-plan`, delete the draft (`rm -f "$HOME/.claude/plans/.drafts/<slug>.draft.md"`).

Write the validated plan (with corrected matrix if Step 4 required fixes) to the plan file. When the work was split into multiple PRs, give each plan a distinct slug (e.g. `<base-slug>-1-<unit>`, `<base-slug>-2-<unit>`) so they sort in dependency order, and write one file per unit.

Then run the mechanical linter on each plan file you wrote and fix anything it reports, in-place, until it exits clean:

```bash
bin/check-plan "$PLAN_PATH"
```

It enforces the deterministic gates from Step 4 (matrix present, no false manuals, no `DECIDE`/`TBD`/`subject to …` tokens, no unresolved decision-trigger surface, reuse targets resolve). A non-zero exit prints each violation prefixed by its gate (`[1]`/`[3]`/`[7]`/`[8]`/`[H]`/`[R]`); resolve each and re-run. Do not leave a plan written until `bin/check-plan` passes.

### Declaring the implementation model (optional)

The automouse implementation agent's model is selectable per-plan via a line-1 YAML frontmatter field. When **every** behavior-changing step has at least one Verification-Matrix row that is objectively machine-checkable and fails on a wrong edit (PHPStan green, PHPUnit/CLI assertion, baseline regen, identical test count, green-green characterization), prepend this block as the **very first lines** of the plan file:

```
---
impl_model: sonnet
---
```

The implementation then runs at Sonnet (cheaper, verified-equivalent quality on uniformly-mechanical plans — parsed by `bin/lib/plan-impl-model`). Omit the marker for any plan carrying a `Truly-manual` or subjective row — absence defaults to Opus. Only the first frontmatter block is parsed, so documenting this syntax inside a plan body never mis-selects a model. Failure modes are bounded: an absent or garbled marker → Opus (safe); a wrongly-applied `sonnet` marker → the plan's objective matrix goes red under Sonnet → caught by CI / post-plan.

### Holding auto-merge (optional)

An interactive implementation session, once it verifies complete, always auto-fires a detached `/post-plan` via `bin/post-plan-now --auto` — opening the PR and running code review, security audit, and CI. Post-plan is **never held**. What you control is whether **auto-merge arms**: for a plan judged by Step 4 gate 14 to want a human at merge, add `auto_merge: false` to the **same** line-1 frontmatter block. Post-plan still opens the PR and reviews it; `/post-plan` Phase 6.5 condition (7) reads the flag and refuses to arm auto-merge, leaving the PR open for a human to merge:

```
---
impl_model: sonnet
auto_merge: false
---
```

Either field may appear alone; the block above shows both (a mechanical column-rename sweep, e.g., can be Sonnet-eligible **and** want a human at merge). A plan that keeps `auto_merge: false` must also carry the `## Automouse Hold Justification` section from Step 4.5 — `bin/check-plan` gate `[H]` fails the plan if it is missing. Absence of `auto_merge` leaves the PR eligible to auto-merge (still subject to every other Phase-6.5 condition). Only the line-1 block is parsed (Phase 6.5), so a body that documents the syntax can't opt a plan out. Failure modes are bounded: absent/garbled → eligible to arm (post-plan's own gates — code review, security audit, CI-green-required, the `feat:` floor, the PR-time safety verdict, and the headless golden-snapshot block — still apply); a wrongly-applied `false` → the PR just waits for a manual merge.

## Step 5.5: Auto-queue queue-safe plans

A plan is **queue-safe** the moment `bin/check-plan` (Step 5) exits 0 — that gate already enforces no unresolved decisions, no `DECIDE`/`TBD`/`subject to…` tokens, resolved decision-triggers, and resolved reuse targets, so a passing plan is fully specified for unattended automouse execution. Queue-safety is **independent of `auto_merge`**: a plan held for human merge (`auto_merge: false`) is still safe to *implement* autonomously — only its merge waits (Phase 6.5 condition (7)).

For every plan that passed `bin/check-plan` in Step 5, decide its disposition by this precedence (the default is **queue**):

1. **Explicit token in `$ARGUMENTS`** wins outright: `--implement` (or "implement now") → do NOT queue; leave the plan on disk and report it ready to implement. `--queue` → queue.
2. **Else, clear session intent to implement now** (the user said they will implement it in this session, or implementation is already underway) → do NOT queue.
3. **Else, default: auto-queue.** Run `bin/automouse-queue <slug>` for the plan.

When the work was split into multiple PRs (Step 2.5), queue **every** queue-safe unit, running `bin/automouse-queue <slug>` once per plan in dependency order (the order they must merge). A plan that did not pass `bin/check-plan` is never queued — fix it first.

Report which plans were queued (and which were left for in-session implementation) in Step 6.

## Step 6: Report

Tell the user:
- The plan file path — **all of them** when the work was split into multiple PRs
- The **disposition** of each plan: auto-queued for automouse (default), or left for in-session implementation (`--implement` / clear implement-now intent) — and the resulting `bin/automouse-queue` state
- A one-line matrix summary per plan (e.g., "12 items: 7 PHPUnit, 3 E2E, 2 CLI-executable, 0 truly-manual")
- Whether any security surface was flagged and how each is defended (or "no security surface touched")
- Whether the PR is eligible to auto-merge on green CI (default) or is held for a human merge (`auto_merge: false`, per Step 4 gate 14) — and why, if held
- Whether any post-merge follow-up was mechanized (the merge-triggered watcher and what it runs on merge), or "no post-merge follow-up"
- For a multi-PR split: the PR sequence and dependency order (which lands first, what each stacks on)
- Whether each plan is ready for implementation or has open questions
