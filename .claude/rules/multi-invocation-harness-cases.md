---
description: When a bin/test-* harness covers a script whose first invocation writes state that a second invocation reads, drive invocation 1 against fixtures and feed its real output forward; when editing a SKILL.md, diff it against the prior version and account for every removed line.
last_verified: 2026-09-30
paths:
  - "bin/test-*"
  - ".claude/skills/**/SKILL.md"
---

# Multi-Invocation Harness Cases

## When this applies

A script is multi-invocation when invocation 1 writes state that invocation 2 reads. One example is a `--gate-candidates` run that prints a `WORK=` directory, followed by a `--work <dir>` run that consumes it. A `--resume` pair has the same shape. The harness for such a script is a `bin/test-*` file. Keep the `paths:` globs in this rule's frontmatter matching a tracked file. A typo such as `bin/tests-*` matches nothing, and the rule never attaches.

## The rule

Every case that asserts invocation-1 behavior runs invocation 1 against fixtures and feeds its real output to invocation 2. A case that writes the intermediate state by hand and calls only invocation 2 proves nothing about invocation 1. A bug in how invocation 1 partitions, filters, or serializes its output passes that case unchanged.

Hand-built state is allowed only for logic that lives entirely in invocation 2, such as a stale-tip check or an unjudged-fails-loud check. Each state-writing branch of invocation 1 still needs its own case that drives it.

## Coverage checklist

- List every branch in invocation 1 that writes or omits a field of the shared state.
- Map each branch to a case that runs invocation 1 and reaches it.
- Confirm those cases read the state path from invocation 1's output. A path the harness built itself does not count.
- Label each hand-built-state case as invocation-2-only in its comment.

## Mutation check

Copy invocation 1's script, inject a wrong value into one state-writing branch, and run the harness against the copy. At least one case must fail. If none fails, that branch has no driving case. The awk-anchor row of `.claude/review-shared/_plan-verification.md` asks for the same inject-and-confirm step.

## Positive example

`bin/test-pr-attack` cases 6 to 11 run `--gate-candidates`, extract the `WORK=` line from its output, and pass that directory to `--work`. Its `populate_work()` helper builds the state by hand and serves only the invocation-2-only cases.

## SKILL.md prose edits

An edit to a `SKILL.md` can delete a guardrail sentence while it rewrites nearby text. Before shipping, diff the file against its prior version and account for every removed line:

```bash
git diff -U0 <base> -- <path/to/SKILL.md> | sed -n '/^@@/,$p' | grep '^-'
```

Each removed line is either intentional or restored. Take extra care when the same edit widens `allowed-tools`. In `.claude/skills/pr-attack/SKILL.md` a widened grant shipped in the same edit that deleted the read-only clause.
