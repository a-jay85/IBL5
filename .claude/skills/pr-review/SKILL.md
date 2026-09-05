---
allowed-tools: Bash(gh pr diff:*), Bash(gh pr view:*), Bash(gh pr comment:*),
  Bash(gh issue view:*), Bash(gh search:*), Bash(gh pr list:*), Bash(gh api:*),
  Bash(git log:*), Bash(git rev-parse:*), Bash(git show:*), Bash(source:*)
name: pr-review
description: Token-efficient code review for pull requests
disable-model-invocation: true
model: claude-sonnet-4-6
last_verified: 2026-09-04
---

Provide a code review for the given pull request. This command optimizes token usage by fetching the diff once and distributing only what each agent needs.

## Step 1: Eligibility check

Use a **Haiku** agent to check if the pull request:
(a) is closed, (b) is a draft, (c) does not need a code review (e.g. automated PR, or very simple and obviously ok), or (d) already has a code review from you earlier (check both PR issue comments and PR **reviews** — `gh pr view --json comments,reviews` — for a prior `Code review` heading from you **at any heading level** — regex `^#{1,6} +Code review`, since `bin/lib/post-review-findings.sh` emits `### Code review` in its source but every such comment currently on a PR carries `## Code review`, so a level-pinned match finds nothing and re-reviews an already-reviewed PR. Check reviews as well as issue comments, since findings are now posted as a review body with inline threads, not only as issue comments. A match must be **from the reviewing identity and be that comment's own heading** — a `/pr-ready` verdict quoting the heading is not a prior review, and treating it as one skips a review that was never done).

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

### 2d. Find directory-specific CLAUDE.md files
Check if any CLAUDE.md files exist in directories whose files the PR modified. Read those — they are not auto-loaded and must be forwarded to agents.

**Do not forward root CLAUDE.md content in agent prompts** — agents auto-load it on init. Forwarding it doubles the token cost (~5K × N agents).

Store all of these results — they will be passed as context to agents below.

## Step 3: Launch parallel agents (merged by tier)

**Read** `.claude/review-shared/_review-agents.md` for the canonical agent definitions. It defines 3 merged agents (A=architecture+bugs+DB, B=git history+code comments, C=previous PRs). For a PR touching shell, workflow, or `.claude/` prose files, also **Read** `.claude/review-shared/_shell-workflow-agent.md` (Agent E) — it gets `$DIFF` from Step 2c and the file list from Step 2b like every other agent, and the **CRITICAL: No agent should call `gh pr diff`** rule below applies to it unchanged. No awk pre-slice here: `/pr-review` has no `$DIFF_FILE` temp file, and its 100KB fallback path already handles oversized diffs.

Launch applicable agents in parallel. Each agent receives:
- The filtered diff from Step 2c
- The file list from Step 2b
- Directory-specific CLAUDE.md content(s) from Step 2d (if any)

**Model tiers** (see `agent-tiering.md` for rationale):
- Agent A (Architecture + Bug detection + DB performance): **Sonnet 4.6** (`subagent_type: "sonnet-4-6"`, omit `model`) — skip if no code files; omit DB section if no PHP
- Agent B (Git history + Code comments): **Sonnet 4.6** (`subagent_type: "sonnet-4-6"`, omit `model`) — skip if no PHP and no code comments in diff
- Agent C (Previous PRs): **Haiku** — skip if no modified (non-added) files
- Agent E (Shell / Workflow / Agent-prose): **Sonnet 4.6** (`subagent_type: "sonnet-4-6"`, omit `model`) — skip unless the Step 2b file list contains a file under a `bin/` directory, a `*.sh`, a `.github/workflows/*.yml`, or a `.claude/**.md`

**CRITICAL: No agent should call `gh pr diff`.** The diff was already fetched in Step 2.

## Step 4: Confidence scoring

**Read** `.claude/review-shared/_review-rubric.md` for the canonical rubric, thresholds, Automatic-Zero rule list, and IBL5 false-positive list.

Collect all issues found in Step 3 into a numbered list. Launch a **single Haiku agent**, pass it the issues list plus the **Scoring scale and Thresholds sections** from `_review-rubric.md` (not the full Automatic Zero or false-positive lists — review agents have already filtered those). Instruct it to return JSON scores per the rubric.

Parse the JSON response and assign scores back to each issue.

## Step 5: Filter

Apply the **Code review** threshold from `_review-rubric.md` (currently `< 80` dropped). If no issues remain, skip to Step 7 with "no issues found".

## Step 6: Re-check eligibility

**Read** `.claude/review-shared/_posting-procedure.md` — it carries this step's eligibility re-check plus the dispositioning and link-format rules cited below. Bind `$PR_NUMBER` from Step 2a before running anything from it. Follow its **Re-check eligibility** section now.

## Step 7: Post findings

Source the shared posting helper (same idiom as `bin/lib/pr-armable.sh`):

```bash
source "$(git rev-parse --show-toplevel)/bin/lib/post-review-findings.sh"
```

**If issues survived Step 5,** build a findings JSON array (write to a temp file — not a shell arg — to avoid quoting limits). Each element:
```json
{ "path": "repo/relative/path.php",
  "line": 17,
  "body": "<description> (CLAUDE.md says \"<rule>\")\n\n<full-SHA range link>",
  "score": 85 }
```
- `path` is the repo-relative file path (matching `+++ b/<path>` in the diff).
- `line` is a single anchor line on the new-file (right) side.
- `body` is the description in the format below, followed by the full-SHA link. Do NOT add the footer — the helper adds it.
- `score` is the Haiku score from Step 4.

Then call:
```bash
post_review_findings "$PR_NUMBER" "$HEAD_SHA" "Code review" "$findings_file"
```

The helper routes on-diff findings to a batch resolvable review POST (inline threads) and out-of-diff findings to a fallback `gh pr comment`. Nothing is dropped.

**If no issues survived Step 5,** call:
```bash
post_review_summary "$PR_NUMBER" "Code review" \
    "No issues found. <1-2 sentence evidence summary>"
```

### Dispositioning open threads:

See `.claude/review-shared/_posting-procedure.md` § Dispositioning open threads (read in Step 6) — use `list_open_review_findings` for the COMMENT_ID, then `resolve_review_finding` to reply in-thread and resolve. Never `gh pr comment`.

### Notes:
- Do not check build signal or attempt to build or typecheck the app. These will run separately.
- Use `gh` to interact with GitHub, not web fetch.
- Make a todo list first.
- You must cite and link each bug (e.g. if referring to a CLAUDE.md, you must link it).

### Per-finding body format:

```
<brief description of bug> (CLAUDE.md says "<...>")

https://github.com/a-jay85/IBL5/blob/FULL_SHA/path/to/file.php#L13-L17
```

(The heading `### Code review`, the found-N summary line, and the footer are emitted by the helper — do NOT include them in individual finding bodies.)

### Link format rules:

See `.claude/review-shared/_posting-procedure.md` § Link format rules (read in Step 6).
