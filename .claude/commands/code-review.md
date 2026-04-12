---
allowed-tools: Bash(gh pr diff:*), Bash(gh pr view:*), Bash(gh pr comment:*),
  Bash(gh issue view:*), Bash(gh search:*), Bash(gh pr list:*), Bash(gh api:*),
  Bash(git log:*), Bash(git rev-parse:*), Bash(git show:*)
description: Token-efficient code review for pull requests
model: sonnet
last_verified: 2026-04-12
---

Provide a code review for the given pull request. This command optimizes token usage by fetching the diff once and distributing only what each agent needs.

## Step 1: Eligibility check

Use a **Haiku** agent to check if the pull request:
(a) is closed, (b) is a draft, (c) does not need a code review (e.g. automated PR, or very simple and obviously ok), or (d) already has a code review from you earlier.

If any of these are true, do not proceed. Tell the user why.

## Step 2: Fetch all data once (parent context — do NOT delegate this to agents)

Run these commands yourself (not via agents) and store the results:

### 2a. Get PR metadata
```bash
gh pr view --json number,headRefOid,headRefName,baseRefName,title,body,author
```

### 2b. Get the file list
```bash
gh pr diff --name-only
```

### 2c. Get the filtered diff and measure its size
```bash
DIFF=$(gh pr diff | awk '/^diff --git.*migrations\//{skip=1} /^diff --git/{skip=0} skip==0{print}')
DIFF_SIZE=$(echo "$DIFF" | wc -c)
echo "Diff size: $DIFF_SIZE bytes"
echo "$DIFF"
```

Use `$DIFF` for all subsequent steps. Do NOT call `gh pr diff` again.

**If the filtered diff is larger than 100,000 bytes:** Instead of the full diff, use the GitHub API to get per-file patches (excluding migration files):
```bash
gh api "repos/a-jay85/IBL5/pulls/{N}/files" --paginate --jq '.[] | select(.filename | test("migrations/") | not) | "--- " + .filename + " ---\n" + (.patch // "(binary or too large)")'
```
If still too large, further exclude test files from the diff content given to agents 1-2 (but note their existence).

### 2d. Read the root CLAUDE.md
Read the file `/Users/ajaynicolas/Documents/GitHub/IBL5/CLAUDE.md`.

### 2e. Find directory-specific CLAUDE.md files
Check if any CLAUDE.md files exist in directories whose files the PR modified. Read those too.

Store all of these results — they will be passed as context to agents below.

## Step 3: Launch parallel Sonnet agents

**Read** `.claude/commands/_review-agents.md` for the canonical agent definitions. It defines up to 6 agents (architectural fitness, bug detection, git history, previous PRs, code comments, database performance).

Launch applicable agents in parallel (consult `_review-agents.md` for each agent's focus; skip Agent 6 if no PHP files changed). Each agent receives:
- The filtered diff from Step 2c
- The file list from Step 2b
- The CLAUDE.md content(s) from Steps 2d/2e

**CRITICAL: No agent should call `gh pr diff`.** The diff was already fetched in Step 2.

## Step 4: Confidence scoring

**Read** `.claude/commands/_review-rubric.md` for the canonical rubric, thresholds, Automatic-Zero rule list, and IBL5 false-positive list.

Collect all issues found in Step 3 into a numbered list. Launch a **single Haiku agent**, pass it the issues list plus the **Scoring scale and Thresholds sections** from `_review-rubric.md` (not the full Automatic Zero or false-positive lists — review agents have already filtered those). Instruct it to return JSON scores per the rubric.

Parse the JSON response and assign scores back to each issue.

## Step 5: Filter

Apply the **Code review** threshold from `_review-rubric.md` (currently `< 80` dropped). If no issues remain, skip to Step 7 with "no issues found".

## Step 6: Re-check eligibility

Run this command directly (no agent needed):
```bash
gh pr view --json state --jq '.state'
```

If the result is not `"OPEN"`, do not post a comment. Tell the user the PR is no longer open.

## Step 7: Post comment

Use `gh pr comment` to post the review. Use the PR number and head SHA from Step 2a for links.

### Notes:
- Do not check build signal or attempt to build or typecheck the app. These will run separately.
- Use `gh` to interact with GitHub, not web fetch.
- Make a todo list first.
- You must cite and link each bug (e.g. if referring to a CLAUDE.md, you must link it).

### Comment format (if issues found):

---

### Code review

Found N issues:

1. \<brief description of bug\> (CLAUDE.md says "\<...\>")

\<link to file and line with full sha1 + line range for context, e.g. https://github.com/a-jay85/IBL5/blob/FULL_SHA/path/to/file.php#L13-L17\>

2. \<brief description of bug\> (bug due to \<file and code snippet\>)

\<link to file and line with full sha1 + line range for context\>

Generated with [Claude Code](https://claude.ai/code)

\<sub\>If this code review was useful, please react with thumbs-up. Otherwise, react with thumbs-down.\</sub\>

---

### Comment format (if no issues):

---

### Code review

No issues found. \<1-2 sentence evidence summary assembled from agent responses, e.g. "Architecture follows Repository/Service/View split. Native-type comparisons consistent with schema. No bind\_param mismatches in modified files."\>

Generated with [Claude Code](https://claude.ai/code)

---

### Link format rules:
- Must use the full git SHA (from Step 2a's `headRefOid`)
- Format: `https://github.com/a-jay85/IBL5/blob/{FULL_SHA}/path/to/file#L{start}-L{end}`
- Provide at least 1 line of context before and after the line you are commenting about
- Do NOT use `$(git rev-parse HEAD)` or any bash interpolation in the comment — expand the SHA beforehand
