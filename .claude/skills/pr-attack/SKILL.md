---
name: pr-attack
description: Compute an optimal merge order for all open PRs — ordered table, excluded set, and hand-resolution forecast — printed to chat and written to a dated file under the home directory.
disable-model-invocation: true
model: claude-sonnet-4-6
context: fork
agent: sonnet-4-6
last_verified: 2026-09-01
allowed-tools: Bash(bin/pr-attack --gate-candidates), Bash(bin/pr-attack --work:*), Read
---

Produce a short, re-runnable **plan of attack**: the order to merge the currently
open PRs in, why each sits where it does, which ones are excluded, and where a
human hand-resolution is coming. Target: under ~2 minutes end to end.

**This skill is strictly read-only.** It never merges, never arms auto-merge,
never edits a PR, never pushes a branch, and never runs `bin/pr-triage --arm`.
Its only write is one dated markdown file in the home directory. It shrinks no
human review: it orders the queue the maintainer already reviews by hand, and it
never recommends batching reviews or arming anything. The guarantee is now
enforced by `bin/pr-attack` refusing `--arm` outright and by the skill's
`allowed-tools` granting only the two ordering invocations.

Take no arguments. Re-run it after every merge — the order is only valid against
the current tip.

## Invocation 1

```bash
bin/pr-attack --gate-candidates
```

Fetches triage output and PR metadata, partitions PRs into orderable and excluded,
identifies gate-sensitive nominees (Step 3b below), fetches diffs for contended
files, and prints a summary ending with `WORK=<tmpdir>`. Save that path; it is the
input to invocation 2.

## Step 3b — Gate judgment (human step)

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

Answer it from G's diff (already fetched and printed by invocation 1), not from
the file names. A gate PR that merely *reformats* a workflow, renames a job, or
edits a comment creates **no** edge.

State the direction explicitly before calling invocation 2. "Gate last" is right
when the affected PRs are already reviewed and compliance is a follow-up; "gate
first" is right when the gate exists precisely to stop what those PRs do.

## Invocation 2

```bash
bin/pr-attack --work <WORK> \
  --gate-edge '#A>#B' \
  --gate-edge '#C>#D'
```

Ingests the gate-edge judgments from Step 3b, runs Kahn's topological sort (with
hub-last, Needs-you? rank, and ascending-PR# tie-breaks), and emits the ordered
plan to stdout and to `$HOME/IBL5-pr-attack-<date>.md`.

If there are no gate nominees, supply `--gate-edges /dev/null` (judged-empty case):

```bash
bin/pr-attack --work <WORK> --gate-edges /dev/null
```

## Exit codes

| Code | Meaning | Action |
|------|---------|--------|
| 0 | ok | — |
| 1 | usage / refused argument (including `--arm`) | Read the error message |
| 2 | gate candidates present and no `--gate-edge`/`--gate-edges` supplied | Answer the gate nominations (Step 3b), then re-run with `--gate-edge` or `--gate-edges` |
| 3 | `$WORK` is stale (tip moved) | Re-run `--gate-candidates` — `master` has moved |
