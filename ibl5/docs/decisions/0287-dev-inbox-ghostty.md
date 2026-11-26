---
description: ADR-0287. bin/discord-dm appends the full text of every message to a local inbox file, and the new bin/dev-inbox shows that file in a terminal tab and rings the bell on alert entries.
last_verified: 2026-11-26
---

> This ADR was drafted by the post-plan harness for this PR. A human must review and approve it before merging.

# ADR-0287: Show dev alerts in a terminal tab from a local inbox file

**Status:** Accepted
**Date:** 2026-11-25
**Deciders:** post-plan harness (auto-draft)

## Context

Repo scripts send dev alerts through `bin/discord-dm`. The owner had to keep Discord open to read them. The sent log that `bin/discord-dm` already writes holds one line per delivered message with a 60-character preview. It drops the rest of the message, and it records nothing for a message that failed to send. `bin/adr-check` flagged the new `bin/dev-inbox` as a new developer tool of at least 50 lines.

## Decision

`bin/discord-dm` appends every message to an inbox file, and `bin/dev-inbox` shows that file in a terminal tab.

- The inbox lives at `~/.claude/dev-inbox.log`. `DISCORD_DM_INBOX` overrides the path for both scripts.
- Each entry is a `@@ <time> <target> <caller>` header line. The message follows with every line indented two spaces, so a body line can never read as a header. Spaces in the caller become underscores, and carriage returns are removed from the body.
- A delivered message uses the sent-log target, with two exceptions. A `--raw` message uses `raw:<path>`. Any `--error` message uses `errors`, even when it went out over the DM or #dev, so the viewer rings for it. The sent log keeps its own target. A message that failed on every attempt uses the target `undelivered`.
- A `--raw` body is JSON for a Discord-only button message. For a delivered one, the inbox stores a note telling the owner to open Discord, adds the JSON's `.digest` text when it has one, and leaves the rest of the JSON out. For a failed one, the inbox says it was not delivered and keeps the full JSON.
- The inbox write is best-effort, like the sent log. A write failure never changes the exit code or output of `bin/discord-dm`. Past 5 MB the file rotates to `.1`.
- `bin/dev-inbox` prints the last 10 entries, then follows the file. One `tail -F` reads both, so an entry that arrives during startup is not lost, and the viewer survives the rotation. It rings the terminal bell on a new entry for the `errors` target, an `undelivered` entry, a `raw:` entry, or a first line that starts with an alert mark. The backlog it prints at start never rings. `--no-bell` and `--once` cover quiet use and tests.
- `bin/test-discord-dm` section S2 checks the header shape, the indent, the raw note and digest, the `--error` target, undelivered entries (including a failed raw send), the unwritable-inbox case, rotation, which entries ring the bell, a bad `--last` value, the `--help` text, an entry added during viewer startup, and a bad UTF-8 byte.

## Alternatives Considered

- **Extend the sent log.** Rejected because the sent log is one tab-separated line per message, and `bin/dm-volume` reads that shape. A full multi-line body would break both.
- **Desktop notification.** Send a macOS notification from `bin/discord-dm`. Rejected because a notification shows a few lines and then disappears. The owner wanted to scroll back through full messages. Ghostty's bell already marks the tab and bounces the Dock icon.
- **Read messages back from Discord.** Rejected because it needs a bot token and network access on the reader side, and it would still miss messages that never reached Discord.

## Consequences

- Positive: the owner can read every alert in full with Discord closed, including messages that failed to send.
- Positive: only alert entries ring the bell, so routine success messages stay quiet.
- Negative: the inbox is a second local copy of every message, kept up to about 10 MB across the live file and `.1`.
- Negative: a button message still needs Discord to act on it. The inbox only says one arrived.
- Negative: the inbox write takes no lock. Two parallel sends of very large messages can mix their lines.
- Negative: the alert test reads the first body line for a fixed set of marks. A sender that puts its alert mark on a later line gets no bell.

## References

- `bin/dev-inbox`
- `bin/discord-dm`
- `bin/test-discord-dm`
- `bin/dm-volume`
