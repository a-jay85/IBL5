---
description: Shell portability gotchas for bin/ scripts and skill inline bash — BSD vs GNU date, SSH single-line commands, heredoc JSON, isolated bash blocks in skills.
last_verified: 2026-09-16
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
