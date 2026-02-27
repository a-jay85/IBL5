---
allowed-tools: Bash(gh pr diff:*), Bash(gh pr view:*), Bash(gh pr comment:*),
  Bash(gh issue view:*), Bash(gh search:*), Bash(gh pr list:*), Bash(gh api:*),
  Bash(git log:*), Bash(git rev-parse:*), Bash(git show:*)
description: Token-efficient code review for pull requests
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

## Step 3: Five parallel Sonnet agents

Launch **5 parallel Sonnet agents**. Each agent should return a list of issues with the reason each was flagged (e.g. CLAUDE.md adherence, bug, historical git context, etc.).

**CRITICAL: No agent should call `gh pr diff`. The diff was already fetched in Step 2.**

### Agent 1: CLAUDE.md compliance
Pass this agent:
- The filtered diff from Step 2c
- The CLAUDE.md content(s) from Steps 2d/2e

Task: Audit the changes to ensure they comply with CLAUDE.md. Note that CLAUDE.md is guidance for Claude as it writes code, so not all instructions will be applicable during code review.

### Agent 2: Bug detection
Pass this agent:
- The filtered diff from Step 2c

Task: Do a shallow scan for obvious bugs. Focus on the changes themselves, not surrounding context. Focus on large bugs — avoid small issues and nitpicks. Ignore likely false positives.

### Agent 3: Git history
Pass this agent:
- The file list from Step 2b
- The filtered diff from Step 2c

Task: Check the git history of modified files for context that might reveal bugs in the current PR. The agent should run git commands itself, with these constraints:

**Scope constraints (mandatory):**
- Use `git log --oneline -10 <file>` only — do NOT use `git blame`
- Skip non-PHP files entirely (any file ending in `.css`, `.xml`, `.json`, `.sql`, `.md`)
- Investigate at most **5 files** — prioritize the PHP files with the most lines changed in the diff
- **Stop early:** return as soon as you find one relevant historical concern — do not exhaustively search all files
- If no relevant concerns are found after checking 5 files, return an empty list

### Agent 4: Previous PRs
Pass this agent:
- The file list from Step 2b (NOT the diff)

Task: Search for previous pull requests that touched these files using `gh search prs` and `gh pr view`. Check for any comments on those PRs that may also apply to the current PR.

### Agent 5: Code comments
Pass this agent:
- The file list from Step 2b
- The filtered diff from Step 2c

Task: Check whether the PR changes comply with guidance in code comments in the modified files. **First** look for comments visible within the diff's `@@` context windows. Only use the Read tool for a full file read if a comment block appears truncated at the edge of a diff hunk.

## Step 4: Confidence scoring

Collect all issues found in Step 3 into a numbered list. Launch a **single Haiku agent** that receives all issues at once and returns a confidence score for each. Give the agent this prompt:

> Score each issue 0-100 using the rubric below. You also receive the PR title, file list, and CLAUDE.md content for context.
>
> **Rubric:**
> - **0:** Not confident at all. This is a false positive that doesn't stand up to light scrutiny, or is a pre-existing issue.
> - **25:** Somewhat confident. This might be a real issue, but may also be a false positive. The agent wasn't able to verify that it's a real issue. If the issue is stylistic, it is one that was not explicitly called out in the relevant CLAUDE.md.
> - **50:** Moderately confident. The agent was able to verify this is a real issue, but it might be a nitpick or not happen very often in practice. Relative to the rest of the PR, it's not very important.
> - **75:** Highly confident. The agent double checked the issue, and verified that it is very likely a real issue that will be hit in practice. The existing approach in the PR is insufficient. The issue is very important and will directly impact the code's functionality, or it is an issue that is directly mentioned in the relevant CLAUDE.md.
> - **100:** Absolutely certain. The agent double checked the issue, and confirmed that it is definitely a real issue, that will happen frequently in practice. The evidence directly confirms this.
>
> For issues flagged due to CLAUDE.md instructions, double check that the CLAUDE.md actually calls out that issue specifically.
>
> Issues:
> 1. [issue 1 description]
> 2. [issue 2 description]
> ...
>
> Return ONLY valid JSON: [{"n": 1, "score": 75}, {"n": 2, "score": 0}, ...]

Parse the JSON response and assign scores back to each issue.

## Step 5: Filter

Filter out any issues with a score less than 80. If no issues meet this criteria, skip to Step 7 with "no issues found".

## Step 6: Re-check eligibility

Run this command directly (no agent needed):
```bash
gh pr view --json state --jq '.state'
```

If the result is not `"OPEN"`, do not post a comment. Tell the user the PR is no longer open.

## Step 7: Post comment

Use `gh pr comment` to post the review. Use the PR number and head SHA from Step 2a for links.

### Examples of false positives to filter (for Steps 3 and 4):
- Pre-existing issues
- Something that looks like a bug but is not actually a bug
- Pedantic nitpicks that a senior engineer wouldn't call out
- Issues that a linter, typechecker, or compiler would catch (e.g. missing imports, type errors, broken tests, formatting issues, pedantic style issues like newlines). Assume CI will catch these.
- General code quality issues (e.g. lack of test coverage, general security issues, poor documentation), unless explicitly required in CLAUDE.md
- Issues called out in CLAUDE.md but explicitly silenced in the code (e.g. lint ignore comment)
- Changes in functionality that are likely intentional or directly related to the broader change
- Real issues, but on lines that the user did not modify in their pull request

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

No issues found. Checked for bugs and CLAUDE.md compliance.

Generated with [Claude Code](https://claude.ai/code)

---

### Link format rules:
- Must use the full git SHA (from Step 2a's `headRefOid`)
- Format: `https://github.com/a-jay85/IBL5/blob/{FULL_SHA}/path/to/file#L{start}-L{end}`
- Provide at least 1 line of context before and after the line you are commenting about
- Do NOT use `$(git rev-parse HEAD)` or any bash interpolation in the comment — expand the SHA beforehand
