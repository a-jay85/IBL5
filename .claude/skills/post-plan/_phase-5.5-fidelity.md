---
description: /post-plan Phase 5.5 — plan-intent fidelity review (one Opus reviewer spawn), verdict parse, remediation, and sticky merge-digest comment.
last_verified: 2026-09-06
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

## Step 2 — Gather the five 6b inputs, then spawn exactly one reviewer

**One-spawn rule:** there is **exactly one `Agent` spawn per `/post-plan` run.** Spawn now and never again in this phase.

**No-re-spawn rule:** a `NOT READY` or `READY WITH NOTES` verdict never re-spawns the reviewer — step 3 keeps the verdict already produced. Re-spawning would let a follow-up run replace the original finding, which undermines the record.

Spawn with `subagent_type: "pr-ready-phase6"` and **omit `model`** so the def's `model: claude-opus-5` pin wins. This must be an `Agent` spawn and not a `/pr-ready` invocation: this skill's frontmatter carries `disallowed-tools: [EnterPlanMode, ExitPlanMode, Skill]`, so `Skill` is not callable at all.

The prompt hands the def its five 6b inputs and the output path. Output path: `/tmp/post-plan-fidelity-verdict-<N>.md` (substitute `<N>` with the `$PR_NUM` value from step 1). Keyed to the PR number, never to a per-shell PID — every block is a fresh shell, so a PID-keyed path would be written by one block and unreadable by the next.

Provide these five inputs in the spawn prompt:

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

## Step 4 — Remediation on `READY WITH NOTES` (and `NOT READY`)

Load the procedure in place: `git show <MASTER_SHA>:.claude/skills/pr-ready/_phase65-remediation.md`. Run it as written — including its step 2 clean-tree precondition (`STOP: worktree dirty before remediation`), its fifth-file gate handoff to one `subagent_type: "sonnet-4-6"` delegate, its single `chore:` commit, and its push through `scripts/push.sh` (a bare `--force-with-lease` publishes nothing on a branch with no upstream).

Three post-plan-specific rules on top — these are where a re-spawn would otherwise creep in:

1. **The verdict does not change.** Remediation never upgrades `READY WITH NOTES` to `READY`, and never re-runs the reviewer. The word posted in step 6 and read by condition (12) is the one from step 3.
2. **`**Reviewed tree:**` keeps the step 1 value** — the tree the reviewer actually saw, not the post-remediation tree. That line tells a reader exactly how much of the shipped head the verdict covers.
3. **The remediation commit is named in the existing `**Machine-authored fixes:**` digest label** — no new field, no sixth line. Append ` (post-plan remediation: <sha>)` to that one line's value. This is the only permitted deviation from `_phase7-verdict.md`'s paste-verbatim rule; it changes a value, not the label set.

On `NOT READY`, run the same remediation for every `Mode: in-PR` finding, then stop — the verdict stays `NOT READY` and condition (12) will block. Do not attempt to reach `READY`.

Skip this step entirely when `FIDELITY=READY`.

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

### Merge digest
**What changed:** <paste line 1 from /tmp/post-plan-digest-lines-<N>.txt>
**Why:** <paste line 2 from /tmp/post-plan-digest-lines-<N>.txt>
**Watch:** <paste line 3 from /tmp/post-plan-digest-lines-<N>.txt>
**Touches:** <paste line 4 from /tmp/post-plan-digest-lines-<N>.txt>
**Machine-authored fixes:** <paste line 5; append " (post-plan remediation: <sha>)" when step 4 ran>

<Remediation: what step 4 fixed, anything left unfixed, and the commit SHA>

READY WITH NOTES
<!-- pr-ready-verdict -->
```

**`**Reviewed tree:**` placement rule:** this line must appear before the digest heading. `bin/pr-cycle`'s `_digest_labels` starts capturing at that heading and treats every `^\*\*[^*]+:\*\*` line inside that span as a label — a bold-labelled line placed inside the block becomes a sixth label and corrupts the ledger parse. Before the heading it is invisible to the parser.

**Five digest labels — do not re-word, re-order, merge, or add a sixth line.** The labels in the given order are: `**What changed:**`, `**Why:**`, `**Watch:**`, `**Touches:**`, `**Machine-authored fixes:**`. If `/tmp/post-plan-digest-lines-<N>.txt` is absent or empty, emit the heading anyway with all five labels carrying `unavailable — digest script did not produce output`. The one permitted amendment is step 4 rule 3 (appending remediation SHA to `**Machine-authored fixes:**`).

Both the HTML marker and the digest heading are byte-identical to `/pr-ready`'s and must not be renamed or reformatted. `bin/pr-cycle`'s `_digest_row` selects the **last** comment containing that HTML marker and feeds it to `_digest_labels`, which anchors on that exact heading. Renaming either silently degrades every ledger row to `digest: unavailable` while every test stays green.

Post with the find-and-update-else-create shape from `bin/pr-canary-check` — grep its `STICKY_MARKER` constant and the `post_sticky()` below it rather than trusting a line number. Do **not** call `/pr-ready`'s `scripts/post-verdict.sh`: it is keyed to the `/tmp/pr-ready-verdict-<N>.md` namespace, and post-plan writing into that namespace would collide with a concurrent `/pr-ready` run on the same PR.

After posting, when `FIDELITY=missing` or `FIDELITY=NOT READY`: STOP with instructions to remediate the reviewer's findings and re-run `/post-plan`. Never hand-edit the verdict file to clear it — an absent file is itself a blocking state for condition (12).
