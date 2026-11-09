---
description: Shell portability gotchas for bin/ scripts and skill inline bash — BSD vs GNU date and stat, SSH single-line commands, heredoc JSON, isolated bash blocks in skills.
last_verified: 2026-11-08
paths:
  - "bin/**"
  - ".claude/skills/**/*.md"
---

# Shell Portability

## BSD vs GNU `date` — use `_date_ago()`

macOS ships BSD `date`; Linux CI ships GNU `date`. Arithmetic date flags differ:

| GNU (Linux) | BSD (macOS) |
|-------------|-------------|
| `date -d '7 days ago'` | `date -v-7d` |
| `date --date='...'` | no `--date` flag |

**Fix:** use the `_date_ago <N>` helper in `bin/automouse/run` (N = number of days; days are hardcoded) — it detects the platform and emits a portable timestamp. Never write raw `date -d` or `date -v` in scripts that run on both.

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

## SSH commands must be single-line strings

Never emit a bare `ssh host` followed by commands on separate lines — those subsequent lines run **locally** (or are eaten as ssh stdin), not on the remote:

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

In `.claude/skills/**/*.md` skill files, every fenced `bash` block runs in a separate shell process — **variable assignments do not persist between blocks**. Do not set a variable in block 1 and read it in block 2. Either:
- Use a single block for the whole sequence, or
- Persist state to a temp file between blocks.

**JSON output from skill bash:** use `printf` not `echo -e` for JSON strings — `echo -e` interprets escape sequences inconsistently across shells (`\n` in dash vs. bash). `printf '%s\n' "$json"` is safe everywhere.
