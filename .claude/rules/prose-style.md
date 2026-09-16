---
description: The AI-tell sentence shapes ("Claude slop") that must not land in repo prose, PR bodies, or chat replies. Lists each tell with its fix, the on-touch policy, and the escape hatches. Enforced by bin/check-prose in CI and by two hooks.
last_verified: 2026-09-16
paths: "**/*.md"
---

# Prose Style

Readers stop reading when prose carries the sentence shapes that language models over-produce. This rule names those shapes, calls them tells, and blocks them mechanically. It does not ask for a new writing style. Plain technical prose with one idea per sentence already passes.

## Scope and policy

The gate is on-touch. It scans only the lines a change adds. Existing prose stays as it is until someone edits it, and then the touched lines must be clean. There is no repo-wide sweep.

Three surfaces are covered:

- Markdown committed to the repo. CI runs `bin/check-prose --since=<base>` on every pull request.
- Markdown edits made through Claude Code. Check 5 in `~/.claude/hooks/plan-gate-edit.sh` runs the same script on the text an Edit or Write would add, and denies the call when it finds a tell.
- Chat replies. A Stop hook at `~/.claude/hooks/prose-gate-stop.py` scans the final assistant text and asks for a rewrite when it finds a tell.

All three call the same script, so the pattern list lives in exactly one place. Add or tune a tell there and add a fixture. `bin/check-prose --self-test` runs the fixtures and fails if any tell lacks one.

## The tells

Each row is one regex in the script. The fix column is what the deny message prints.

| Tell | Shape | Fix |
|------|-------|-----|
| em-dash | A dash hanging extra explanation off a sentence. | End the sentence. Start a new one. For a bullet label write `**Label.** text`. |
| not-x-but-y | `not just X`, `not merely X`, `isn't only X`. | Say what it is. Drop the negated half. |
| comma-not | `X, not Y`, `X, never Y`. | Keep the true half. Delete the tail. |
| isnt-x-its-y | `It isn't X, it's Y`. `It's not about X, it's Y`. | Write only the half that is true. |
| x-isnt-the-issue | `X isn't the problem`, `X isn't the point`. | Name the real issue directly. |
| not-because-but-because | `not because X but because Y`. | Give the real reason only. |
| not-x-not-y-just-z | A run of `Not X. Not Y. Just Z.` | Say the one thing it is. |
| label-colon-opener | A line that opens with `The problem:`, `Why this matters:`, `Bottom line:`, `TL;DR:`. | Delete the label. Say the thing. |
| heres-the | `here's the thing`, `here's the catch`, `here's the verdict`. | Cut it. Start with the sentence that follows. |
| filler | `it's worth noting`, `in other words`, `at its core`, `that said`, `which is exactly why`. | Cut the phrase. The sentence stands without it. |
| buzzword | `crucial`, `seamless`, `leveraging`, `streamline`, `pivotal`, `nuanced`, `delve`, `utilize`. | Use the plain word. |
| adverb-opener | A sentence that opens with `Essentially,`, `Ultimately,`, `Crucially,`. | Delete the adverb. |
| bold-sentence | A whole sentence of 45 or more characters wrapped in bold. | Unbold it. If it is the point, put it first. |
| rhetorical-question | `Why?` as a line opener, or `? Because` mid-line. | Say the answer without the question. |
| triple-no | `no X, no Y, no Z`. | Two items is fine. Drop the padding. |
| quip-fragment | A paragraph that ends on `That's it.`, `Full stop.`, `Every time.`, `Done.` | Drop the fragment. The previous sentence already said it. |
| closing-offer | `let me know if`, `happy to help`, `want me to`, `shall I`. | Stop when the content stops. |
| sycophant-opener | `Great question`, `Certainly!`, `Absolutely,`. | Delete it. Start with the answer. |
| lets-opener | A line that opens with `Let's` or `Let me`. Docs only. | State what the section does. |

Two shapes from the same family stay judgment-only because a regex cannot separate them from normal usage: `rather than` and generic three-item lists. Cut them when you see them while editing. Metaphor restatement and narrating your steps are also judgment-only.

## What the scanner skips

Frontmatter, fenced code, inline code, HTML comments, blockquotes, URLs, link targets, table separator rows, and text inside double quotes. Directories named `archive`, `node_modules`, `vendor`, and any `-snapshots` directory are skipped whole.

Nothing here restricts what goes inside a code span. Quote a tell inside backticks when a doc needs to show one.

## Escape hatches

- A line that carries `<!-- slop-ok -->` is skipped. Use it when a line quotes someone verbatim or shows a tell as an example.
- The Edit and Write gate accepts a one-shot override. The deny message prints the exact `touch` command. The file is consumed on the next call.
- The chat gate never blocks twice in one turn. If the rewrite still trips a pattern, the turn ends normally.

## Running it by hand

```bash
bin/check-prose --self-test               # fixtures
bin/check-prose --since=master            # added lines on this branch
bin/check-prose --files path/a.md path/b.md
printf '%s\n' "$text" | bin/check-prose --stdin --chat
```

The script exits 1 on hits, 0 when clean, 2 on a usage error. Every hit prints the file, line, tell name, a snippet, and the fix.
