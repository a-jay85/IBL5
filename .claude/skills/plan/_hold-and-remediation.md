---
description: The conditional parts of plan/SKILL.md Steps 4.5 and 5 (the hold challenge, drift remediation, staleness remediation), each loaded by the /plan orchestrator one section at a time and only when its trigger fires.
last_verified: 2026-10-26
---

This file is Read-on-demand only, one section at a time. Never Read it whole. The SKILL.md Step 4.5 stub and two pointers in `_validate-and-write.md` § Step 5 each name a section and the `awk` command that loads it.

## Step 4.5: Challenge the auto-merge hold

If you did **not** set `auto_merge: false` (Step 4 gate 14), skip the auto-merge challenge below — there is nothing to challenge there. **The pre-prod-exception challenge at the end of this step is separate and is NOT skipped**: it runs whenever the plan carries a `pre-prod-exception:` marker, armed or held, because a coverage exception and a merge hold are independent verdicts.

When you *are* about to hold the merge, run an adversarial second pass on that verdict yourself (Opus — irreducibility is Opus-tier judgment, never delegated). The forcing question, lifted from the manual re-prompt that breaks false holds in practice, is: **"What would I add to this plan to make it safe for automouse to merge unattended?"** Apply it according to the hold's *type* — the standard differs, and conflating them regresses safety in one direction or autonomy in the other.

An intrinsic claim on a merge-adjacent change (a workflow, a `bin/check-*` script, `bin/lib/pr-*`, the post-plan harness, or `/post-plan` prose) must name which of gate 14(e)'s two tests it meets: gate weakening (a previously rejected input now passes) or bootstrap hazard (the diff rewrites the rule that governs its own merge). A claim that names neither is a reducible CI/config/tooling hold and goes through the reducible path below.

**Reducible holds — dissolve or prove infeasible.** The hold is *reducible* when its only basis is one of:
- a **verification gap** (gate 15) — correctness is silent, integration-only, observable-only-in-prod, or "I can't tell mechanically whether it works";
- a **reversible schema tightening** (gate 14c-reducible) — a `varchar` narrowing, or a length / `NOT NULL` add;
- a **dischargeable security surface** (gate 14b-reducible) — a state-free extraction, a pure-function move, or a validator that never sees actor identity, where the safety property is a *structural* invariant a test can assert;
- a **CI/config/tooling** change you "can't verify."

You may **not** keep the hold on that basis alone. Resolve it one of two ways — **both are passing outcomes**, this is not a one-way push to arm:
- (a) **Dissolve it.** Add the mechanization phase — a lever-3 self-check (Verification-gap mechanization), the Schema-safety guard, or the Security-surface mechanization assertion — and its matrix rows, then **remove `auto_merge: false`**. The check now does the watching; the hold is gone.
- (b) **Confirm it, with cause.** Keep the hold only if you can state concretely *why no mechanical check can be built* for this specific gap. "It would be effort" is not a reason; "the only signal is subjective human perception of X" or "the asserting artifact does not exist until prod" is.

**Intrinsic holds — name the category and stand.** Do **not** apply pressure to these; an intrinsic hold *should* wait for a human, and challenging it into arming is the safety regression this step exists to prevent. A hold stands as-is when it rests on:
- **subjective UI/UX taste** (gate 14a/d) — a genuine look-and-feel / flow judgment;
- an **irreducible security surface** (gate 14b) — a check that genuinely carries actor identity (`$loggedInTeamID`, session, current user), a new POST/form endpoint, a new SQL construction site, or new user-facing output rendering. A surface is intrinsic **unless** it matches a named dischargeable shape *and* the plan builds the assertion; ambiguity resolves to intrinsic;
- an **irreversibly-destructive or design-data-blocked** change (gate 14c) — DROP / lossy backfill / in-place data mutation, a column-rename sweep, an FK-ordering migration, or a migration whose *target shape* depends on prod data unreadable from CI;
- a **self-gating / bootstrap hazard** (gate 14e), and only when the change meets one of the two Step 3 check-1 tests: it weakens an executable gate (some input the gate previously rejected now passes) or it rewrites the arming, escalation, or auto-merge rule that governs its own merge. No self-run check can be trusted for that shape, because the thing under change *is* the verifier; a human merges it under the old, known-good floor, and lever 3 cannot mechanize a check whose own validity the PR is rewriting. Making a gate stricter is not weakening. Touching a workflow, `bin/check-*`, `bin/lib/pr-*`, `tools/postplan-harness/`, or `/post-plan` prose on its own is not a trigger either; such a change takes the gate-14 no-hold default or, when its correctness is merely unverified, the reducible path above;
- a **golden-snapshot change** or a **recorded-and-unresolved design fork** (gate 14f / 14g): the first because a regenerated baseline passes its own check by construction, the second because the run has already said it could not decide.

**Record the outcome.** Every plan that *keeps* `auto_merge: false` must carry an `## Automouse Hold Justification` section (its presence is enforced by `bin/check-plan`; validity is your judgment). State in it: the hold **category** (reducible-confirmed or intrinsic) and which gate-14/15 trigger it rests on, plus one line — for a reducible-confirmed hold, *why no mechanical check is buildable*; for an intrinsic hold, *why the judgment is irreducible*. A reducible hold you **dissolved** carries no section, because the plan no longer holds.

**A hold justification carries a decision.** The section names the one judgment the human renders at the merge button; every *observable* claim ("every reference resolves", "the counts agree", "no logic changed outside the escaping hunks") is a Verification Matrix row and belongs nowhere else. Write it in this shape: a `**Category:**` line, a `**Decision:**` line (wording rules below), a `**Discharged by matrix rows:**` line pointing at the rows that already assert what a reader might otherwise expect the human to re-run, and the one line of *why* this step already requires. **The test to apply, per sentence:** *could an agent with the repo, the diff, and a shell settle this?* If yes it is a matrix row, and deleting it from the hold section costs nothing; write the row if it is missing, then cite it. If the only settling instrument is the human's own judgment (taste, risk appetite, willingness to accept an irreversible write) that is the decision, and it stays. `bin/check-plan` gate `[H]` fails a hold section that carries an ask-shaped sentence outside a `**Decision:**` block (the exemption runs from that line to the next blank line, so a decision may wrap but a later paragraph cannot hide inside it). It is a narrow pattern check over `bin/lib/hold-check-patterns.txt`, calibrated to stay silent on perception and taste prose. It catches the "here is a list of things for you to confirm" shape and no other phrasing, so passing it is the floor and the standard sits above it. **Decision wording.** The Decision line is the only hold text the PR body shows, and the person pressing merge reads it. Write one plain yes/no sentence of at most 40 words, for example "Merge if you're OK with about 7% more PRs being held until their body declares scope." Do not open it with "The human accepts that ...", and do not phrase it as an instruction to go run something. Leave a blank line after it. Gate `[H]` enforces the cap, the opener, and `bin/check-prose` tells on the Decision body.

**Second challenge.** A claimed `pre-prod-exception:` marker. Run this whenever the plan carries one (Step 4 gate 16), *independently* of the auto-merge verdict above. Same adversarial posture, same **reducible / intrinsic** vocabulary, and the forcing question is slice-level. Do not ask *"is this testable pre-prod?"*, which invites a yes/no about the whole feature and gets answered "no". Ask:

> **"Which slice of this is intrinsic — and what am I building for the rest?"**

Apply the reducible/intrinsic split from gate 16. The default answer for a `bin/<name>-tick`-class script is fixed: scheduling and the network/credential boundary are intrinsic; everything else is reducible, and the exception does not cover it. Gate 16's resolution list has the moves.

**A plan claiming the exception over a whole artifact is claiming too much. Reject it** and send it back to build the reducible slice's exercise path. "The daemon can't run on my laptop" is a claim about *registration*, not about the code the daemon runs.

**The failure this challenge exists to prevent, concretely:** an exception claimed over the integration's **logic**, licensed by a Verification Matrix that reads fully green because its only coverage of the live boundary is a stub (the `bin/lib/bug-pipeline-test-stubs.sh` shape). The stub proves the caller called the stub. Nothing then verifies the real service does the thing — and the gap ships behind a green matrix *plus* a recorded, plausible-sounding exception. That pairing is worse than an openly uncovered plan, because it reads as covered and no later gate re-opens it.

**Disposal.** An exception that survives the challenge is recorded as `pre-prod-exception: <reason>` naming **which slice** is intrinsic — scheduling, reachability, or credential — never the artifact as a whole and never the plan as a whole, plus its one-line entry in `## Pre-prod Exception Justification`. In the same plan, the reducible slice still gets its pre-prod exercise path **built**, with its own matrix rows. An exception is never a whole-plan escape, and it is never satisfied by deleting the row it was claimed against.

<!-- end: step-4.5 -->

## Drift remediation

```
SCAF=$(awk '/<!-- PLAN OUTLINE/,/-->/' "$DRAFT" \
  | grep -cE '^[[:space:]]*[0-9]+\.[[:space:]]+(Phase|Step)[[:space:]]*[0-9]')
BODY=$(grep -cE '^##[[:space:]]+(Phase|Step)[[:space:]]*[0-9]' "$DRAFT")
if [ "$SCAF" -gt 0 ] && [ "$BODY" -gt "$SCAF" ]; then
  # first body phase heading beyond the scaffold's declared count
  EXCESS_LINE=$(grep -nE '^##[[:space:]]+(Phase|Step)[[:space:]]*[0-9]' "$DRAFT" \
    | awk -F: -v n="$SCAF" 'NR==n+1{print $1}')
  # first NON-phase level-2 heading at/after it; else end of file
  CLOSE_LINE=$(awk -v s="$EXCESS_LINE" \
    'NR>=s && /^## / && !/^##[[:space:]]+(Phase|Step)[[:space:]]*[0-9]/ {print NR; exit}' "$DRAFT")
  if [ -n "$CLOSE_LINE" ]; then END=$((CLOSE_LINE-1)); else END='$'; fi
  sed -i "" "${EXCESS_LINE},${END}d" "$DRAFT"   # macOS BSD sed: -i takes an empty-string suffix
fi
bin/check-plan --draft "$DRAFT"
```

Re-run the check **once**. If it still exits non-zero, stop and report — do not loop; an unbounded retry burns the run, exactly as the Turn-1 outline retry is bounded. The deleted prose is recoverable from the architect's transcript if it mattered; a drifted plan silently shipped is not.
<!-- end: drift-remediation -->

## Staleness remediation

**Put the cue on the token's own physical line.** The guard tests `grep -F <token> … | grep -E <cues>` line by line, and plans hard-wrap, so a cue that opens the sentence one line above does not count. Find the token's line and place the cue adjacent to its backtick span:

```
`ibl5/classes/Module/ModuleAccessControl.php` (example) (moved from `ibl5/classes/ModuleAccessControl.php` (example))
```

| What the token actually is | Repair |
|---|---|
| A typo (the path exists at a different name) | Correct the path. No cue needed; a live path never flags. |
| Created, moved or renamed by this plan | Add a same-line cue from `_architect-contract.md` § safe positions: `— NEW`, `Create`, `Write`, `moved from`, `→`. |
| Cited only as an alternative this plan rejected | Move the line under a non-dependency heading (`## Out of Scope`, `## Non-Goals`, `## Rejected`, `## Alternatives`). |
| Illustrative path, never real | Append `(example)` after the closing backtick (`bin/check-docs` convention). |
| A path you cannot resolve to anything | Escalate (see below). |

A `>` blockquote is **not** a safe position; quoted prose is scanned exactly like body prose. A fenced code block is.

**Escalation.** After 3 attempts, or immediately for a token with no resolvable target: stop remediating and do not loop further. Never delete the plan. Leave the plan file on disk, add `auto_merge: false` to the line-1 frontmatter, write an `## Automouse Hold Justification` entry naming each unresolved `STALE:` token and why it could not be resolved, and skip Step 5.5. The plan is written for a human to pick up.
<!-- end: staleness-remediation -->
