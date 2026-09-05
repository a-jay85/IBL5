---
name: pr-ready
description: Take one open PR from conflicted-or-unknown state to a posted readiness verdict — rebase onto master, resolve conflicts, wait for CI, judge plan fidelity, post a sticky verdict comment, then stop.
disable-model-invocation: true
model: claude-sonnet-4-6
disallowed-tools:
  - EnterPlanMode
  - ExitPlanMode
  - Skill
last_verified: 2026-09-04
---
<!-- `model: claude-sonnet-4-6` IS DELIBERATE — DO NOT REMOVE IT, and never write
     `model: sonnet` (that alias resolves to Sonnet 5). User-authorized 2026-08-26,
     superseding the previous "NO model: KEY" note that lived here.
     The orchestrator runs the mechanical phases (0 through 5.9, 6.5, 7). Runtime
     Phase 6 — the plan-intent fidelity review, the one Opus-column judgment in this
     skill — is NOT performed here: it runs in the pinned Opus def
     `.claude/agents/pr-ready-phase6.md`, spawned exactly once per run by the Phase 6
     stub. `.claude/rules/agent-tiering.md`'s "Never delegate understanding" is
     honoured by keeping that judgment on Opus, not by keeping it on whatever tier the
     orchestrator happens to run at — a Sonnet orchestrator with no pin was the failure
     mode this pin closes, not the one it opens.
     Do NOT "harmonise" this pin away against /pr-review: that skill is a pure diff
     review with no fidelity judgment and no Opus delegate; this one has both. -->

# /pr-ready — PR readiness and plan-fidelity checker

Drives **one** open PR from "conflicted / unknown state" to a posted readiness verdict: enter the PR's existing worktree, delegate the rebase chore, resolve conflicts and push, watch CI, judge the implementation against its plan's stated intent, post a sticky verdict comment — then stop.

This skill adds **semantic** judgment the existing pipeline does not cover. `/post-plan` Phase 5.0 checks mechanical declared-artifact conformance; Phase 4B runs structured code review over the **pre-rebase** diff. Neither asks whether the code does what the plan *intended*. `/pr-ready` asks exactly that and nothing else — it does not re-run structured code review, does not define review agents, and does not reference the shared review-agent definitions.

## Invariants — stated once; later phases cite these rather than repeat them

- **One PR per invocation.** No batch mode, no PR-list iteration.
- **Runtime Phase 6 runs in the pinned Opus def, spawned exactly once.** The fidelity judgment is the deliverable and stays on the Opus tier; performing it on the Sonnet orchestrator, splitting it across agents, or re-spawning the reviewer per finding is a defect. The handoff is a written verdict file (`/tmp/pr-ready-phase6-verdict-<N>.md`), never a captured stdout — see the substitution invariant below.
- **Sub-agent returns are thin pointers** — `path:line`, SHAs, status words. Never pasted diffs or file bodies.
- **Flat fan-out only.** A delegate may not spawn a delegate.
- **Every glob is quoted** — `--include="*.md"`, never `--include=*.md`.
- **The run STOPS at the verdict, with one amendment.** After Phase 6 and before Phase 7, the new Phase 6.5 (remediation) is permitted: fix the reported findings, file prevention backlog items, commit, and push — then Phase 7 updates the same sticky comment. What remains unconditionally forbidden: merge, auto-merge arming, the full `/backlog-housekeep` chain beyond appending a backlog row and bumping its `last_verified`, any `/post-plan` chain, worktree teardown, and a second new comment.
- **After `EnterWorktree`, no Bash command may contain `$(…)` or `<(…)`.** The worktree-isolated session refuses command and process substitution outright — "too complex to verify that it stays inside the worktree"; the refusal's "…without the redirect" wording is canned and misleading. Pipelines, redirects, conditionals and `;`-separated statements all run fine: substitution is the only trigger. On the 0.3 `ALREADY-IN-TARGET` path no `EnterWorktree` call is ever made, so — *unless the session was already isolated when `/pr-ready` was invoked* — substitution would in fact work everywhere. That caveat is why the exception is worthless: you cannot tell from inside which regime you are in without probing. The later phases are written substitution-free regardless, so the same text runs unchanged on all three paths. Do not "simplify" them back on the strength of that. **Phase 0 is NOT exempt.** The gate keys on the *session* being `EnterWorktree`-tracked, not on the cwd being a worktree — a session launched directly in a worktree is unrestricted (probed: `X=$(echo hi); echo "subst-ok $X"` printed `subst-ok hi` from a worktree cwd), but a session that called `EnterWorktree` **before** `/pr-ready` was invoked is already isolated at 0.3. Phase 0 therefore has to run under both regimes, and the 0.3 guard block is written in the script-file shape for exactly that reason. It is also **re-run** after step 4's `EnterWorktree`, which is isolated by definition — so `Write` the guard file once at 0.3, *before* any `EnterWorktree`, then `bash` it up to three times; no `Write` ever needs to land post-isolation. Everywhere else, use one of two shapes:
  - **Multi-command blocks** — they live as committed scripts under `.claude/skills/pr-ready/scripts/` (example) and run in one call: `git show <MASTER_SHA>:<path> > /tmp/<file>.sh && test -s /tmp/<file>.sh && bash /tmp/<file>.sh <args>`. Key the filename to the PR number, never `$$`. If the `test -s` trips, re-run the whole command — never `bash` a leftover `/tmp` copy. The one exception is the Phase 0.3 guard, which runs before the Phase 1.3 pin exists and so stays an inline `Write`.
    - **Declared fallback, identical in shape to the include-fallback clause below.** **If it trips a second time the pin genuinely predates the script, and the declared fallback below applies to `scripts/` exactly as it does to the seven includes**: run the worktree copy directly — `bash .claude/skills/pr-ready/scripts/<name>.sh <args>` (example) — and record `include-source: worktree (pin predates skill)` in the verdict; if the worktree has no copy either, print `STOP: cannot load <name>.sh from <MASTER_SHA> or from the worktree` and stop. Without that arm a genuinely missing path and a torn write are indistinguishable, and “re-run the whole command” is a deterministic infinite loop on the dogfooding case — the case this very skill hits on its own PR.
  - **Single-value captures** (the master pin, `STRICT`, the branch name) — run the bare command so it **prints** the value, then hold that value as a **literal** in the run notes and substitute it into every later command.
- **A value captured in one Bash call does not survive to the next — hold it as a literal, not a shell variable.** Every Bash call is a fresh shell, so `MASTER_SHA=$(git rev-parse origin/master)` in one call is *empty* in the next, and `git rev-list --count "$MASTER_SHA"..HEAD` then degrades to `git rev-list --count ..HEAD`, which exits 0 printing `0` — a silent wrong answer that skips the Phase 2c squash gate for every PR. `<MASTER_SHA>` in a later block therefore means **substitute the recorded literal here**. That form also fails *closed*: forget to substitute and git rejects the literal `<MASTER_SHA>` as a bad rev, where `"$MASTER_SHA"` would have failed open. The same rule is why every `/tmp` path in this skill is keyed to `<N>` rather than `$$`.
- **Every repo file this skill reads after Phase 0 comes from `git show <MASTER_SHA>:<path>` — never a bare repo-relative `Read`.** That covers all seven progressive-disclosure includes and the linear-history rule the Phase 2 include names. The reason is structural, not stylistic: on the ordinary path the harness loads this `SKILL.md` from the **main checkout** and Phase 0.4 then `EnterWorktree`s into the *target PR's* worktree — and that tree is by definition behind master, so the skill's own files are simply **absent** there until the branch rebases, which is Phase 2, the phase whose instructions live in the missing file. (On the 0.3 `ALREADY-IN-TARGET` path the loaded `SKILL.md` is the target worktree's own copy, so skill discovery *did* resolve inside a worktree — the rule holds unchanged there, because that tree is exactly the one whose includes may be missing. On the 0.3b `WRONG-WORKTREE` path the loaded copy came from a *third* tree — the one the session started in, which has nothing to do with the PR — while the includes still come from `<MASTER_SHA>`; that is documentation, not a new case, because the rule already forbids reading any include by repo-relative path.) The main-checkout path is not a fallback: reading it from a worktree-entered session is **denied** by the cross-worktree straddle gate in `~/.claude/hooks/plan-gate-edit.sh`. `git show` against the Phase 1.3 pin sidesteps both horns — same shared object store, no gate crossed, no `$(…)` needed, and the include is guaranteed to be the revision that matches the `SKILL.md` the harness loaded. Measured before this was fixed: `_rebase-and-conflicts.md` existed in **1 of 34** worktrees on this machine — the skill's own.
  - **Exactly one fallback for the orchestrator's seven progressive-disclosure includes — `_rebase-and-conflicts.md` (Phase 2), `_phase4-push-and-ci.md` (Phase 4), `_phase59-files-changed.md` (Phase 5.9), `_plan-fidelity-review.md` (Phase 6), `_phase65-remediation.md` (Phase 6.5), `_remediation.md` (Phase 6.5, loaded from inside `_phase65-remediation.md`), and `_phase7-verdict.md` (Phase 7) — and it must be declared.** (The Phase 2 delegation packet carries its own separate fallback for the rule file it names — declared in the delegate's report line 1, per `_rebase-and-conflicts.md`. That one is not covered by, and does not violate, this clause.) `git show` fails with `fatal: invalid object name` or `path … does not exist` when the pinned master predates this skill — which, once merged, happens only when you are dogfooding `/pr-ready` on its own still-open PR. In that case, and only when the file is genuinely present in the current worktree, `Read` it by path and record `include-source: worktree (pin predates skill)` in the verdict. If neither source yields the file, print `STOP: cannot load <file> from <MASTER_SHA> or from the worktree` and stop. Never reach for the main-checkout path — that is the gated horn, and a denial there is not a signal to retry.
- **`STOP:` lines are hard stops for the model, not just for the shell.** Five runtime blocks below print a `STOP:` line: the Phase 0 argument gate (0.1), the Phase 0 worktree guard (0.3, on an unresolvable head branch or a missing `bin/lib/git-helpers.sh`), the Phase 0 exit-recovery check (0.3b, when the post-`ExitWorktree` re-run does not print `MAIN-CHECKOUT`), the Phase 0 worktree-entry step (0.4, on a missing worktree or a post-`EnterWorktree` re-run that does not print `ALREADY-IN-TARGET`), and the Phase 1 plan check (1.1). The include-fallback clause above prints a sixth, from whichever phase was loading an include or materialising a script. Where such a block runs `exit 1`, that `exit` terminates **only that Bash invocation** — it does not terminate the skill, because a skill's blocks are run by the model. The contract is therefore: **on a non-zero rc, or on printing a `STOP:` line, stop the run and make no further tool call.** None of the five relies on shell exit status to halt anything but the shell, so in all five the printed line *is* the gate. Only 0.3's own two failure arms gate via `exit 1`; 0.1 and 1.1 have no shell block at all. **0.3b and 0.4 gate on the printed word, never on the rc** — both re-run the *same* guard script, and its remaining arms all exit 0, so a re-printed `WRONG-WORKTREE` (or a `MAIN-CHECKOUT` where `ALREADY-IN-TARGET` was required) is a passing shell carrying a failing verdict. 0.4's `git worktree list` likewise only captures data, so its rc carries no signal either. The `gh pr view` whose failure that rc used to mask now lives in 0.3, behind an explicit empty-`SLUG` check that does exit non-zero. The externalized scripts print their own `STOP:` lines on a missing argument, a missing worktree, or an unreadable input, and the same contract applies — stop the run and make no further tool call.

## Runtime phases

**Phase 0 — be in the PR's worktree.**

1. **Argument gate.** `/pr-ready` takes exactly one argument, the PR number. If it is missing or does not match `^[0-9]+$`, print

   `STOP: /pr-ready needs exactly one PR number, e.g. /pr-ready 1742`

   and stop without any further tool call.

2. **Load the deferred tools first.** `ToolSearch("select:EnterWorktree,ExitWorktree")` is the **first** tool call of the run, before any worktree read. Both are deferred, so calling either without loading the schema fails with `InputValidationError`; and a worktree read attempted first trips the cross-worktree straddle gate in `~/.claude/hooks/plan-gate-edit.sh`. Load both up front even though `ExitWorktree` is only used on the 0.3b path — by the time 0.3b is reached the session may already be isolated, and a schema load is cheap.

3. **Worktree guard — resolve the PR's head branch first, then branch three ways.** `EnterWorktree`'s `path:` form only accepts a target listed in `git worktree list` *for the current repo* and, from inside a worktree, only targets under that repo's `.claude/worktrees/` (example) directory. This repo's worktrees live at `~/GitHub/IBL5-worktrees/<slug>`, so a worktree→worktree *switch* is rejected. What that forbids is switching **directly** — not running, and not going the long way round via `ExitWorktree` to the main checkout and entering from there (step 3b). When the session is **already in the PR's own worktree** there is nothing to switch to and the run proceeds in place, which is why the head branch has to be resolved before the guard can decide. Each of the three arms routes somewhere: `MAIN-CHECKOUT` → step 4, `ALREADY-IN-TARGET` → Phase 1, `WRONG-WORKTREE` → step 3b, then step 4. Run this block first:

   `Write` this to `/tmp/pr-ready-guard-<N>.sh` with `<N>` substituted, then run `bash /tmp/pr-ready-guard-<N>.sh`:

   ```bash
   TOP=$(git rev-parse --show-toplevel)
   if [ ! -r "$TOP/bin/lib/git-helpers.sh" ]; then
     echo "STOP: $TOP/bin/lib/git-helpers.sh is missing — cannot resolve is_in_worktree."
     exit 1
   fi
   source "$TOP/bin/lib/git-helpers.sh"
   SLUG=$(gh pr view <N> --json headRefName --jq .headRefName)
   HERE=$(git rev-parse --abbrev-ref HEAD)
   echo "SLUG: $SLUG"
   if [ -z "$SLUG" ]; then
     echo "STOP: could not resolve a head branch for PR <N> — check the number and that gh is authenticated."
     exit 1
   elif ! is_in_worktree; then
     echo "MAIN-CHECKOUT — enter the worktree in step 4."
   elif [ "$HERE" = "$SLUG" ]; then
     echo "ALREADY-IN-TARGET — skip step 4 entirely; do NOT call EnterWorktree."
   else
     echo "WRONG-WORKTREE — session is in '$HERE'; PR <N> lives on '$SLUG'. Recover in step 3b."
   fi
   ```

   **This file is written once and run up to three times** — here, again in 3b after `ExitWorktree`, and again in 4 after `EnterWorktree`. Write it *now*, before any `EnterWorktree` call: the later two runs happen in an isolated session where `$(…)` is refused, and re-`Write`ing the same content there would be a second chance to get the substitution wrong. The script-file shape is load-bearing rather than stylistic, per the Phase-0-is-not-exempt invariant above.

   **Record the printed `SLUG:` value as the `<SLUG>` literal** in the run notes — per the invariants above it does not survive into the next Bash call, and step 4 substitutes it into an `EnterWorktree` path.

   **The order of the three arms is load-bearing: `is_in_worktree` is tested before the branch match.** A main checkout that happens to have the PR's branch checked out must still take the `MAIN-CHECKOUT` arm and enter the worktree — proceeding in place there would have Phases 2–4 rebase and force-push *from the main checkout*, which ADR-0062 forbids outright.

   **The discriminator is the checked-out branch, not the worktree's directory name.** `bin/wt-new` keeps the two equal, but the branch is what the PR actually names, so it is the authoritative test — a renamed or manually-created worktree directory would fool a basename comparison.

   **Why the third arm no longer stops.** `EnterWorktree` will not switch worktree→worktree for sibling `IBL5-worktrees` paths, but `ExitWorktree` *does* return the session to the main checkout, and entering from there is the ordinary supported move. The wrong-worktree case is therefore a two-hop route, not a dead end — step 3b performs the exit, and the guard's own re-run is what proves the hop landed.

   Source via `$TOP` from a bare `git rev-parse --show-toplevel`, not a relative path: the skill's cwd is not guaranteed to be the repo root, and on the 3b/4 re-runs the repo root is a *different* directory than it was at 0.3 — which is precisely the state change being tested. The explicit readability check ahead of the `source` keeps a worktree predating `bin/lib/git-helpers.sh` from failing as an obscure `is_in_worktree: command not found`. `is_in_worktree()` compares `--absolute-git-dir` against `--git-common-dir`. Per the invariants above, a non-zero rc here ends the run.

3b. **Exit to the main checkout — only on `WRONG-WORKTREE`.** On the other two verdicts skip this step entirely. Call

   `ExitWorktree(action: "keep")`

   then re-run the guard, unchanged:

   ```bash
   bash /tmp/pr-ready-guard-<N>.sh
   ```

   **It must now print `MAIN-CHECKOUT`.** If it prints anything else — `WRONG-WORKTREE` again, a `STOP:` line, or nothing — print

   `STOP: ExitWorktree did not return the session to the main checkout — the guard still reports <verdict>. Re-invoke /pr-ready from /Users/ajaynicolas/GitHub/IBL5 or from the <SLUG> worktree directly.`

   and stop. This is a fail-**closed** post-condition, and it is the whole reason the wrong-worktree case can be recovered rather than refused: the guard is not weakened, it is re-asserted after the move. Never infer success from the tool's return message — re-running the guard is the only evidence that counts. Note that `ExitWorktree`'s own schema claims it is a no-op outside a session that called `EnterWorktree`; observed behavior contradicts that (it returned a directly-launched worktree session to the main checkout), so the skill relies on neither claim and simply re-checks.

   **`action: "keep"` is mandatory — never `"remove"`, never `discard_changes`.** The session did not create this worktree; it is a peer's active workspace, quite possibly with uncommitted work, and `remove` deletes the worktree *and its branch*. `/pr-ready` is a read-and-rebase skill for one PR; destroying an unrelated worktree is never part of its contract.

4. **Enter the worktree — on `MAIN-CHECKOUT`, whether that was the original verdict or the one 3b produced.** On `ALREADY-IN-TARGET`, skip this step entirely and go straight to Phase 1; the session is where it needs to be, and calling `EnterWorktree` from there would be the rejected worktree→worktree switch.

   ```bash
   git worktree list
   ```

   Confirm `~/GitHub/IBL5-worktrees/<SLUG>` — the literal recorded in step 3 — appears in the output. If it does not, print

   `STOP: no existing worktree for branch <SLUG> — /pr-ready enters an existing worktree, it never creates one.`

   and stop. Otherwise call `EnterWorktree(path: "/Users/ajaynicolas/GitHub/IBL5-worktrees/<SLUG>")`, with the literal substituted.

   **Then re-run the guard a final time and require `ALREADY-IN-TARGET`:**

   ```bash
   bash /tmp/pr-ready-guard-<N>.sh
   ```

   If it prints anything else, print

   `STOP: EnterWorktree did not land in the <SLUG> worktree — the guard reports <verdict>. Phases 2–4 rebase and force-push, which ADR-0062 forbids outside the PR's own worktree.`

   and stop. **Do not substitute a bare branch comparison for this check.** A main checkout that happens to have `<SLUG>` checked out passes a branch-only test while the session is still in the main checkout — the exact hazard the arm order guards against. `ALREADY-IN-TARGET` is the only verdict that asserts location *and* branch, in that order. This post-condition matters more since 3b exists: before it, a wrong-worktree session could never reach step 4 at all, so a silent `EnterWorktree` failure had no path to Phase 2.

5. **Docker note** — only if a later step needs the app running. Derive the slug from the tree you are actually in — run `git rev-parse --show-toplevel` bare and take the basename yourself (this runs post-`EnterWorktree`, so no `$(…)`) — then `docker start ibl5-db-<slug> ibl5-php-<slug>`. Never hardcode a slug from a previous session; never use `main.localhost` from a worktree; always navigate `/ibl5/` paths, never bare `/`.

**Phase 1 — plan, master pin, protection, prior-review probe.**

1. **Read the plan.** Run `git rev-parse --abbrev-ref HEAD` bare, then `Read` `~/claude-plans/<branch>.md` with the printed branch name substituted. The path is deterministic — resolve it, never search for it. If it does not exist, print loudly

   `STOP: no plan at ~/claude-plans/<branch>.md. /pr-ready's Phase 6 judges implementation against the plan's stated intent; without the plan there is nothing to judge against. Re-run once the plan file is restored, or run /pr-review instead for a plain code review.`

   and stop. Do **not** fall back to the PR body for plan intent — the PR body is one of the things Phase 6 audits.

2. `git fetch origin`. Nothing in this skill ever runs a bare `git rebase` against `origin/master`; see the `--onto` recipe in the Phase 2 include.

3. **Pin master before spawning anything.** Run `git rev-parse origin/master` bare and record the printed SHA as `<MASTER_SHA>` in the run notes — a **literal**, per the invariants above, never a shell variable. Every later step, and the Phase 2 delegate, substitute that literal — never a re-resolved `origin/master`.

4. **Branch-protection strict flag.**

   ```bash
   gh api "repos/{owner}/{repo}/branches/master/protection" --jq '.required_status_checks.strict // false'
   ```

   Record the printed value as `<STRICT>` in the run notes. On a 403/404 (a token without admin read), record `<STRICT>` as `true` and say so in the verdict. Failing closed costs one extra divergence check; failing open ships a stale-base merge.

5. **Prior-Phase-4B probe.** Look for the review heading in **both** the issue comments and the review bodies — findings are posted as a review body with inline threads, not only as issue comments. `git show <MASTER_SHA>:.claude/skills/pr-ready/scripts/4b-probe.sh > /tmp/pr-ready-4bprobe-<N>.sh && test -s /tmp/pr-ready-4bprobe-<N>.sh && bash /tmp/pr-ready-4bprobe-<N>.sh <N>`. The probe prints `PROBE-COMPLETE` as its last line; no output before it means no prior review, and no `PROBE-COMPLETE` at all means the probe never ran.

   Record `PHASE_4B_RAN` (any line printed ⇒ true) **and the earliest timestamp printed**, which runtime Phase 6 reports. This is a **probe, not a gate**: the value is reported in Phase 6 and never used to skip work.

   **A match is evidence, not proof — read the lines before recording `true`.** Loosening the level trades one error for its mirror: a comment that merely *quotes* a review heading at line-start (another `/pr-ready` verdict, a pasted excerpt) matches too, and a false `PHASE_4B_RAN=true` is the worse failure — Phase 6 then asserts a review ran and **suppresses** the `/pr-review <N>` recommendation on a PR that never got one. The `.user.login` field above is there for this check: confirm each hit is from the reviewing identity and that the heading is the comment's own, not something it is citing. On PRs #1790/#1872/#1876 all six hits were genuine and none of the surrounding `/pr-ready` verdicts matched — their heading mentions are inline-backticked, not line-initial — but that is an observation, not a guarantee.

**Phases 2 and 3 — rebase and conflict resolution.**

Run `git show <MASTER_SHA>:.claude/skills/pr-ready/_rebase-and-conflicts.md` — the Phase 1.3 literal substituted — and follow the printed file end-to-end before continuing. **Do not reach for it by path first**: per the `git show` invariant above, the worktree you are now in almost certainly does not contain it, and the main-checkout copy is behind the straddle gate. On a `git show` failure take the single declared fallback in that invariant — nothing else. It holds the Phase 2 delegation packet and the Phase 3 three-way conflict-resolution procedure. Pass the delegate the pinned `<MASTER_SHA>` literal from Phase 1.3 — never let it resolve `origin/master` itself.

**Phase 4 — prove nothing was lost, push, watch CI.**

Run `git show <MASTER_SHA>:.claude/skills/pr-ready/_phase4-push-and-ci.md` — the Phase 1.3 literal substituted — and follow the printed file end-to-end before continuing. **Do not reach for it by path first**: per the `git show` invariant above, the worktree you are now in almost certainly does not contain it, and the main-checkout copy is behind the straddle gate. On a `git show` failure take the single declared fallback in that invariant — nothing else. If neither source yields the file, print `STOP: cannot load _phase4-push-and-ci.md from <MASTER_SHA> or from the worktree` and stop. **Never push, and never arm the CI watcher, from memory.** It holds the lost-work proof, the push, the `mergeable=UNKNOWN` handling, and the `Agent`-delegate CI watcher — the one watcher shape that survives a headless run.

**Phase 5 — strict re-check loop.**

If `<STRICT>` is false, skip this phase. If true, then after CI reports complete and green, re-check divergence against the *current* master:

```bash
git fetch origin
gh pr view <N> --json mergeStateStatus --jq .mergeStateStatus   # BEHIND => must re-base
```

If `BEHIND`, re-pin — run `git rev-parse origin/master` bare again and record the new printed SHA as `<MASTER_SHA>`, replacing the Phase 1.3 literal — then loop back to Phase 2 with a fresh delegate on the new pin. **Bound the loop at 3 iterations**; on the fourth, stop and report `master is moving faster than this branch can rebase — merge manually or retry when master quiets`. An unbounded loop is the failure mode a strict-protection repo with a busy master produces.

**Phase 5.9 — refresh the machine-generated files-changed block.**

Run `git show <MASTER_SHA>:.claude/skills/pr-ready/_phase59-files-changed.md` — the Phase 1.3 literal substituted — and follow the printed file end-to-end before continuing. **Do not reach for it by path first**: per the `git show` invariant above, the worktree you are now in almost certainly does not contain it, and the main-checkout copy is behind the straddle gate. On a `git show` failure take the single declared fallback in that invariant — nothing else. If neither source yields the file, print `STOP: cannot load _phase59-files-changed.md from <MASTER_SHA> or from the worktree` and stop. **Never hand-write the files-changed block** — it is machine-generated and a hand-written one is the failure this phase exists to prevent. It holds the `scripts/files-changed.sh` materialise-and-run call and its non-empty guard.

**Phase 6 — plan-intent fidelity review.**

This phase does not run on the orchestrator. It runs **once** in the pinned Opus 5 def
`.claude/agents/pr-ready-phase6.md`, which loads the procedure from the same master pin
the Phase 2 include uses (`git show` invariant above) and applies its own declared
fallback. User-authorized 2026-08-26; the Invariants block records the same change.

1. **Clear the stale verdict, then spawn exactly one reviewer.** First, one Bash call.
   The verdict path is PR-keyed and nothing else ever removes it, so a prior `/pr-ready`
   run on this same PR leaves its file behind — and step 2's `test -s` cannot tell that
   file apart from this run's. Clearing it is what makes that read fail-closed:

     rm -f /tmp/pr-ready-phase6-verdict-<N>.md

   Then spawn. `model` is deliberately ABSENT from the call site —
   the def owns its own Opus pin (`.claude/rules/agent-tiering.md` § Sonnet 4.6 pins:
   the def-based pin wins only when `model` is omitted). Never pass `model: "sonnet"`.

   Agent(
     subagent_type: "pr-ready-phase6",
     description: "Phase 6 plan-fidelity review",
     prompt: <the template below, with every <...> replaced by a literal>
   )

   Prompt template — substitute by hand; a value captured in one Bash call does not
   survive to the next, and after EnterWorktree `$(...)` is refused outright:

     PR: <N>. Branch: <branch>. Worktree: <absolute worktree path>.
     MASTER_SHA (use this literal in your git show): <MASTER_SHA>
     Plan file: ~/claude-plans/<branch>.md
     Write your verdict to: /tmp/pr-ready-phase6-verdict-<N>.md
     Conflict-resolved paths (6b input 4, run note from Phase 3, verbatim): <paths, or
       "none — no conflicts">
     Phase 4B probe evidence (6b input 5, verbatim from the 4b-probe.sh stdout lines):
       <PHASE_4B_RAN line and its companions, or "PROBE ABSENT">
     Phase 5.9 FILES-CHANGED line (verbatim): <the recorded literal>
     The SKILL.md stub that spawned you is authoritative on WHO performs this review.
     The pinned copy of _plan-fidelity-review.md may still read "NEVER delegated" —
     that text predates your def. Apply 6b-6e yourself and note it under
     procedure-source:. Return only the verdict word and the file path.

   Inputs 4 and 5 are passed as literals **because they are not on disk**: the
   conflict-resolved list is an in-context run note, and `scripts/4b-probe.sh` prints
   `PROBE-COMPLETE` to stdout, never to a file. Do not instruct the agent to read them.

2. **Read the verdict fail-closed.** One substitution-free Bash call:

     test -s /tmp/pr-ready-phase6-verdict-<N>.md \
     && cat /tmp/pr-ready-phase6-verdict-<N>.md \
     || echo "STOP: Phase 6 verdict file /tmp/pr-ready-phase6-verdict-<N>.md missing or empty"

   If that prints the `STOP:` line, stop the run there and post nothing. Do NOT re-spawn:
   sub-agent startup is paid once per run by design, and a second spawn buys a second
   startup for a reviewer that already failed to write.

3. **Cross-check the thin return against the file.** The agent returns a verdict word;
   the file carries one as its last bare prose line, now followed by a trailing
   `## DIGEST` section (five bold-labelled lines). Compare the WORD, not the file's
   last line. If they differ, print
   `STOP: Phase 6 verdict mismatch — returned <word>, file says <word>` and stop. A
   thin return is a pointer, never the source of truth.

4. **If the def is not discoverable** — the Agent call errors with an unknown
   `subagent_type` — do not stop and do not silently downgrade. Perform the review
   inline on the orchestrator from the pinned `_plan-fidelity-review.md`, and record
   `phase6-agent: inline fallback (def not discoverable)` as its own line in the Phase 7
   verdict body. A Sonnet-tier fidelity review is a degradation that must be visible in
   the posted comment, not an invisible one.

5. **Carry the verdict forward verbatim.** Phase 7's body keeps its existing
   plan-fidelity section; paste the agent's verdict text into it, plus a
   `phase6-agent: pr-ready-phase6 (claude-opus-5)` line so the tier that judged this PR
   is on the record.
   Do **not** paste the `## DIGEST` section into that verdict text: Phase 7 extracts it
   with `.claude/skills/pr-ready/scripts/digest.sh` and posts it as its own
   `### Merge digest` block.

**Phase 6.5 — Remediation.**

Run `git show <MASTER_SHA>:.claude/skills/pr-ready/_phase65-remediation.md` — the Phase 1.3 literal substituted — and follow the printed file end-to-end before continuing. **Do not reach for it by path first**: per the `git show` invariant above, the worktree you are now in almost certainly does not contain it, and the main-checkout copy is behind the straddle gate. On a `git show` failure take the single declared fallback in that invariant — nothing else. If neither source yields the file, print `STOP: cannot load _phase65-remediation.md from <MASTER_SHA> or from the worktree` and stop. **Never remediate from memory** — the fifth-file gate, the overflow rule, and the dirty-tree guard all live in that file. It also loads `.claude/skills/fix-and-prevent/_remediation.md` itself; that nested load has its own declared `STOP:`.

**Phase 7 — verdict and stop.**

Run `git show <MASTER_SHA>:.claude/skills/pr-ready/_phase7-verdict.md` — the Phase 1.3 literal substituted — and follow the printed file end-to-end before continuing. **Do not reach for it by path first**: per the `git show` invariant above, the worktree you are now in almost certainly does not contain it, and the main-checkout copy is behind the straddle gate. On a `git show` failure take the single declared fallback in that invariant — nothing else. If neither source yields the file, print `STOP: cannot load _phase7-verdict.md from <MASTER_SHA> or from the worktree` and stop. **This phase is mandatory and always runs** — reaching it is never conditional on Phase 6.5. It holds the arm-hold evaluation, the sticky verdict comment, and the hard terminator. The comment body now carries a `### Merge digest` block — the five digest lines, after the plan-fidelity verdict section and before the remediation section.
