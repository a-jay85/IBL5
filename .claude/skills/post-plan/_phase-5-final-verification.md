# Phase 5 — Final Verification (post-plan reference)

Purpose: the full Phase 5 final-verification how-to (all prose steps + all bash blocks).

### Phase 5.0: Plan→test & Plan→file conformance — skip if `PLAN_FOUND=none` or `! $HAS_MATRIX`

At Phase 5.0 START, clear the conformance bridge file so each run begins from a clean slate (an empty file means "nothing unresolved"):

```bash
: > /tmp/post-plan-missing-tests-$PPID
```

Read the Verification Matrix from `$PLAN_FILE`. Collect the test-file path from the "Test file / location" column of every row whose Test type is PHPUnit, API-test, E2E, or Visual-regression. Confirm the PR diff actually wrote each one:

```bash
git diff --name-only origin/master...HEAD > /tmp/post-plan-changed-$PPID
# For each planned test path $T extracted from the matrix:
grep -qF "$T" /tmp/post-plan-changed-$PPID || echo "MISSING: $T (matrix planned a test the diff never wrote)"
```

For each `MISSING:` test the impl silently dropped planned coverage — now likelier to matter, since the matrix carries the negative-path and security rows `/plan` gates 9 and 12 require. Write the missing test, run it green, and checkpoint (same as Phase 6 test authoring). Skip a planned test **only** if its target behavior was cut from the implementation; note that in a PR comment rather than writing a hollow test.

A `MISSING:` item is **resolved** when its test was authored-and-run-green OR was explicitly cut-from-implementation with a PR comment noting the cut. An item that is neither — a planned test the diff never wrote and which you did not author or explicitly cut — is **unresolved**.

**Plan→file conformance.** The same failure mode that drops a planned test also drops a planned *non-test* edit: an impl agent can end its turn with a summary claiming files were changed that never landed in the commit (PR #923 claimed workflow + rule edits that were absent). The test-path check above only covers test files, so additionally verify every **must-appear** file in the plan's `## Critical Files` section actually shows up in the diff. A Critical File is **must-appear by default** — it is **exempt only when its annotation carries an explicit reference marker** (matches `reference|read-?only|verif(y|ication)|template|no[- ]edit|no[- ]change|unchanged|context`, case-insensitive, after stripping backticked tokens). Bare entries AND change-described entries (e.g. `— add the foo helper`, `(new)`, `(header comment only)`) are all must-appear — because authors routinely describe what each change-target does, exempting on *any* annotation silently lets a described-but-dropped file slip through (it inertly exempted every entry of this very plan in dog-food). The canonical marker is `(reference)` / `(read-only reference)`, mandated for non-changed entries by the `/plan` rule; the broader keyword set is a legacy fallback for plans written before that rule. **Failure mode by design: loud and resolvable** — a reference annotated without a recognized marker yields a `MISSING-FILE:` that the resolution step below dismisses with a one-line PR comment, vs. the old rule's silent, total non-coverage. Validated against the full 222-plan corpus: every must-appear entry is a genuine change-target, zero false-blocks. Plans with no `## Critical Files` section produce an empty loop and are silently skipped.

```bash
# Reuses /tmp/post-plan-changed-$PPID (written above) and $PLAN_FILE.
EXEMPT_RE='reference|read-?only|verif(y|ication)|template|no[- ]edit|no[- ]change|unchanged|context'
awk '/^## *Critical Files/{f=1;next} /^## /{f=0} f' "$PLAN_FILE" \
  | grep -E '^[[:space:]]*-[[:space:]]*`' | while IFS= read -r LINE; do
    CF=$(printf '%s\n' "$LINE" | grep -oE '`[^`]+`' | head -1 | tr -d '`')   # primary path only; inline `pattern from X` refs ignored
    [ -z "$CF" ] && continue
    REST=$(printf '%s\n' "$LINE" | sed -E 's/`[^`]*`//g')                    # annotation prose, backticks removed
    printf '%s\n' "$REST" | grep -qiE "$EXEMPT_RE" && continue               # explicit reference marker => exempt
    grep -qF "$CF" /tmp/post-plan-changed-$PPID \
      || echo "MISSING-FILE: $CF (plan Critical File never appeared in the diff)"
done
```

For each `MISSING-FILE:`, the impl dropped a planned change. Either (a) make the change now — the plan's implementation steps describe it (this is the #923 remedy: finish the work), run any relevant check, and checkpoint (commit + push) — or (b) if the file was legitimately cut from scope, or is a reference the plan author forgot to annotate, note that in a PR comment. A `MISSING-FILE:` item is **resolved** by (a) or (b); otherwise **unresolved**.

At Phase 5.0 END, append each remaining **UNRESOLVED** `MISSING:` and `MISSING-FILE:` item (label + path + reason) to `/tmp/post-plan-missing-tests-$PPID`, one per line. Authored-green / implemented-and-checkpointed / cut-with-comment items are NOT written. This bridge file is consulted by the Phase 6.5 auto-merge gate.

**PHPUnit + PHPStan — direct Bash (no agent):** **Skip if** `! $HAS_PHP`. The PostToolUse hook already ran both during edits, and a PHP-less diff cannot regress either suite. Run both as **blocking** (foreground) direct Bash calls — do **NOT** pass `run_in_background: true`. Both finish in ~1–2 min, well under the per-phase cap, and backgrounding them here is the trap that stall-killed the 2026-06-21 runs: when the E2E track is skipped there is nothing left to wait on in-turn, so the model backgrounds them and ends the turn expecting a re-invocation that headless mode never delivers. Running blocking returns their results in-turn and you proceed straight to Phase 6. Output is ~5 lines each — agent overhead (~25K tokens) is never justified. (If you ever do background them for parallelism with the E2E agent, the drain rule at the top of this skill is mandatory: poll `BashOutput` to completion before computing `PHASE5_VERIFY_STATUS` — never end the turn on a pending task.)

```bash
cd <worktree>/ibl5 && vendor/bin/phpunit --no-progress --no-output --testdox-summary | tail -n 3
```
```bash
cd <worktree>/ibl5 && composer run analyse
```

**Go engine track — direct Bash (no agent):** **Skip if** `! $HAS_GO`. Runs **alongside** the PHP tracks (additive — a mixed PHP+Go PR runs both). All commands target the repo-root engine module; the `make` targets are defined in `engine/Makefile`.

1. **Format + tests/golden/coverage (load-bearing local gate):**
   ```bash
   make -C <worktree>/engine fmt-check
   make -C <worktree>/engine cover
   ```
   `cover` runs `go test ./...` — which includes `TestGolden`, the byte-for-byte snapshot comparison at `engine/internal/sim/testdata/golden.json` — and enforces the coverage floor (`COVER_MIN`). The Go toolchain is always present, so these always run. A non-zero exit from either is a **deterministic Go-track failure**.
2. **Lint (conditional — CI is the fallback gate):**
   ```bash
   if command -v golangci-lint >/dev/null 2>&1; then
       make -C <worktree>/engine lint
   else
       echo "golangci-lint not on PATH — deferring lint to engine.yml CI job (Phase 7)"
   fi
   ```
   golangci-lint is not preinstalled in a fresh automouse env. A missing linter is **not** a Go-track `fail` — the `engine.yml` CI job enforces lint and is watched in Phase 7.
3. If `fmt-check` or `cover` fails: fix in worktree, commit, push, and re-run the Go track (same fix-and-rerun discipline as the PHP tracks). **Never** resolve a red `TestGolden` by running `make -C <worktree>/engine golden-update` unless the output change was intentional and is called out in the PR — a silent regenerate masks a behavior regression (see Phase 6.5 condition (5)).

**E2E agent (Haiku):**

Steps:
1. Run `bin/wt-down <worktree-name>` then `bin/wt-up <worktree-name> --seed`
2. Run `bin/e2e-for-pr <worktree-name>` and capture both stdout and exit code
3. Branch on the result:
   - **Exit 0, empty stdout** → print "No E2E tests map to changed files — skipping E2E" and stop
   - **Exit 2** → run full suite: `bin/e2e-wt.sh <worktree-name>`
   - **Exit 0, test file list on stdout** → run targeted: `bin/e2e-wt.sh <worktree-name> <test-files-from-stdout>`

Prompt MUST include: "Run these commands and report the summary output. Do NOT investigate, re-run, or diagnose individual test failures — just report the pass/fail counts and any error output."

Prompt MUST ALSO include this long-run handling rule: "`bin/e2e-wt.sh` can exceed the Bash tool's 600s cap. If it does, invoke Bash with `run_in_background: true` and poll via the **BashOutput** tool — do NOT pipe to a file and shell-loop on `grep`. If you absolutely must shell-poll, the terminator must accept every Playwright outcome (`grep -qE 'passed|failed|did not run|timed out|error'` scanning `tail -10`, not a single last-line match): Playwright's trailing line is often `N did not run` after an early setup failure, which will hang a `passed|failed`-only check forever."

If either fails, fix in worktree, commit, push, and re-run the failing track.

### Phase 5 END: emit `PHASE5_VERIFY_STATUS`

**Drain barrier (do this FIRST):** before computing the status, confirm **no background shell from this phase is still running** — poll `BashOutput` until every backgrounded track (a long-running E2E launched with `run_in_background`, or any backgrounded PHPUnit/PHPStan) reports a terminal result. You may not aggregate or advance to Phase 6 while a Phase 5 task is pending. "Still waiting on a track" is not a status — resolve it in-turn.

**After** the fix-and-rerun loop above has resolved (every launched track green) or given up (a deterministic failure survives), aggregate the Phase 5 tracks — PHPUnit, PHPStan (both direct Bash, skipped when `! $HAS_PHP`), the **Go engine track** (direct Bash, skipped when `! $HAS_GO`), and the E2E Haiku sub-agent — into one status. The E2E track runs in a sub-agent whose shell state does not persist, so you (Opus) read its reported pass/fail from context, combine it with the PHPUnit/PHPStan/Go results, and write the flag. Persist it for durability across the per-phase cap (same `$PPID` temp-file pattern Phase 3 / Phase 5.0 use):

```bash
# PHASE5_VERIFY_STATUS: pass = at least one track ran and every launched track is green (or only-flaky-on-retry);
#                       fail = a deterministic failure survived the fix-and-rerun loop in ANY launched track (PHPUnit, PHPStan, Go, or E2E);
#                       skipped = no track ran at all (e.g. docs-only / CSS-only PR: ! $HAS_PHP and ! $HAS_GO and E2E mapped to nothing).
echo "PHASE5_VERIFY_STATUS=$PHASE5_VERIFY_STATUS" > /tmp/post-plan-phase5-status-$PPID
```

Rules for the value:
- A flaky failure (e.g. shared-session/CSRF) that passes on retry **with no code change** counts as `pass` — only a deterministic failure surviving the loop is `fail`.
- `fail` if **any** launched track failed deterministically — the Go track (a red `cover`/`TestGolden`/coverage-floor) counts exactly like a red PHPUnit. An **engine-only** PR with the Go track green is `pass`, **not** `skipped` (this is the core fix: an engine PR is now verified, so it no longer slips through Phase 6.5 as `skipped`).
- `skipped` is NOT `fail`: the value is `skipped` only when **no** track ran at all — a docs-only / CSS-only PR with no PHP, no Go, and E2E mapped to nothing (`bin/e2e-for-pr` exit 0 with empty stdout).
- Record the status **after** the loop resolves or gives up — never mid-fix.
