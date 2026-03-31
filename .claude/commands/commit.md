---
allowed-tools: Bash(bin/tracked-files:*), Bash(git add:*), Bash(git status:*), Bash(git commit:*), Bash(git diff:*), Bash(git branch:*)
description: Commit only files edited by this Claude instance
---

## Context

- Current branch: !`git branch --show-current`
- Instance-tracked files: !`bin/tracked-files --ppid $PPID`
- Warnings: !`bin/tracked-files --ppid $PPID --warnings`
- Recent commits: !`git log --oneline -5`

## Instructions

1. If warnings were printed above, inform the user and ask how to proceed before staging anything
2. If no tracked files were listed, say "No files tracked by this instance have changes" and stop
3. Stage ONLY the listed tracked files: `git add <file1> <file2> ...`
4. Review the staged diff with `git diff --staged` to understand the changes
5. Create a single commit with a message following this format:

```
<type>: <short summary under 72 chars>

## Section
- bullet point describing what changed and why
```

Valid types: feat, fix, refactor, test, style, docs, chore

6. After a successful commit, clear the tracking file: `> /tmp/claude-edited-files-$PPID.txt`

Do not send any other text or messages besides the tool calls for staging, reviewing, and committing.
