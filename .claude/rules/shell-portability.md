---
description: Shell portability gotchas for bin/ scripts and skill inline bash. Covers the bash 4 floor (Homebrew bash on macOS, env-bash shebang, version guard), BSD vs GNU date and stat, SSH single-line commands, heredoc JSON, isolated bash blocks in skills, and shellcheck on only the scripts you changed.
last_verified: 2026-11-23
paths:
  - "bin/**"
  - ".claude/skills/**/*.md"
---

# Shell Portability

## Bash 4+ only: Homebrew bash on macOS

Every repo shell script runs on bash 4 or newer. macOS ships bash 3.2 at `/bin/bash`, so the Mac uses Homebrew bash (`brew install bash`, at `/opt/homebrew/bin/bash`). CI and production run Linux bash 5.

- Start every bash script with `#!/usr/bin/env bash`. An absolute-path bash shebang such as `#!/bin/bash` pins 3.2 on the Mac.
- Every executable bash script under `bin/`, `ibl5/bin/` and `tools/` carries the one-line version guard before its first command. It exits 2 and names `brew install bash` when bash is older than 4. Sourced-only libs (no exec bit), `bin/hooks/`, and `fixtures/` dirs carry none.
- A plist or launcher that runs a repo script needs `/opt/homebrew/bin` ahead of `/usr/bin` in PATH. `bin/lib/launchd-sched.sh` supplies `LSCHED_JOB_PATH` for that.
- `bin/check-shell-portability` rejects an absolute bash shebang and a missing or moved guard. CI runs it on every pull request.

This allows bash 4 features but does not ask for them: existing 3.2-safe code stays as written.

## BSD vs GNU `date`: use `_date_ago()`

macOS ships BSD `date`; Linux CI ships GNU `date`. Arithmetic date flags differ:

| GNU (Linux) | BSD (macOS) |
|-------------|-------------|
| `date -d '7 days ago'` | `date -v-7d` |
| `date --date='...'` | no `--date` flag |

**Fix:** use the `_date_ago <N>` helper in `bin/automouse/run` (N = number of days; days are hardcoded). It detects the platform and emits a portable timestamp. Never write raw `date -d` or `date -v` in scripts that run on both.

## BSD vs GNU `stat`: GNU form first

macOS ships BSD `stat`. Linux CI ships GNU `stat`. The format flags differ:

| GNU (Linux) | BSD (macOS) |
|-------------|-------------|
| `stat -c %Y file` (mtime epoch) | `stat -f %m file` |
| `stat -c %s file` (size in bytes) | `stat -f %z file` |

On Linux, `stat -f` selects filesystem status. It prints a block of filesystem text to stdout and then exits 1. A BSD-first fallback such as `stat -f %m f || stat -c %Y f` captures that text plus the real epoch, and the next arithmetic test fails. BSD `stat -c` prints nothing to stdout and exits 1, so the GNU-first order is safe on both:

```bash
mtime=$(stat -c %Y "$f" 2>/dev/null || stat -f %m "$f" 2>/dev/null || echo 0)
```

`bin/check-shell-portability` enforces this on every shell file under `bin/`, and CI runs it on each pull request. It flags a `stat -f` call with a format operand when no `stat -c` call comes earlier in the same statement. Backslash-continued lines count as one statement. When a site is correct for another reason, such as output validated before use or a GNU branch chosen by a probe, end the line with `# portability-ok: <reason>`. The reason must be non-empty.

## Directory locks: own the lock by an O_EXCL file

uutils mkdir, the default on Ubuntu 26.04, can report success to both of two racing `mkdir` calls on one path. A lock that counts `mkdir` success as ownership then has two owners. Every directory lock under `bin/` takes ownership by creating a file inside the lock dir with `claim_file` from `bin/lib/claim-file.sh`. The helper creates the file under noclobber, which bash opens with O_EXCL, and restores the caller's noclobber setting. `mkdir` only makes room for that file. A failed claim means the lock is held, and the script takes the same wait, reap, or refuse path that a failed `mkdir` takes.

- Take a lock with `mkdir "$d" 2>/dev/null && claim_file "$d/pid" printf '%s\n' "$$"`.
- Remove a lock dir with `rm -rf`. `rmdir` fails once the dir holds a claim file.
- Reclaim a stale lock with `claim_reclaim <lockdir> <claim-name> <stale-fn> [cmd...]` from the same file. Never run `rm -rf` and a fresh `mkdir` inline. Two runners can both judge one dead lock stale, and the second `rm -rf` then deletes the first runner's fresh lock. The helper takes the sibling mutex `<lockdir>.reclaim.lock`, runs `<stale-fn> <lockdir>` again under it, and only then removes and re-takes the lock. Each site passes its own predicate and claim file name. `bin/test-claim-file` case `reclaim_helper_race_one_owner` runs two real racers through a barrier.
- A launchd job string cannot source a repo file, so `ljob_submit_wrapper_text` claims its marker inline with `(set -C; : > "$marker")`.
- Test a lock with `claim_file_lying_mkdir_stub`, which writes a `mkdir` that always exits 0. Put its dir first on PATH.

No gate checks this. A text lint cannot tell a lock `mkdir` from a plain directory create, so review and each lock's harness case carry it.

## SSH commands must be single-line strings

Never emit a bare `ssh host` followed by commands on separate lines. Those later lines run **locally** on the Mac, or ssh eats them as stdin:

```bash
# WRONG — echo runs on the Mac, never reaches the server
ssh iblhoops.net
echo 'FOO=bar' >> /home/iblhoops/app/.env

# CORRECT — all remote work in one quoted string
ssh iblhoops.net "echo 'FOO=bar' >> /home/iblhoops/app/.env"
```

Use `&&` chaining inside the quotes for multi-step remote work. Follow each write step with a separate read-only verification: `ssh host '<check>'` so a silent failure is visible immediately.

For complex remote logic, write a script file, `scp` it, then execute by path.

## Backslash continuations for long commands

Assume a narrow terminal (~80 chars). Any shell command exceeding ~80 characters must use `\` line continuations at logical boundaries (before flags, arguments, or the target in `scp`/`rsync`) so the user can copy-paste without breakage. Never rely on terminal soft-wrap.

## Skill inline bash: each block is an isolated shell

In `.claude/skills/**/*.md` skill files, every fenced `bash` block runs in a separate shell process, so a variable set in one block is gone in the next. Do not set a variable in block 1 and read it in block 2. Either:
- Use a single block for the whole sequence, or
- Persist state to a temp file between blocks.

**JSON output from skill bash:** use `printf` instead of `echo -e` for JSON strings. `echo -e` interprets escape sequences inconsistently across shells (`\n` in dash vs. bash). `printf '%s\n' "$json"` is safe everywhere.

## Shellcheck only the scripts you changed

Never run the full-repo shellcheck on the Mac (`bin/lib/shell-scripts.sh --full` piped into `xargs -P`). It starts one shellcheck per core and uses about 2.5 GB in total. Several sessions doing it at once stall the machine. CI's `ShellCheck` job already runs it on every push. That job never failed in the 1000 test runs from 2026-10-30 to 2026-11-11.

Lint only the scripts you touched:

```bash
shellcheck --severity=warning --shell=bash \
  --exclude=SC2034,SC1090,SC2207 bin/foo bin/lib/bar.sh
```

`bin/pre-commit-hook` runs the same check on staged shell files at commit time.
