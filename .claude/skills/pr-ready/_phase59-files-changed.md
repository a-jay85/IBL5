---
description: /pr-ready runtime Phase 5.9 — refresh the PR body's files-changed block before review. Loaded by SKILL.md via git show at Phase 5.9.
last_verified: 2026-08-27
---

# /pr-ready runtime Phase 5.9 — refresh the files-changed block

Purpose: the full Phase 5.9 procedure, lifted out of `SKILL.md` so it is resident only from the turn it loads.

Read at runtime via `git show <MASTER_SHA>:.claude/skills/pr-ready/_phase59-files-changed.md`.

`<MASTER_SHA>` and `<N>` below are **literals to substitute** with the values pinned in Phase 1.3 — a value captured in one Bash call does not survive into the next.

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

