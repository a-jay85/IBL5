---
name: pr-attack
description: Compute an optimal merge order for all open PRs — ordered table, excluded set, and hand-resolution forecast — printed to chat and written to a dated file under the home directory.
disable-model-invocation: true
model: claude-sonnet-4-6
last_verified: 2026-08-24
allowed-tools: Bash(bin/pr-triage), Bash(bin/pr-overlap:*), Bash(gh pr list:*),
  Bash(gh pr view:*), Bash(gh pr diff:*), Bash(git rev-parse:*), Bash(date:*),
  Bash(mktemp:*), Bash(awk:*), Bash(sort:*), Bash(grep:*), Write
---

Produce a short, re-runnable **plan of attack**: the order to merge the currently
open PRs in, why each sits where it does, which ones are excluded, and where a
human hand-resolution is coming. Target: under ~2 minutes end to end.

**This skill is strictly read-only.** It never merges, never arms auto-merge,
never edits a PR, never pushes a branch, and never runs `bin/pr-triage --arm`.
Its only write is one dated markdown file in the home directory (Step 5). It
shrinks no human review: it orders the queue the maintainer already reviews by
hand, and it never recommends batching reviews or arming anything.

Take no arguments. Re-run it after every merge — the order is only valid against
the current tip.

## Step 0: Scratch space

```bash
WORK="$(mktemp -d)"; echo "$WORK"
```

Every intermediate lands under `$WORK`. Nothing is written inside the repo.

## Step 1: Triage intake (do not re-derive)

`bin/pr-triage` is step 1 of this skill, not a thing to reimplement. Run it
read-only — never with `--arm`:

```bash
bin/pr-triage > "$WORK/triage.txt"
```

Its stdout is a fixed-width report, one line per open PR:

```
PR#  BUCKET  NEXT-ACTION  MSS  CLEARANCE  <one column per required check>  HOLDS
```

**Parse it with `-F'  +'`, not by column offsets.** Every column is padded to a
fixed width and separated by exactly TWO spaces, and no value contains a double
space — so a two-or-more-space field separator gives one field per column even
when a long value overflows its width. Column offsets do not, because the widths
do not truncate.

The number of check columns is **not fixed** — `bin/pr-triage` reads the required
contexts from the live branch-protection API. Only the check block is variable, so
never index past it: `HOLDS` is read as `$NF`, not as a fixed number. The four
columns *before* the check block are fixed in count, and none of them can ever be
empty (`state_of` and `pr_manual_testing_clearance` are total over closed enums;
`MSS` is `${mss:-?}`), so no field collapses into the separator — `CLEARANCE` is
therefore stably `$5`.

```bash
awk -F'  +' '/^#[0-9]+/ {
       printf "%s\t%s\t%s\t%s\n", $1, $2, $5, $NF
     }' "$WORK/triage.txt" > "$WORK/triage.tsv"
```

That leaves `$WORK/triage.tsv` with four fields: **PR#**, **BUCKET**,
**CLEARANCE** (`CLEARED` | `HELD` | `UNKNOWN`), and **HOLDS**.

`HOLDS` is a space-separated list of only the hold tokens that actually fired, or
a lone `-` when none did. The tokens this skill reads are `body-hold`,
`golden-changed`, `feat-awaiting-signoff`, `depends-on:#N` (one per declared
predecessor), and `unresolved-finding:SCORE`. Match them with `index()` /
`~ /token/`, not by position — the order is fixed but the set is not.

`depends-on:#N` is the authoritative declared-dependency signal: it comes from
`pr_dep_holds()` in `bin/lib/pr-armable.sh`, which reads only **anchored**
`Depends-on: #N` lines and ignores inline prose mentions. Never re-scrape PR
bodies for dependencies here.

`bin/pr-triage` reports every OPEN PR **including drafts**, and does not emit
draft state, so fetch the missing metadata in one more call (this same call also
supplies the per-PR file lists Step 2 needs):

```bash
gh pr list --state open --limit 200 \
  --json number,title,isDraft,mergeStateStatus,url,files > "$WORK/prs.json"
```

### Partition

- **Excluded** — a PR is excluded from the ordering table when *either*:
  - `isDraft == true` (the author has not asked for it), or
  - its `bin/pr-triage` bucket is `DIRTY` (`mergeStateStatus == DIRTY`, a real
    merge conflict with master — it cannot be ordered until it is rebased).

  Record each excluded PR with its number, title, and the one-word reason
  (`draft` or `conflicted`). Excluded PRs are listed **below** the table in
  Step 5, never inside it.
- **Orderable** — everything else, whatever its bucket. A `BLOCKED-CHECK` or
  `UNCLEARED` PR still has a correct position in the merge order; the ordering
  question ("what must land before what") is independent of the readiness
  question ("is it green yet"). Readiness surfaces in the `Needs you?` column.

```bash
gh pr list --state open --limit 200 --json number,isDraft \
  --jq '.[] | select(.isDraft | not) | "#\(.number)"' | sort > "$WORK/not-draft.txt"
awk -F'\t' '$2 != "DIRTY" {print $1}' "$WORK/triage.tsv" | sort > "$WORK/not-dirty.txt"
grep -Fx -f "$WORK/not-draft.txt" "$WORK/not-dirty.txt" > "$WORK/orderable.txt"
```

### The `Needs you?` value

Derive it per orderable PR from the triage bucket and signals, **first match
wins**:

| Condition | `Needs you?` |
|---|---|
| bucket `HELD`, or **any** hold signal — `CLEARANCE` = `HELD`, or `HOLDS` contains `body-hold`, `golden-changed`, or `unresolved-finding:` | `manual test` |
| bucket `FEAT-AWAITING-SIGNOFF`, or `HOLDS` contains `feat-awaiting-signoff` | `sign feat:` |
| bucket `UNCLEARED`, or `CLEARANCE` = `UNKNOWN` | `manual test` |
| otherwise | `—` |

**Read the signals, not just the bucket.** `bin/pr-triage` assigns buckets
**first-match-wins**, so `DIRTY` and `BLOCKED-CHECK` are evaluated *before*
`HELD` — a PR with a live hold plus a red check buckets `BLOCKED-CHECK` and its
hold never reaches the bucket name. That is why row 1 enumerates the raw signals:
they are exactly the four disjuncts of the `HELD` branch in `bin/pr-triage`
(`clearance` = `HELD` OR `golden-changed` OR `body-hold` OR `unresolved-finding:`).
Rows 2 and 3 read `feat-awaiting-signoff` and `CLEARANCE` directly for the same
reason — `BLOCKED-DEP` outranks both of their buckets. **If a fifth disjunct is ever added to that branch, add it
here too**; `bin/pr-triage`'s `HELD` branch is the source of truth, this table
mirrors it.

The third row is deliberate and fail-closed: no `## Manual Testing` section means
`bin/pr-triage` never evaluated a clearance, so a human must look. Treating an
absent section as "nothing needed" would invert the whole fail-closed design of
`bin/lib/pr-armable.sh`. This column is a **secondary** sort key only — it never
reorders a PR across a MUST constraint.

## Step 2: Find the contended files, then diff only those

Filenames alone over-report: two PRs touching the same file usually touch
different parts of it. So resolve overlap in two cheap steps, and only fetch
diffs for files that are actually shared.

**2a — file lists, one call, no diff download.** `$WORK/prs.json` already carries
`files` for every open PR (the same `gh pr list --json … files` shape
`bin/pr-triage:160` uses), so no per-PR call is needed:

```bash
gh pr list --state open --limit 200 --json number,files \
  --jq '.[] | . as $p | $p.files[].path | "#\($p.number)\t\(.)"' \
  | sort > "$WORK/pr-files.tsv"
awk -F'\t' 'NR == FNR { ok[$1] = 1; next } ok[$1]' \
  "$WORK/orderable.txt" "$WORK/pr-files.tsv" > "$WORK/orderable-files.tsv"
```

> GitHub caps this list at 100 files per PR. If any orderable PR shows exactly
> 100 files, fall back to `gh pr view <N> --json files --jq '.files[].path'` for
> that PR alone and note the truncation in the forecast.

**2b — contended files.** A file is *contended* when two or more **orderable**
PRs touch it:

```bash
awk -F'\t' '{ prs[$2] = prs[$2] " " $1; n[$2]++ }
            END { for (f in n) if (n[f] > 1) printf "%s\t%s\n", f, substr(prs[f], 2) }' \
  "$WORK/orderable-files.tsv" | sort > "$WORK/contended.tsv"
```

If `$WORK/contended.tsv` is empty, there is no conflict-cost constraint at all —
skip 2c/2d and say so in the forecast.

**2c — diffs, only for PRs on a contended file.**

```bash
awk -F'\t' '{ n = split($2, a, " "); for (i = 1; i <= n; i++) print a[i] }' \
  "$WORK/contended.tsv" | sort -u > "$WORK/need-diff.txt"
while read -r pr; do
  gh pr diff "${pr#\#}" > "$WORK/diff-${pr#\#}.txt"
done < "$WORK/need-diff.txt"
```

`gh pr diff` returns the diff against the PR's **merge base**, so every diff here
shares one coordinate system — which is what makes old-side hunk ranges
comparable across PRs.

**2d — range comparison.** For each unordered pair of PRs that share at least one
contended file, run the overlap detector once:

```bash
bin/pr-overlap "$WORK/diff-<A>.txt" "$WORK/diff-<B>.txt" "#<A>" "#<B>"
```

It prints `CLEAN <file>` / `HAND-RESOLUTION <file> (…)` per shared file, then a
`SUMMARY contended=… clean=… hand=…` line. Exit `0` means the analysis ran;
findings live on stdout, so read the lines, not the status. Exit `2` or `3` means
a diff file was empty or malformed — report that pair as **unknown**, never as
clean. Record every `HAND-RESOLUTION` line: those are the conflict-cost edges
Step 3 consumes and the raw material for the Step 5 forecast.

## Step 3: Classify the constraints

Every edge between two PRs is exactly one of three types. **Only the first two
are MUST constraints**; the third changes effort, never order.

| Type | Source | Meaning | MUST? |
|---|---|---|---|
| `declared` | the `depends-on:#N` tokens in Step 1's `HOLDS` field (anchored `Depends-on: #N`, read by `pr_dep_holds()` in `bin/lib/pr-armable.sh`) | the author stated #N must land first | **yes** |
| `gate` | the heuristic + judgment pass below | PR A adds or tightens a CI check that PR B's diff would violate, so the wrong order either red-lights B or manufactures false confidence in it | **yes**, once judgment confirms |
| `conflict-cost` | `HAND-RESOLUTION` lines from `bin/pr-overlap` | the two PRs edit the same lines; whoever goes second rebases by hand | **no** — advisory only |

Never promote a `conflict-cost` edge to a MUST. Two PRs with colliding hunks can
merge in either order; only the rebase bill changes, and the hub-last heuristic in
Step 4 is what minimises it.

### 3a — declared

For each orderable PR, scan its `HOLDS` field for `depends-on:#N` tokens. No such
token (in particular a bare `-`) means no declared edge. Each `depends-on:#N`
token is an edge `#N → this PR`.

Two things to resolve before ordering:

- If `#N` is in the **excluded** set (draft or DIRTY), this PR cannot be ordered
  either. Move it to excluded with the reason `blocked by excluded #N`.
- If `#N` is not among the open PRs at all (already merged, or closed), the
  signal would not have been emitted — `pr_dep_holds()` only reports predecessors
  that are not yet `MERGED`. If you nonetheless see one, treat it as excluded with
  `depends on non-open #N` and say so.

### 3b — gate (heuristic, then judgment)

A PR is **gate-sensitive** when its file list touches any of:

```
.github/workflows/
bin/check-*
bin/adr-check
bin/check-docs
bin/generate-codebase-map
bin/lib/pr-armable.sh
```

The heuristic only nominates candidates — it never concludes. For each
gate-sensitive PR G, read what G actually changes in those files, then ask, per
other orderable PR B:

> Does G add or tighten a check whose scan surface includes files B touches, such
> that B merged **before** G would pass a gate that no longer exists, or B merged
> **after** G would newly fail?

Answer it from G's diff (already fetched in Step 2c if G is on a contended file;
otherwise `gh pr diff <G>`), not from the file names. A gate PR that merely
*reformats* a workflow, renames a job, or edits a comment creates **no** edge.

Emit the result as a small table before the merge order, and omit it entirely
when no edge survives judgment:

```
| Gate PR | Adds/tightens | Affects | Direction |
|---|---|---|---|
| #NNNN | new gate script scanning `ibl5/**` | #NNNN, #NNNN | gate merges LAST (let them land under the old gate) or FIRST (force them to comply) — state which and why |
```

State the direction explicitly. "Gate last" is right when the affected PRs are
already reviewed and compliance is a follow-up; "gate first" is right when the
gate exists precisely to stop what those PRs do.

### 3c — conflict-cost

Every `HAND-RESOLUTION` line from Step 2d is a conflict-cost edge, recorded with
its PR pair and file. Feed the count into the hub-degree used in Step 4, and the
lines themselves into the Step 5 forecast.

## Step 4: Build the merge order

Deterministic, so two runs against the same tip agree. Kahn's algorithm over the
MUST edges only:

1. **Nodes** = orderable PRs. **Edges** = `declared` + confirmed `gate` MUSTs.
2. If the MUST graph has a **cycle**, do not invent an order for it. Print the
   cycle (`#A → #B → #A`), leave those PRs out of the numbered table, and list
   them under `## Excluded` with the reason `dependency cycle`. Order the rest.
3. Repeatedly take the set of nodes with no unmerged predecessor and emit the one
   that wins this tie-break, in order:
   1. **Hub-last** — fewest contended files touched goes first. A "hub" is a PR
      sitting on many contended files; merging it last means every other PR lands
      against an unchanged base and the hub absorbs all the churn in **one** final
      rebase instead of forcing N rebases on everyone else. Hub-degree = the
      number of distinct contended files this PR appears on in
      `$WORK/contended.tsv`.
   2. **`Needs you?` rank** — `—` (0) before `sign feat:` (1) before
      `manual test` (2). Merging the no-action PRs first keeps the queue moving
      while human items wait on you.
   3. **Ascending PR number** — the final, arbitrary but stable tie-break.

Rule 3.1 outranks 3.2 on purpose: a hub merged early costs everyone a rebase,
which is real work, while a `Needs you?` PR ordered a slot early costs nothing but
your attention arriving sooner.

## Step 5: Emit the plan of attack — twice

Assemble the markdown **once**, then (a) print it to chat verbatim and (b) write
the identical text to a dated file. Target 20–40 lines total; no per-PR prose
paragraphs.

```markdown
# Plan of attack — <YYYY-MM-DD>

Base: `master` @ <short sha from `git rev-parse --short origin/master`> ·
<N> open · <M> orderable · <K> excluded

| # | PR | Constraint | Needs you? |
|---|----|-----------:|:----------:|
| 1 | #NNNN branch-slug | — | — |
| 2 | #NNNN branch-slug | declared: after #N (MUST) | — |
| 3 | #NNNN branch-slug | gate: after #N (MUST) | sign feat: |
| 4 | #NNNN branch-slug | conflict-cost: rebase over #N | manual test |

## Excluded
- #NNNN branch-slug — DIRTY (merge conflict; rebase before it can be ordered)
- #NNNN branch-slug — DRAFT

## Hand-resolutions needed
- #NNNN vs #NNNN on `path/to/file` — <what the resolution is, one clause>

## Rebase (run per PR, after the one above it merges)
\`\`\`bash
cd /Users/ajaynicolas/GitHub/IBL5-worktrees/<slug> \
  && git fetch origin \
  && git rebase origin/master \
  && git push --force-with-lease
\`\`\`
```

Rules for the emitted document:

- The `Constraint` cell carries the type word plus the PR it points at. Suffix
  `(MUST)` on `declared` and `gate` only — never on `conflict-cost`.
- Omit `## Hand-resolutions needed` **entirely** when `bin/pr-overlap` reported no
  overlapping hunks. Do not emit an empty heading, and never write a
  hand-resolution line that was not backed by a `HAND-RESOLUTION` output line.
- Emit the rebase block **once**, at the bottom. When a PR's branch is stacked on
  another PR that has already merged, read
  `.claude/rules/linear-history-squash-merge.md` before rebasing — a squash-merged
  parent makes a bare rebase report phantom conflicts.
- Write the file with the `Write` tool at the **absolute** path
  `/Users/ajaynicolas/IBL5-pr-attack-<DATE>.md`, where `<DATE>` is
  `date +%Y-%m-%d`. The `Write` tool does not expand `~`. Overwriting a same-day
  file is correct and expected — the skill is re-runnable and the newest run wins.
  This is the skill's only write, and it is outside the repository.

Then stop. Do not merge anything, do not arm auto-merge, do not open or edit a PR,
and do not suggest batching reviews — the maintainer reviews every PR by hand on
purpose, and this skill only orders that queue.
