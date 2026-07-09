---
name: plan
description: "Plan an implementation task: enforces a verification matrix, directs code reuse, flags security surfaces, and requires negative-path tests so plans drive clean, secure, well-tested implementations."
disallowed-tools:
  - EnterPlanMode
  - ExitPlanMode
last_verified: 2026-07-09
---

# /plan — Implementation Planning with Verification Matrix

You are planning an implementation task. The user's request follows this skill's instructions as `$ARGUMENTS`.

**Do NOT write or edit any code files.** This skill produces a plan only. The output is one plan file per PR.

**One plan = one PR.** A single plan file must be implementable and mergeable as exactly one pull request. If the work naturally spans multiple PRs (independent concerns, separate review surfaces, a refactor that should land before the feature that uses it, or a change too large to review in one sitting), do NOT bundle them. Split the work into PR-sized units and produce a separate, fully self-contained plan — its own implementation steps and its own Verification Matrix — for each one. The user should never have to ask for this split.

**Maximize automouse autonomy.** A plan is most valuable when automouse can implement *and merge* it with no human in the loop. Three levers turn a would-be-supervised plan autonomous — apply them by default instead of reaching for `auto_merge: false`:

1. **Pin, don't supervise.** Uncovered code is not a reason to hold the merge — it's a reason to add a **Phase-1 characterization pin** (Step 4 gate 11). A behavior-preserving refactor with a pin is green-green; the pin *is* the coverage.
2. **Decide at plan-time, not merge-time.** A single discrete design fork doesn't have to wait for a human at merge — surface it to the user *now* (Step 3.5) and record the answer in the plan; automouse then executes a fully-specified decision.
3. **Mechanize the check, don't watch it.** When the *reason* to want a human at merge is "we can't tell mechanically whether it works" — a silent or integration-only failure mode, an "observe in prod" property, or a reflex to have a human confirm it behaves — that is a missing-verification problem, not irreducible judgment. Design the self-asserting check as an implementation phase: a required CI job that fails *loudly* on the bad outcome, a loud-failure signal replacing a silent fallback, an independent invariant assertion on the real artifact, or a shadow/burn-in rollout that enforces the invariant before flipping. This extends to a **reversible schema tightening** (a `varchar` narrowing, a length / `NOT NULL` constraint): an apply-time fail-closed guard that runs on the *target* DB self-gates the migration on prod at deploy, so "needs prod data unreachable from CI" becomes a guard to build — which *releases* the gate-14(c) hold once present and test-proven — rather than a permanent hold (see Schema-safety mechanization). Let auto-merge arm once the check exists; reach for `auto_merge: false` only after the check genuinely can't be built (see Verification-gap mechanization, Step 4 gate 15).

Fall back to `auto_merge: false` only when the judgment is **irreducible**: *distributed* across the implementation (per-site security verdicts, per-test correctness on untested code) or *data-blocked* (needs production data unreachable from CI). "A decision exists" is not a hold reason; "the decision can't be made once, up-front" is; and "I can't verify it works without watching it" is a verification gap to close with lever 3, not a hold reason. The flag holds only the **merge** — post-plan still runs and opens the PR for review either way.

## Step 1: Verification rule

Read `.claude/review-shared/_plan-verification.md` and use its full content as `$VERIFICATION_RULE` for injection into the Plan agent prompt in Step 3. Do not summarize or paraphrase the rule.

## Step 2: Orient on the codebase

**Prefer direct tool calls over Explore agents.** Most orientation can be done without spawning an agent:

1. Read `.claude/rules/codebase-map.md` to identify affected modules and their file locations
2. Run targeted `grep`/`find` via Bash for specific symbols, callers, or file paths
3. Read key files directly (migrations, interfaces, existing tests)

**Spawn an Explore agent when EITHER** trigger fires — read volume is a trigger in its own right, not only residual uncertainty:

1. **Open questions** — direct lookups leave something genuinely unanswered.
2. **Byte isolation** — answering would mean reading large source files (roughly: any file over ~300 lines, or a combined read budget past ~600 lines) into *this* session. `/plan` is **delegation-terminal**: the deliverable is produced by the `plan-architect` sub-agent in its own fresh window, so the orchestrator never needs those files resident. Even with zero open questions, pull the heavy reads into an Explore agent (or defer them to the plan-architect) so the bytes land in a disposable window, not the orchestrator's — three consecutive auto-compactions before any planning work is the failure this prevents. What you carry forward is **pointers** (`path:line` + the one load-bearing fact per file), never the file contents; the plan-architect re-reads at those pointers in its own window (targeted confirmation, not re-exploration — see Step 3 point 2).

Tier per `.claude/rules/agent-tiering.md`:

- Single-module change → 0 agents (direct tools suffice) or 1 Haiku for enumeration
- Spans 2+ modules → up to 2 agents (Sonnet for cross-module traces, Haiku for file/grep lookups)
- Never spawn 3 agents

Provide each agent a single concrete question, pre-resolved paths, and a response cap (under 150 lines). An agent spawned purely for byte isolation must return **distilled pointers** (`path:line` + the load-bearing fact), not pasted file bodies — pasting the contents back defeats the isolation.

Collect: file paths, existing patterns, dependencies, blast radius, existing test coverage for affected code, **the specific existing helpers/services/repositories the implementation should reuse** (name the exact methods — e.g. `SalaryCapRepository::getTeamTotalSalary()` — so the plan directs reuse instead of leaving the impl agent to rediscover them), and **which security surfaces the change touches** (SQL queries, POST/form endpoints, auth/authz-gated routes, user-facing output rendering). If none of these surfaces are touched, record that explicitly. Also record **whether the task resolves a finding tracked in a status/tracking doc** (e.g. `ibl5/docs/backlog/maintenance-backlog.md`, an a11y/security backlog, a roadmap with per-item status markers) — if so, the doc path, the finding id, and its current status marker — so Step 3 can scope the status-flip edit into the **same** PR. Separately, record **whether the work leaves a follow-up that can only run *after* the PR merges** — something the merge event itself unblocks: a stale memory/doc/plan that stays valid until the change lands (e.g. a `MEMORY.md` "delete this entry when #N merges" pointer), a temporary compat shim that can be removed once its consumer deploys, a feature flag to retire post-rollout. Note each one so Step 3 can **mechanize** it as a merge-triggered watcher rather than leaving it to the user's memory.

## Step 2.5: Scope into PRs

Using the blast radius from Step 2, decide how many PRs the work requires. Default to **one** — most tasks are a single PR. Split into multiple only when a boundary is real:

- Independent concerns that can land and be reviewed separately
- A refactor/extraction that should merge before the feature that depends on it (stacked PRs)
- A change large enough that one reviewer cannot reasonably review it in a single sitting
- Distinct migrations or schema changes that each warrant their own rollback boundary
- **Implementation-context budget** — the plan's execution would not fit one implementation session comfortably under the ~100–150K context dumb-zone, where reasoning measurably degrades. Size proxies: roughly **12+ numbered phases**, **~500+ plan lines**, or **2+ inline bulk sweeps** without delegation packets. (Measured 2026-07-07 on the automouse corpus: 60% of Opus implementation runs breached 150K peak context, and the breaching runs executed plans of median ~566 lines / ~20 phases; the runs that stayed under ran ~100-line plans.) `bin/check-plan` gate `[C]` enforces the proxy mechanically at Step 5 — when it fires, the fix is THIS split into stacked PR-sized plans, never padding or a reflexive `context-budget:` marker.

If the work is **one PR**, proceed to Step 3 once.

If the work is **multiple PRs**, list the PR-sized units in dependency order (what must merge first), then run Steps 3–5 **once per unit** — each producing its own plan file. Plans for stacked PRs should note their base branch in the implementation steps (`bin/wt-new --base <branch>`). Do not collapse the units back into one plan to "save effort" — the split is the deliverable.

## Step 3: Design the plan

Run this step once per PR-sized unit identified in Step 2.5. Each run plans exactly one PR.

The Plan agent auto-loads CLAUDE.md, all always-loaded rules (agent-tiering, core-coding, etc.), and user memory. Do NOT re-inject any of these into the prompt — only supply what the agent cannot get on its own.

Launch a **single Plan agent** (`subagent_type: "plan-architect"` — its definition carries `model: opus` and `effort: high`; do NOT pass an inline `model` override) with a prompt containing ALL of these. **Escalate to `plan-architect-xhigh` (effort: xhigh) when Step 2 flagged any of: a security surface, a trust boundary (auth/authz-gated route), a destructive migration, or a change to `.claude/skills` ship-pipeline invariants.**

**Run this step inline — never delegate `/plan` itself.** The orchestrating session owns Steps 1–5 directly and spawns exactly **one** `plan-architect` per PR-sized unit. Do NOT hand the whole `/plan` invocation to a `general-purpose`/`claude` sub-agent (or fan it out across several), and do NOT have any such agent fire `/plan` on your behalf. Those agent types carry `Tools: *` — they *can* spawn further agents, so delegating `/plan` to them produces a `general-purpose → plan-architect` nest, exactly the multi-level `plan-architect` tree the flat-fan-out rule forbids (`agent-tiering.md` § Flat fan-out). `plan-architect`/`Plan`/`Explore` cannot cause this themselves — they lack the `Agent` tool — so the only way the nest appears is an orchestrator delegating `/plan` outward. Keep planning one level deep: this session → one `plan-architect`.

1. **Task description** from `$ARGUMENTS` — when the work was split in Step 2.5, scope this to the single PR being planned and state which PR it is and what it depends on
2. **Exploration results** from Step 2 — file paths, code traces, existing patterns, test coverage findings. **Tell the agent these findings are authoritative and that it must NOT re-explore them.** The agent already ran with `effort: xhigh`, so its instinct is to re-derive everything from scratch — but you've supplied the orientation, and every redundant `grep`/`Read`/agent call extends the run and raises the stall risk (each tool round-trip is another window for the idle timeout to land before the agent reaches its Bash-persist). Instruct it to spend tool calls only on **targeted confirmations** of anything the findings leave genuinely open — cap ~2–3 — then go straight to composing the plan. "Verify everything myself" is the failure mode here, not diligence.
3. **The full `$VERIFICATION_RULE`** from Step 1, prefixed with: `MANDATORY — you must follow this rule exactly:`
4. **Full output contract** — instruct the `plan-architect` to Read `.claude/skills/plan/_architect-contract.md` as its first action. That reference (created in Phase 1 of this plan) carries the complete "what the plan MUST produce" list, the conditional-section catalogue, the agent-tiering labels to apply per phase (Sonnet / Haiku / self), and the delegation-packet format. Do NOT inline any of it into the prompt — the architect Reads it into its own sub-context, so this bulk never enters the orchestrator's context.
5. **Draft output path** — the absolute path `$HOME/.claude/plans/.drafts/<slug>.draft.md` (using this PR's Step-5 slug) that you seed and the agent appends its sections to across the sectioned delivery (see **Deliver the plan in sections** below). Pre-resolve `$HOME` and the slug yourself; pass a literal path.

**Deliver the plan in sections (timeout durability).** The `plan-architect` agent has no `Write`/`Edit`/`NotebookEdit` tool — its only file-writing channel is Bash (a `cat >>` heredoc), and its only message channel is its streamed turn output, which a long generation can lose to an `API Error: Stream idle timeout`. A single-shot "compose the whole plan, then one big persist" puts the entire deliverable in one vulnerable window: a stall anywhere before that lone Bash call completes loses the whole plan. Instead, **YOU (the orchestrator) drive the architect across turns** and the draft is assembled incrementally, so a mid-stream stall costs at most one section — never the whole plan.

- **Setup.** Before turn 1, `mkdir -p "$HOME/.claude/plans/.drafts"` and use the **Draft output path** (item 5) as `$DRAFT`.
- **Turn 1 — outline (the single remaining total-loss window).** Tell the architect this is a *sectioned* delivery whose first turn returns ONLY a numbered list of the section titles the plan will contain — ordered implementation phases first, then every fixed/conditional section (`Critical Files`, `Architectural trade-offs`, `Verification Matrix`, plus `Out of Scope` / `Automouse Hold Justification` when warranted) — one title per line, no bodies, no frontmatter, nothing else. Parse the numbered titles into an ordered list (you drive the loop from it) and persist the outline to the draft: write `# <plan title>` then the outline as an HTML comment (`<!-- PLAN OUTLINE (loop scaffold; dropped at Step 5): … -->`). If turn 1 returns empty, times out, or yields zero parseable titles: **retry once**, then **abort loudly** ("plan-architect produced no outline") — do not proceed. Turn 1 is the only window where a stall loses everything, because nothing is on disk until the outline lands.
- **Turns 2..N — one section per turn (loss bounded to one turn).** For each parsed title, in order, `SendMessage` the architect to output ONLY that section and **Bash-append it to the draft itself** — a completed `cat >> "$DRAFT"` heredoc writing `## <exact title>` plus the body, durable the instant the tool call returns — then return a THIN one-line ack (`section "<title>" appended`), never the section body. The architect must persist via Bash rather than return the body: a returned body is a long streamed message that reintroduces the exact stall vulnerability, whereas a completed append is transcript-durable and the trailing ack is tiny. **Enforce the turn boundary:** run each turn under a wall-clock cap (`timeout`, default 360s) and reject/re-prompt any turn whose ack reports more than one section, whose append dumped multiple sections, or that overran the cap. This orchestrator-side rejection is what makes "loss ≤ one section" a real bound rather than an aspiration. Do not read the growing draft between turns.
- **Loss bound.** By construction every completed section is on disk before the next turn begins, so a mid-stream stall loses at most the single in-flight section — re-drivable by re-sending that one title. Only turn 1 can lose the whole plan, and it is guarded by retry-once-then-abort above.

(`API_FORCE_IDLE_TIMEOUT=0` in `~/.claude/settings.json` separately disables the 5-minute idle-gap abort; this sectioning loop defends even if that env var is unset or the run hits the total `API_TIMEOUT_MS` ceiling.) The orchestrator reads the assembled draft only at the end (Step 5).

## Step 3.5: Front-load design decisions

The Plan agent runs in a sub-context and **cannot ask the user**. For each `needs-user-input` fork it flagged in its **Design decisions** section, you (the orchestrator) surface it now with `AskUserQuestion` — one question, 2–4 concrete options, recommendation first; use the `preview` field to show a proposed module layout or code shape for structural choices. Record each answer + a one-line rationale into the plan's **Approach** section as a fixed constraint, then patch the affected implementation steps so the decision is fully specified.

A recorded decision is **no longer a fork**: it does not trip Step 4 gate 7 (unresolved decision) and does not, by itself, force `auto_merge: false` (gate 14) — the human judgment already happened at plan-time.

Do **not** ask when: the fork is conventional (let the Plan agent's self-resolution stand), the judgment is `irreducible` (distributed per-site/per-test, or data-blocked — that legitimately keeps the plan supervised), or asking is ceremony. `AskUserQuestion` is for forks where the answer actually changes the implementation.

## Step 4: Validate the matrix

After receiving the Plan agent's output, check these gates yourself — do NOT delegate validation.

**The deterministic gates are scripted, not hand-run.** `bin/check-plan` (invoked in Step 5, once the plan is on disk) mechanically enforces the false-positive-free subset: gate 1 (matrix exists), gate 3 (no false manuals), the `DECIDE`/`TBD`/`subject to validation`/`subject to review` tokens of gate 7, gate 8 (decision-trigger ADR — flags a declared new trigger-surface file lacking an ADR step or `no-adr:` marker), reuse-target existence (a PHP `Class::method` named in a **Reuse** note whose class exists in `ibl5/` but whose method is absent — a likely typo), **and** the context budget (gate `[C]` — a marathon-sized plan, ≥500 lines or ≥12 numbered phases; the fix is a Step 2.5 split, not a marker). Do **not** hand-scan for those; fix whatever the script reports. The gates below are the ones that need judgment a script cannot do:

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
13. **impl_model criterion — bidirectional; the model is a deliberate choice in BOTH directions.** (a) If the plan declares `impl_model: sonnet` frontmatter (see Step 5), scan the Verification Matrix; if ANY row is classified `Truly-manual`, strip the marker so the plan runs at the Opus default. Sonnet may drive a plan only when every behavior-changing step has an objectively machine-checkable row that fails on a wrong edit. (b) Conversely, if the plan does NOT declare `impl_model` and that criterion **holds** — no `Truly-manual` or subjective row, every behavior-changing step machine-checked — ADD `impl_model: sonnet` to the line-1 frontmatter yourself. Opus-by-omission is a model-assignment miss, not a safe default: audited 2026-07-07, 57 of the 109 Opus-default plans in the corpus carried no `Truly-manual` row, and Opus implementation runs are both ~1.7× the per-token cost and the ones that marathon into the context dumb-zone. The failure mode stays bounded exactly as Step 5 notes: a wrongly-added marker turns the objective matrix red under Sonnet and is caught by CI / post-plan.
14. **auto-merge hold criterion** — post-plan **always** runs and opens the PR (with code review, security audit, and CI) the moment an implementation session verifies complete; what you decide here is only whether **auto-merge arms** or the PR waits for a human to merge. Decide yourself — do NOT delegate — whether this plan wants a human at merge, and if so declare `auto_merge: false` (see Step 5; `/post-plan` Phase 6.5 condition (7) reads it and refuses to arm). Hold the merge when **any** hold: (a) the Verification Matrix carries a `Truly-manual` (or otherwise subjective) row — post-plan's machine gates can't validate it; (b) Step 2 flagged a touched security surface; (c) the plan is a high-blast-radius data/schema change — an *irreversibly destructive* migration (DROP / lossy backfill / in-place data mutation), a column-rename sweep, or an FK-ordering migration. A **reversible schema tightening** (a `varchar` narrowing, a length or `NOT NULL` constraint) is **held under this gate by default**; the hold is *released* (auto-merge may arm) **only** when the plan carries the complete, test-proven Schema-safety mechanization phase (apply-time fail-closed guard + forward-bound assertion + idempotency + documented lossless rollback + a guard-abort test) — absent or incomplete mechanization, it stays held. See gate 15; (d) the plan introduces **new or redesigned user-visible UI/UX** — the forced manual-verification trigger in `_plan-verification.md` (new/restyled CSS component, new rendered page/module, new nav entry/indicator/badge, or a new multi-step user flow). Set `auto_merge: false` directly when any of these hold — it is the single authoritative hold, so you do not need to keep it in sync with the matrix: Phase 6.5 condition (1) *independently* holds a UI/UX plan (its Truly-manual row lands in the PR's Manual-Testing section, which blocks arming), so neither lever re-arms if the other is dropped. This composes with gate 13: a `Truly-manual` row both strips `impl_model: sonnet` **and** sets `auto_merge: false`. A genuine design fork is **not** a hold trigger once it has been front-loaded and recorded via Step 3.5 — the judgment already happened at plan-time; hold only when the judgment is irreducible (distributed per-site/per-test, or data-blocked). Omitting the flag leaves the PR eligible to auto-merge, still subject to every Phase-6.5 condition — including the PR-time safety verdict (9) on the realized diff and the `feat:` floor (8).
15. **verification-gap mechanization (autonomy lever 3)** — when the *motivation* for `auto_merge: false` is a **verification gap** — the change's correctness is silent, integration-only, observable-only-in-prod, or would otherwise need a human to confirm it works — rather than one of gate 14's intrinsic triggers (a)–(d), the hold does NOT stand on that basis alone. First require lever 3: the plan must carry the mechanical self-check (a required CI job that fails loudly on the bad outcome, a loud-failure signal replacing a silent fallback, an independent invariant assertion, or a shadow/burn-in rollout) — see Verification-gap mechanization — or state concretely why one cannot be built. A hold whose only justification is an un-mechanized verification gap **fails this gate**: add the self-check phase (and its matrix rows) and leave auto-merge armed, unless an intrinsic 14(a)–(d) trigger independently applies. Note gate 14(c) holds an *irreversibly destructive* schema change (DROP / lossy backfill / in-place data mutation), a column-rename sweep, an FK-ordering migration, or one whose **target shape itself depends on production data you cannot read** (genuinely design-data-blocked). It does **not** *permanently* hold (i) a CI/config/tooling change ("I can't verify this" is mechanizable — lever-3 territory), nor (ii) a **reversible schema tightening** (a `varchar` narrowing, a length or `NOT NULL` constraint): each stays held only until its mechanization phase exists. A tightening's release condition is the complete Schema-safety mechanization phase, **proven by its guard-abort test**. The guard runs on the *target* DB at deploy, so prod gates its own migration — "needs prod `MAX(LENGTH)`, prod unreachable from CI" is a guard to build, not a data-blocked hold. Two correctness traps the plan must respect: the runner is `mysqli::multi_query` (`MigrationRepository`), which halts the batch only on a statement that raises a real **error** — so the guard must use a `sql_mode`-independent erroring idiom (e.g. `SELECT IF(<violation>, (SELECT 1 UNION SELECT 2), 0)` → ERROR 1242), never one that merely *warns* under non-strict mode; and prod `sql_mode` is **non-strict** while local/CI MariaDB is strict-by-default, so a strict-only idiom passes the local guard-abort test yet leaves prod unguarded. Existing rows are then mechanically protected; future over-length writes are bounded only by the forward-bound assertion (a static plan-time argument — non-strict prod truncates rather than rejects). With the phase present and its guard-abort test green, leave auto-merge armed.

If validation fails on any gate, fix the matrix yourself rather than re-running the Plan agent.

## Step 4.5: Challenge the auto-merge hold

If you did **not** set `auto_merge: false` (Step 4 gate 14), skip this step — there is nothing to challenge.

When you *are* about to hold the merge, run an adversarial second pass on that verdict yourself (Opus — irreducibility is Opus-tier judgment, never delegated). The forcing question, lifted from the manual re-prompt that breaks false holds in practice, is: **"What would I add to this plan to make it safe for automouse to merge unattended?"** Apply it according to the hold's *type* — the standard differs, and conflating them regresses safety in one direction or autonomy in the other.

**Reducible holds — dissolve or prove infeasible.** The hold is *reducible* when its only basis is one of:
- a **verification gap** (gate 15) — correctness is silent, integration-only, observable-only-in-prod, or "I can't tell mechanically whether it works";
- a **reversible schema tightening** (gate 14c-reducible) — a `varchar` narrowing, or a length / `NOT NULL` add;
- a **CI/config/tooling** change you "can't verify."

You may **not** keep the hold on that basis alone. Resolve it one of two ways — **both are passing outcomes**, this is not a one-way push to arm:
- (a) **Dissolve it.** Add the mechanization phase — a lever-3 self-check (Verification-gap mechanization) or the Schema-safety guard — and its matrix rows, then **remove `auto_merge: false`**. The check now does the watching; the hold is gone.
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

**Source the plan from the persisted draft.** Across Step 3's sectioned delivery the draft at `$HOME/.claude/plans/.drafts/<slug>.draft.md` was assembled incrementally — you seeded its title + outline scaffold and the agent Bash-appended one section per turn (see Step 3 § Deliver the plan in sections). Read **that draft file** as the source of truth, and drop the `<!-- PLAN OUTLINE … -->` scaffold comment when writing `$PLAN_PATH`. The per-section acks carry no plan content, so never reconstruct the plan from the streamed messages — the draft on disk is authoritative. If the draft is missing or empty (the agent errored before appending any section), re-run the Step-3 sectioned delivery. After writing the final `$PLAN_PATH` and passing `bin/check-plan`, delete the draft (`rm -f "$HOME/.claude/plans/.drafts/<slug>.draft.md"`).

Write the validated plan (with corrected matrix if Step 4 required fixes) to the plan file. When the work was split into multiple PRs, give each plan a distinct slug (e.g. `<base-slug>-1-<unit>`, `<base-slug>-2-<unit>`) so they sort in dependency order, and write one file per unit.

Then run the mechanical linter on each plan file you wrote and fix anything it reports, in-place, until it exits clean:

```bash
bin/check-plan "$PLAN_PATH"
```

It enforces the deterministic gates from Step 4 (matrix present, no false manuals, no `DECIDE`/`TBD`/`subject to …` tokens, no unresolved decision-trigger surface, reuse targets resolve, context budget). A non-zero exit prints each violation prefixed by its gate (`[1]`/`[3]`/`[7]`/`[8]`/`[H]`/`[R]`/`[C]`); resolve each and re-run. Do not leave a plan written until `bin/check-plan` passes. A `[C]` (context-budget) violation is resolved by **returning to Step 2.5 and splitting the plan into stacked PR-sized units** — reach for a `context-budget:` justification marker only when the size is illusory (e.g. the length is mostly fenced reference material or delegation-packet recipes and the actual phase count is small).

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
