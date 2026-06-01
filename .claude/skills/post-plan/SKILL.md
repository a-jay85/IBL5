---
name: post-plan
description: Single orchestrator for the post-plan workflow. Runs commit/push/PR, diff classification, code review, security audit, verification, CI monitoring, retrospective, worktree teardown, and background process cleanup as one uninterrupted sequence.
disallowed-tools:
  - EnterPlanMode
  - ExitPlanMode
  - Skill
last_verified: 2026-06-01
---

# Post-Plan Orchestrator

Execute all phases below **sequentially in a single response**. Do NOT stop, ask for input, or return control between phases.

**Phase 11 (Background Process Cleanup) is MANDATORY and must ALWAYS be the last thing you execute before ending your turn.** No phase — including Phase 9 (Retrospective) — is a valid stopping point. If you reach Phase 9 and have nothing to save, continue directly to Phase 10 and Phase 11. Ending your turn before Phase 11 leaves background processes alive, which prevents the `claude` process from exiting and triggers a stall-kill in the nightly runner.

Phase numbers below are local to this skill. The variables computed in Phase 3 (`HAS_PHP`, `NON_CODE_ONLY`, `DOCS_ONLY`, `CSS_ONLY`, `MIGRATION_ONLY`, `HAS_MODIFIED`, `HAS_COMMENTS_IN_DIFF`, `HAS_GO`, `GO_TOUCHED`, `ENGINE_ONLY`, `GOLDEN_CHANGED`, `DIFF`, etc.) are consulted by every downstream phase to gate sub-agent launches — never recompute them locally.

## Incremental Checkpoints

After any phase that modifies files, commit and push before moving to the next phase. Never carry uncommitted work across phase boundaries — if the session dies mid-skill (usage limit, crash), the latest checkpoint is preserved on the branch and a fresh session can resume.

```bash
cd <worktree>
git add -A
if ! git diff --cached --quiet; then
    git commit -m "<scope>: <what this phase changed>"
    git push origin HEAD
fi
```

Phase 2 makes the initial commit and opens the PR. Phases that may modify files after Phase 2 (Phase 4D follow-up fixes if review identifies real bugs, Phase 5 fix-and-rerun loops, Phase 6 test writing, Phase 7 CI fixes) MUST checkpoint before continuing. The squash-merge armed in Phase 6.5 collapses the chain.

---

## Phase 1: Clear Plan Gate & Locate Plan

Remove the plan workflow gate so that commits and edits within this skill are not blocked by PreToolUse hooks:

```bash
rm -f /tmp/claude-plan-active-$PPID
```

Then locate the plan backing this branch so later phases can verify the implementation against its intent. The plan is the spec; phases 4–6 check conformance to it.

```bash
# Authoritative in nightly mode: the handoff JSON's plan_file (the postplan prompt passes its path).
# Interactive fallback: branch slug -> ~/.claude/plans/<slug>.md.
SLUG=$(git rev-parse --abbrev-ref HEAD)
PLAN_FILE=""
[ -f "$HOME/.claude/plans/$SLUG.md" ] && PLAN_FILE="$HOME/.claude/plans/$SLUG.md"
if [ -n "$PLAN_FILE" ]; then
    echo "PLAN_FOUND=$PLAN_FILE"
    grep -qiE '^\s*\|.*Test type' "$PLAN_FILE" && echo "HAS_MATRIX=true" || echo "HAS_MATRIX=false"
    grep -qiE '^#+ *Security'    "$PLAN_FILE" && echo "HAS_PLAN_SECURITY=true" || echo "HAS_PLAN_SECURITY=false"
    grep -qiE 'Reuse'            "$PLAN_FILE" && echo "HAS_PLAN_REUSE=true" || echo "HAS_PLAN_REUSE=false"
else
    echo "PLAN_FOUND=none — plan-blind mode: skip every plan-conformance step below; behave exactly as before."
fi
```

When the nightly postplan prompt supplied an authoritative plan path, use it instead of the branch-slug derivation above. Record `$PLAN_FILE` and the flags. **Plan conformance is additive, never a hard dependency** — when `PLAN_FOUND=none` (any PR with no `/plan` file), every "if a plan exists" gate below is skipped and post-plan runs as it does today.

---

## Phase 2: Commit, Push & PR

If the working tree is clean and `git diff origin/master...HEAD` is also empty (nothing to ship), abort the entire skill — there is nothing to post-plan.

1. **If working tree has uncommitted changes:** stage relevant changes, review with `git diff --staged`, commit (CLAUDE.md conventions), push. Skip this sub-step if the working tree is already clean (user committed before invoking the skill).
2. **If no PR exists for the current branch:** create one with `gh pr create`. **Stacked PRs:** If branched from a feature branch (not `master`), use `--base <parent-branch>`. Skip if a PR already exists.
3. **Manual testing in PR description:** Check the plan file for a Verification Matrix. If one exists, copy only the rows classified as `Truly-manual` into the PR's `## Manual Testing` section. If the matrix has zero truly-manual rows (or the plan says "All verification is automated"), write: `No manual testing needed — all changes are covered by unit and E2E tests.` If no plan file or no matrix exists, fall back to the original rule: list only steps requiring subjective human judgment on new or redesigned UI/UX ("does this look/feel good?", "does this flow work well?"). Production comparison and "does output still match?" are visual-regression-replaceable, not manual. Do NOT list CLI commands or script invocations — Phase 6 executes those.
4. Use Haiku agents for commit message generation if delegating

---

## Phase 3: Classify Diff

Run this bash block once. It computes classification flags and writes the filtered diff to `/tmp/post-plan-diff-$PPID` (same `$PPID` pattern Phase 1 uses — stable across Bash tool calls in the session). Uses `gh pr diff` when a PR exists (correct base for stacked PRs), falls back to `git diff origin/master...HEAD` pre-PR. Every later phase consults these flags and reads the diff file — do not recompute.

```bash
DIFF_FILE=/tmp/post-plan-diff-$PPID

# Detect whether a PR exists for this branch
HAS_PR=false
if gh pr diff --name-only &>/dev/null; then
    HAS_PR=true
fi

# Changed file list (deleted files excluded — nothing to review)
if $HAS_PR; then
    FILES=$(gh pr diff --name-only)
else
    FILES=$(git diff --name-only origin/master...HEAD)
fi

# Per-type counts (grep -cE, default 0 if no match)
COUNT_TOTAL=$(echo "$FILES" | grep -c . || true)
COUNT_PHP=$(echo "$FILES" | grep -cE '\.php$' || true)
COUNT_CSS=$(echo "$FILES" | grep -cE '\.css$|^ibl5/design/' || true)
COUNT_MD=$(echo "$FILES" | grep -cE '\.md$' || true)
COUNT_MIGRATION=$(echo "$FILES" | grep -cE '^ibl5/migrations/.*\.sql$' || true)
COUNT_TEST=$(echo "$FILES" | grep -cE '^ibl5/tests/|\.test\.(ts|js|php)$|\.spec\.(ts|js)$' || true)
COUNT_E2E_SPECS=$(echo "$FILES" | grep -cE '^ibl5/tests/e2e/.*\.ts$' || true)
COUNT_LOCK=$(echo "$FILES" | grep -cE '(composer|package|bun)\.lock$' || true)
COUNT_SNAPSHOT=$(echo "$FILES" | grep -cE '__snapshots__/|\.snap$' || true)
COUNT_NON_CODE=$(( COUNT_MD + COUNT_LOCK + COUNT_SNAPSHOT ))
# Go engine (repo-root engine/, NOT under ibl5/). Anchored at ^engine/ so other
# worktree checkouts under worktrees/<slug>/engine/*.go never false-positive —
# PR file lists are repo-root-relative.
COUNT_GO=$(echo "$FILES" | grep -cE '^engine/.*\.go$' || true)
COUNT_IBL5=$(echo "$FILES" | grep -cE '^ibl5/' || true)
GO_TOUCHED_COUNT=$(echo "$FILES" | grep -cE '^engine/' || true)

# Derived flags (true/false strings for readable gates downstream)
HAS_PHP=$([ "$COUNT_PHP" -gt 0 ] && echo true || echo false)
HAS_CSS=$([ "$COUNT_CSS" -gt 0 ] && echo true || echo false)
HAS_MIGRATION=$([ "$COUNT_MIGRATION" -gt 0 ] && echo true || echo false)
HAS_TEST=$([ "$COUNT_TEST" -gt 0 ] && echo true || echo false)
HAS_E2E_SPECS=$([ "$COUNT_E2E_SPECS" -gt 0 ] && echo true || echo false)
HAS_GO=$([ "$COUNT_GO" -gt 0 ] && echo true || echo false)
GO_TOUCHED=$([ "$GO_TOUCHED_COUNT" -gt 0 ] && echo true || echo false)
# Engine-only = engine files touched and NOT a single ibl5/PHP file in the diff.
# Drives Agent A skip (Phase 4B) and the Phase 10 Path A guard.
ENGINE_ONLY=$([ "$GO_TOUCHED" = true ] && [ "$COUNT_PHP" -eq 0 ] && [ "$COUNT_IBL5" -eq 0 ] && echo true || echo false)
# Golden-snapshot change — INDEPENDENT of HAS_GO (golden.json is not a .go file).
# Drives the Phase 6.5 headless auto-merge block.
GOLDEN_CHANGED=$(echo "$FILES" | grep -qxF 'engine/internal/sim/testdata/golden.json' && echo true || echo false)

# E2E spec module extraction (drives Agent D cross-reference)
E2E_SPEC_MODULES=""
HAS_E2E_PROD_OVERLAP=false
if [ "$COUNT_E2E_SPECS" -gt 0 ]; then
    E2E_SPEC_FILES=$(echo "$FILES" | grep -E '^ibl5/tests/e2e/.*\.ts$')
    E2E_SPEC_MODULES=$(
      git diff origin/master...HEAD -- $E2E_SPEC_FILES \
        | grep -E '^\+' \
        | grep -oE "(modules\.php\?name=[A-Za-z][A-Za-z0-9_]*|modules/[A-Za-z][A-Za-z0-9_]*/)" \
        | sed -E 's#modules\.php\?name=##; s#modules/##; s#/##' \
        | sort -u
    )
    if [ -n "$E2E_SPEC_MODULES" ]; then
        for M in $E2E_SPEC_MODULES; do
            if echo "$FILES" | grep -qE "^ibl5/modules/$M/"; then
                HAS_E2E_PROD_OVERLAP=true
                break
            fi
        done
    fi
fi

# "X-only" means every file in $FILES matches that category
DOCS_ONLY=$([ "$COUNT_TOTAL" -gt 0 ] && [ "$COUNT_MD" -eq "$COUNT_TOTAL" ] && echo true || echo false)
CSS_ONLY=$([ "$COUNT_TOTAL" -gt 0 ] && [ "$COUNT_CSS" -eq "$COUNT_TOTAL" ] && echo true || echo false)
MIGRATION_ONLY=$([ "$COUNT_TOTAL" -gt 0 ] && [ "$COUNT_MIGRATION" -eq "$COUNT_TOTAL" ] && echo true || echo false)
TEST_ONLY=$([ "$COUNT_TOTAL" -gt 0 ] && [ "$COUNT_TEST" -eq "$COUNT_TOTAL" ] && echo true || echo false)
NON_CODE_ONLY=$([ "$COUNT_TOTAL" -gt 0 ] && [ "$COUNT_NON_CODE" -eq "$COUNT_TOTAL" ] && echo true || echo false)

# "Has modified (not added) files" — gates Phase 4B Agent C (previous PRs)
MODIFIED_COUNT=$(git diff --diff-filter=M --name-only origin/master...HEAD 2>/dev/null | grep -c . || true)
HAS_MODIFIED=$([ "$MODIFIED_COUNT" -gt 0 ] && echo true || echo false)

# Filtered diff -> temp file (single awk pass stripping migrations, lockfiles, snapshots)
DIFF_AWK='
  /^diff --git.*(migrations\/|composer\.lock|package-lock\.json|bun\.lock|__snapshots__\/|\.snap$)/ {skip=1; next}
  /^diff --git/ {skip=0}
  skip==0 {print}
'
if $HAS_PR; then
    gh pr diff | awk "$DIFF_AWK" > "$DIFF_FILE"
else
    git diff origin/master...HEAD | awk "$DIFF_AWK" > "$DIFF_FILE"
fi

# Fallback: if filtered diff is still > 100KB and a PR exists, shrink via gh api
if [ "$(wc -c < "$DIFF_FILE")" -gt 102400 ] && $HAS_PR; then
  PR_NUM=$(gh pr view --json number --jq '.number')
  gh api "repos/a-jay85/IBL5/pulls/$PR_NUM/files" --paginate \
    --jq '.[] | select(.filename | test("migrations/|composer\\.lock|package-lock\\.json|bun\\.lock|__snapshots__/|\\.snap$") | not) | "--- " + .filename + " ---\n" + (.patch // "(binary or too large)")' \
    > "$DIFF_FILE"
fi

# Code-comment detection on added lines only (gates Phase 4B Agent B)
COMMENT_COUNT=$(grep -cE '^\+[[:space:]]*(//|#|/\*|\*)' "$DIFF_FILE" || true)
HAS_COMMENTS_IN_DIFF=$([ "$COMMENT_COUNT" -gt 0 ] && echo true || echo false)

# PHP lines changed (gates Phase 4B Agents B-C size threshold)
LINES_PHP_CHANGED=$(git diff origin/master...HEAD -- '*.php' | grep -cE '^\+[^+]' || true)

# Classification summary for the run log (Claude reads these and remembers them for later phases)
echo "=== Diff classification ==="
echo "  total=$COUNT_TOTAL php=$COUNT_PHP css=$COUNT_CSS md=$COUNT_MD migration=$COUNT_MIGRATION test=$COUNT_TEST lock=$COUNT_LOCK snapshot=$COUNT_SNAPSHOT"
echo "  DOCS_ONLY=$DOCS_ONLY CSS_ONLY=$CSS_ONLY MIGRATION_ONLY=$MIGRATION_ONLY TEST_ONLY=$TEST_ONLY NON_CODE_ONLY=$NON_CODE_ONLY"
echo "  HAS_PHP=$HAS_PHP HAS_CSS=$HAS_CSS HAS_MODIFIED=$HAS_MODIFIED HAS_COMMENTS_IN_DIFF=$HAS_COMMENTS_IN_DIFF LINES_PHP_CHANGED=$LINES_PHP_CHANGED"
echo "  HAS_E2E_SPECS=$HAS_E2E_SPECS HAS_E2E_PROD_OVERLAP=$HAS_E2E_PROD_OVERLAP"
echo "  HAS_GO=$HAS_GO GO_TOUCHED=$GO_TOUCHED ENGINE_ONLY=$ENGINE_ONLY GOLDEN_CHANGED=$GOLDEN_CHANGED COUNT_GO=$COUNT_GO"
echo "  E2E_SPEC_MODULES=$(echo $E2E_SPEC_MODULES | tr '\n' ' ')"
echo "  DIFF_FILE=$DIFF_FILE ($(wc -c < "$DIFF_FILE") bytes)"
```

Each Bash tool call runs in a fresh shell, so the classification flags are **not** bash state you can reference later — they're output Claude records from this block's stdout and applies as gates in later phases. The filtered diff is bridged via `$DIFF_FILE` (same `$PPID` across calls).

---

## Phase 4: Code Review + Security Audit

Agent definitions and scoring rubric live in shared include files under `.claude/commands/` so this skill, `/pr-review`, and `/security-audit` all share one source of truth. Read them as instructed below — do NOT inline the definitions or duplicate them.

### 4A: Fetch PR data (shared by both)

Run these commands yourself (not via agents):

```bash
gh pr view --json number,headRefOid,headRefName,baseRefName,title,body,author
cat /tmp/post-plan-diff-$PPID   # filtered diff written by Phase 3 (already < 100KB after the fallback)
```

Capture the `cat` output — that is `$DIFF` for every sub-agent prompt below. No sub-agent calls `gh pr diff`.

**Do not forward CLAUDE.md content in agent prompts** — sub-agents auto-load CLAUDE.md on init, so forwarding it doubles the token cost (~5K × N agents). If directory-specific `CLAUDE.md` files exist for modified directories, read them and forward only those (they are not auto-loaded).

### 4B: Code Review — up to 3 parallel agents (merged by tier)

**Read** `.claude/commands/_review-agents.md` (Agents A/B/C) and `.claude/commands/_test-spec-agent.md` (Agent D — E2E specs). The canonical agent definitions.

Pass each agent: PR metadata, file list, and filtered `$DIFF`. **No agent calls `gh pr diff`.** Do not forward CLAUDE.md content (auto-loaded).

**Reuse conformance (Agent A only, when `PLAN_FOUND` and `$HAS_PLAN_REUSE`):** extract the plan's Reuse notes from `$PLAN_FILE` and append them to Agent A's prompt under a `PLANNED REUSE:` heading, instructing it to flag any step that hand-rolled logic the plan directed it to reuse (e.g. plan named `SalaryCapRepository::getTeamTotalSalary()`, impl wrote a raw query). This turns Section 1's open-ended architectural judgment into a concrete conformance check.

**Model tiers:**

- Agent A (Architecture + Bug detection + DB performance): **Sonnet**
- Agent B (Git history + Code comments): **Sonnet**
- Agent C (Previous PRs): **Haiku**
- Agent D (E2E specs — POST-effect + assertion discrimination + coverage-branch): **Sonnet**

**Launch gates** (consult Phase 3 variables — skip the launch entirely, don't let the agent exit early):

- Agent A: skip if `$NON_CODE_ONLY` or `$ENGINE_ONLY`. (Agent A is a "Senior PHP Architect"; a pure-Go engine diff has no PHP architecture to review — skipping avoids low-signal PHP-rubric review of Go code. A **mixed** PR — `HAS_PHP=true`, `ENGINE_ONLY=false` — still launches Agent A to review the PHP portion.) If `$MIGRATION_ONLY`, instruct agent to skip Section 2 (bug detection). If `! $HAS_PHP`, instruct agent to skip Section 3 (DB performance).
- Agent B: skip if BOTH sub-gates fail: (`! $HAS_PHP` or `$LINES_PHP_CHANGED <= 50`) AND (`$NON_CODE_ONLY` or `! $HAS_COMMENTS_IN_DIFF`). If only one sub-gate passes, instruct agent to run only that section.
- Agent C: skip if `$NON_CODE_ONLY` or `! $HAS_MODIFIED` or `$LINES_PHP_CHANGED <= 50`
- Agent D: skip if `! $HAS_E2E_SPECS`. When launched, pre-slice the diff into two temp files before forwarding to the agent:
  ```bash
  # Spec portion of the diff (only .ts under ibl5/tests/e2e/)
  awk '
    /^diff --git.*ibl5\/tests\/e2e\/.*\.ts/ {keep=1; print; next}
    /^diff --git/ {keep=0}
    keep==1 {print}
  ' "$DIFF_FILE" > /tmp/post-plan-spec-diff-$PPID

  # Production portion: only files under ibl5/modules/<M>/ for M in E2E_SPEC_MODULES
  MODULES_REGEX=$(echo "$E2E_SPEC_MODULES" | tr '\n' '|' | sed 's/|$//')
  if [ -n "$MODULES_REGEX" ]; then
      awk -v re="ibl5/modules/($MODULES_REGEX)/" '
        $0 ~ "^diff --git.*"re {keep=1; print; next}
        /^diff --git/ {keep=0}
        keep==1 {print}
      ' "$DIFF_FILE" > /tmp/post-plan-spec-prod-diff-$PPID
  else
      : > /tmp/post-plan-spec-prod-diff-$PPID
  fi
  ```
  Pass Agent D: PR metadata, the spec file list, `/tmp/post-plan-spec-diff-$PPID`, `/tmp/post-plan-spec-prod-diff-$PPID`, and `$HAS_E2E_PROD_OVERLAP`. The agent does **not** call `gh pr diff`.

### 4C: Security Audit — single conditional Haiku agent

**Skip entire 4C if** `! $HAS_PHP`. CSS, markdown, migrations, and lockfile bumps cannot introduce SQLi/CSRF/auth vulnerabilities.

**Read** `.claude/commands/_security-agents.md` — the canonical security agent definition and pattern-detection bash block.

Run the pattern-detection block from that file to get SQL and Forms category counts. Build the `CATEGORIES:` line (always include Auth/Authz; add SQL Injection if SQL > 0; add CSRF Protection if Forms > 0). Launch a **single Haiku agent** with the categories line and the PHP-only subset of `$DIFF`. Do not forward CLAUDE.md content (auto-loaded).

**Plan-backed mode (when `PLAN_FOUND` and `$HAS_PLAN_SECURITY`):** the plan already declares each touched surface and its intended defense. Pass the plan's Security section to the agent as an `EXPECTED DEFENSES:` checklist and instruct it to (a) confirm each planned defense is present in the diff and (b) flag any state-changing surface the plan did *not* anticipate. You may build `CATEGORIES:` directly from the plan's declared surfaces instead of running the pattern-detection grep. This shifts the audit from discovery to verification — it catches "CSRF was planned but the impl omitted it" and cuts the false positives blind pattern-matching produces.

**XSS and Input Validation are NOT audited here** — they're deterministically enforced by `RequireEscapedOutputRule` and `BanRawSuperglobalsRule` (run in PostToolUse and CI).

### 4D: Score, filter, and post

**Read** `.claude/commands/_review-rubric.md` — the canonical rubric, thresholds (`< 80` for code review, `< 75` for security), Automatic-Zero rule list, and IBL5 false-positive list.

Combine ALL issues from 4B and 4C into one numbered list.

**Skip the scoring agent if the combined list is empty** — jump straight to posting "No issues found." comments in the two `gh pr comment` steps below.

Otherwise launch a **single Haiku agent**, pass it the issues list plus the **Scoring scale and Thresholds sections** from `_review-rubric.md` (not the full Automatic Zero or false-positive lists — review agents have already filtered those). Instruct it to return JSON scores per that rubric. Parse the response and assign scores back to each issue.

**Filter** per the thresholds in `_review-rubric.md`.

**Re-check PR state:** `gh pr view --json state --jq '.state'` — skip posting if not `OPEN`.

**Post two `gh pr comment` entries** (code review + security audit) using full SHA from 4A.

Code review format (issues found): `### Code review\n\nFound N issues:\n\n1. <description> (CLAUDE.md says "<rule>")\n\n<link>`

Code review format (no issues): `### Code review\n\nNo issues found.` followed by a 1-2 sentence evidence summary assembled from agent responses (e.g., "Architecture follows Repository/Service/View split. Native-type comparisons consistent with schema. No bind_param mismatches in modified files.").

Security audit format (issues found): `### Security audit\n\nFound N issue(s):\n\n**[SEVERITY]** Type in \`Class::method()\` — description\n\n<link>` Severity: CRITICAL (SQLi/CMDi), HIGH (missing auth/open redirect), MEDIUM (CSRF/missing auth on non-critical endpoints), LOW (best practice).

Security audit format (no issues): `### Security audit\n\nNo security issues found.` followed by brief evidence per category that launched (e.g., "SQL: all queries use prepared statements. CSRF: token validated on line N. Auth: guard present on state-changing endpoints.") and `(XSS and input validation are enforced by PHPStan custom rules.)`

**Link format:** `https://github.com/a-jay85/IBL5/blob/{FULL_SHA}/path/to/file#L{start}-L{end}` — expand SHA beforehand, never use bash interpolation in the comment. Include 1 line of context before/after.

Both comments end with: `Generated with [Claude Code](https://claude.ai/code)` and `<sub>If this was useful, react with thumbs-up. Otherwise, thumbs-down.</sub>`

---

## Phase 5: Final Verification

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

**PHPUnit + PHPStan — direct Bash (no agent):** **Skip if** `! $HAS_PHP`. The PostToolUse hook already ran both during edits, and a PHP-less diff cannot regress either suite. Run both as direct Bash calls with `run_in_background: true` so they execute in parallel with the E2E agent below. Output is ~5 lines each — agent overhead (~25K tokens) is never justified.

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
   golangci-lint is not preinstalled in a fresh nightly env. A missing linter is **not** a Go-track `fail` — the `engine.yml` CI job enforces lint and is watched in Phase 7.
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

---

## Phase 6: Manual Testing Automation

**Skip if** PR description says "No manual testing needed."

### Step 1: Extract

```bash
EXTRACTED=$(gh pr view --json body --jq '.body' | sed -n '/## Manual Testing/,/^## /p')
echo "$EXTRACTED"
```

**Also skip Phase 6 entirely if `$EXTRACTED` is empty or whitespace-only** — the section is absent or was already cleared. Do not launch the Sonnet review gate on empty input.

### Step 2: Sonnet Review Gate

**Skip this gate when `PLAN_FOUND` and the surviving Manual Testing steps came from the plan's matrix** — `/plan` gates 3, 9, and 12 already classified automatable-vs-manual upstream and authoritatively, so re-litigating it here is wasted. Treat the remaining steps as truly-manual and leave them in the PR. Run the gate below only for plan-less PRs, where no upstream classification occurred.

Launch a **single Sonnet agent** with this prompt (substitute the extracted steps and file list):

> You are a **Senior QA Automation Engineer** reviewing manual testing steps from a PR. Your job: eliminate every step that can be replaced by automated verification. Be aggressive — manual testing is expensive and error-prone. Only steps requiring subjective human judgment on **new or redesigned** UI/UX should survive ("does this look/feel good?", "does this flow work well?").
>
> **PR manual testing steps:**
> {extracted steps from Step 1}
>
> **Changed files:** {file list from Phase 4A}
>
> Classify each step into exactly one category:
>
> | Category | Description | Action |
> |----------|-------------|--------|
> | **CLI-executable** | A command or script Claude can run directly (curl, bin/db-query, grep output) | Opus runs it |
> | **PHPUnit-replaceable** | Unit/integration test can assert the behavior (DB state, service output, calculation) | Opus writes PHPUnit test |
> | **API-test-replaceable** | HTTP request/response can be verified programmatically (endpoint returns correct JSON/HTML, status codes, headers) | Opus writes integration or API test |
> | **E2E-replaceable** | Browser interaction needed (form submit, page navigation, HTMX swap, DOM state) | Opus writes Playwright test |
> | **Visual-regression-replaceable** | "Does output still match?" / production comparison where UI/UX was not intentionally redesigned | Opus writes Playwright visual-regression test or screenshot diff |
> | **Truly manual** | Requires subjective human judgment on **new or redesigned** UI/UX that no automated test can replicate ("does this look/feel good?", "does this new flow work well?") | Stays in PR description |
>
> For each step, return a JSON array:
> ```json
> [
>   {"step": "original step text", "category": "cli-executable|phpunit|api-test|e2e|visual-regression|truly-manual", "rationale": "why this category", "test_hint": "what the test should assert (omit for cli-executable and truly-manual)"}
> ]
> ```
>
> **Bias toward automation.** If a step says "verify X works" or "check that Y returns Z", that is automatable — not manual. "Compare against production" is visual-regression-replaceable (screenshot diff) unless UI/UX was intentionally redesigned — it is NOT truly manual. Only subjective judgment on new/redesigned UI/UX is truly manual.

### Step 3: Execute findings

Using the Sonnet agent's classifications:

1. **CLI-executable:** Run directly in the worktree. Fix failures, commit.
2. **PHPUnit/API-test/E2E-replaceable:** Write the appropriate test type. Fix until green. Do not reclassify as truly manual — if the test is hard to write, that's a reason to spend more effort, not less. After 3 failed attempts, keep the item in the PR description as-is (not reclassified) and note what was tried.
3. **Truly manual:** Keep in PR description.
4. **Update PR:** Remove verified/automated steps. If none remain, replace section with `No manual testing needed — all changes are covered by automated tests.` Apply: `gh pr edit --body "<updated>"`
5. **Checkpoint:** If any new tests were written or files modified, commit and push before continuing to Phase 6.5.

---

## Phase 6.5: Arm Auto-Merge

Enable auto-merge **before** watching CI. This is the earliest point all gating conditions are known — review/audit findings are scored in Phase 4, manual-testing is resolved in Phase 6, and Phase 5's local verification status is recorded at its END — and arming here (rather than after the watch) is the whole point: if this post-plan phase is later killed mid-watch by the per-phase cap (`MAX_PP_SECS`) or a usage limit, GitHub still holds the queued merge and fires it once required checks pass, with no further agent action needed. Arming after the watch (the old Phase 8 placement) meant any phase that ran out of budget during the watch shipped a PR that was never set to auto-merge.

**Already merged?** If `gh pr view --json state --jq '.state'` returns `MERGED`, there is nothing to arm — skip to Phase 7 (which will early-exit).

**All five** conditions must be true: (1) PR says "No manual testing needed", (2) no review/audit findings scored >= 80, (3) no unresolved `MISSING:` planned-test items NOR `MISSING-FILE:` planned-file items remain from Phase 5.0 — i.e. `/tmp/post-plan-missing-tests-$PPID` is absent or empty. Phase 5.0 is skipped entirely when `PLAN_FOUND=none` or `! $HAS_MATRIX`, so this bridge file frequently never exists; **absent/empty = PASS (non-blocking)**. Only a non-empty file blocks: `[ -s /tmp/post-plan-missing-tests-$PPID ]` → condition (3) fails. (4) Phase 5's local verification did not deterministically fail — i.e. `PHASE5_VERIFY_STATUS` is `pass` or `skipped`, **not** `fail`. This is the condition #887 lacked: it armed auto-merge with red E2E because no gate checked Phase 5's result. (5) golden-snapshot safety: a change to `engine/internal/sim/testdata/golden.json` does NOT auto-ship unattended (see below).

**Condition (4) blocks on the VALUE, not file presence** — the status file is non-empty for `pass` and `skipped` too (it always contains `PHASE5_VERIFY_STATUS=...`), so the `[ -s ... ]` idiom condition (3) uses would wrongly block every `pass`/`skipped`. Block only on the literal `fail` value; **absent file OR `pass` OR `skipped` = PASS (non-blocking)** — a `skipped` status (docs-only / PHP-less PR with no mapped E2E) must NOT block, or every such PR would stop arming, a regression worse than #887:

```bash
# condition (4): fails ONLY when the status is the literal `fail`
grep -q 'PHASE5_VERIFY_STATUS=fail' /tmp/post-plan-phase5-status-$PPID 2>/dev/null && echo "BLOCKED: Phase 5 deterministic failure"
```

**Condition (5) — golden-snapshot safety (headless only).** If `$GOLDEN_CHANGED` is `true` AND `$CLAUDE_HEADLESS` is set, **block** auto-merge: a change to `engine/internal/sim/testdata/golden.json` means the engine's simulation output changed, and a snapshot change with no human present is exactly when not to auto-ship (an agent can turn a red `TestGolden` green by regenerating the snapshot, silently masking a regression). In **interactive** mode (`$CLAUDE_HEADLESS` unset), do **not** block — emit a prominent warning with the same text so the human confirms intent before merging. This condition is independent of `HAS_GO` (a golden-only diff is `HAS_GO=false` but must still trigger it):

```bash
# condition (5): blocks ONLY when golden changed AND running headless (nightly autonomous)
[ "$GOLDEN_CHANGED" = true ] && [ -n "$CLAUDE_HEADLESS" ] \
  && echo "BLOCKED: golden.json (simulation behavior) changed in headless mode — confirm this was an intentional 'make -C engine golden-update', not a masked regression"
```

If met: `gh pr merge --squash --auto --delete-branch`. The `--auto` flag queues the merge — it does **not** merge now, it arms; GitHub executes it once all required status checks pass. Do not sync local to master here; the merge has not happened yet.

If not met: do **not** arm auto-merge. Report which condition(s) blocked — the user merges manually after review. When condition (3) is the blocker, cite which planned test (`MISSING:`) or planned Critical File (`MISSING-FILE:`) is missing by `cat`-ing the bridge file (`cat /tmp/post-plan-missing-tests-$PPID`) into the report. When condition (4) is the blocker, report which Phase 5 track failed (PHPUnit / PHPStan / Go / E2E). When condition (5) is the blocker (headless + golden changed), report that the golden snapshot changed and the merge needs a human to confirm the behavior change was intentional. Continue to Phase 7 regardless, to monitor and fix CI — the fix-and-rerun there clears the red track so a later run can arm.

**Interactive golden warning:** Whenever `$GOLDEN_CHANGED` is `true` and `$CLAUDE_HEADLESS` is unset (so condition (5) did not block), still surface the warning prominently in the report — "⚠️ golden.json changed: simulation behavior changed. Confirm this was an intentional `make -C engine golden-update`, not a masked regression." — so the human reviews intent before the queued merge fires.

---

## Phase 7: CI Monitoring

**Auto-merge is already armed (Phase 6.5). Monitor CI and fix failures so the queued merge can fire; then continue to Phase 8.**

> **Field-shape gotcha — read before editing this phase.**
> Two `gh` commands return different shapes; mixing them produces unsatisfiable conditions.
> - `gh pr checks <pr> --json name,state,link` → `state` ∈ `SUCCESS | FAILURE | SKIPPED | PENDING | CANCELLED | NEUTRAL`. **No `conclusion` field, no `COMPLETED` value.**
> - `gh pr view <pr> --json statusCheckRollup` → `status` ∈ `COMPLETED | IN_PROGRESS | QUEUED`, `conclusion` ∈ `SUCCESS | FAILURE | SKIPPED | …`.
> Do not write `state == "COMPLETED"` or `conclusion == "failure"` against `gh pr checks` — both are silently false forever.

0. **Early-exit on merged PR:** Before any polling, run `gh pr view <pr> --json state --jq '.state'`. If `MERGED`, skip the rest of Phase 7 and continue at Phase 8 — the auto-merge armed in Phase 6.5 has already fired (required checks were already green) and watching CI to completion adds nothing but wall-clock burn. This is the load-bearing optimization that keeps the nightly loop from burning a full watch timeout on an already-shipped PR.
1. **Wait for checks to register:** Poll `gh pr checks <pr> --json name,state 2>/dev/null | jq 'length'` up to 4 times with 15s waits. If count stays 0, warn user and continue to Phase 8.
2. **Block until CI settles:** `gh pr checks <pr> --watch --fail-fast --interval 20` (Bash timeout 1200000 = 20 min cap — leaves a ~40-min cushion under `MAX_PP_SECS=3600` for Phase 5.0 conformance + Phases 8-11 cleanup). The gh CLI handles the polling and exit logic itself; do not re-implement it in jq. Exit codes: `0` = all checks passed, `8` = at least one failed, other = transport error.
3. **If exit 0** → Phase 8. (Mid-watch merge detection was intentionally dropped: `gh pr checks --watch` exits as soon as the last check settles, so the only window auto-merge could fire inside the watch is the ~5–30s between final-check-pass and auto-merge action — not worth a hand-rolled poll loop. Step 0 already covers the case where the PR merged before Phase 7 started.)
4. **If exit 8:** Get failed checks via `gh pr checks <pr> --json name,state,link --jq '[.[] | select(.state == "FAILURE")]'` (uppercase `FAILURE`, field is `state` not `conclusion`). Download logs (`gh run view <id> --log-failed`). **Fix all failures** — master's CI is green, so any failure on this PR is this PR's fault (even in files outside the diff). The only exception is a flaky test that passes on retry with no code change; note it in a PR comment and move on. Fix, commit, push, loop back to step 1. After 3 iterations, report failures to user and continue to Phase 8 — auto-merge will fire when CI eventually passes.
5. **If bash times out (20 min elapsed):** Run one final `gh pr checks <pr>` (no `--watch`) to capture settled state. If it now exits 0, continue to Phase 8. If 8, jump to step 4. Otherwise report to user and continue to Phase 8 — do not re-enter watch.

---

## Phase 8: Confirm Merge State

Auto-merge was already armed (or deliberately not armed) in Phase 6.5 — this phase only confirms the outcome and syncs local if the merge landed. Do not re-run `gh pr merge` here.

- **If `gh pr view --json state --jq '.state'` returns `MERGED`:** the queued merge fired. Run `cd <repo-root> && git checkout master && git pull origin master` to sync local, then continue to Phase 9.
- **If still `OPEN` and auto-merge was armed in Phase 6.5:** the merge is queued and will fire when required checks pass — no further action. Do not sync local to master; the merge has not happened yet. Continue to Phase 9.
- **If still `OPEN` and auto-merge was NOT armed (a Phase 6.5 condition blocked):** report which condition(s) blocked. The user merges manually.

---

## Phase 9: Retrospective

Before saving any memory, ask: **"Can this be a PHPStan rule instead?"** If the mistake is mechanical and deterministic, it belongs in `ibl5/phpstan-rules/` as a new custom rule — open a TODO comment in the plan file rather than a memory entry. (For an **engine-side** learning, the analog is: "Can this be a `golangci-lint` linter or a `go vet` rule?" — a mechanical, deterministic Go mistake belongs in `engine/.golangci.yml` config or a custom analyzer, not memory.) Memories are for things a linter cannot express (architectural judgment, environment quirks, incident context).

Save to memory only if something was learned that would **prevent a bug** in a future session AND cannot be mechanized AND isn't already in MEMORY.md, CLAUDE.md, `.claude/rules/`, or an existing PHPStan rule. Read the target memory file first to avoid duplicates. If nothing qualifies, skip silently.

---

## Phase 10: Preview Environment

**Skip entirely if** `$CLAUDE_HEADLESS` is set (nightly autonomous mode — no human present to verify).

Check the PR state using the PR number captured in Phase 4A:

```bash
PR_STATE=$(gh pr view <PR_NUMBER> --json state --jq '.state')
```

### Path A: Main-stack rebuild (when `$PR_STATE` = `MERGED`)

**Skip the rebuild if `$ENGINE_ONLY`** — an engine-only change touches no `ibl5/` PHP and cannot affect the rendered app, so tearing down and re-streaming prod data adds nothing. Print "Engine-only change — skipping Path A main-stack rebuild." and end Phase 10.

The PR has been merged (either auto-merge fired during CI watch, or it was already merged before post-plan started). Run `cd <repo-root> && git checkout master && git pull origin master` to sync local, then rebuild the main Docker stack with fresh prod data.

1. **Update vendor** (may be stale after merge):
   ```bash
   (cd <repo-root>/ibl5 && composer install)
   ```

2. **Check for prod credentials** before tearing down the running stack:
   ```bash
   grep -q '^REMOTE_HOST=' <repo-root>/.env \
     && grep -q '^REMOTE_USER=' <repo-root>/.env \
     && grep -q '^REMOTE_PASSWORD=' <repo-root>/.env
   ```
   If any `REMOTE_*` credential is missing: warn "Fresh prod data unavailable — REMOTE_* credentials not found in .env. Skipping main-stack rebuild." and **stop Phase 10** (leave the existing main stack untouched).

3. **Tear down and restart** with stale seed skipped:
   ```bash
   cd <repo-root> && docker compose down -v
   docker compose up -d
   ```
   `docker compose down -v` removes only the main project's volume (`ibl5-mariadb-data`) — worktree volumes are in separate compose projects and are not affected.

4. **Wait for MariaDB to be healthy:**
   ```bash
   RETRIES=0
   until docker exec ibl5-mariadb healthcheck.sh --connect --innodb_initialized &>/dev/null; do
       RETRIES=$((RETRIES + 1))
       if [ "$RETRIES" -ge 30 ]; then
           echo "Error: MariaDB did not become healthy after 30 attempts"
           break
       fi
       sleep 2
   done
   ```

5. **Stream fresh prod data** (redirect to log to keep context clean):
   ```bash
   bin/db-sync-prod > /tmp/db-sync-prod.log 2>&1 && echo "PASS: db-sync-prod completed" && tail -20 /tmp/db-sync-prod.log || { echo "FAIL: db-sync-prod"; tail -40 /tmp/db-sync-prod.log; }
   ```
   With no arguments, targets the main `ibl5-mariadb` container. Streams from prod, handles generated columns, strips DEFINER clauses, backfills `schema_migrations`, and runs `bin/db-migrate` for pending migrations.

6. **Smoke test** — verify main.localhost loads with prod content:
   ```bash
   HTTP_CODE=$(curl -s -o /dev/null -w '%{http_code}' http://main.localhost/ibl5/)
   if [ "$HTTP_CODE" != "200" ]; then
       echo "FAIL: main.localhost returned HTTP $HTTP_CODE"
       docker logs ibl5-php --tail 30
   fi

   BODY=$(curl -s http://main.localhost/ibl5/)
   if echo "$BODY" | grep -qi 'fatal error\|500 Internal'; then
       echo "FAIL: PHP fatal error detected in response"
       docker logs ibl5-php --tail 30
   fi

   if echo "$BODY" | grep -qi 'standings\|scores\|roster'; then
       echo "PASS: Prod content detected"
   else
       echo "WARN: Could not confirm prod content in response"
   fi
   ```
   If the smoke test fails: print the error details. Do NOT retry the full rebuild — the logs are more useful for diagnosis.

7. **Print preview URL:** `http://main.localhost/ibl5/`

### Path B: Worktree preview (when `$PR_STATE` != `MERGED`)

**Skip if** worktree was pre-existing or earlier phases left uncommitted fixes.

1. Tear down and restart with production data:
   ```bash
   bin/wt-down <worktree-name>
   bin/wt-up <worktree-name> --prod
   ```
2. Print preview URL: `http://<slug>.localhost/ibl5/`
3. Do NOT run `wt-remove` or `git branch -D`

---

## Phase 11: Background Process Cleanup (MANDATORY — never skip)

**You MUST execute this phase before ending your turn.** This is not optional even if all earlier phases succeeded cleanly.

Background shells from earlier phases (`bin/e2e-wt.sh` in Phase 5, `gh pr checks --watch` in Phase 7) may still be running. If they hold the pipeline open after you emit your final response, the nightly runner's stall-kill fires after 10 minutes and burns an attempt — three burns poison-pill the plan.

Kill known lingering patterns so their tool results deliver immediately (cache warm) rather than hours later (cache miss):

```bash
pkill -f 'bin/e2e-wt\.sh' 2>/dev/null
pkill -f 'bunx.*playwright' 2>/dev/null
pkill -f 'gh pr checks.*--watch' 2>/dev/null
rm -f /tmp/post-plan-spec-diff-$PPID /tmp/post-plan-spec-prod-diff-$PPID /tmp/post-plan-missing-tests-$PPID 2>/dev/null
echo "Background process cleanup complete"
```

This is a single Bash call — no agent needed. Ignore any "no matching processes" output; the `2>/dev/null` suppression handles it. The killed processes deliver their tool results immediately as errors, which is expected and harmless.
