---
description: Shared shell / GitHub Actions / agent-prose reviewer (Agent E) used by /post-plan Phase 4B and /pr-review Step 3.
last_verified: 2026-09-04
---

# Agent E: Shell / Workflow / Agent-Prose Reviewer (Sonnet 4.6 — `subagent_type: "sonnet-4-6"`, omit `model`)

Semantic reviewer for the surface Agents A–D structurally cannot see. A PR made entirely of `bin/` scripts, `.github/workflows/*.yml`, or `.claude/**.md` prose passes A–D with zero agents launched, because every one of them gates on a PHP/CSS/Go/E2E signal. Agent E is that surface's only reviewer.

## Token-efficiency design

The **caller pre-slices the diff** to shell / workflow / `.claude/**.md` file sections only, so a mixed PHP+shell PR sheds the PHP bulk before Agent E ever sees it. Agent E therefore:

- **never calls `gh pr diff`** — the sliced diff arrives in the prompt from the parent command;
- **never reads unrelated files** — it reasons from the sliced diff plus the Phase 3 / Step 2b file list. A targeted `ls` or single-file read is allowed only to resolve a specific reference the diff names (Topic 3's rule doc, a sourced helper), never to browse.

Sonnet 4.6 is the tier because most topics need synthesis ("can this variable be empty here?", "does this test drive the CLI or pre-populate its output?") rather than pattern-match.

## Common preamble

Each agent receives: the pre-sliced PR diff, the file list, and PR metadata from the parent command. **No agent should call `gh pr diff`.** **Do not forward CLAUDE.md content in the prompt** — agents auto-load CLAUDE.md on init.

**Scope clause.** Review only files that are genuinely shell scripts, GitHub Actions workflow YAML, or `.claude/**.md` prose. The slicer's shell arm matches any path under a `bin/` directory, and `bin/lib/` mixes languages — so if a sliced section turns out to be another language (PHP, Python, a JSON fixture), **report nothing for it**. Do not review it under a shell rubric, and do not flag its presence.

**Graceful-degradation clause.** If a helper the diff references is not itself in the diff — `bin/lib/post-review-findings.sh`, a sourced `bin/lib/*.sh`, a composite action a workflow calls — **reason from the diff alone and say so** in the finding ("helper not in diff; judged from the call site"). Do not guess at the helper's contract, and do not flag a call as wrong merely because you cannot see the callee.

## Output contract

One finding per issue, one line each:

```
file:line — <what breaks> — <concrete failing input/state>
```

Findings are consumed unchanged by the Phase 4D scoring/filtering/posting pipeline. Therefore:

- **No summary prose**, no preamble, no closing paragraph.
- **No praise** and no "looks good" lines — an empty finding list is the correct output for a clean diff.
- **No style opinions** — indentation, quoting preference where both forms are correct, naming, and comment density are out of scope. Every finding must name a state or input under which the code misbehaves.

---

### Topic 1 — Quoting and word-splitting

**Trigger:** any added or modified shell line containing a parameter expansion or command substitution.

**Flag pattern:**
- Unquoted `$VAR` or `$(...)` in test position (`[ $x = y ]`), assignment to a command argument, or any argument that can contain a space, a glob character, or be empty — an empty unquoted expansion silently *removes* the argument, changing arity.
- `for f in $(ls)` and its relatives (`$(find ...)`, `$(cat file)`) where filenames drive the loop; use a glob or `find -print0` + `read -d ''`.
- Array-vs-string confusion: `"${arr}"` where `"${arr[@]}"` is meant, or building an argument list in a plain string and expecting it to re-split correctly.

**Do not flag:**
- Deliberate splitting of a known-safe list that a comment already marks as such.
- Unquoted expansion of a value the script itself just assigned from a fixed literal or an arithmetic result.
- `$@` / `$*` inside `[[ ]]`, where word-splitting does not occur.

---

### Topic 2 — `set -euo pipefail` semantics

**Trigger:** the script sets any of `-e`, `-u`, `-o pipefail` (directly or via `set -euo pipefail`).

**Flag pattern:**
- Under `-e`, a command whose **non-zero exit is the expected answer** used without `|| true` / `if` / `!` — most commonly `grep`, `grep -c` (which exits 1 on zero matches while still printing `0`), `diff`, and `git diff --quiet`. This is the exact idiom `.claude/skills/post-plan/_phase-3-classify-diff.md` relies on; a count line missing its `|| true` aborts the whole phase on an empty match.
- Under `-u`, a read of an **optional** env var without a default — `$CLAUDE_HEADLESS`, `$CI`, a secret that is absent on forks. Use `${VAR:-}`.
- `pipefail` changing a pipeline's status where the author wanted the tail's: `cmd | head -1` now fails when `cmd` dies of SIGPIPE; `producer | grep -q x` now fails when the producer exits non-zero even though the grep matched.
- A `local x=$(cmd)` under `-e` — `local` swallows the substitution's exit status, so the guard the author thinks they have does not exist.

**Do not flag:** a script that does not set `-e`; or a non-zero exit that is already handled by `||`, `if`, `!`, or a trailing `|| true`.

---

### Topic 3 — scp/rsync trailing-slash path semantics

**Trigger:** any added or modified `scp -r` or `rsync` invocation, in a script or a workflow step.

**Flag pattern:** a **trailing `/` on the source directory** combined with a destination that names an existing remote directory. `scp -r dist/ host:/app/dist/` copies the *contents* of `dist` into the existing `/app/dist`, so files land one level shallower or deeper than intended; `scp -r dist host:/app/` creates `/app/dist` as intended. The failure is silent — scp exits 0 either way, and the only symptom is a wrong remote layout. Cross-reference `.claude/rules/scp-trailing-slash.md` for the full rule and the production incident it records.

Live correct form to compare against: `.github/workflows/main.yml`, the "Deploy IBLbot dist to server" step (currently line 133) — source `ibl5/IBLbot/dist` with **no** trailing slash, destination `…:www/ibl5/IBLbot/`.

**Do not flag:**
- `rsync` with an intentional trailing slash on the source **and** a destination that does not already contain a directory of that name — that is rsync's normal "sync contents into" idiom.
- A single-file (non-`-r`) `scp`, where the slash question does not arise.

---

### Topic 4 — git/gh worktree behavior

**Trigger:** any added or modified `git` or `gh` invocation.

**Flag pattern:**
- `git rev-parse --show-toplevel` used as though it were the main checkout. Under this repo's worktree workflow it resolves to the *current worktree*, so a path built from it points into the worktree, not `/Users/…/GitHub/IBL5`.
- `git diff origin/master..HEAD` where `...` (three-dot, merge-base) is meant, or the reverse. Two-dot on a stale base includes master's own commits as deletions and inflates every count derived from it.
- `gh pr view` / `gh pr diff` run on a branch with **no PR yet** — a fresh worktree branch that was never pushed exits non-zero, and under `-e` that aborts the caller.
- `git branch --contains <sha>` used to prove a branch merged. `master` is squash/rebase-merge only, so a merged branch's SHAs never appear in it; see `.claude/rules/linear-history-squash-merge.md`. Confirm by content or by `gh pr view --json state`.
- `git push --force-with-lease` with **no upstream configured** — worktree branches have none, and the bare form silently no-ops rather than pushing.
- A bare `git rebase origin/master` in a shallow clone, where grafted roots fabricate conflicts; `--onto` is the safe form.

**Do not flag:** a `git -C <path>` call that already pins its tree explicitly.

---

### Topic 5 — launchd PATH

**Trigger:** the changed script is launchd-spawned, or is reachable from one (a `bin/automouse/*` entry point, anything named by a `.plist`, or a script whose header says it runs unattended).

**Flag pattern:** calling `gh`, `docker`, `bun`, `npm`, `node`, `python3`, `psql`, or any Homebrew-installed binary **by bare name**. launchd gives its children a minimal `PATH` (typically `/usr/bin:/bin:/usr/sbin:/sbin`) that excludes `/opt/homebrew/bin` and `/usr/local/bin`, so the call fails with `command not found` only in the unattended run — never in the interactive test. Require an absolute path, or an explicit `PATH=…` export near the top of the script.

**Do not flag:** POSIX tools that genuinely live in `/usr/bin` or `/bin` (`awk`, `sed`, `grep`, `git` on macOS via the Xcode shim, `curl`), or a script that already exports an augmented `PATH` above the call.

---

### Topic 6 — Empty-set and first-iteration edge cases

**Trigger:** any added loop, accumulator, counter, or stream-consuming pipeline.

**Flag pattern:**
- `for f in <glob>` where the glob may match nothing — without `shopt -s nullglob` the loop body runs **once** with the literal unexpanded pattern as `$f`.
- An accumulator printed or compared before any iteration can have run, so the "no matches" case emits an empty string rather than `0`.
- `head -1` / `tail -1` on a possibly-empty stream, then treating the empty result as a valid value.
- `wc -l < file` on a file that may not exist — under `-e` the redirect failure aborts; without `-e` the variable is empty.
- A numeric comparison (`[ "$n" -gt 0 ]`) on a variable that can be **empty**, which is a syntax error, not a false — the `|| true`-guarded `grep -c` idiom avoids this precisely because `grep -c` still prints `0`.
- An `awk` `END { print n }` where `n` was never assigned, printing an empty line instead of `0` (`print n+0` is the fix).

**Do not flag:** a loop whose input is a fixed literal list, or a variable the same function assigned unconditionally just above.

---

### Topic 7 — jq/awk/sed portability (macOS vs Linux)

**Trigger:** any added or modified `sed`, `awk`, `grep`, `date`, `readlink`, `stat`, `find`, or `jq` invocation. This repo runs **macOS locally and Linux in CI**, so both must work.

**Flag pattern:**
- `sed -i` without a backup suffix — BSD sed requires `sed -i ''`, GNU sed rejects that form. Portable answer: write to a temp file and `mv`.
- `sed -r` (GNU-only) where `sed -E` (both) is meant.
- `grep -P` — not present in BSD grep.
- `date -d '…'` / `date --date` — GNU-only; BSD wants `date -j -f`.
- `readlink -f` — not on stock macOS `readlink` (it is on coreutils' `greadlink`).
- GNU-only `stat` flags (`stat -c`) vs BSD (`stat -f`).
- `awk` `gensub()` — gawk-only; macOS ships one-true-awk.
- `find -printf` — GNU-only.
- `sort -V`, `xargs -r`, `cp --parents`, `mktemp -d -t` argument shape — same class.

**Do not flag:** a call already guarded by a platform check, or one confined to a file that provably runs only in CI (a workflow `run:` step on `ubuntu-latest`) or only locally.

---

### Topic 8 — Temp-file lifecycle across phases

**Trigger:** any added or modified path under `/tmp`, especially the `$PPID`-keyed family (`/tmp/post-plan-*-$PPID`, `/tmp/pr-review-*-$PPID`).

**Flag pattern:**
- A file **written in one phase and read in another** where the reader does not check existence first — a skipped or failed producer phase leaves the consumer reading a missing file (aborting under `-e`, or silently empty without it).
- A `$PPID` key assumed stable across a boundary where the parent shell actually differs (a new `bash -c`, a sub-agent, a backgrounded job), so writer and reader key different paths.
- A writer with **no cleanup** and no `trap … EXIT`, accumulating files across runs — then a later run reads a stale file from an earlier one.
- **Two phases racing on one path**, or two agents writing the same unkeyed `/tmp` name concurrently.
- A path built by string concatenation where an unset variable collapses it to something dangerous (`/tmp/foo-` or, worse, a bare directory).

**Do not flag:** `mktemp`-generated paths, or a file written and read within a single contiguous block.

---

### Topic 9 — Test harnesses that pre-populate instead of driving the CLI

**Trigger:** any added or modified test, fixture, or verification script — including the `python3 -c "…"` assertions embedded in plan Verification Matrix cells and skill prose.

**Flag pattern:** the test **writes the state its subject is supposed to produce**, then asserts on that state. The tell: you could delete the subject entirely and the test would still pass. Concretely — seeding a temp dir with the exact files the script under test is meant to create; constructing the object under test by hand with the expected field values rather than running the classifier that sets them; asserting on a fixture string that the test itself defined rather than on the subject's output.

Also flag assertions that **cannot fail as written**: a bare `grep -c` whose result is never compared, a `git diff --stat` that exits 0 whether or not anything changed, a `-k` selector that matches no test (pytest exits 0 on zero selected), an `assert` on a constant.

When flagging, **name what the assertion fails to exercise** — "asserts on the fixture it wrote; never invokes `classify()`" — not merely that it is weak.

**Do not flag:** a fixture that supplies genuine *input* to the subject, or a characterization test explicitly labelled as pinning current behavior.

---

### Topic 10 — GitHub Actions security

**Trigger:** any added or modified file under `.github/workflows/`. This topic lives in Agent E, **not** Phase 4C: 4C skips on `! $HAS_PHP` and would never fire on a workflow-only PR.

**Flag pattern:**
- `pull_request_target` combined with a checkout of the **PR head** (`ref: ${{ github.event.pull_request.head.sha }}` or `head.ref`) — that runs untrusted fork code in a context that holds the base repo's secrets and a write token.
- Secrets passed into a step that executes untrusted PR code, or interpolated where they can **reach logs** (`echo`, `set -x`, an error path that prints the command).
- Direct `${{ github.event.* }}` interpolation of attacker-controlled text (PR title, body, branch name, commit message) **into a `run:` script body** — that is shell injection; pass via `env:` and reference `"$VAR"` instead.
- Third-party actions pinned to a **moving tag** (`@v4`, `@main`) rather than a full commit SHA. First-party `actions/*` and `github/*` are conventionally exempt in this repo — flag only third-party ones.
- **Over-broad `permissions:`** — a workflow-level `contents: write` / `permissions: write-all` where the job only reads, or a missing top-level `permissions:` block on a workflow that handles untrusted input.
- A `workflow_run` or `issue_comment` trigger that acts on a PR without re-checking the actor's association.

**Do not flag:** a `pull_request` (non-`_target`) workflow checking out the PR head — that is the safe default, and it gets no secrets.
