---
description: bin/discord-dm appends one tab-separated line per delivered message to a local sent log, and the new bin/dm-volume counts those lines per caller, target and day so the owner can find the noisiest senders.
last_verified: 2026-11-16
---

> This ADR was drafted by the post-plan harness for this PR. A human must review and approve it before merging.

# ADR-0256: Log Every Delivered Discord Send Locally and Count Them with bin/dm-volume

**Status:** Accepted
**Date:** 2026-11-16
**Deciders:** post-plan harness (auto-draft)
Related: ADR-0097 (the host transport), ADR-0146 (the #dev webhook), ADR-0254 (the #errors webhook)

## Context

Every host-side notice goes through `bin/discord-dm` (ADR-0097). After ADR-0146 and ADR-0254 it posts to #dev, #errors, the IBLbot DM, or a raw IBLbot route. The owner wanted to cut Discord noise. Nothing recorded which script sent a message, so the owner could only guess which callers were the loudest. Discord history shows the text of each message and hides the caller. `bin/adr-check` flagged the two new developer tools this PR adds: `bin/dm-volume` and its harness `bin/test-dm-volume`.

## Decision

1. `bin/discord-dm` appends one line to a sent log after each delivered message. A chunked message counts once. The default file is `discord-dm-sent.log` in the home Claude directory, and `DISCORD_DM_SENT_LOG` overrides it.
2. Each line has five tab-separated fields: an ISO-8601 time, the target (`dev`, `errors`, `dm`, or `route:<path>`), the caller, the byte length, and the first 60 characters of the first line. Tabs and carriage returns in the caller and preview become spaces.
3. The caller is `DISCORD_DM_CALLER` when set. Otherwise `bin/discord-dm` reads the parent process's arguments and takes the basename of the first `bin/` script it finds there, then the parent command's basename, then `unknown`.
4. Logging is best-effort. `log_sent` runs with its output discarded and its status ignored, so a logging failure leaves the exit code and output of the send unchanged. A send that fails and spools writes no line.
5. `bin/dm-volume [--days=N]` reads the log and prints the total for the window, then counts by caller (busiest first), by target, and by day. The window defaults to 7 calendar days with today included. A missing or empty log prints `no sends logged yet` and exits 0. A space-form flag or a non-positive `--days` exits 2.
6. `bin/test-dm-volume` checks the counts against a fixture log and runs as its own line in `.github/workflows/harness-legs.txt`. The new S1 cases in `bin/test-discord-dm` pin the log format, the one-line-per-chunked-send rule, each target kind, the caller fallback, the failure case, and the unwritable-path case.

## Alternatives Considered

- **Count from Discord history.** Read #dev, #errors and the DM through the bot API and group the messages. Rejected because the messages do not carry the caller, and reading the channels needs a bot token and network on every count.
- **Require every caller to pass a name.** Make `DISCORD_DM_CALLER` or a `--caller` flag mandatory. Rejected because it means editing every call site, and a caller that forgot would fail its notice. The parent-process fallback fills the caller with no call-site change, and the env variable stays for callers that want a fixed name.
- **Log inside `bin/lib/dm.sh`.** Record sends in the shell helpers. Rejected because scripts that call `bin/discord-dm` directly would go unlogged. The send loop in `bin/discord-dm` is the one place every delivery passes.
- **Fold the report into `bin/discord-dm` as a flag.** Rejected because the sender would then carry a second job with its own flags and tests. A separate read-only script keeps the sender's help and exit codes unchanged.

## Consequences

- Positive: the owner can run `bin/dm-volume` and see which callers send the most messages, and to which channel, before deciding what to quiet.
- Positive: a sent-log failure cannot turn a delivered notice into a reported failure. Cases S1m and S1n in `bin/test-discord-dm` hold that.
- Negative: the log grows without bound. Nothing rotates or trims it, and each line is about 100 bytes.
- Negative: the log is local to each host. Sends from CI through `.github/actions/notify-discord` and sends from other machines do not appear in it.
- Negative: the parent-process fallback names the nearest `bin/` script. A send made through a wrapper reports the wrapper's name unless the caller sets `DISCORD_DM_CALLER`.

## References

- `bin/discord-dm` (`log_sent`, the `WEBHOOK_KIND` target)
- `bin/dm-volume`
- `bin/test-dm-volume`
- `bin/test-discord-dm` (S1 cases)
- `.github/workflows/harness-legs.txt`
- `bin/README.md` (Notifications row)
- `ibl5/docs/decisions/0097-single-retrying-host-notification-transport.md`
- `ibl5/docs/decisions/0146-discord-dev-webhook-notify.md`
- `ibl5/docs/decisions/0254-discord-errors-webhook.md`
