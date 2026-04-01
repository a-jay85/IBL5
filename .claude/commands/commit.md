---
allowed-tools: Bash(bin/tracked-files:*), Bash(git add:*), Bash(git status:*), Bash(git commit:*), Bash(git diff:*), Bash(git branch:*)
description: Commit only files edited by this Claude instance
---

## Context

- Current branch: !`git branch --show-current`
- Instance-tracked files: !`bin/tracked-files --ppid $PPID`
- Recent commits: !`git log --oneline -5`

## Instructions

1. If no tracked files were listed, say "No files tracked by this instance have changes" and stop
2. Stage ONLY the listed tracked files (skip any that no longer exist or have no changes): `git add <file1> <file2> ...`
3. Review the staged diff with `git diff --staged` to understand the changes
4. Create a single commit with a message following this format:

```
<type>: <short summary under 72 chars>

## Section
- bullet point describing what changed and why
```

Valid types: feat, fix, refactor, test, style, docs, chore

5. After a successful commit, clear the tracking file: `> /tmp/claude-edited-files-$PPID.txt`

Do not send any other text or messages besides the tool calls for staging, reviewing, and committing.
