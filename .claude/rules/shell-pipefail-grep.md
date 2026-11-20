---
description: Avoid printf-pipe-grep-q under set -o pipefail (herestring, no SIGPIPE), and keep pipeline-status traps out of errexit+pipefail bin/ scripts (two diff gates).
last_verified: 2026-11-19
paths: "bin/**"
---

# Shell Pipefail + grep -q Portability

## Rule

Under `set -o pipefail`, never write:

```bash
printf '%s' "$var" | grep -qF -- "$token"
```

When grep finds a match and exits early, printf gets SIGPIPE (exit 141). Under pipefail, the pipeline's exit status is the rightmost non-zero — 141 — even though grep succeeded with 0. The `if !` check then enters the failure branch on macOS-passing, Linux-failing grounds.

**Use a herestring instead:**

```bash
grep -qF -- "$token" <<< "$var"
```

No pipe means no SIGPIPE possible. Behavior is identical between Linux and macOS bash.

## Why

PR #2174's `bin/test-architect-contract-split` used the printf-pipe form under `set -uo pipefail`. All 37 bold-token assertions passed on macOS in development but failed in CI (ubuntu-latest) because grep exited early on every match, sending SIGPIPE to printf. `bin/test-architect-contract-split: line 197: printf: write error: Broken pipe` appeared alongside each false-failure line.

## Applies to

Any shell script with `set -o pipefail` (or `set -euo pipefail`) that pipes `echo` or `printf` into a `grep -q`-family call (`-qF`, `-qE`, `-qP`, `--quiet`). The quiet flag may come after the pattern (`grep -F "$pat" -q`), and the pipe may pass through other stages first (`echo "$x" | tr A-Z a-z | grep -q y`). Both forms exit 141 under pipefail. A `-q` after `--` is a file operand, so it does not count. `grep -c` reads to EOF and is safe (`bin/test-sigpipe-grep-q-guard` case 2). `bin/check-pipefail-grep-q` fails a PR that adds a new instance under `bin/`. The gate also flags an aggregating middle stage such as `wc -l`, which drains the producer and cannot exit 141. Rewrite those lines the same way, since the fix costs one line.

The gate reads text and does not parse quotes. It only sees `echo` and `printf` producers. A quoted `;` `&` `(` `)` `|` or `#` inside a middle stage or a grep pattern ends the match early, so lines like `echo "$x" | sed -E 's/(a)/b/' | grep -q y` or `echo "$x" | grep -E "a|b" -q` pass the gate while still hitting SIGPIPE. A `2>&1` or `&>` redirect in a stage is fine. A gate pass is not proof the line is safe.

With a middle stage, capture the stage's output first and feed the quiet grep a herestring:

```bash
lower=$(tr A-Z a-z <<< "$x")
grep -q y <<< "$lower"
```

ShellCheck does not catch this pattern — it passes SC lint.

## Early-exit awk and head

The same SIGPIPE hits any consumer that quits before its producer finishes: `awk '... {print $2; exit}'`, `head -1`. `git worktree list --porcelain | awk '/^worktree / {print $2; exit}'` returns 141 once the list outgrows a pipe buffer, and `set -euo pipefail` kills the script with no output. Keep reading all input instead: `awk '/^worktree / && !f {print $2; f=1}'` or `sed -n '1s/^worktree //p'`. Appending `|| true` hides the failure but also drops a real one. Tiny producers (a few lines) are safe.

## Pipeline status under errexit

`bin/check-pipefail-assignment-guard` fails a PR that adds one of three shapes to a `bin/` shell file that sets both errexit and pipefail:

- **pipeline-status.** `v=$(cmd | filter)` with no `||` on the statement, or a pipeline statement whose last stage is `grep`. A failing `cmd`, or a `grep` with no match, aborts the script before the next line can test `$v`. An `echo` or `printf` producer counts only when the last stage is `grep`.
- **default-form.** `"${V:-$(cmd | filter)}"` or `${V:=...}`. The fallback on the next line never runs.
- **early-exit.** A `cmd | awk '... exit'`, `cmd | head`, or `cmd | sed ...q` stage. The gate flags it even with `|| true`, because `|| true` also drops a real failure. Use the rewrites in the section above.

To fix a hit, put the fallback on the statement (`v=$(cmd | filter) || v=''`), or capture first and apply the default after. When a site is safe, end the line with `# pipefail-ok: <reason>`. The reason must be non-empty.

The gate reads only added lines, plus the whole file when the PR newly arms errexit and pipefail. It skips `local v=$(...)`, `if` or `while` conditions, and pipes inside a `<(...)` or `>(...)` process substitution, because the shell ignores their status. The matcher does not parse quotes or join continued lines, so a gate pass does not prove a line is safe. `bin/test-check-pipefail-assignment-guard` holds a fixture for each review finding behind backlog issue 1645.
