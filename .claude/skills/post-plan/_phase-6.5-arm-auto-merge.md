# Phase 6.5 — Arm Auto-Merge (post-plan reference)

Purpose: the per-condition run-and-report blocks for Phase 6.5 arming.

**Condition (1) — Manual-Testing clearance (mechanized via the shared predicate).** Previously a prose gate ("PR says No manual testing needed") the orchestrator eyeballed — itself a judgment-error surface. Now deterministic: `pr_manual_testing_clearance` returns `CLEARED` (the `No manual testing needed` sentinel is present — the positive signal Phase 6 writes after resolving every manual-testing item), `HELD` (real manual rows remain), or `UNKNOWN` (no `## Manual Testing` section at all). **Fail-closed: arm only on `CLEARED`.** Phase 6 always writes the section, so a post-plan PR is `CLEARED` or `HELD`, never `UNKNOWN` on this path — the armed set is unchanged; the stricter `UNKNOWN→block` only matters to `bin/pr-triage`'s cross-PR sweep of hand-made PRs:

```bash
# condition (1): block unless the Manual-Testing section is the positive sentinel.
source "$(git rev-parse --show-toplevel)/bin/lib/pr-armable.sh"
CLEAR=$(pr_manual_testing_clearance "$(gh pr view --json body --jq '.body')")
[ "$CLEAR" != "CLEARED" ] && echo "BLOCKED: Manual-Testing not cleared (state=$CLEAR) — held for human review"
```

**Condition (3) is three-state, and an indeterminate Phase 5.0 BLOCKS.** The bridge file `/tmp/post-plan-missing-tests-$PPID` is truncated at 5.0 START and appended to at 5.0 END, so *empty* is ambiguous on its own: it is byte-identical for "5.0 ran and found nothing unresolved" and "5.0 died before it finished". Reading empty as PASS is a fail-OPEN that contradicts the fail-closed default. The disambiguator is the **done-marker** `/tmp/post-plan-conformance-done-$PPID`, written as the last action of Phase 5.0 on **both** the normal path and the skip path (`PLAN_FOUND=none` or `! $HAS_MATRIX` — see `SKILL.md` Phase 5.0). Marker absent ⇒ indeterminate ⇒ **BLOCKED**; marker present + empty bridge ⇒ clean ⇒ pass. The skip-path write is what keeps this from blocking every plan-blind PR (the dominant case), so treat it as load-bearing, not defensive. The compiled harness computes conformance in-process and uses neither file:

```bash
# condition (3): three-state — missing done-marker means Phase 5.0 never finished,
# which is INDETERMINATE, not clean, so it blocks (fail-closed). $DONE_MARK/$BRIDGE
# are overridable only so bin/test-postplan-arm-conditions can exercise this block.
DONE_MARK="${DONE_MARK:-/tmp/post-plan-conformance-done-$PPID}"
BRIDGE="${BRIDGE:-/tmp/post-plan-missing-tests-$PPID}"
if [ ! -f "$DONE_MARK" ]; then
  echo "BLOCKED: Phase 5.0 conformance never reached its end (no done-marker) — indeterminate, not clean"
elif [ -s "$BRIDGE" ]; then
  echo "BLOCKED: unresolved Phase 5.0 conformance items:"; cat "$BRIDGE"
fi
```

**Condition (4) blocks on the VALUE, not file presence** — the status file is non-empty for `pass` and `skipped` too (it always contains `PHASE5_VERIFY_STATUS=...`), so the `[ -s ... ]` idiom condition (3) uses on its bridge file would wrongly block every `pass`/`skipped`. Block only on the literal `fail` value; **absent file OR `pass` OR `skipped` = PASS (non-blocking)** — a `skipped` status (docs-only / PHP-less PR with no mapped E2E) must NOT block, or every such PR would stop arming, a regression worse than #887:

```bash
# condition (4): fails ONLY when the status is the literal `fail`
grep -q 'PHASE5_VERIFY_STATUS=fail' /tmp/post-plan-phase5-status-$PPID 2>/dev/null && echo "BLOCKED: Phase 5 deterministic failure"
```

**Condition (5) — golden-snapshot safety (headless only).** If `$GOLDEN_CHANGED` is `true` AND `$CLAUDE_HEADLESS` is set, **block** auto-merge: a change to `engine/internal/sim/testdata/golden.json` means the engine's simulation output changed, and a snapshot change with no human present is exactly when not to auto-ship (an agent can turn a red `TestGolden` green by regenerating the snapshot, silently masking a regression). In **interactive** mode (`$CLAUDE_HEADLESS` unset), do **not** block — emit a prominent warning with the same text so the human confirms intent before merging. This condition is independent of `HAS_GO` (a golden-only diff is `HAS_GO=false` but must still trigger it):

```bash
# condition (5): blocks ONLY when golden changed AND running headless (automouse autonomous).
# Self-contained: source the predicate and fetch the live file list here. Detection
# is the Phase-3 diff flag $GOLDEN_CHANGED (a shell var — may be empty in a fresh
# block) OR the shared predicate's live file-list check (pr_golden_hold, the same
# one bin/pr-triage uses — reliable regardless of block isolation). Mode policy
# (headless-only block) stays in the caller; $CLAUDE_HEADLESS is an env var so it
# survives. Interactive (unset) still warns rather than blocks.
source "$(git rev-parse --show-toplevel)/bin/lib/pr-armable.sh"
{ [ "${GOLDEN_CHANGED:-}" = true ] || [ -n "$(pr_golden_hold "$(gh pr view --json files --jq '.files')")" ]; } \
  && [ -n "${CLAUDE_HEADLESS:-}" ] \
  && echo "BLOCKED: golden.json (simulation behavior) changed in headless mode — confirm this was an intentional 'make -C engine golden-update', not a masked regression"
```

**Condition (6) — merge-order dependency (both modes).** When a PR must not merge ahead of another (a refactor that ships first, a migration-number sequence, shared files that re-conflict on merge), its body declares the predecessors with a `Depends-on:` line, e.g. `Depends-on: #1066, #1071`. Arming auto-merge here would let GitHub queue the merge the instant checks pass — out of order — so block until every named PR is `MERGED`. Reads the **live** PR body via `gh` (independent of which branch's skill is running), so a stale local checkout can't bypass it. No `Depends-on:` line ⇒ no dependency ⇒ never blocks. This gate prevents *premature arming only*; it does not rebase — after a predecessor merges, the successor still needs its own `git merge master` + re-green before its checks pass and a later post-plan run can arm:

```bash
# condition (6): block if any Depends-on: predecessor is not yet MERGED — shared
# predicate (also used by bin/pr-triage). Self-contained: source + fetch the body
# here. pr_dep_holds anchors the marker to start-of-line (an inline prose mention
# is ignored) and splits per-line (bash+zsh safe), echoing `depends-on:#N` for
# each unmerged predecessor.
source "$(git rev-parse --show-toplevel)/bin/lib/pr-armable.sh"
pr_dep_holds "$(gh pr view --json body --jq '.body')" | while read -r dep; do
  echo "BLOCKED: depends on ${dep#depends-on:} — not yet MERGED"
done
```

**Condition (7) — plan-time hold (`auto_merge: false`).** The plan author predicts at plan-time (via `/plan` Step 4 gate 14) whether the change wants a human at merge, and records it as a line-1 frontmatter field. If the located plan file declares `auto_merge: false`, **block** — the PR opens and gets reviewed, but auto-merge does not arm. Parse the line-1 YAML frontmatter only (a body documenting the syntax can't self-select). **Derive `$PLAN_FILE` inside this block** — bash variables do not survive across phases/shells (see Phase 3's note), so a bare `$PLAN_FILE` assigned in Phase 1 would be empty here and the check would silently no-op. The snippet re-derives the path from the branch slug (the same derivation `bin/post-plan-now` and Phase 1's interactive fallback use; in automouse the authoritative path the postplan prompt supplies is identical, since the queue symlinks plans by branch slug). Absent file or absent field ⇒ no hold:

```bash
# condition (7): block if the plan declares auto_merge: false (line-1 frontmatter only).
# Self-contained: derive PLAN_FILE here (honor an in-block-set value, else slug-derive) so
# the check can't no-op on a non-surviving cross-phase variable.
PLAN_FILE="${PLAN_FILE:-$HOME/claude-plans/$(git rev-parse --abbrev-ref HEAD).md}"
AUTO_MERGE=$(awk '
    NR==1 && /^---[[:space:]]*$/ {infm=1; next}
    infm && /^---[[:space:]]*$/ {infm=0; exit}
    infm && /^auto_merge:[[:space:]]*/ { sub(/^auto_merge:[[:space:]]*/,""); gsub(/[[:space:]]/,""); print; exit }
' "$PLAN_FILE" 2>/dev/null)
[ "$AUTO_MERGE" = false ] && echo "BLOCKED: plan declares auto_merge: false — held for human merge"
```

**Condition (8) — commit-type floor (never arm `feat:`).** A literal PR-title grep, deterministic. Mirrors the required `human-signoff` check (`.github/workflows/human-signoff.yml`) as defense-in-depth, so post-plan **never arms a `feat:` PR in the first place** (no arm-then-strip). This is a floor, not a soft signal — commit-type weighs into condition (9) only to *add* holds on maintenance PRs; it can never *release* a `feat:`:

```bash
# condition (8): block a conventional-commit feature title — shared predicate
# (also used by bin/pr-triage). REFINEMENT: pr_feat_hold is label-aware — a feat:
# carrying the `human-approved` label does NOT block (the maintainer has signed
# off, matching the human-signoff check). At post-plan's normal call time (PR
# just created) no label is present, so this blocks every feat: exactly as before;
# the label-aware path only releases on a later re-run after a human labeled it.
source "$(git rev-parse --show-toplevel)/bin/lib/pr-armable.sh"
FEAT_HOLD=$(pr_feat_hold "$(gh pr view --json title --jq '.title')" "$(gh pr view --json labels --jq '.labels')")
[ -n "$FEAT_HOLD" ] && echo "BLOCKED: feat: PR — auto-merge held for the human-signoff floor (post-plan never arms an unlabeled feat:)"
```

**Condition (9) — PR-time safety verdict (both modes).** Read the realized diff (`/tmp/post-plan-diff-$PPID`) plus the Phase-3 flags (`HAS_MIGRATION`, `GOLDEN_CHANGED`, `COUNT_*`) and the PR title, and **enumerate every concrete reason this PR should wait for a human**. If the list is non-empty, **block**. This catches *drift* — a plan that looked auto-merge-safe at plan-time but whose realized implementation grew past it. Frame it as enumerating holds, **never** as certifying safe: certifying safe is proving a negative, which is unreliable, and the only dangerous failure is *under*-holding (a false ARM); a false HOLD merely costs a human merge. **Bias hard to HOLD on any doubt.** Hold-triggers to look for in the realized diff:

- an introduced or expanded SQL / POST-form / auth-gated surface that a Phase-4 finding scored < 80 did not already flag;
- a destructive or FK-ordering migration, or a column-rename sweep (`HAS_MIGRATION=true` with a diff that drops, renames, or backfills);
- new or redesigned user-visible UI/UX that the plan's matrix did not classify (a CSS component, a rendered page/module, a nav entry / indicator / badge, a new multi-step flow);
- any change whose blast radius or reversibility you cannot confidently bound.

Emit one `BLOCKED: <reason>` line per reason found. Condition (9) can only *add* holds on top of (1)–(8); it never releases one, and it never overrides the `feat:` floor (8) or the required `human-signoff` check.

**Condition (10) — pipeline-authored floor (never arm a pipeline PR).** Two independent axes hold any PR opened by the Discord bug/feature pipeline from auto-merge regardless of commit type — a pipeline `fix:` must never auto-ship to prod. Unlike the `feat:` floor (8) there is **no** override label: a pipeline PR always waits for a human merge. Shared predicate (`pr_pipeline_authored_hold`, also usable by `bin/pr-triage`); self-contained — source the lib and fetch labels and headRefName in-block, exactly like (8):

- **Axis 1 (label):** the PR carries the `pipeline-authored` label.
- **Axis 2 (branch name):** the head ref matches `^bug-[0-9]+(-|$)`. This axis exists because `reconcile_pr_open_rows()` in `bin/bug-pipeline-tick` applies the label on a **later** cron tick than `ship_via_cron()` which arms auto-merge — so at arming time the PR may be unlabeled and axis 1 alone cannot hold it. The digit anchor (`[0-9]+`) is load-bearing: the human branch `bug-pipeline-reproduce-gate` must NOT fire this predicate.

```bash
# condition (10): block if the PR carries the `pipeline-authored` label OR the
# branch name matches `^bug-[0-9]+(-|$)` — shared predicate, two axes.
# UNCONDITIONAL: unlike condition (8)'s feat: floor there is NO override label;
# every bug/feature-pipeline PR holds for a human merge regardless of commit type
# (a pipeline `fix:` must never auto-ship to prod). The branch-name axis guards
# against label-timing: the `pipeline-authored` label is applied by
# reconcile_pr_open_rows() on a later cron tick than ship_via_cron() which arms
# auto-merge, so at arming time the PR is unlabeled and axis 1 alone cannot hold.
# The digit anchor (`^bug-[0-9]+`) is load-bearing: `bug-pipeline-*` human
# branches must not fire. Self-contained: source the predicate and fetch labels
# and headRefName in-block.
source "$(git rev-parse --show-toplevel)/bin/lib/pr-armable.sh"
PIPELINE_HOLD=$(pr_pipeline_authored_hold \
    "$(gh pr view --json labels --jq '.labels')" \
    "$(gh pr view --json headRefName --jq '.headRefName')")
[ -n "$PIPELINE_HOLD" ] && echo "BLOCKED: pipeline-authored PR — auto-merge held; a human reviews and merges every bug-pipeline PR regardless of commit type"
```

**(11) Unresolved scored review finding** — a review thread carrying `<!-- score: N -->`
with N >= 80 that is still unresolved holds the merge. This is the live-state counterpart
to condition (2): (2) only sees findings scored by *this* run, so a >= 80 finding posted
by an earlier post-plan run or by a standalone `/pr-review` / `/security-audit` would
otherwise sail through. Human threads (no score marker) and threads scored below 80 never
block; resolved threads never block. Self-contained: source the predicate and resolve the
PR number in-block.

```bash
# condition (11)
source "$(git rev-parse --show-toplevel)/bin/lib/pr-armable.sh"
PR_NUM=$(gh pr view --json number --jq '.number')
UNRESOLVED_HOLD=$(pr_unresolved_findings_hold "$PR_NUM" | tr '\n' ' ')
case "$UNRESOLVED_HOLD" in
  '') ;;
  *unresolved-findings-api-error*|*unresolved-findings-cap*)
    echo "BLOCKED: cannot verify review-thread state [$UNRESOLVED_HOLD] — fail-closed; merge manually after a look" ;;
  *)
    echo "BLOCKED: unresolved review finding(s) scored >= 80 [$UNRESOLVED_HOLD] — fix the finding, call resolve_review_finding, then re-run post-plan" ;;
esac
```

```bash
# condition (12): plan-intent fidelity. Additive — this block can only print a
# BLOCKED line; it never clears another condition. Three-state and fail-closed: a
# missing or unparseable verdict is INDETERMINATE, not clean, so it blocks.
# There is no pr-armable.sh predicate for this one — the verdict is run-local state
# written by Phase 5.5 — so self-containment means re-deriving inputs in-block, not
# sourcing a lib. $PR_NUM is re-derived here and never inherited (an unexported
# inherit would key the path to the empty string and block every PR forever);
# $FIDELITY_VERDICT_FILE is overridable only so bin/test-postplan-arm-conditions
# can exercise this block. Never keyed to $$ or $PPID — Phase 5.5 wrote this file
# from a different shell.
PR_NUM=$(gh pr view --json number --jq '.number')
FIDELITY_VERDICT_FILE="${FIDELITY_VERDICT_FILE:-/tmp/post-plan-fidelity-verdict-$PR_NUM.md}"
if [ ! -s "$FIDELITY_VERDICT_FILE" ]; then
  echo "BLOCKED: no Phase 5.5 fidelity verdict at $FIDELITY_VERDICT_FILE — indeterminate, not clean"
else
  FID_WORD="$(sed '/^## DIGEST/,$d' "$FIDELITY_VERDICT_FILE" \
    | grep -E '^(READY WITH NOTES|NOT READY|READY)[[:space:]]*$' \
    | tail -1 | sed 's/[[:space:]]*$//')"
  case "$FID_WORD" in
    READY|'READY WITH NOTES') ;;
    'NOT READY') echo "BLOCKED: Phase 5.5 fidelity verdict is NOT READY — remediate the reviewer's findings and re-run /post-plan" ;;
    *) echo "BLOCKED: Phase 5.5 verdict file has no parseable verdict word — indeterminate, not clean" ;;
  esac
fi
```

If met: `gh pr merge --squash --auto`. The `--auto` flag queues the merge — it does **not** merge now, it arms; GitHub executes it once all required status checks pass. Do not sync local to master here; the merge has not happened yet. **Never add `--delete-branch`** — the repo already sets `deleteBranchOnMerge`, so remote cleanup is automatic; the flag's only effects are harmful (local delete fails in a multi-worktree clone, and a parent merge carrying it permanently closes stacked child PRs). `LiveGh.pr_merge_auto` in `tools/postplan-harness/harness/adapters/ghad.py` omits it for the same reason.

If not met: do **not** arm auto-merge. Report which condition(s) blocked — the user merges manually after review. When condition (3) is the blocker, cite which planned test (`MISSING:`) or planned Critical File (`MISSING-FILE:`) is missing by `cat`-ing the bridge file (`cat /tmp/post-plan-missing-tests-$PPID`) into the report. When condition (4) is the blocker, report which Phase 5 track failed (PHPUnit / PHPStan / Go / E2E). When condition (5) is the blocker (headless + golden changed), report that the golden snapshot changed and the merge needs a human to confirm the behavior change was intentional. When condition (6) is the blocker, report which `Depends-on:` PR(s) are not yet `MERGED` — this PR re-arms on a later post-plan run once the predecessor ships (and this PR has been `git merge master`'d + re-greened). When condition (7) is the blocker, report that the plan declared `auto_merge: false` — the PR is open and reviewed but held for a human merge by author intent. When condition (8) is the blocker, report that this is a `feat:` PR — it waits for a maintainer to apply the `human-approved` label (the required `human-signoff` check). When condition (9) is the blocker, list each enumerated hold-reason from the realized-diff verdict. When condition (10) is the blocker, report whether the hold fired on the `pipeline-authored` label, the `^bug-[0-9]+` branch name, or both — every bug-pipeline PR is held for a human merge regardless of commit type. When condition (11) is the blocker, list each held thread's score and report that a review or audit finding scored >= 80 is still unresolved — the human fixes it and calls `resolve_review_finding`, after which a later post-plan run clears the hold. `unresolved-findings-api-error` and `unresolved-findings-cap` are fail-closed outcomes, not findings: report that the GraphQL call failed (or the PR exceeds the 100-thread page) and the merge is held pending a manual look. Continue to Phase 7 regardless, to monitor and fix CI — the fix-and-rerun there clears any red track so a later run can arm (conditions (7), (8), and (10) are intent/type holds that a re-run will not clear — those PRs stay held until the human acts; (11) clears on a re-run only after the threads are actually resolved). For condition (12), the fix is to remediate the fidelity reviewer's findings and re-run /post-plan — never hand-edit or delete the verdict file to clear it, since an absent file is itself a blocking state.

**Interactive golden warning:** Whenever `$GOLDEN_CHANGED` is `true` and `$CLAUDE_HEADLESS` is unset (so condition (5) did not block), still surface the warning prominently in the report — "⚠️ golden.json changed: simulation behavior changed. Confirm this was an intentional `make -C engine golden-update`, not a masked regression." — so the human reviews intent before the queued merge fires.
