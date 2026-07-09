# Phase 3 reference — Classify Diff

Purpose: the Phase 3 diff-classification bash. Run it exactly once; its printed flags gate every later phase.

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
# worktree checkouts under IBL5-worktrees/<slug>/engine/*.go never false-positive —
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
