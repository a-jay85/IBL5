---
name: pr-ready
description: Take one open PR from conflicted-or-unknown state to a posted readiness verdict — rebase onto master, resolve conflicts, wait for CI, judge plan fidelity, post a sticky verdict comment, then stop.
disable-model-invocation: true
disallowed-tools:
  - EnterPlanMode
  - ExitPlanMode
  - Skill
last_verified: 2026-08-25
---
<!-- NO `model:` KEY — DELIBERATE, DO NOT ADD ONE.
     Runtime Phase 6 (plan-intent fidelity review) is Opus-column judgment:
     .claude/rules/agent-tiering.md — "Never delegate understanding." A `model:`
     pin would force this skill onto a fixed tier regardless of the invoking
     session's model. Do NOT "harmonise" this file with /pr-review's
     `model: claude-sonnet-4-6` — that skill is a pure diff review with no
     fidelity judgment, so a Sonnet pin is correct there and wrong here. -->

# /pr-ready — PR readiness and plan-fidelity checker

Drives **one** open PR from "conflicted / unknown state" to a posted readiness verdict: enter the PR's existing worktree, delegate the rebase chore, resolve conflicts and push, watch CI, judge the implementation against its plan's stated intent, post a sticky verdict comment — then stop.

This skill adds **semantic** judgment the existing pipeline does not cover. `/post-plan` Phase 5.0 checks mechanical declared-artifact conformance; Phase 4B runs structured code review over the **pre-rebase** diff. Neither asks whether the code does what the plan *intended*. `/pr-ready` asks exactly that and nothing else — it does not re-run structured code review, does not define review agents, and does not reference the shared review-agent definitions.

## Invariants — stated once; later phases cite these rather than repeat them

- **One PR per invocation.** No batch mode, no PR-list iteration.
- **The orchestrator never delegates runtime Phase 6.** The fidelity judgment is the deliverable; delegating it is a defect.
- **Sub-agent returns are thin pointers** — `path:line`, SHAs, status words. Never pasted diffs or file bodies.
- **Flat fan-out only.** A delegate may not spawn a delegate.
- **Every glob is quoted** — `--include="*.md"`, never `--include=*.md`.
- **The run STOPS at the verdict, with one amendment.** After Phase 6 and before Phase 7, the new Phase 6.5 (remediation) is permitted: fix the reported findings, file prevention backlog items, commit, and push — then Phase 7 updates the same sticky comment. What remains unconditionally forbidden: merge, auto-merge arming, the full `/backlog-housekeep` chain beyond appending a backlog row and bumping its `last_verified`, any `/post-plan` chain, worktree teardown, and a second new comment.
- **After `EnterWorktree`, no Bash command may contain `$(…)` or `<(…)`.** The worktree-isolated session refuses command and process substitution outright — "too complex to verify that it stays inside the worktree"; the refusal's "…without the redirect" wording is canned and misleading. Pipelines, redirects, conditionals and `;`-separated statements all run fine: substitution is the only trigger. On the 0.3 `ALREADY-IN-TARGET` path no `EnterWorktree` call is ever made, so — *unless the session was already isolated when `/pr-ready` was invoked* — substitution would in fact work everywhere. That caveat is why the exception is worthless: you cannot tell from inside which regime you are in without probing. The later phases are written substitution-free regardless, so the same text runs unchanged on all three paths. Do not "simplify" them back on the strength of that. **Phase 0 is NOT exempt.** The gate keys on the *session* being `EnterWorktree`-tracked, not on the cwd being a worktree — a session launched directly in a worktree is unrestricted (probed: `X=$(echo hi); echo "subst-ok $X"` printed `subst-ok hi` from a worktree cwd), but a session that called `EnterWorktree` **before** `/pr-ready` was invoked is already isolated at 0.3. Phase 0 therefore has to run under both regimes, and the 0.3 guard block is written in the script-file shape for exactly that reason. It is also **re-run** after step 4's `EnterWorktree`, which is isolated by definition — so `Write` the guard file once at 0.3, *before* any `EnterWorktree`, then `bash` it up to three times; no `Write` ever needs to land post-isolation. **Exempt:** any command passed to `Monitor`, which is not gated (probed: `Monitor(command: 'X=$(echo hi); echo "$X"')` emits `hi`, so the Phase 4.5 watcher loop runs as written). Everywhere else, use one of two shapes:
  - **Multi-command blocks** — `Write` the block to `/tmp/pr-ready-<step>-<N>.sh`, then run `bash /tmp/pr-ready-<step>-<N>.sh` as one plain command. Key the filename to the PR number, never `$$`.
  - **Single-value captures** (the master pin, `STRICT`, the branch name) — run the bare command so it **prints** the value, then hold that value as a **literal** in the run notes and substitute it into every later command.
- **A value captured in one Bash call does not survive to the next — hold it as a literal, not a shell variable.** Every Bash call is a fresh shell, so `MASTER_SHA=$(git rev-parse origin/master)` in one call is *empty* in the next, and `git rev-list --count "$MASTER_SHA"..HEAD` then degrades to `git rev-list --count ..HEAD`, which exits 0 printing `0` — a silent wrong answer that skips the Phase 2c squash gate for every PR. `<MASTER_SHA>` in a later block therefore means **substitute the recorded literal here**. That form also fails *closed*: forget to substitute and git rejects the literal `<MASTER_SHA>` as a bad rev, where `"$MASTER_SHA"` would have failed open. The same rule is why every `/tmp` path in this skill is keyed to `<N>` rather than `$$`.
- **Every repo file this skill reads after Phase 0 comes from `git show <MASTER_SHA>:<path>` — never a bare repo-relative `Read`.** That covers all three progressive-disclosure includes and the linear-history rule the Phase 2 include names. The reason is structural, not stylistic: on the ordinary path the harness loads this `SKILL.md` from the **main checkout** and Phase 0.4 then `EnterWorktree`s into the *target PR's* worktree — and that tree is by definition behind master, so the skill's own files are simply **absent** there until the branch rebases, which is Phase 2, the phase whose instructions live in the missing file. (On the 0.3 `ALREADY-IN-TARGET` path the loaded `SKILL.md` is the target worktree's own copy, so skill discovery *did* resolve inside a worktree — the rule holds unchanged there, because that tree is exactly the one whose includes may be missing. On the 0.3b `WRONG-WORKTREE` path the loaded copy came from a *third* tree — the one the session started in, which has nothing to do with the PR — while the includes still come from `<MASTER_SHA>`; that is documentation, not a new case, because the rule already forbids reading any include by repo-relative path.) The main-checkout path is not a fallback: reading it from a worktree-entered session is **denied** by the cross-worktree straddle gate in `~/.claude/hooks/plan-gate-edit.sh`. `git show` against the Phase 1.3 pin sidesteps both horns — same shared object store, no gate crossed, no `$(…)` needed, and the include is guaranteed to be the revision that matches the `SKILL.md` the harness loaded. Measured before this was fixed: `_rebase-and-conflicts.md` existed in **1 of 34** worktrees on this machine — the skill's own.
  - **Exactly one fallback for the orchestrator's three progressive-disclosure includes — `_rebase-and-conflicts.md` (Phase 2), `_plan-fidelity-review.md` (Phase 6), and `_remediation.md` (Phase 6.5) — and it must be declared.** (The Phase 2 delegation packet carries its own separate fallback for the rule file it names — declared in the delegate's report line 1, per `_rebase-and-conflicts.md`. That one is not covered by, and does not violate, this clause.) `git show` fails with `fatal: invalid object name` or `path … does not exist` when the pinned master predates this skill — which, once merged, happens only when you are dogfooding `/pr-ready` on its own still-open PR. In that case, and only when the file is genuinely present in the current worktree, `Read` it by path and record `include-source: worktree (pin predates skill)` in the verdict. If neither source yields the file, print `STOP: cannot load <file> from <MASTER_SHA> or from the worktree` and stop. Never reach for the main-checkout path — that is the gated horn, and a denial there is not a signal to retry.
- **`STOP:` lines are hard stops for the model, not just for the shell.** Five runtime blocks below print a `STOP:` line: the Phase 0 argument gate (0.1), the Phase 0 worktree guard (0.3, on an unresolvable head branch or a missing `bin/lib/git-helpers.sh`), the Phase 0 exit-recovery check (0.3b, when the post-`ExitWorktree` re-run does not print `MAIN-CHECKOUT`), the Phase 0 worktree-entry step (0.4, on a missing worktree or a post-`EnterWorktree` re-run that does not print `ALREADY-IN-TARGET`), and the Phase 1 plan check (1.1). The include-fallback clause above prints a sixth, from whichever phase was loading an include. Where such a block runs `exit 1`, that `exit` terminates **only that Bash invocation** — it does not terminate the skill, because a skill's blocks are run by the model. The contract is therefore: **on a non-zero rc, or on printing a `STOP:` line, stop the run and make no further tool call.** None of the five relies on shell exit status to halt anything but the shell, so in all five the printed line *is* the gate. Only 0.3's own two failure arms gate via `exit 1`; 0.1 and 1.1 have no shell block at all. **0.3b and 0.4 gate on the printed word, never on the rc** — both re-run the *same* guard script, and its remaining arms all exit 0, so a re-printed `WRONG-WORKTREE` (or a `MAIN-CHECKOUT` where `ALREADY-IN-TARGET` was required) is a passing shell carrying a failing verdict. 0.4's `git worktree list` likewise only captures data, so its rc carries no signal either. The `gh pr view` whose failure that rc used to mask now lives in 0.3, behind an explicit empty-`SLUG` check that does exit non-zero.

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

5. **Prior-Phase-4B probe.** Look for the review heading in **both** the issue comments and the review bodies — findings are posted as a review body with inline threads, not only as issue comments. Multi-command, so per the invariants it runs via the script-file shape: `Write` this to `/tmp/pr-ready-4bprobe-<N>.sh`, then run `bash /tmp/pr-ready-4bprobe-<N>.sh`.

   ```bash
   gh api "repos/{owner}/{repo}/issues/<N>/comments" --paginate \
     --jq '.[] | select((.body // "") | test("(?m)^#{1,6} +Code review\\b")) | "comment\t\(.id)\t\(.user.login)\t\(.created_at)"'
   gh api "repos/{owner}/{repo}/pulls/<N>/reviews" --paginate \
     --jq '.[] | select((.body // "") | test("(?m)^#{1,6} +Code review\\b")) | "review\t\(.id)\t\(.user.login)\t\(.submitted_at)"'
   ```

   (**The `(?m)` is load-bearing — never reduce it to a bare `^`.** In jq's Oniguruma engine a bare `^` anchors to **string start only**, not to each line start; `(?m)` is what makes it line-anchored. Dropping it breaks the probe on the shape every review actually has — the helper wraps its output in `<details><summary>…`, so the heading is never at offset 0. Measured 2026-08-14 on jq 1.7.1 against a `<details>`-wrapped body: bare `^…` ⇒ `false`, `(?m)^…` ⇒ `true`, engine-agnostic `(^|\n)…` ⇒ `true`. This failure is the **mirror image** of the false-positive risk flagged at the end of this step: it makes `PHASE_4B_RAN` falsely *false*, so Phase 6 reports "never reviewed" for a PR that was reviewed, and re-recommends `/pr-review` needlessly. Do not "correct" `(?m)` to `(?s)` either — `(?s)` is dot-matches-newline, a different flag, verified the same day.)

   **Match the heading at any level (`#{1,6}`), never a fixed one.** The helper that posts it — `post_review_summary` / `post_review_findings` in `bin/lib/post-review-findings.sh` — emits `### Code review` in its source today, yet **every** review comment actually on a PR right now carries `## Code review`, and GitHub renders both identically so nothing looks wrong. Measured 2026-08-14 across PRs #1790, #1872 and #1876: an h3-pinned probe matched **zero** of the six genuine review comments those PRs carry between them. So the level-pinned form was not "occasionally stale" — it made `PHASE_4B_RAN` structurally false for every PR, which reads as "this PR was never reviewed" and sends the verdict's headline recommendation the wrong way. This is why the probe is a command rather than an instruction to eyeball the output: a heading is prose, and prose gets matched by whatever regex the reader assumes.

   Record `PHASE_4B_RAN` (any line printed ⇒ true) **and the earliest timestamp printed**, which runtime Phase 6 reports. This is a **probe, not a gate**: the value is reported in Phase 6 and never used to skip work.

   **A match is evidence, not proof — read the lines before recording `true`.** Loosening the level trades one error for its mirror: a comment that merely *quotes* a review heading at line-start (another `/pr-ready` verdict, a pasted excerpt) matches too, and a false `PHASE_4B_RAN=true` is the worse failure — Phase 6 then asserts a review ran and **suppresses** the `/pr-review <N>` recommendation on a PR that never got one. The `.user.login` field above is there for this check: confirm each hit is from the reviewing identity and that the heading is the comment's own, not something it is citing. On PRs #1790/#1872/#1876 all six hits were genuine and none of the surrounding `/pr-ready` verdicts matched — their heading mentions are inline-backticked, not line-initial — but that is an observation, not a guarantee.

**Phases 2 and 3 — rebase and conflict resolution.**

Run `git show <MASTER_SHA>:.claude/skills/pr-ready/_rebase-and-conflicts.md` — the Phase 1.3 literal substituted — and follow the printed file end-to-end before continuing. **Do not reach for it by path first**: per the `git show` invariant above, the worktree you are now in almost certainly does not contain it, and the main-checkout copy is behind the straddle gate. On a `git show` failure take the single declared fallback in that invariant — nothing else. It holds the Phase 2 delegation packet and the Phase 3 three-way conflict-resolution procedure. Pass the delegate the pinned `<MASTER_SHA>` literal from Phase 1.3 — never let it resolve `origin/master` itself.

**Phase 4 — prove nothing was lost, push, watch CI.**

1. **Load the deferred watcher tools here, not in Phase 0:** `ToolSearch("select:Monitor,TaskStop")`. Deferring keeps `Monitor`'s long schema out of context for the phases that never use it.

2. **Lost-work proof, two signals.** `git cherry -v origin/<branch> HEAD` is the weak signal: after a squash **every** replayed commit shows `+` by design, so `git cherry` alone cannot carry the proof. The authoritative check is content equivalence of the tree diff captured before and after the rebase (the Phase 2 include wrote `/tmp/pr-ready-diff-pre-<N>.patch`, keyed to the PR number — **never `$$`**, which differs between that call's shell and this one's).

   The block below is multi-command, so per the invariants it runs via the script-file shape: `Write` it to `/tmp/pr-ready-lostwork-<N>.sh`, then run `bash /tmp/pr-ready-lostwork-<N>.sh`.

   ```bash
   set -o pipefail   # without this, the `||` guards below catch `sort`'s status, not `git apply`'s
   PRE=/tmp/pr-ready-diff-pre-<N>.patch
   POST=/tmp/pr-ready-diff-post-<N>.patch
   git diff origin/master...HEAD > "$POST" || { echo "TREE DIVERGED — could not capture the post-rebase diff"; exit 1; }
   for f in "$PRE" "$POST"; do
     [ -s "$f" ] || { echo "TREE DIVERGED — $f is missing or empty; nothing was compared"; exit 1; }
   done
   git apply --numstat "$PRE"  | sort > /tmp/pr-ready-numstat-pre-<N>.txt  || { echo "TREE DIVERGED — git apply --numstat failed on $PRE"; exit 1; }
   git apply --numstat "$POST" | sort > /tmp/pr-ready-numstat-post-<N>.txt || { echo "TREE DIVERGED — git apply --numstat failed on $POST"; exit 1; }
   [ -s /tmp/pr-ready-numstat-pre-<N>.txt ] || { echo "TREE DIVERGED — numstat of $PRE is empty"; exit 1; }
   if diff /tmp/pr-ready-numstat-pre-<N>.txt /tmp/pr-ready-numstat-post-<N>.txt; then
     echo "TREE-EQUIVALENT"
   else
     echo "TREE DIVERGED — inspect before pushing"
   fi
   ```

   **The guards are the point: this proof must fail closed.** The naked `diff <(…) <(…) && echo "TREE-EQUIVALENT"` form it replaces printed the *proceed* word when it had compared nothing — `git apply --numstat` on a missing patch exits 128 writing only to stderr, `sort` of empty input is empty, and `diff` of two empty streams exits 0. So a `/tmp` clean, a re-invocation, or a silently failed Phase 2a capture all produced `TREE-EQUIVALENT`, and Phase 4.3 pushed on it. Every failure path above emits the stop word instead.

   `TREE DIVERGED` is expected **only** when Phase 3 resolved a real conflict; in that case name each diverging path in the Phase 6 verdict. Any other divergence — including a guard trip, which means the comparison never happened — stops the run.

3. **Push with an explicit refspec and an explicit lease ref — three numbered steps, four Bash calls (3c reads twice).** A bare `--force-with-lease` publishes nothing on a worktree branch with no upstream (measured 2026-08-24 against a local bare repo: `fatal: The current branch <b> has no upstream branch`, remote ref unchanged; the exit status is `push.default`-dependent, so never treat "no recognised error" as "pushed"), so the lease value must be supplied by hand. `$(…)` is unavailable here and values do not survive between Bash calls, so read each SHA off the printed output and type it as a literal into the next call.

   **3a — read the remote's current value for THIS branch:** `git ls-remote origin <branch>`

   Read the 40-char SHA from the printed line. **Do not use `git ls-remote origin HEAD`** — that returns origin's default-branch (master) tip, not this branch's, and the lease would never match. If the output is **empty** the branch has no remote ref yet; use `0000000000000000000000000000000000000000`, which is git's value for "this ref must not exist".

   **3b — push:** `git push -u --force-with-lease=<branch>:<remote-sha-from-3a> origin <branch>:<branch>`

   Never a bare `--force`. The lease catches a concurrent push into the same branch: if someone pushed between 3a and 3b, git rejects with `stale info` — stop and re-run Phase 3; never re-read and retry with a fresh lease.

   **3c — verify the remote actually moved (two reads, one step):** `git ls-remote origin <branch>`, then `git rev-parse HEAD`.

   If the two SHAs differ, print `PUSH FAILED` and stop — do not continue to Phase 5. "No error printed" is not evidence of a push.

4. **`mergeable=UNKNOWN` handling — bounded, and never with a foreground `sleep`.** GitHub computes mergeability asynchronously, so the first read right after a push is usually `UNKNOWN`. Read once:

   ```bash
   gh pr view <N> --json mergeable,mergeStateStatus,state
   ```

   If the answer is anything other than `UNKNOWN`, act on it now — in particular `mergeStateStatus=DIRTY` means the rebase did not actually clear the conflict, so stop and re-run Phases 2–3 rather than pushing on. If it **is** `UNKNOWN`, **do not wait here**: proceed to step 5, whose watcher polls `mergeStateStatus` on every iteration and breaks immediately on `DIRTY`. That is the resolution path — the watcher is the wait, and a conflict surfaces on its first poll rather than after CI finishes. Do not re-read here, and do not loop.

   **Never insert a foreground `sleep` to bridge the gap.** The harness refuses one (`Blocked: sleep 30 … To wait for a condition, use Monitor with an until-loop … Do not chain shorter sleeps to work around this block`), so a `sleep`-based wait does not merely cost time — it hard-fails the call and stalls the run.

5. **CI watcher — exactly one, keyed to the head SHA.** If a watcher from an earlier iteration is live, kill it first with `TaskStop(task_id: "<the id recorded when it was armed>")`. **Record the id `Monitor` returns in the run notes at arm time** — `TaskStop` has no "stop all" form, so an unrecorded id is an orphaned watcher. **Never** poll with `sleep N; gh pr checks` on the main thread: that re-reads the full orchestrator context on every call, the spend bug `.claude/rules/work-triage.md` names.

   Arm one `Monitor` with `description`, `persistent`, and `timeout_ms` all set (all three are required):

   ```
   Monitor(
     description: "CI checks for PR <N> @ <HEAD_SHA>",
     persistent: false,
     timeout_ms: 3600000,
     command: <the bash loop below>
   )
   ```

   ```bash
   HEAD_SHA="$(git rev-parse HEAD)"; prev=""; prev_ms=""; seen=""; grace=10
   while true; do
     v="$(gh pr view <N> --json headRefOid,mergeStateStatus 2>/dev/null || echo '{}')"
     live="$(jq -r '.headRefOid // ""' <<<"$v")"
     [ "$live" = "$HEAD_SHA" ] && seen=1
     if [ -z "$seen" ]; then
       grace=$((grace - 1))
       if [ "$grace" -le 0 ]; then
         echo "STALE: head never reached $HEAD_SHA (last seen ${live:-none}); this watcher is obsolete"; break
       fi
       sleep 30; continue
     fi
     if [ -n "$live" ] && [ "$live" != "$HEAD_SHA" ]; then
       echo "STALE: head moved $HEAD_SHA -> $live; this watcher is obsolete"; break
     fi
     ms="$(jq -r '.mergeStateStatus // ""' <<<"$v")"
     if [ -n "$ms" ] && [ "$ms" != "$prev_ms" ]; then echo "mergeStateStatus: $ms"; prev_ms="$ms"; fi
     if [ "$ms" = "DIRTY" ]; then
       echo "MERGE CONFLICT: mergeStateStatus=DIRTY; stop and re-run Phases 2-3"; break
     fi
     s="$(gh pr checks <N> --json name,bucket 2>/dev/null || echo '[]')"
     cur="$(jq -r '.[] | select(.bucket!="pending") | "\(.name): \(.bucket)"' <<<"$s" | sort)"
     comm -13 <(printf '%s\n' "$prev") <(printf '%s\n' "$cur")
     prev="$cur"
     jq -e 'length > 0 and all(.[]; .bucket!="pending")' <<<"$s" >/dev/null && { echo "CI COMPLETE"; break; }
     sleep 30
   done
   ```

   The emitted line is `"\(.name): \(.bucket)"` and **not** a success-only filter because **silence is not success**. A filter matching only `pass` stays mute through `fail`, `cancel`, `skipping`, and `action_required`, and mute is indistinguishable from "still running". The `mergeStateStatus` line is emitted on **change**, which is what resolves step 4's `UNKNOWN` without a wait — the first poll prints the real value, and `DIRTY` breaks out on that same poll rather than after CI finishes.

   **The `seen` gate is what makes the rest of the loop trustworthy — do not remove it.** This watcher is armed immediately after the Phase 4.3 push, and GitHub's API serves the **previous** head for a window of seconds afterwards. Without the gate that window produces two failures, and the second is the dangerous one: the stale-SHA break false-fires and kills a watcher that is not actually obsolete; and, worse, `gh pr checks` returns the *old* head's already-finished buckets, so `all(.[]; .bucket!="pending")` is true on iteration one and the loop prints a **false `CI COMPLETE`** — the run then proceeds to a verdict having never watched the current head's CI at all. Observed live while dogfooding this skill on its own PR (#1830). So: until `live` has matched `HEAD_SHA` at least once, evaluate **nothing** — not the stale break, not `mergeStateStatus`, not the checks predicate. `grace` bounds that silence at 10 polls (~5 min) so a genuinely superseded push still reports `STALE` promptly instead of idling to the `timeout_ms`.

**Phase 5 — strict re-check loop.**

If `<STRICT>` is false, skip this phase. If true, then after CI reports complete and green, re-check divergence against the *current* master:

```bash
git fetch origin
gh pr view <N> --json mergeStateStatus --jq .mergeStateStatus   # BEHIND => must re-base
```

If `BEHIND`, re-pin — run `git rev-parse origin/master` bare again and record the new printed SHA as `<MASTER_SHA>`, replacing the Phase 1.3 literal — then loop back to Phase 2 with a fresh delegate on the new pin. **Bound the loop at 3 iterations**; on the fourth, stop and report `master is moving faster than this branch can rebase — merge manually or retry when master quiets`. An unbounded loop is the failure mode a strict-protection repo with a busy master produces.

**Phase 5.9 — refresh the machine-generated files-changed block.**

Runs on **every** run, before Phase 6 reads the body — including runs where Phase 5 was skipped. The block's format and splice rules are `/post-plan`'s, not this skill's: `.claude/skills/post-plan/SKILL.md` § *Files-changed block* — build it from `git diff --name-status origin/master...HEAD`, delimit it exactly by `<!-- files-changed:begin -->` / `<!-- files-changed:end -->`, one `` - `<status>` `<path>` `` bullet per file; regenerate and **replace what sits between the two markers** rather than appending a second copy; if only one marker is present, append a fresh block and leave the orphan alone. Do not re-derive that format here.

This refresh is **unconditional and unclassified**. It always runs, always reports its outcome to Phase 7, and is never itself a Phase 6 finding: a stale generated block is a thing this skill fixes, not a thing it reports. Only the `AMBIGUOUS` outcome — duplicate or out-of-order markers, body deliberately left untouched — is a finding, and 6d.4 owns it.

This step needs command substitution, which a Bash tool call may not carry after `EnterWorktree`, so it ships as a committed script — `.claude/skills/pr-ready/scripts/files-changed.sh`, materialised from the pin and run in one call:

```bash
git show <MASTER_SHA>:.claude/skills/pr-ready/scripts/files-changed.sh > /tmp/pr-ready-filesblock-<N>.sh && test -s /tmp/pr-ready-filesblock-<N>.sh && bash /tmp/pr-ready-filesblock-<N>.sh <N>
```

The Bash tool call is that one line and nothing else. If the `test -s` trips, re-run the whole command — never `bash` a leftover `/tmp` copy. The script takes the PR number only: it runs in the **current** worktree, because every Phase 0 path leaves cwd inside the target, and it prints a `STOP:` line rather than proceeding if cwd is not inside a git work tree. It uses no process substitution — `bin/test-pr-ready-now` case 17 asserts the committed script contains zero occurrences.

Record the printed `FILES-CHANGED:` line **verbatim, as a literal**, for the Phase 7 verdict — a value captured in one Bash call does not survive to the next.

Six properties inside that script are load-bearing and must not be "simplified" away: `grep -cFx`/`grep -nFx` over bare `-F`; the `sed 's/\r$//'` CRLF strip; the write-nothing `AMBIGUOUS` arm; `$NF` over `$2` in the awk; the deliberately **unsorted** block order; and the `[ "$lb" -gt 1 ]` guard around BSD `head`. Each carries its measured rationale as a comment at its own site in the script — read them before editing it. `bin/test-pr-ready-now` case 17 pins all six against ten body fixtures.

**Phase 6 — plan-intent fidelity review.**

Run `git show <MASTER_SHA>:.claude/skills/pr-ready/_plan-fidelity-review.md` — same pin, same reason as the Phase 2 include (`git show` invariant above) — and perform the printed review **yourself**. By this phase the rebase has landed, so a path read would *usually* work; it is loaded from the pin anyway, so that the procedure applied never depends on how far behind master the branch started, and never silently reads an older copy of itself off the branch. This phase is NEVER delegated — `.claude/rules/agent-tiering.md`: "Never delegate understanding." Spawning any sub-agent for this phase is a defect.

**Phase 6.5 — Remediation.**

Every Phase 6 finding gets fixed and its prevention filed, in this PR's existing worktree. This is the one amendment to the stop-at-verdict invariant; everything that invariant still forbids stays forbidden.

1. **Load the shared procedure.** `git show <MASTER_SHA>:.claude/skills/fix-and-prevent/_remediation.md` — same pin, same reason as the Phase 2 and Phase 6 includes (`git show` invariant above). Declared fallback, per the include-fallback clause: if `git show` fails and the file is genuinely present in this worktree, `Read` it by path and record `include-source: worktree (pin predates skill)` in the verdict. If neither source yields it, print `STOP: cannot load _remediation.md from <MASTER_SHA> or from the worktree` and stop.

2. **Prove the tree carried nothing else — before the first remediation edit.** `git status --porcelain`. If it prints anything, print `STOP: worktree dirty before remediation` followed by that output, and stop. Phase 0.3 may have entered a worktree that is a peer's active workspace; the `git add -A` in step 4 would sweep their uncommitted work into this commit and step 5 would force-push it. A clean tree here is the normal case — Phase 4.3 step 3c already proved remote == HEAD. Run this once, here at Phase 6.5 entry; from step 3 onward the tree is dirty by design, so never repeat it.

3. **Remediate every finding.** For each Phase 6 finding — **notes as well as blocking**, across all six 6d classes — follow `_remediation.md` in **`Mode: in-PR`**. State that mode line out loud before its step 1; the procedure refuses to run without a declared mode. In-PR mode overrides `/fix-and-prevent`'s § Calibration "Out of scope" carve-outs: a finding too small to name a defect class still gets an entry, with `class: n/a — <reason>`. Never zero entries.
"Never zero entries" binds the findings Phase 6 actually emitted — it does not manufacture one. A Phase 5.9 outcome of `REPLACED`, `APPENDED` or `UNCHANGED` is a routine refresh, not a finding: it produces no remediation entry, no backlog row, and no `last_verified:` bump. Only `AMBIGUOUS` reaches this phase, as the 6d.4 finding it is, and that one does get an entry.

   - **Worktree:** this PR's existing one. Never `bin/wt-new`, never a second worktree, never a teardown.
   - **Backlog:** append the table row and the prose entry to the LIVE backlog file `_remediation.md` step 3 selects, and bump that file's `last_verified:`. Bump no other file's, and do not run the rest of the `/backlog-housekeep` chain. Consolidate findings sharing a surface into one entry.
   - **Fifth-file gate — design the handoff before you reach it, not after the denial.** `~/.claude/hooks/plan-gate-edit.sh` Check 1 **denies** the 5th distinct repo file edited on the main thread in one turn. Count the distinct files edited this turn. Before the 5th, route the remaining fixes to **one** `subagent_type: "sonnet-4-6"` sub-agent (omit `model`; `model: "sonnet"` now resolves to Sonnet 5). State the delegate's boundary out loud before spawning: **remaining code fixes and backlog-row appends only — no commit, no push, no auto-merge arming, no worktree change, and no further delegates** (flat fan-out, per the invariants). Exactly one delegate; if its share is still too large, apply the overflow rule rather than spawning a second.
   - **Overflow rule.** Fix what is clearly in scope of this PR; file the remainder as backlog rows marked `not fixed — filed`; say so in the Phase 7 verdict. A `/pr-ready` run never expands into a sweep.

4. **Commit — exactly one.** Step 2 already proved the tree carried nothing but this phase's own edits.

   Substitutions, so this is a script file: `Write` to `/tmp/pr-ready-commit-<N>.sh`, then run `bash /tmp/pr-ready-commit-<N>.sh`.

   ```bash
   cd /Users/ajaynicolas/GitHub/IBL5-worktrees/<slug> || exit 1
   git add -A
   git commit -m "chore: pr-ready remediation for PR #<N>"
   ```

   `chore:` is correct per `.claude/rules/commit-conventions.md` — a fidelity remediation plus a backlog row is invisible to a league GM. Classify by what the diff is; never retitle to route around a hold.

5. **Push — the Phase 4.3 three-step shape, unchanged.** Never a bare push, never a bare `--force-with-lease` (it publishes nothing on a branch with no upstream).

   - **5a** — `git ls-remote origin <branch>`. Read the 40-char SHA off the printed line. Empty output means no remote ref; use `0000000000000000000000000000000000000000`.
   - **5b** — `git push -u --force-with-lease=<branch>:<remote-sha-from-5a> origin <branch>:<branch>`
   - **5c** — `git ls-remote origin <branch>`, then `git rev-parse HEAD`. If the two SHAs differ, print `PUSH FAILED` and stop. "No error printed" is not evidence of a push.

   On `stale info` someone pushed concurrently: stop and report it in the Phase 7 verdict. Never re-read and retry with a fresh lease.

6. **Re-watch CI on the new head — exactly one watcher.** If the Phase 4.5 watcher is still live, `TaskStop(task_id: "<the id recorded when it was armed>")` first. Then arm **one** `Monitor` on the new head SHA using the Phase 4.5 step 5 watcher loop, with `description`, `persistent: false`, and `timeout_ms: 3600000` all set. **Re-read that loop from Phase 4.5; do not restate it here** — it is the only process-substitution expression in the runtime phases, and duplicating it would break the process-substitution count this file is checked on. Record the new `Monitor` id in the run notes; `TaskStop` has no "stop all" form.

7. **One bounded staleness check, then stop.** After `CI COMPLETE`, and only when `<STRICT>` is `true`, read once:

   ```bash
   gh pr view <N> --json mergeStateStatus --jq .mergeStateStatus
   ```

   If `BEHIND`, state it in the Phase 7 verdict and stop. **Do NOT loop back into Phases 2–3.** Phase 5's 3-iteration rebase loop is a pre-Phase-6 invariant; this is a separate, deliberately single-shot check. Re-rebasing here would invalidate the fidelity review Phase 6 has already performed on a diff that no longer exists.

Then proceed to Phase 7, which posts the single verdict comment covering both the findings and this remediation.

**Phase 7 — verdict and stop.**

1. **Run the shared hold predicates.** `bin/lib/pr-armable.sh` is **sourced, not executed** — it carries no `set -euo pipefail` at file scope by design. Reuse its six predicates rather than re-deriving any hold logic:

   Substitutions again, so this is a script file: `Write` it to `/tmp/pr-ready-holds-<N>.sh`, then run `bash /tmp/pr-ready-holds-<N>.sh`. Sourcing and the six calls have to land in **one** invocation anyway — the predicates are shell functions, and a `source` in one Bash call is gone by the next.

   ```bash
   cd /Users/ajaynicolas/GitHub/IBL5-worktrees/<slug> || exit 1
   source bin/lib/pr-armable.sh
   BODY="$(gh pr view <N> --json body --jq .body)"
   TITLE="$(gh pr view <N> --json title --jq .title)"
   LABELS="$(gh pr view <N> --json labels --jq '.labels')"
   FILES="$(gh pr view <N> --json files --jq '.files')"
   HEADREF="$(gh pr view <N> --json headRefName --jq .headRefName)"
   pr_manual_testing_clearance "$BODY"
   pr_golden_hold "$FILES"
   pr_dep_holds "$BODY"
   pr_feat_hold "$TITLE" "$LABELS"
   pr_pipeline_authored_hold "$LABELS" "$HEADREF"
   pr_unresolved_findings_hold <N>
   ```

   Report each predicate's result as one line in the verdict. These are **advisory inputs to the human's merge decision** — `/pr-ready` never arms auto-merge and never merges.

2. **Post the sticky verdict.** Marker, placed as the **last line of the body** so an update matches:

   `<!-- pr-ready-verdict -->`

   **First write the composed comment body to `/tmp/pr-ready-verdict-<N>.md` with the `Write` tool.** The path is keyed to the PR number for the same reason Phase 2a's is: a `tmpfile=$(mktemp)` assigned in one Bash call is gone by the next one, so the post below would send an empty `--body-file`. Compose the body in full, write it, then run the post.

   There is no helper in `bin/lib/` for this, so use the find-and-update-else-create shape from `bin/pr-canary-check` (see its `STICKY_MARKER` constant and the `post_sticky()` below it — grep the symbols rather than trusting a line number, which drifts).

   The post itself substitutes, so it is a script file too: `Write` it to `/tmp/pr-ready-post-<N>.sh`, then run `bash /tmp/pr-ready-post-<N>.sh`.

   ```bash
   cd /Users/ajaynicolas/GitHub/IBL5-worktrees/<slug> || exit 1
   id=$(gh api "repos/{owner}/{repo}/issues/<N>/comments" --paginate \
     --jq '.[] | select(.body | contains("<!-- pr-ready-verdict -->")) | .id' | head -1)
   if [ -n "$id" ]; then
     gh api --method PATCH "repos/{owner}/{repo}/issues/comments/$id" \
       -F body=@/tmp/pr-ready-verdict-<N>.md --jq .html_url
   else
     gh pr comment <N> --body-file /tmp/pr-ready-verdict-<N>.md
   fi
   ```

   Comment body sections, in order: **rebase result** (the master SHA used, conflicts resolved), **CI result**, **files-changed refresh** (the Phase 5.9 `FILES-CHANGED:` line verbatim — `REPLACED` / `APPENDED` / `UNCHANGED` / `AMBIGUOUS`, with its file count and `+added -removed` delta; on `AMBIGUOUS`, state that the body was left untouched and that the markers need repair), **plan-fidelity verdict**, **remediation** (what Phase 6.5 fixed, each backlog item filed with its file and ID, anything left `not fixed — filed`, and the post-remediation CI result), **hold predicates**, and one explicit **READY / NOT READY** line — the last reflecting the state *after* remediation, not the Phase 6 findings. If any include was loaded by the declared fallback rather than from the pin, say so here — one `include-source:` line — so the verdict states which revision of its own procedure it followed. The files-changed block reflects the diff as of Phase 5.9. If Phase 6.5 pushed remediation commits after it, say so on the refresh line — the block is one commit behind by design, and the next `/post-plan` body write regenerates it. Never open a second body edit to catch it up.

3. **STOP — hard terminator.** The run ends at the posted-or-updated comment. No merge. No auto-merge arming. No `/backlog-housekeep` chain beyond the row and `last_verified` bump Phase 6.5 already filed. No `/post-plan` chain. No worktree teardown. No second comment. The user reviews every PR deliberately.
