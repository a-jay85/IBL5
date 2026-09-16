---
description: /post-plan Phase 5.5 — plan-intent fidelity review (one Opus reviewer spawn, plus one bounded re-review after remediation), verdict parse, remediation, and sticky merge-digest comment.
last_verified: 2026-09-13
---

# /post-plan Phase 5.5 — Plan-intent fidelity review & merge digest

Purpose: ask whether the implementation does what the plan *intended*, not merely what its tests assert — the semantic question Phase 5.0 structurally cannot answer. The fidelity criteria stay in `.claude/skills/pr-ready/_plan-fidelity-review.md`; the remediation procedure stays in `.claude/skills/pr-ready/_phase65-remediation.md`. This file sequences them and adds the post-plan-specific glue.

`<MASTER_SHA>` and `<N>` below are **literals to substitute** with the values pinned in step 1 — a value captured in one Bash call does not survive into the next, and every `/post-plan` block runs in a fresh shell.

## Step 1 — Pin the run's identifiers

Each block runs in its own shell; nothing is exported between them. Re-derive everything in-block. `REVIEWED_TREE` is captured **before** the spawn so it names the tree the reviewer actually sees. Substitute the printed values as literals into every step below.

```bash
PR_NUM=$(gh pr view --json number --jq '.number')
MASTER_SHA=$(git rev-parse origin/master)
REVIEWED_TREE=$(git rev-parse HEAD^{tree})
echo "PR_NUM=$PR_NUM MASTER_SHA=$MASTER_SHA REVIEWED_TREE=$REVIEWED_TREE"
```

## Step 2 — Gather the seven inputs, then spawn exactly one reviewer

**Bounded-re-spawn rule:** at most two `Agent` spawns per `/post-plan` run, and never more. Spawn the first reviewer now. **Exactly one re-spawn** is permitted, in step 4b, and only when **both** hold: (a) step 4's remediation addressed **every** `Mode: in-PR` finding, and (b) the plan does **not** declare `auto_merge: false`.

**The first verdict is never replaced.** The re-review writes a **separate file** (`/tmp/post-plan-fidelity-verdict-<N>-2.md`); verdict 1's word, findings and digest stay exactly as the reviewer wrote them. The record grows, it never gets rewritten. Never hand-edit or regenerate verdict 1 to flip its word.

If anything was left unfixed, do **not** re-spawn — a reviewer looking at a tree that still carries a known finding buys nothing and costs an Opus turn.

Spawn with `subagent_type: "pr-ready-phase6"` and **omit `model`** so the def's `model: claude-opus-5` pin wins. This must be an `Agent` spawn and not a `/pr-ready` invocation: this skill's frontmatter carries `disallowed-tools: [EnterPlanMode, ExitPlanMode, Skill]`, so `Skill` is not callable at all.

The prompt hands the def its five 6b inputs and the output path. Output path: `/tmp/post-plan-fidelity-verdict-<N>.md` (substitute `<N>` with the `$PR_NUM` value from step 1). Keyed to the PR number, never to a per-shell PID — every block is a fresh shell, so a PID-keyed path would be written by one block and unreadable by the next.

Provide these seven inputs in the spawn prompt (items 1, 2, 6, 7 are post-plan-specific; items 3–5 are the `_plan-fidelity-review.md` contract inputs):

1. **Output path** — `/tmp/post-plan-fidelity-verdict-<N>.md`. The def's output contract item 1 writes the verdict to the absolute path the prompt names.
2. **`<MASTER_SHA>`** — the pinned value from step 1, so the def can `git show <MASTER_SHA>:.claude/skills/pr-ready/_plan-fidelity-review.md`. If the `Read`-by-worktree-path fallback fires instead, the def records `include-source: worktree (pin predates skill)` and step 6 surfaces that line in the sticky comment.
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

Load the procedure in place: `git show <MASTER_SHA>:.claude/skills/pr-ready/_phase65-remediation.md`. Run it as written — including its step 2 clean-tree precondition (`STOP: worktree dirty before remediation`), its fifth-file gate handoff to one `subagent_type: "sonnet-4-6"` delegate, its single `chore:` commit, and its push through `scripts/push.sh` (a bare `--force-with-lease` publishes nothing on a branch with no upstream).

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

On `RE_REVIEW=spawn`, spawn **one** reviewer: `subagent_type: "pr-ready-phase6"`, **omit `model`** so the def's `model: claude-opus-5` pin wins. An `Agent` spawn, never a `/pr-ready` invocation — this skill's frontmatter carries `disallowed-tools: [EnterPlanMode, ExitPlanMode, Skill]`.

**Output path:** `/tmp/post-plan-fidelity-verdict-<N>-2.md` (substitute `<N>` = step 1's `$PR_NUM`). PR-number-keyed, never `$$`/`$PPID`-keyed — condition (12) reads it from a different shell. The `-2` suffix ensures verdict 2 can never overwrite verdict 1.

**The same seven inputs as step 2**, with two changes: input 4 is the **post-remediation** diff (`gh pr diff <N>` re-run after step 4's push, so it includes the remediation commit), and one added pointer — "reviewer 1's findings are at `/tmp/post-plan-fidelity-verdict-<N>.md`; confirm each was addressed by the remediation commit, and report a new finding only if the remediation itself introduced one."

After the reviewer returns, re-run step 3's parse against the `-2` path to get the second verdict word, then run step 3b's record block against the `-2` path so verdict 2 also carries `REVIEWED_TREE=`. Both must run **after** step 4's push, so the recorded tree is the tree the reviewer saw.

**Failure paths:** if the re-review writes no file, or writes one with no parseable verdict word, treat it as "no re-review happened" — verdict 1 stands, condition (12) blocks on verdict 1's word, and the terminal line is the fully-remediated `NOT READY` row. Never re-spawn a third time to retry.

## Step 5 — Materialise the digest lines

Mirror `_phase7-verdict.md`'s chain exactly, pointed at post-plan's verdict path. `digest.sh` takes the verdict file as `$1`, so it works unchanged. The **trailing `cat` is load-bearing** — without it the five lines sit on disk and never enter context, so there is nothing to paste into the `Write` call in step 6. `digest.sh` exits 0 on every degrade path and prints five `unavailable — <reason>` lines rather than failing, so this chain never aborts the run.

```bash
git show <MASTER_SHA>:.claude/skills/pr-ready/scripts/digest.sh > /tmp/post-plan-digest-<N>.sh \
  && test -s /tmp/post-plan-digest-<N>.sh \
  && bash /tmp/post-plan-digest-<N>.sh /tmp/post-plan-fidelity-verdict-<N>.md > /tmp/post-plan-digest-lines-<N>.txt \
  && cat /tmp/post-plan-digest-lines-<N>.txt
```

## Step 6 — Compose and post the sticky comment

**Write the composed body to `/tmp/post-plan-fidelity-comment-<N>.md` with the `Write` tool first**, then post — a `tmpfile=$(mktemp)` assigned in one Bash call is gone by the next, so an inline compose would send an empty `--body-file`.

The body template (write to `/tmp/post-plan-fidelity-comment-<N>.md` with the `Write` tool):

```
REBASE=<Phase 1 REBASE= line verbatim>
CI: <result — and post-remediation CI result when step 4 ran>

Plan-fidelity verdict: <FIDELITY word> — <reviewer findings, REVIEW-COVERAGE: marker line, and include-source: line if the fallback fired>

**Reviewed tree:** <REVIEWED_TREE from step 1>
**Re-reviewed tree:** <tree from step 4b, and the re-review's verdict word — omit this line entirely when step 4b did not run>

### Merge digest
**What changed:** <paste line 1 from /tmp/post-plan-digest-lines-<N>.txt>
**Why:** <paste line 2 from /tmp/post-plan-digest-lines-<N>.txt>
**Watch:** <paste line 3 from /tmp/post-plan-digest-lines-<N>.txt>
**Touches:** <paste line 4 from /tmp/post-plan-digest-lines-<N>.txt>
**Machine-authored fixes:** <paste line 5; append " (post-plan remediation: <sha>)" when step 4 ran>

<Remediation: what step 4 fixed, anything left unfixed, and the commit SHA>

<terminal verdict line — build it from step 4's terminal-line recipe; never a bare verdict word>
<!-- pr-ready-verdict -->
```

**`**Reviewed tree:**` and `**Re-reviewed tree:**` placement rule:** both bold-labelled lines must appear before the digest heading. `bin/pr-cycle`'s `_digest_labels` starts capturing at that heading and treats every `^\*\*[^*]+:\*\*` line inside that span as a label — a bold-labelled line placed inside the block becomes a sixth label and corrupts the ledger parse. Before the heading they are invisible to the parser. The five digest labels and their order are unchanged.

**Five digest labels — do not re-word, re-order, merge, or add a sixth line.** The labels in the given order are: `**What changed:**`, `**Why:**`, `**Watch:**`, `**Touches:**`, `**Machine-authored fixes:**`. If `/tmp/post-plan-digest-lines-<N>.txt` is absent or empty, emit the heading anyway with all five labels carrying `unavailable — digest script did not produce output`. The one permitted amendment is step 4 rule 3 (appending remediation SHA to `**Machine-authored fixes:**`).

Both the HTML marker and the digest heading are byte-identical to `/pr-ready`'s and must not be renamed or reformatted. `bin/pr-cycle`'s `_digest_row` selects the **last** comment containing that HTML marker and feeds it to `_digest_labels`, which anchors on that exact heading. Renaming either silently degrades every ledger row to `digest: unavailable` while every test stays green.

Post with the find-and-update-else-create shape from `bin/pr-canary-check` — grep its `STICKY_MARKER` constant and the `post_sticky()` below it rather than trusting a line number. Do **not** call `/pr-ready`'s `scripts/post-verdict.sh`: it is keyed to the `/tmp/pr-ready-verdict-<N>.md` namespace, and post-plan writing into that namespace would collide with a concurrent `/pr-ready` run on the same PR.

After posting, when `FIDELITY=missing` or `FIDELITY=NOT READY`: STOP with instructions to remediate the reviewer's findings and re-run `/post-plan`. Never hand-edit the verdict file to clear it — an absent file is itself a blocking state for condition (12).
