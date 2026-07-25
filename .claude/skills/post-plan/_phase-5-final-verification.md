# Phase 5 — Final Verification (post-plan reference)

Purpose: the full Phase 5 final-verification how-to (all prose steps + all bash blocks).

### Phase 5.0: Plan→test & Plan→file conformance — skip if `PLAN_FOUND=none` or `! $HAS_MATRIX`

At Phase 5.0 START, clear the conformance bridge file AND remove the done-marker, so each run begins from a clean slate. An empty bridge file alone is **not** enough to mean "nothing unresolved" — it is byte-identical to "5.0 died before writing anything", which is why the separate done-marker exists (Phase 6.5 condition (3) treats a missing marker as indeterminate → BLOCKED):

```bash
: > /tmp/post-plan-missing-tests-$PPID
rm -f /tmp/post-plan-conformance-done-$PPID
```

Read the Verification Matrix from `$PLAN_FILE`. Collect the test-file path from the "Test file / location" column of every row whose Test type is PHPUnit, API-test, E2E, or Visual-regression. **Skip every row inside a fenced code block** — a plan that documents the matrix format carries scaffold rows (`| 1 | thing works | PHPUnit | post-impl | \`tests/X.php\` |`) inside a ```` ```bash ```` fixture block, and those are illustration, not declarations. Reading one produces a phantom path no diff can ever contain, so the resulting `MISSING:` is unresolvable and condition (3) holds the PR forever (observed on plan `critical-files-parser-unification`). Fence tracking is **width-aware**, same rule as `## Critical Files` below: this repo wraps 3-backtick blocks in 4-backtick outer ones, and per CommonMark a closing fence must be at least as long as its opener — a width-blind toggle closes on the inner fence and swallows the real matrix underneath. The same skip applies to Truly-manual rows (condition (1) reads those). Then confirm the PR diff actually wrote each collected path:

```bash
git diff --name-only origin/master...HEAD > /tmp/post-plan-changed-$PPID
# For each planned test path $T extracted from the matrix:
grep -qF "$T" /tmp/post-plan-changed-$PPID || echo "MISSING: $T (matrix planned a test the diff never wrote)"
```

For each `MISSING:` test the impl silently dropped planned coverage — now likelier to matter, since the matrix carries the negative-path and security rows `/plan` gates 9 and 12 require. Write the missing test, run it green, and checkpoint (same as Phase 6 test authoring). Skip a planned test **only** if its target behavior was cut from the implementation; note that in a PR comment rather than writing a hollow test.

A `MISSING:` item is **resolved** when its test was authored-and-run-green OR was explicitly cut-from-implementation with a PR comment noting the cut. An item that is neither — a planned test the diff never wrote and which you did not author or explicitly cut — is **unresolved**. A `MISSING-METHOD:` item resolves identically — the named method is authored and run green, **or** explicitly cut with a PR comment stating why the plan-named assertion was dropped; anything else is **unresolved** and holds the arm. Renaming the method in the plan after the fact is not a resolution.

> **Run the CLI-executable rows.** A `CLI-executable` row is a command, not a file, so the path check above cannot see it. Execute the location cell of every **`post-impl`** `CLI-executable` row. `bin/check-plan` gate `[V]` guarantees each such cell is a single runnable shell command with any pipe written `\|`.

```bash
# Reuses $PLAN_FILE. Runs from the WORKTREE ROOT (the dir holding bin/, ibl5/,
# engine/) — never <worktree>/ibl5: a cell like `bin/check-docs` is root-relative.
cd "$(git rev-parse --show-toplevel)"
TO=$(command -v gtimeout || command -v timeout || true)   # macOS ships neither by default
MSEN=$'\001'   # same escaped-pipe sentinel as bin/check-plan's matrix_split()
if [ -z "$TO" ]; then echo "SKIP: no timeout binary — CLI-executable rows not run"; fi
awk '/\|[[:space:]]*[Ww]hat to verify[[:space:]]*\|/{m=1;next} m&&/^[[:space:]]*\|/{print;next} m{m=0}' "$PLAN_FILE" \
  | while IFS= read -r ROW; do
    [ -z "$TO" ] && break
    case "$ROW" in *[!\ \|:-]*) : ;; *) continue ;; esac        # skip the |---|---| row
    R="${ROW//\\|/$MSEN}"
    IFS='|' read -r _lead NUM _WHAT TYPE TIMING CELL _REST <<< "$R"
    printf '%s' "$TYPE"   | grep -qiE 'cli[- ]executable' || continue
    printf '%s' "$TIMING" | grep -qiE 'post-impl'         || continue   # Timing CELL only
    CELL=$(printf '%s' "${CELL//$MSEN/|}" | sed -E 's/^[[:space:]]*`?//; s/`?[[:space:]]*$//')
    [ -z "$CELL" ] && continue
    "$TO" 30 bash -c "$CELL" </dev/null >/dev/null 2>&1
    rc=$?   # capture BEFORE the row-number $(printf|tr) below, else $? reads tr's exit (0)
    [ "$rc" -ne 0 ] && echo "MISSING: row $(printf '%s' "$NUM" | tr -d ' ') — $CELL (CLI-executable row exited non-zero: $rc)"
done
```

> **`pre-impl` rows are excluded, deliberately.** A `pre-impl` cell asserts the state the implementation *destroys* (a characterization grep of the old code, a baseline count). Running it after the change reports failure for a change that worked, so a whole-row `grep post-impl` is wrong too: it matches a row whose "What to verify" prose merely mentions post-impl. Read the Timing cell — the 5th pipe-delimited field, `cut -d'|' -f5` counting the leading empty field — and nothing else.
>
> Exit **124** is `timeout`'s kill code and counts as non-zero: a cell that needs more than 30s is not a Phase 5.0 check. Tell the planner, not the executor — a `CLI-executable` cell must be a **fast, read-only, non-prompting** command, because this block runs it unattended in a headless run. `</dev/null` makes non-prompting mechanical; `>/dev/null 2>&1` keeps a chatty cell out of the log; the timeout caps the run.
>
> A failing row follows the same **resolution** discipline as every other `MISSING:` item: resolved when the underlying defect is fixed and the command re-runs exit 0, or when the failure is environmental / the row was cut and a PR comment says so. Only items still **unresolved** at Phase 5.0 END reach `/tmp/post-plan-missing-tests-$PPID` via the existing append step. Writing to the bridge file *inline* here would be wrong — a row you then fix would still block auto-merge at Phase 6.5 condition (3).
>
> Scope: skill path only. As `:54` already states, `tools/postplan-harness` computes conformance in-process and never touches these `/tmp` files; do not port this executor to the harness.

**Plan→file conformance.** The same failure mode that drops a planned test also drops a planned *non-test* edit: an impl agent can end its turn with a summary claiming files were changed that never landed in the commit (PR #923 claimed workflow + rule edits that were absent). The test-path check above only covers test files, so additionally verify every **must-appear** file in the plan's `## Critical Files` section actually shows up in the diff. A Critical File is **must-appear by default** — it is **exempt only when its annotation contains a parenthesized group whose contents include a canonical token** (implemented in `bin/lib/critical-files.sh`, the single source of truth for this rule). A keyword in surrounding prose does **not** exempt — only parentheses signal "this is a marker, not a description." Canonical markers: `(reference)`, `(read-only)`, `(read-only reference)`, `(verify)`, `(verification)`, `(template)`, `(no-edit)`, `(no-change)`, `(unchanged)`, `(context)`, `(conditional)` — case-insensitive; tokens match as **whole words** (`(filename references the affected class)` is not exempt). An explanatory tail inside the same parens is allowed for the non-`conditional` tokens (`(reference — pattern to mirror)`). Use `(conditional)` for entries that may or may not appear in the diff depending on implementation choices — **`conditional` must *open* the group and be immediately followed by `)` or a separator (`—`/`–`/`-`/`:`/`;`/`,`)**: `(conditional — Phase 4 only)` → EXEMPT; `(conditional Phase 4 only)` → MUST_APPEAR. Bare entries AND change-described entries (e.g. `— add the foo helper`, `(new)`, `(header comment only)`) are all must-appear. **Failure mode by design: loud and resolvable** — a reference annotated without a recognized parenthesized marker yields a `MISSING-FILE:` that the resolution step below dismisses with a one-line PR comment, vs. the old rule's silent, total non-coverage. Across the 259-plan corpus (1578 `## Critical Files` entries), 61 annotations that the old unscoped keyword match exempted are must-appear under the current rule — 46 of the 1121 entries carrying a parenthesized group, plus all 15 prose-only exemptions; pre-existing plans carrying a prose-only exemption now yield a MISSING-FILE that path **(b)** below dismisses with a one-line PR comment — the accepted, bounded migration cost (only plans whose branch is still unmerged can ever re-run Phase 5.0). Plans with no `## Critical Files` section produce an empty loop and are silently skipped.

```bash
# Reuses /tmp/post-plan-changed-$PPID (written above) and $PLAN_FILE.
# The exempt-marker regex and the entry parsing are deliberately NOT inlined
# here: they live in bin/lib/critical-files.sh, the single canonical parser
# shared with bin/check-plan and mirrored by harness/planfile.py. Three
# hand-maintained copies is what produced the false-EXEMPT bug this replaces.
# Source IN-BLOCK — this block runs in its own shell (pr-armable.sh discipline).
source "$(git rev-parse --show-toplevel)/bin/lib/critical-files.sh"
cf_parse_section "$PLAN_FILE" | while IFS= read -r ENTRY; do
    case "$ENTRY" in
        EXEMPT:*)      continue ;;
        MUST_APPEAR:*) CF="${ENTRY#MUST_APPEAR:}" ;;
        *)             continue ;;
    esac
    [ -z "$CF" ] && continue
    grep -qF "$CF" /tmp/post-plan-changed-$PPID \
      || echo "MISSING-FILE: $CF (plan Critical File never appeared in the diff)"
done
```

For each `MISSING-FILE:`, the impl dropped a planned change. Either (a) make the change now — the plan's implementation steps describe it (this is the #923 remedy: finish the work), run any relevant check, and checkpoint (commit + push) — or (b) if the file was legitimately cut from scope, or is a reference the plan author forgot to annotate, note that in a PR comment. A `MISSING-FILE:` item is **resolved** by (a) or (b); otherwise **unresolved**.

**Plan→method conformance.** The path check above proves the *file* landed; this confirms the plan's named methods landed. For each name the plan lists under `## Required Test Methods` (fenced examples stripped width-aware), grep the diff *body* — `git diff --name-only` cannot see declarations:

```bash
# Plan→method conformance. The path check above proves the FILE landed; this
# proves the plan's NAMED methods landed. Reads the diff BODY (the name-only
# file cannot see declarations). Width-aware fence stripping is mandatory: a
# fenced example list would yield a phantom MISSING-METHOD: with no resolution
# path — the failure `critical-files-parser-unification` hit.
git diff origin/master...HEAD > /tmp/post-plan-diff-$PPID
source "$(git rev-parse --show-toplevel)/bin/lib/critical-files.sh"
cf_section_named "$PLAN_FILE" "Required Test Methods" \
  | sed -nE 's/^[[:space:]]*[-*][[:space:]]+`?([A-Za-z_][A-Za-z0-9_]*)`?.*/\1/p' \
  | while IFS= read -r M; do
      grep -qE "(function|def)[[:space:]]+$M\b" /tmp/post-plan-diff-$PPID \
        || echo "MISSING-METHOD: $M (plan required a test method the diff never wrote)"
    done
```

At Phase 5.0 END, append each remaining **UNRESOLVED** `MISSING:`, `MISSING-FILE:` and `MISSING-METHOD:` item (label + path-or-method-name + reason) to `/tmp/post-plan-missing-tests-$PPID`, one per line. Authored-green / implemented-and-checkpointed / cut-with-comment items are NOT written. This bridge file is consulted by the Phase 6.5 auto-merge gate.

Then — **last action of Phase 5.0, after the appends above** — write the done-marker:

```bash
touch /tmp/post-plan-conformance-done-$PPID
```

The marker is the positive assertion that 5.0 reached its end; the bridge file only says *what* was unresolved. Without it, "5.0 ran and found nothing" and "5.0 died mid-section" are indistinguishable and condition (3) would fail OPEN. **The marker must also be written on the skip path** (`PLAN_FOUND=none` or `! $HAS_MATRIX`) — that instruction lives in `SKILL.md` Phase 5.0, because a run that skips 5.0 may never read this file. Omitting the skip-path write would block auto-merge on every plan-blind PR, which is the dominant case.

Compiled-harness note: `tools/postplan-harness` computes conformance in-process (`harness/conformance.py` returns the unresolved list to `armable.py`) and never touches either `/tmp` file. The marker is a **skill-path** mechanism only — do not "port" it to the harness. Per ADR-0092 `bin/post-plan-now` pins the **main-checkout** harness, so `planfile.py`'s matching change is inert until this PR merges to main-checkout `master`; until then the skill-path block above is the live parser.

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
