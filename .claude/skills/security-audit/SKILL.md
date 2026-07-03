---
allowed-tools: Bash(gh pr diff:*), Bash(gh pr view:*), Bash(gh pr comment:*),
  Bash(gh api:*), Bash(git rev-parse:*), Bash(source:*)
name: security-audit
description: Token-efficient security audit for pull requests
disable-model-invocation: true
model: sonnet
last_verified: 2026-07-03
---

Perform a security audit on the given pull request. This command optimizes token usage by fetching the diff once and passing it to a single merged security agent.

## Step 1: Eligibility check

Use a **Haiku** agent to check if the pull request:
(a) is closed, (b) is a draft, (c) has zero PHP files changed, or (d) already has a `### Security audit` from you in a PR issue comment OR a PR **review** body — `gh pr view --json comments,reviews` — since findings now post as a review with inline threads, not only as issue comments.

If any of these are true, do not proceed. Tell the user why.

## Step 2: Fetch all data once (parent context — do NOT delegate this to agents)

Run these commands yourself (not via agents) and store the results:

### 2a. Get PR metadata
```bash
gh pr view --json number,headRefOid,headRefName,baseRefName
```

### 2b. Get the PHP file list
```bash
gh pr diff --name-only | grep '\.php$'
```

### 2c. Get the PHP-only diff and measure its size
```bash
DIFF=$(gh pr diff | awk '/^diff --git/{found=0} /^diff --git.*\.php/{found=1} found{print}')
DIFF_SIZE=$(echo "$DIFF" | wc -c)
echo "Diff size: $DIFF_SIZE bytes"
echo "$DIFF"
```

Use `$DIFF` for all subsequent steps. Do NOT call `gh pr diff` again.

**If the PHP diff is larger than 100,000 bytes:** Instead of the full diff, use the GitHub API to get per-file patches (excluding test files):
```bash
gh api "repos/a-jay85/IBL5/pulls/{N}/files" --paginate --jq '.[] | select(.filename | test("\\.php$")) | select(.filename | test("tests/") | not) | "--- " + .filename + " ---\n" + (.patch // "(binary or too large)")'
```

Store all of these results — they will be passed as context to agents below.

## Step 3: Pattern detection and agent launch

**Read** `.claude/review-shared/_security-agents.md` for the canonical pattern-detection bash block and agent definition.

Run the pattern-detection block from that file to get SQL and Forms category counts. Build the `CATEGORIES:` line (always include Auth/Authz; add SQL Injection if SQL > 0; add CSRF Protection if Forms > 0). Launch a **single Haiku agent** with the categories line and the PHP-only diff from Step 2c. Do not forward CLAUDE.md content (auto-loaded).

**XSS and Input Validation are NOT audited here** — they're deterministically enforced by `RequireEscapedOutputRule` and `BanRawSuperglobalsRule`. Any finding those rules would catch is out of scope.

**CRITICAL: The agent should not call `gh pr diff`.** The diff was already fetched in Step 2.

## Step 4: Confidence scoring

**Read** `.claude/review-shared/_review-rubric.md` for the canonical rubric, thresholds, Automatic-Zero rule list, and IBL5 false-positive list.

Collect all findings from Step 3 into a numbered list. Launch a **single Haiku agent**, pass it the findings plus the **Scoring scale and Thresholds sections** from `_review-rubric.md` (not the full Automatic Zero or false-positive lists — review agents have already filtered those). Instruct it to return JSON scores per the rubric.

Parse the JSON response and assign scores back to each finding.

## Step 5: Filter

Apply the **Security audit** threshold from `_review-rubric.md` (currently `< 75` dropped). If no findings remain, skip to Step 7 with "no issues found".

## Step 6: Re-check eligibility

Run this command directly (no agent needed):
```bash
gh pr view --json state --jq '.state'
```

If the result is not `"OPEN"`, do not post a comment. Tell the user the PR is no longer open.

## Step 7: Post findings

Source the shared posting helper (same idiom as `bin/lib/pr-armable.sh`):

```bash
source "$(git rev-parse --show-toplevel)/bin/lib/post-review-findings.sh"
```

**If findings survived Step 5,** build a findings JSON array (write to a temp file — not a shell arg). Each element:
```json
{ "path": "repo/relative/path.php",
  "line": 17,
  "body": "**[SEVERITY]** Vulnerability type in `Class::method()` — description\n\n<full-SHA range link>",
  "score": 88 }
```
- `path` is the repo-relative file path.
- `line` is a single anchor line on the new-file (right) side.
- `body` is the finding description in the format below, followed by the full-SHA link. Do NOT add the footer — the helper adds it.
- `score` is the Haiku score from Step 4.

Then call:
```bash
post_review_findings "$PR_NUMBER" "$HEAD_SHA" "Security audit" "$findings_file"
```

The helper routes on-diff findings to a batch resolvable review POST (inline threads) and out-of-diff findings to a fallback `gh pr comment`. Nothing is dropped.

**If no findings survived Step 5,** call:
```bash
post_review_summary "$PR_NUMBER" "Security audit" \
    "No security issues found. <brief evidence per category> (XSS and input validation are enforced by PHPStan custom rules.)"
```

### Per-finding body format:

```
**[SEVERITY]** Vulnerability type in `Class::method()` — description

https://github.com/a-jay85/IBL5/blob/FULL_SHA/path/to/file.php#L13-L17
```

(The heading `### Security audit`, the found-N summary line, and the footer are emitted by the helper — do NOT include them in individual finding bodies.)

### Severity mapping:
- **CRITICAL** — SQL injection, command injection
- **HIGH** — Missing auth on state-changing endpoints, open redirect
- **MEDIUM** — Missing CSRF token, missing auth on non-critical endpoints
- **LOW** — Best practice deviations

(XSS and input validation are not in this list because `RequireEscapedOutputRule` and `BanRawSuperglobalsRule` catch them in CI before this audit runs.)

### Link format rules:
- Must use the full git SHA (from Step 2a's `headRefOid`)
- Format: `https://github.com/a-jay85/IBL5/blob/{FULL_SHA}/path/to/file#L{start}-L{end}`
- Provide at least 1 line of context before and after the line you are commenting about
- Do NOT use `$(git rev-parse HEAD)` or any bash interpolation in the body string — expand the SHA beforehand

### Notes:
- Do not check build signal or attempt to build or typecheck the app. These will run separately.
- Use `gh` to interact with GitHub, not web fetch.
- Make a todo list first.
