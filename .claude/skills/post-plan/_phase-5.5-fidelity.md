---
description: /post-plan Phase 5.5 — plan-intent fidelity review (a carry-forward gate that skips the spawn on an unchanged tree and plan, otherwise one Opus reviewer spawn, plus one bounded re-review after remediation), verdict parse, remediation, and sticky merge-digest comment.
last_verified: 2026-10-05
---

# /post-plan Phase 5.5 — Plan-intent fidelity review & merge digest

Purpose: ask whether the implementation does what the plan *intended*. The semantic question Phase 5.0 structurally cannot answer. The fidelity criteria stay in `.claude/review-shared/_plan-fidelity-review.md`; the remediation procedure stays in `.claude/review-shared/_phase65-remediation.md`. This file sequences them and adds the post-plan-specific glue.

`<MASTER_SHA>` and `<N>` below are **literals to substitute** with the values pinned in step 1 — a value captured in one Bash call does not survive into the next, and every `/post-plan` block runs in a fresh shell.

## Step 1 — Pin the run's identifiers

Each block runs in its own shell; nothing is exported between them. Re-derive everything in-block. `REVIEWED_TREE` is captured **before** the spawn so it names the tree the reviewer actually sees. Substitute the printed values as literals into every step below. `PLAN_SHA256` is the sha256 of the plan file Phase 1 resolved, or the literal `none` on a plan-blind run. Step 6 records it in the sticky audit trail so a later run can tell an unchanged tree with an unchanged plan from an unchanged tree with a rewritten plan.

```bash
PR_NUM=$(gh pr view --json number --jq '.number')
MASTER_SHA=$(git rev-parse origin/master)
REVIEWED_TREE=$(git rev-parse HEAD^{tree})
source "$(git rev-parse --show-toplevel)/bin/lib/plan-resolve.sh"
PLAN_SLUG_DRIFT=""
resolve_plan_file
PLAN_SHA256=none
if [ -n "${PLAN_FILE:-}" ] && [ -f "$PLAN_FILE" ]; then
  PLAN_SHA256=$(shasum -a 256 "$PLAN_FILE" | cut -c1-64)
fi
echo "PR_NUM=$PR_NUM MASTER_SHA=$MASTER_SHA REVIEWED_TREE=$REVIEWED_TREE PLAN_SHA256=$PLAN_SHA256"
```

## Step 1b — Carry-forward gate <!-- slop-ok -->

Purpose: decide whether the sticky comment already carries a terminal verdict for exactly this tree and this plan. The decision is three checks in sequence, all fail-closed: the pinned `skip-review.sh` (one sticky, well-formed `**Reviewed tree:**`, tree equals `HEAD^{tree}`, no conflicts flag, five digest labels), then the prior verdict word, then plan identity. Any `RUN-REVIEW`, a missing script, a parse doubt, or a plan mismatch means Step 2 runs exactly as before. `<MASTER_SHA>` and `<PLAN_SHA256>` are Step 1 literals. `CONFLICT_FLAG` is the empty string unless `_phase-2-conflict-resolution.md` ran this run (Phase 1 printed `REBASE=conflict`), in which case set it to the literal `conflicts-resolved`.

```bash
# phase 5.5 carry-forward probe
PR_NUM=$(gh pr view --json number --jq '.number')
CONFLICT_FLAG=""
SR="/tmp/post-plan-skip-review-$PR_NUM.sh"
OUT="/tmp/post-plan-skip-review-out-$PR_NUM.txt"
rm -f "$SR" "$OUT"
if git show <MASTER_SHA>:.claude/review-shared/scripts/skip-review.sh > "$SR" 2>/dev/null && test -s "$SR"; then
  bash "$SR" "$PR_NUM" "$CONFLICT_FLAG" > "$OUT" 2>/dev/null || printf 'RUN-REVIEW probe-failed\n' > "$OUT"
else
  printf 'RUN-REVIEW script-unavailable\n' > "$OUT"
fi
cat "$OUT"
```

```bash
# phase 5.5 carry-forward decision
PR_NUM=$(gh pr view --json number --jq '.number')
PROBE_OUT="${PROBE_OUT:-/tmp/post-plan-skip-review-out-$PR_NUM.txt}"
PRIOR_BODY="${PRIOR_BODY:-/tmp/pr-ready-prior-verdict-$PR_NUM.md}"
PRIOR_DIGEST="${PRIOR_DIGEST:-/tmp/pr-ready-digest-lines-$PR_NUM.txt}"
CUR_PLAN_HASH="${CUR_PLAN_HASH:-<PLAN_SHA256>}"
HEAD_TREE="${HEAD_TREE:-$(git rev-parse HEAD^{tree})}"
GATE=spawn; REASON=""; CARRY_WORD=""; CARRY_TREE=""
SKIP_LINE=$(grep -E '^SKIP-REVIEW [0-9a-f]{40}$' "$PROBE_OUT" 2>/dev/null | tail -1)
CARRY_TREE="${SKIP_LINE#SKIP-REVIEW }"
if [ -z "$SKIP_LINE" ]; then
  REASON=$(grep -E '^RUN-REVIEW ' "$PROBE_OUT" 2>/dev/null | tail -1)
  REASON="${REASON:-RUN-REVIEW probe-output-missing}"
elif [ "$CARRY_TREE" != "$HEAD_TREE" ]; then
  REASON="tree-mismatch-with-head"
elif [ ! -s "$PRIOR_BODY" ] || [ "$(grep -c . "$PRIOR_DIGEST" 2>/dev/null)" != 5 ]; then
  REASON="carry-forward-files-missing"
else
  TERMINAL=$(awk '{ if ($0=="<!-- pr-ready-verdict -->") {print prev; exit} prev=$0 }' "$PRIOR_BODY" | sed 's/[[:space:]]*$//')
  BANNER_VETO=$(sed -n '1p' "$PRIOR_BODY" | grep -c 'NOT READY')
  PLAN_LINE=$(grep -m1 -E '^\*\*Plan hash:\*\* ([0-9a-f]{64}|none)$' "$PRIOR_BODY" | sed 's/^\*\*Plan hash:\*\* //')
  case "$TERMINAL" in
    "READY"|"READY WITH NOTES") ;;
    *) REASON="prior-verdict-not-terminal" ;;
  esac
  if [ -z "$REASON" ] && [ "$BANNER_VETO" != 0 ]; then REASON="prior-banner-not-ready"; fi
  if [ -z "$REASON" ] && { [ -z "$PLAN_LINE" ] || [ "$PLAN_LINE" = none ]; }; then REASON="plan-identity-absent"; fi
  if [ -z "$REASON" ] && { [ "$CUR_PLAN_HASH" = none ] || [ "$PLAN_LINE" != "$CUR_PLAN_HASH" ]; }; then REASON="plan-changed"; fi
  if [ -z "$REASON" ]; then GATE=skip; CARRY_WORD="$TERMINAL"; fi
fi
echo "FIDELITY_GATE=$GATE"
echo "FIDELITY_GATE_REASON=${REASON:-carry-forward}"
if [ "$GATE" = skip ]; then echo "CARRY_WORD=$CARRY_WORD"; echo "CARRY_TREE=$CARRY_TREE"; fi
```

The `${VAR:-...}` defaults on the five inputs exist so `bin/test-postplan-arm-conditions` can drive the block with fixture files. A live run never sets them.

`CARRY_WORD` can only be the exact string `READY` or `READY WITH NOTES`. A suffixed terminal line (`READY WITH NOTES` plus a remediation suffix, `READY (re-review)` plus a suffix, any `NOT READY` form) never carries. A suffix means that run remediated, so its `**Reviewed tree:**` is the pre-remediation tree and the probe already said `tree-changed`. The exact-match rule is the second lock on the same door.

A sticky with no `**Plan hash:**` line (every sticky written before this gate existed, and every harness sticky whose regex did not fire) never carries. Plan-blind runs (`<PLAN_SHA256>` is `none`) never carry.

On `FIDELITY_GATE=spawn`, continue to Step 2 unchanged and print the reason in the run log. On `FIDELITY_GATE=skip`, run Step 1c, then Step 3, then Step 3b (its guard is a no-op because the carried file already carries `REVIEWED_TREE=`). Skip Steps 2, 4, 4b and 5, and compose Step 6 from the skip-path recipe. `/tmp/post-plan-fidelity-verdict-<N>-2.md` is left alone. Condition (12) already treats a stale `-2` file as fallback-to-verdict-1.

## Step 1c — Materialise the carried verdict (skip path only) <!-- slop-ok -->

Run this step only when Step 1b printed `FIDELITY_GATE=skip`. The block writes the verdict file condition (12) reads, from the sticky comment's own words, with provenance. It refuses anything that is not a terminal word on the current tree, so it can never manufacture an indeterminate or invented verdict. `<CARRY_WORD>` and `<CARRY_TREE>` are the literals Step 1b printed.

```bash
# phase 5.5 carry-forward materialise
PR_NUM=$(gh pr view --json number --jq '.number')
VF="${FIDELITY_VERDICT_FILE:-/tmp/post-plan-fidelity-verdict-$PR_NUM.md}"
PRIOR_DIGEST="${PRIOR_DIGEST:-/tmp/pr-ready-digest-lines-$PR_NUM.txt}"
CARRY_WORD="${CARRY_WORD:-<CARRY_WORD>}"
CARRY_TREE="${CARRY_TREE:-<CARRY_TREE>}"
HEAD_TREE="${HEAD_TREE:-$(git rev-parse HEAD^{tree})}"
case "$CARRY_WORD" in "READY"|"READY WITH NOTES") ;; *) echo "STOP: refusing to materialise '$CARRY_WORD'"; exit 0 ;; esac
[ "$CARRY_TREE" = "$HEAD_TREE" ] || { echo "STOP: carried tree $CARRY_TREE is not HEAD tree $HEAD_TREE"; exit 0; }
[ "$(grep -c . "$PRIOR_DIGEST" 2>/dev/null)" = 5 ] || { echo "STOP: prior digest lines missing"; exit 0; }
{
  printf '%s\n\n' "$CARRY_WORD"
  printf 'REVIEW-COVERAGE: carried-forward; no reviewer spawned this run; word copied from the PR #%s sticky comment terminal line covering tree %s\n\n' "$PR_NUM" "$CARRY_TREE"
  printf '## FINDINGS\n\nFindings: carried forward; see the sticky comment body. Nothing was re-assessed this run.\n\n'
  printf '## DIGEST\n'
  cat "$PRIOR_DIGEST"
  printf 'REVIEWED_TREE=%s\n' "$CARRY_TREE"
} > "$VF"
cp "$PRIOR_DIGEST" "/tmp/post-plan-digest-lines-$PR_NUM.txt"
echo "CARRIED=$VF"
```

The word lands before `## DIGEST`, so Step 3's canonical parse reads it unchanged. `REVIEWED_TREE=` lands after `## DIGEST`, exactly where Step 3b would put it. The `**Machine-authored fixes:**` line is copied as-is, including any `(post-plan remediation: <sha>)` suffix from the prior run.

## Step 2 — Gather the seven inputs, then spawn exactly one reviewer

Skip this step when Step 1b printed `FIDELITY_GATE=skip`. The spawn budget below applies only to the spawn path.

**Bounded-re-spawn rule:** at most two `Agent` spawns per `/post-plan` run, and never more. Spawn the first reviewer now. **Exactly one re-spawn** is permitted, in step 4b, and only when **both** hold: (a) step 4's remediation addressed **every** `Mode: in-PR` finding, and (b) the plan does **not** declare `auto_merge: false`.

**The first verdict is never replaced.** The re-review writes a **separate file** (`/tmp/post-plan-fidelity-verdict-<N>-2.md`); verdict 1's word, findings and digest stay exactly as the reviewer wrote them. The record grows, it never gets rewritten. Never hand-edit or regenerate verdict 1 to flip its word.

If anything was left unfixed, do **not** re-spawn — a reviewer looking at a tree that still carries a known finding buys nothing and costs an Opus turn.

Spawn with `subagent_type: "pr-ready-phase6"` and **omit `model`** so the def's `model: claude-opus-5-5` pin wins. This must be an `Agent` spawn and not a `/pr-ready` invocation: this skill's frontmatter carries `disallowed-tools: [EnterPlanMode, ExitPlanMode, Skill]`, so `Skill` is not callable at all.

The prompt hands the def its five 6b inputs and the output path. Output path: `/tmp/post-plan-fidelity-verdict-<N>.md` (substitute `<N>` with the `$PR_NUM` value from step 1). Keyed to the PR number, never to a per-shell PID — every block is a fresh shell, so a PID-keyed path would be written by one block and unreadable by the next.

Provide these seven inputs in the spawn prompt (items 1, 2, 6, 7 are post-plan-specific; items 3–5 are the `_plan-fidelity-review.md` contract inputs):

1. **Output path** — `/tmp/post-plan-fidelity-verdict-<N>.md`. The def's output contract item 1 writes the verdict to the absolute path the prompt names.
2. **`<MASTER_SHA>`**: the pinned value from step 1, so the def can `git show <MASTER_SHA>:.claude/review-shared/_plan-fidelity-review.md`. If the `Read`-by-worktree-path fallback fires instead, the def records `include-source: worktree (pin predates skill)` and step 6 surfaces that line in the sticky comment.
3. **Plan file** — the plan resolved in Phase 1 (`~/claude-plans/<branch>.md`). On a plan-blind run (`PLAN_FOUND=none`), declare input 1 absent and instruct the reviewer to state that plainly, mark 6d checks 1, 2 and 5 `not assessable — plan-blind run`, still perform checks 3, 4 and 6, and still emit one 6e verdict word plus the 6e(b) digest (taking `**Why:**` from the PR body, which 6e(b) permits). Do **not** skip the review and do **not** synthesise a `NOT READY` — either would block auto-merge on every ad-hoc PR, a behaviour regression.
4. **Full post-rebase diff** — `gh pr diff <N>`. Phase 1's rebase already ran, so this diff is post-rebase by construction; say so in the prompt.
5. **PR body** — `gh pr view <N> --json body`.
6. **Conflict-resolved path list** — provably empty on any run that reaches Phase 5.5, because Phase 1's rebase fail-closes on conflict rather than resolving. State that in the prompt in those words so 6d check 6 passes on the stated grounds. `/tmp/pr-ready-diff-pre-<N>.patch` is neither produced nor referenced.
7. **`PHASE_4B_RAN`** and the review timestamp from Phase 4B's result earlier in this run.

The def returns a thin pointer only (its output contract item 4). Treat the returned text as a pointer and read the file; never treat captured stdout as the verdict.

## Step 3 — Read the verdict word

This is the **single canonical parse** — condition (12)'s block in `.claude/skills/post-plan/_phase-6.5-arm-auto-merge.md` re-derives the identical expression, and `bin/test-postplan-arm-conditions`'s drift guard asserts both copies agree.

Why this shape: 6e(b) requires the verdict file to *end* in the `## DIGEST` section, so the verdict word is never the last line. Deleting from `^## DIGEST` to end-of-file before matching prevents a digest body that happens to contain the word `READY` from being mistaken for the verdict. `tail -1` takes the terminal verdict word when the reviewer restated it. An unmatched or empty result yields `missing`, which condition (12) treats as indeterminate and therefore blocking.

```bash
# phase 5.5 verdict parse
PR_NUM=$(gh pr view --json number --jq '.number')
FIDELITY_VERDICT_FILE="${FIDELITY_VERDICT_FILE:-/tmp/post-plan-fidelity-verdict-$PR_NUM.md}"
if [ ! -s "$FIDELITY_VERDICT_FILE" ]; then
  echo "FIDELITY=missing"
  echo "STOP: Phase 5.5 reviewer wrote no verdict at $FIDELITY_VERDICT_FILE — fail-closed, nothing posted."
else
  FID_WORD="$(sed '/^## DIGEST/,$d' "$FIDELITY_VERDICT_FILE" \
    | grep -E '^(READY WITH NOTES|NOT READY|READY)[[:space:]]*$' \
    | tail -1 | sed 's/[[:space:]]*$//')"
  echo "FIDELITY=${FID_WORD:-missing}"
fi
```

If `$FIDELITY_VERDICT_FILE` does not exist or is empty → `FIDELITY=missing` — STOP. Post nothing. The reviewer either produced no output or wrote to a different path; fail-closed means nothing proceeds.

If `FIDELITY=missing` for any other reason (no parseable verdict word before `## DIGEST`) — STOP. The verdict is indeterminate, not clean.

## Step 3b — Record the reviewed tree on the verdict file

```bash
# phase 5.5 record reviewed tree
PR_NUM=$(gh pr view --json number --jq '.number')
FIDELITY_VERDICT_FILE="${FIDELITY_VERDICT_FILE:-/tmp/post-plan-fidelity-verdict-$PR_NUM.md}"
if [ -s "$FIDELITY_VERDICT_FILE" ] && ! grep -q '^REVIEWED_TREE=' "$FIDELITY_VERDICT_FILE"; then
  printf 'REVIEWED_TREE=%s\n' "$(git rev-parse "HEAD^{tree}")" >> "$FIDELITY_VERDICT_FILE"
fi
grep -m1 '^REVIEWED_TREE=' "$FIDELITY_VERDICT_FILE" || echo "REVIEWED_TREE=unrecorded"
```

The value is the **tree** SHA (`HEAD^{tree}`), the same value step 1 pins as `REVIEWED_TREE` and step 6 prints as `**Reviewed tree:**`. One concept, one name, one value — an operator can grep the sticky comment's SHA straight out of the verdict file.

The append is **guarded and idempotent** — a second pass never writes a second line, and `grep -m1` means only the first would ever be read anyway.

The line lands at **end of file, after the `## DIGEST` section**, so the canonical parse (`sed '/^## DIGEST/,$d'` first) deletes it before the verdict grep ever sees it. The verdict word is unaffected by construction; condition (12) reads the raw file for this field.

Appending a metadata line is **not** editing the verdict: the word, the findings and the digest are untouched. The prohibition that stands is on changing the **verdict word**.

**Negative-path note:** if the verdict file is missing or empty, write nothing and do not create it — an absent verdict is condition (12)'s blocking state and this step must not manufacture a file that makes it look present.

## Step 4 — Remediation on `READY WITH NOTES` (and `NOT READY`)

Load the procedure in place: `git show <MASTER_SHA>:.claude/review-shared/_phase65-remediation.md`. Run it as written, including its step 2 clean-tree precondition (`STOP: worktree dirty before remediation`) and its fifth-file gate handoff to one `subagent_type: "sonnet-5-5"` delegate. On the harness path, the harness commits and pushes after the phase exits. On the skill-fallback path, the `/post-plan` skill's own commit and push phases follow.

Three post-plan-specific rules on top — these are where a re-spawn would otherwise creep in:

1. **Two channels; the gate word is frozen, the comment's last line is not.** The `FIDELITY=<word>` that condition (12) reads is the step-3 word and never changes — remediation never upgrades it and never re-runs the reviewer. The sticky comment's **terminal verdict line** is a separate channel governed by `_phase7-verdict.md`: it states what this run left the PR in, so it must name its own reason whenever that state is anything but a plain `READY`. Compose it from the terminal-line recipe below; never emit the bare step-3 word as the last line for any outcome other than a plain `READY`.
2. **`**Reviewed tree:**` keeps the step 1 value** — the tree the reviewer actually saw, not the post-remediation tree. That line tells a reader exactly how much of the shipped head the verdict covers.
3. **The remediation commit is named in the existing `**Machine-authored fixes:**` digest label** — no new field, no sixth line. Append ` (post-plan remediation: <sha>)` to that one line's value. This is the only permitted deviation from `_phase7-verdict.md`'s paste-verbatim rule; it changes a value, not the label set.

On `NOT READY`, run the same remediation for every `Mode: in-PR` finding, then stop. `FIDELITY` stays `NOT READY` and condition (12) will block; do not attempt to reach `READY` and do not re-spawn the reviewer.

### Terminal-line recipe

The last line of the sticky comment, immediately above the `<!-- pr-ready-verdict -->` marker. The row is chosen by two lookups, not by judgement. Column 1 is the step-3 `FIDELITY` literal. Column 2 is decided by re-reading `$FIDELITY_VERDICT_FILE`'s finding list — `Mode: in-PR` treats every Phase 6 finding, notes and blockers alike — and checking each one against what step 4's commit actually changed. Every finding addressed is the "fixed" row; anything else is the "left unfixed" row, and the unfixed finding is named in the line by copying its title verbatim. Re-read the file; do not answer this from memory of what step 4 did. The `READY`/`NOT READY` **prefix** is load-bearing: `bin/pr-cycle`'s verdict parse anchors on it (`"NOT READY"*` is a prefix glob) and `bin/pr-ready-now`'s `derive_status` anchors likewise, so a trailing ` — <reason>` is invisible to both. Never put the reason *before* the word.

| Step-3 `FIDELITY` | Step 4 outcome | Terminal line |
|---|---|---|
| `READY` | step 4 skipped | `READY` |
| `READY WITH NOTES` | every `Mode: in-PR` finding fixed | `READY WITH NOTES — all notes remediated in <sha>; reviewer verdict covers tree <REVIEWED_TREE>, not the post-remediation head` |
| `READY WITH NOTES` | something left unfixed | `READY WITH NOTES — <what remains, named>; remediated the rest in <sha>` |
| `NOT READY` | all fixed; step 4b re-review returned `READY` or `READY WITH NOTES` | `READY (re-review) — findings remediated in <sha> and re-reviewed clean on tree <tree>; condition (12) arms on the next /post-plan Phase 6.5 run` |
| `NOT READY` | all fixed; step 4b re-review returned `NOT READY` | `NOT READY (re-review) — <what the re-review still blocks on, named>; remediate and re-run /post-plan` |
| `NOT READY` | all fixed; step 4b skipped (`auto_merge: false`) | `NOT READY — held for your final review` |
| `NOT READY` | something left unfixed | `NOT READY — <what remains, named>` |

**A `NOT READY` line carries action items only.** Its suffix names what the reader must still
do to reach `READY` — nothing else. Remediation the run already completed is **not** an action
item and never appears there: work that is done is evidence *for* readiness, so narrating it
under a blocking word reads as a contradiction and buries the one thing the reader has to act
on. The remediation `<sha>` is not lost — step 4 point 3 above already appends it to the
`**Machine-authored fixes:**` digest label, which is where a reader looks for what the run
changed. Likewise, `auto_merge: false` needs no explanation of the mechanism: the action item
is simply `held for your final review`. `READY`-prefixed rows are unaffected — on a passing
verdict the extra context is doing real work.

This does not relax PR #2192's lesson ("don't mis-report the reason for the hold") — terse is
not mis-reported; each `NOT READY` row still states its own true reason, just only that. The
hold itself is unchanged for both `NOT READY` remediated rows — condition (12) still reads the
frozen `FIDELITY` word from the verdict file, never this line. The `READY (re-review)` line
signals the fidelity hold is cleared, but **does not mean the PR is armed** — Phase 6.5 has not
run yet, and conditions (1)-(11) and (13) may still hold it.

Skip this step entirely when `FIDELITY=READY`.

## Step 4b — Bounded re-review on a fully remediated tree

Run this step only when step 4 addressed **every** `Mode: in-PR` finding. Skip it when `FIDELITY=READY` (step 4 never ran) or when any finding was left unfixed.

**Skip gate first.** Reuse the existing condition-(7) resolver rather than re-implementing plan lookup — `bin/lib/plan-resolve.sh` sets `$PLAN_FILE`, and the `awk` below is the same line-1-frontmatter parse condition (7) uses. The `</dev/null` is load-bearing: macOS `awk` with a program and no file argument reads STDIN and hangs.

```bash
# phase 5.5 re-review skip gate
source "$(git rev-parse --show-toplevel)/bin/lib/plan-resolve.sh"
PLAN_SLUG_DRIFT=""
resolve_plan_file
AUTO_MERGE=""
if [ -n "${PLAN_FILE:-}" ] && [ -f "$PLAN_FILE" ]; then
    AUTO_MERGE=$(awk '
        NR==1 && /^---[[:space:]]*$/ {infm=1; next}
        infm && /^---[[:space:]]*$/ {infm=0; exit}
        infm && /^auto_merge:[[:space:]]*/ { sub(/^auto_merge:[[:space:]]*/,""); gsub(/[[:space:]]/,""); print; exit }
    ' "$PLAN_FILE" </dev/null 2>/dev/null)
fi
echo "RE_REVIEW=$([ "$AUTO_MERGE" = false ] && echo skip || echo spawn)"
```

On `RE_REVIEW=skip`, do not spawn. The plan's author already decided a human merges this PR, so a second Opus verdict changes nothing that will happen to it. Terminal line for that case is the `auto_merge: false` row in the terminal-line recipe above.

On `RE_REVIEW=spawn`, spawn **one** reviewer: `subagent_type: "pr-ready-phase6"`, **omit `model`** so the def's `model: claude-opus-5-5` pin wins. An `Agent` spawn. This skill's frontmatter carries `disallowed-tools: [EnterPlanMode, ExitPlanMode, Skill]`.

**Output path:** `/tmp/post-plan-fidelity-verdict-<N>-2.md` (substitute `<N>` = step 1's `$PR_NUM`). PR-number-keyed, never `$$`/`$PPID`-keyed — condition (12) reads it from a different shell. The `-2` suffix ensures verdict 2 can never overwrite verdict 1.

**The same seven inputs as step 2**, with two changes: input 4 is the **post-remediation** diff (`gh pr diff <N>` re-run after step 4's push, so it includes the remediation commit), and one added pointer — "reviewer 1's findings are at `/tmp/post-plan-fidelity-verdict-<N>.md`; confirm each was addressed by the remediation commit, and report a new finding only if the remediation itself introduced one."

After the reviewer returns, re-run step 3's parse against the `-2` path to get the second verdict word, then run step 3b's record block against the `-2` path so verdict 2 also carries `REVIEWED_TREE=`. Both must run **after** step 4's push, so the recorded tree is the tree the reviewer saw.

**Findings hand-off.** Verdict 2's `## FINDINGS` body is what the digest renders, so extract it into `V2_FINDINGS` in the same step that recorded the tree. The block is self-contained, because each skill bash block runs in its own shell (`.claude/rules/shell-portability.md` § Skill inline bash), so it re-derives the `-2` path instead of inheriting a variable. Every degrade path leaves `V2_FINDINGS` empty: step 4b's failure-path contract below treats that as "no re-review happened".

```bash
# phase 5.8 findings render — extract verdict 2's FINDINGS body for the digest
VERDICT_2_FILE="/tmp/post-plan-fidelity-verdict-<N>-2.md"   # <N> = step 1's $PR_NUM
V2_FINDINGS=""
if [ -f "$VERDICT_2_FILE" ]; then
    V2_FINDINGS=$(awk '
        /^## FINDINGS/ {found=1; next}
        found && /^## / {exit}
        found {print}
    ' "$VERDICT_2_FILE" 2>/dev/null)
    # whitespace-only body counts as absent
    printf '%s\n' "$V2_FINDINGS" | grep -q '[^[:space:]]' || V2_FINDINGS=""
fi
printf 'V2_FINDINGS_PRESENT=%s\n' "$([ -n "$V2_FINDINGS" ] && echo yes || echo no)"
[ -n "$V2_FINDINGS" ] && printf '%s\n%s\n' '--- V2_FINDINGS ---' "$V2_FINDINGS"
```

**Failure paths:** if the re-review writes no file, or writes one with no parseable verdict word, treat it as "no re-review happened" — verdict 1 stands, condition (12) blocks on verdict 1's word, and the terminal line is the fully-remediated `NOT READY` row. Never re-spawn a third time to retry.

## Step 5 — Materialise the digest lines

On the skip path do not run this chain. Step 1c already copied the five prior lines to `/tmp/post-plan-digest-lines-<N>.txt`, and running `digest.sh` here would clobber them.

Mirror `_phase7-verdict.md`'s chain exactly, pointed at post-plan's verdict path. `digest.sh` takes the verdict file as `$1`, so it works unchanged. The **trailing `cat` is load-bearing** — without it the five lines sit on disk and never enter context, so there is nothing to paste into the `Write` call in step 6. `digest.sh` exits 0 on every degrade path and prints five `unavailable — <reason>` lines rather than failing, so this chain never aborts the run.

```bash
git show <MASTER_SHA>:.claude/review-shared/scripts/digest.sh > /tmp/post-plan-digest-<N>.sh \
  && test -s /tmp/post-plan-digest-<N>.sh \
  && bash /tmp/post-plan-digest-<N>.sh /tmp/post-plan-fidelity-verdict-<N>.md > /tmp/post-plan-digest-lines-<N>.txt \
  && cat /tmp/post-plan-digest-lines-<N>.txt
```

## Step 6 — Compose and post the sticky comment

**Write the composed body to `/tmp/post-plan-fidelity-comment-<N>.md` with the `Write` tool first**, then post — a `tmpfile=$(mktemp)` assigned in one Bash call is gone by the next, so an inline compose would send an empty `--body-file`.

The body template (write to `/tmp/post-plan-fidelity-comment-<N>.md` with the `Write` tool):

```
**<FIDELITY word>** — <YYYY-MM-DD HH:MM:SS TZ>

<Phase 1 REBASE= line verbatim; omit when it is a clean or plain-rebase success spelling>
<CI: result, and post-remediation CI result when step 4 ran; omit when local verification passed>

<reviewer findings from the verdict file: drop every REVIEW-COVERAGE: line and the bare
verdict word; when ## FINDINGS is empty, write `Findings: none.` in its place; keep an
include-source: line when the fallback fired>

<findings slot — when `$V2_FINDINGS` (extracted in step 4b) is non-empty, emit a blank line, then its content pasted VERBATIM (no condensing, no re-wording, no re-ordering), then a blank line, before the `### Merge digest` heading below. When `$V2_FINDINGS` is empty, or step 4b did not run, emit nothing here and leave the single blank line above `### Merge digest` exactly as it is.>

### Merge digest
**What changed:** <paste line 1 from /tmp/post-plan-digest-lines-<N>.txt>
**Why:** <paste line 2 from /tmp/post-plan-digest-lines-<N>.txt>
**Watch:** <paste line 3 from /tmp/post-plan-digest-lines-<N>.txt>
**Touches:** <paste line 4 from /tmp/post-plan-digest-lines-<N>.txt>
**Machine-authored fixes:** <paste line 5; append " (post-plan remediation: <sha>)" when step 4 ran>

---

<details><summary>Audit trail</summary>

<REVIEW-COVERAGE: line(s) lifted from the verdict file, byte-identical>
**Reviewed tree:** <REVIEWED_TREE from step 1>
**Plan hash:** <PLAN_SHA256 from step 1; the literal none on a plan-blind run>
**Re-reviewed tree:** <tree from step 4b and the re-review's verdict word; omit when step 4b did not run>

</details>

<terminal verdict line from step 4's terminal-line recipe>
<!-- pr-ready-verdict -->
```

**Skip path (`FIDELITY_GATE=skip`):** do not compose the template above. The body is the prior sticky body with one disclosure line, so every field the next run's gate reads (terminal line, `**Reviewed tree:**`, `**Plan hash:**`, five labels) stays byte-identical to what a reviewer wrote. Post it with the same find-and-update-else-create shape named at the end of this step.

```bash
# phase 5.5 carry-forward repost body
PR_NUM=$(gh pr view --json number --jq '.number')
NOW=$(date '+%Y-%m-%d %H:%M:%S %Z')
awk -v now="$NOW" '
  /^\*\*Carried forward:\*\* / { next }
  { print }
  /^\*\*Plan hash:\*\* / { printf "**Carried forward:** %s; no reviewer spawned, tree and plan unchanged since the verdict above\n", now }
' "/tmp/pr-ready-prior-verdict-$PR_NUM.md" > "/tmp/post-plan-fidelity-comment-$PR_NUM.md"
grep -c '^\*\*Carried forward:\*\* ' "/tmp/post-plan-fidelity-comment-$PR_NUM.md"
```

The disclosure line sits directly under `**Plan hash:**`. In the skill layout that is inside `<details>` after `---`. In the harness layout it is above `### Merge digest`. Neither position is inside the digest span, so `_digest_labels` and the horizontal-rule stop in `skip-review.sh` never read it. Repeated carries replace the line instead of stacking. The `grep -c` must print `1`.

**Top banner and timestamp fields:** the banner has one timestamp slot. Fill it from `date '+%Y-%m-%d %H:%M:%S %Z'`. The banner word equals the step-3 `FIDELITY` word. The banner carries no auto-merge segment, because this fallback posts before Phase 6.5 decides arming. `bin/pr-cycle`'s `_precheck_verdict` strips `*`, `#` and backticks and reads the LAST verdict-shaped line. That line is the terminal line, so the banner cannot outvote it.

**The `---` after the digest is load-bearing.** `_digest_labels` in `bin/digest-dm-build` folds every later non-label, non-blank line into the LAST label's value until a heading or a horizontal rule stops it. Without the rule, the audit trail, the terminal verdict line and the `<!-- pr-ready-verdict -->` marker all get appended to `**Machine-authored fixes:**` in the Discord merge DM. Emit the rule.

**`**Reviewed tree:**`, `**Plan hash:**` and `**Re-reviewed tree:**` placement rule:** all three lines go inside the `<details>` audit trail AFTER `---`, each at column 0. The `**Plan hash:**` value is 64 lowercase hex characters or the literal `none`. The carry-forward regexes are line-anchored, so they still match there, and `_digest_labels` has already stopped at the rule, so it never reads them. The digest span stays free of bold-labelled lines. The five digest labels and their order are unchanged.

**Findings slot placement:** `$V2_FINDINGS` must also stay out of the digest span. When it is non-empty, insert a blank line, then `$V2_FINDINGS` verbatim, then a blank line, between the reviewer findings and the `### Merge digest` heading. Place it above that heading only. Re-review findings routinely carry their own `####` sub-headings and `**Finding N:**` bold labels: below the heading a `#+ ` line truncates the digest block and a bold label becomes a sixth digest label, either of which corrupts `bin/digest-dm-build`'s parse. Above the heading the parser never sees them, so the findings text needs no escaping, no re-wrapping, and no label stripping.

**Five digest labels — do not re-word, re-order, merge, or add a sixth line.** The labels in the given order are: `**What changed:**`, `**Why:**`, `**Watch:**`, `**Touches:**`, `**Machine-authored fixes:**`. If `/tmp/post-plan-digest-lines-<N>.txt` is absent or empty, emit the heading anyway with all five labels carrying `unavailable — digest script did not produce output`. The one permitted amendment is step 4 rule 3 (appending remediation SHA to `**Machine-authored fixes:**`).

Both the HTML marker and the digest heading are byte-identical to `/pr-ready`'s and must not be renamed or reformatted. `.github/workflows/merge-digest-notify.yml` selects the **last** comment containing that HTML marker and pipes it into `bin/digest-dm-build`'s `_digest_labels`, which anchors on that exact heading. Renaming either silently degrades every DM to the fallback message while every test stays green.

Post with the find-and-update-else-create shape from `bin/pr-canary-check` — grep its `STICKY_MARKER` constant and the `post_sticky()` below it rather than trusting a line number. Do **not** call `/pr-ready`'s `scripts/post-verdict.sh`: it is keyed to the `/tmp/pr-ready-verdict-<N>.md` namespace, and post-plan writing into that namespace would collide with a concurrent `/pr-ready` run on the same PR.

After posting, when `FIDELITY=missing` or `FIDELITY=NOT READY`: STOP with instructions to remediate the reviewer's findings and re-run `/post-plan`. Never hand-edit the verdict file to clear it — an absent file is itself a blocking state for condition (12).
