---
name: source-ideas
description: "Pull improvement ideas for this repo out of a video transcript, talk, article, or post. Use whenever the owner pastes or links a source and asks what we can improve, learn, or copy from it."
last_verified: 2026-11-20
---

# /source-ideas: turn a talk or article into real gaps

<user_request>
$ARGUMENTS
</user_request>

The request above holds the source: pasted text, a file path, or a URL. Fetch a URL with the built-in `WebFetch` tool.

## Why this skill exists

On 2026-11-20 the owner audited 8 old threads like this one. About 49 ideas came out of them. About 19 were fully right. About 15 were half right, because the repo already did part of the work and the reply never said so. About 5 named things the repo already had. The misses came from these habits:

- **Searching with the source's words.** A grep for "screenshot" missed `ibl5/tests/e2e/vr-gallery.ts`, which posts a visual-regression gallery on every UI PR.
- **Searching only today's code.** A weekly job that mines chats for owner corrections was suggested 3 times (10-08, 10-11, 11-18). The repo had built it as `bin/retro-mine` (example) and dropped it on 10-06 (ADR-0178). The code was gone, so a code search found nothing.
- **Not reading the reason at the site.** A suggestion to lower the bug classifier's effort ignored the comment that explains why it's high. A suggestion to retry automouse at another tier missed ADR-0085.
- **Not checking built-in tools.** A `bin/revert-pr` (example) script was suggested when `gh pr revert` already exists.
- **Ignoring known no's.** A Codex reviewer was suggested to an owner with no Codex plan.
- **Quoting a number from memory.** The rules were called 400 KB. They were 304 KB.

The ideas about doing an existing thing better were mostly right. Nearly every miss was a "the repo doesn't have X" claim. Steps 3 and 5 target those claims.

## Step 1: list every idea in the source

Before you judge any idea, write all of them to `~/claude-plans/_reports/<YYYY-MM-DD>-source-ideas-<slug>.md`. One line each, numbered, with a short quote. Include the hard ones and the ones that seem not to fit. A source's best idea is often the one that looks too big at first.

## Step 2: load the known no's

Read the owner's standing decisions from the main project's memory folder. A worktree session may point at a different folder, so use this absolute path:

```bash
mem=/Users/ajaynicolas/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/memory
cat "$mem"/MEMORY.md "$mem"/constraint_*.md "$mem"/feedback_no_*.md
```

These hold decisions such as no merge queue, no new frontend features, and no Codex plan. An idea that hits one goes to the "doesn't fit" bucket, with the memory file named.

## Step 3: check each idea against the repo

For each idea:

1. Write 3 or more words the repo might use for it. Read nearby file names first to learn the repo's words. "Screenshots on PRs" becomes `screenshot`, `visual`, `vr-`, `gallery`, `snapshot`.
2. Search every place below with all of those words. A place you skipped can't support a "missing" claim. Run from the repo root. Put the words in `w` with plain `|` between them, because `\|` under `-E` matches a real pipe and finds nothing.

   ```bash
   w='screenshot|visual|vr-|gallery|snapshot'
   git grep -liE "$w"                                    # code, tests, bin/, skills, rules, workflows
   grep -liE "$w" ibl5/docs/decisions/*.md               # decisions, including dropped ones
   git log origin/master --oneline -i -E --grep="$w"     # code that was later removed
   grep -liE "$w" ~/.claude/hooks/* ~/.claude/skills/*/SKILL.md   # outside git
   gh pr list --state all --search "<two plain words>" --limit 10
   gh issue list -R a-jay85/IBL5-backlog --state all --search "<two plain words>" --limit 10
   launchctl list | grep com.ibl5                        # local scheduled jobs
   ```

   Also run `list_scheduled_tasks` (T3) and `<tool> --help` for any built-in tool the idea touches, such as `gh`, `git`, PHPStan, Playwright, or Claude Code. `gh` search takes plain words, so run it once per word pair.

3. Read the reason at the site. When a hit sets a value the source would change, read the comment beside it and any ADR it names. The repo often chose on purpose.
4. Recount any number you plan to quote with a command, in this step.

## Step 4: sort each idea into one bucket

| Bucket | Must cite |
|---|---|
| **Have it** | The file that does it. |
| **Have a weaker version** | The file, and what the source does better. |
| **Tried it, dropped it** | The ADR or PR that dropped it, and why. Re-suggest it only when the source answers that reason. |
| **Missing** | Every search from Step 3, words included. |
| **Doesn't fit** | One line on why, or the memory file it hits. |
| **Can't check** | What blocked the check. |

## Step 5: have a fresh agent try to prove you wrong

Spawn one `sonnet-5-5` agent (omit `model`). Give it only the "missing" and "weaker version" ideas, each as one plain line. Leave out your searches and your reasoning, so it isn't anchored on them. Tell it:

> For each item, try to prove this repo already does it, fully or in part. Search code, `bin/`, `~/.claude/hooks/`, skills, rules, workflows, `ibl5/docs/decisions/`, git history of removed files, and closed PRs. Use the repo's own words, not the item's. For each item, report FOUND with the path and line, PARTLY with the path and what is still missing, or NOT FOUND with the searches you ran.

Its FOUND answers move an idea to "have it" or "tried it, dropped it". PARTLY moves it to "weaker version". Open the path it names before you move anything. When it's wrong, keep the idea and say why in one line.

## Step 6: reply

Show only two groups:

- **Missing.** What it is, the source's quote, where it would live, and how big it is (ad-hoc or `/plan`).
- **Do it better.** The file you have today, and what to change.

Each item gets a `↳ Deeper:` line that names the search and the fresh agent's answer.

After those, give one line each:

- How many ideas the source had, and how many you already have.
- Each "tried it, dropped it" item, with its ADR. The owner should know when a source repeats a dropped idea.

End with the one idea you would start first and why. Keep the report file from Step 1 updated with every bucket, so a later run can see what this one checked.
