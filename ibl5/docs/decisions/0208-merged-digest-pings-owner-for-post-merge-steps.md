---
description: The #merged digest @mentions the owner only for a manual or failed post-merge step, decided from pipeline job outputs.
last_verified: 2026-10-21
---

# ADR-0208: The merge digest pings the owner when a post-merge step needs them

**Status:** Accepted
**Date:** 2026-10-21
**Deciders:** a-jay85
**Amends:** ADR-0198 (the separate #merged checklist message is retired)

## Context

ADR-0198 posts After-merge rows as a sticky PR comment plus a second #merged message. PR #3340 pinned the rule that #merged posts never @mention the owner. The owner has to remember to read #merged after each merge, and real post-merge actions were missed, such as the sim-recap install from #3245. ADR-0146's 2026-10-09 note says the #merged move left the owner ping unchanged. This ADR overrides that for the one case below.

## Decision

1. Row grammar: `- **Row N** [<tag>] — text`, where the tag is `manual`, `auto:ci <name>`, or `auto:mac <name>`. `<name>` matches `^[A-Za-z0-9][A-Za-z0-9._-]*$`. An untagged row is manual. A malformed tag is treated as manual. The grammar lives only in `bin/lib/post-merge-rows.sh`.
2. `bin/after-merge-checklist` posts one sticky comment that lists every row with its tag. Auto rows say they run automatically. The script emits `status`, `manual_count`, `auto_ci_count`, `auto_mac_count` and `comment_url` as job outputs. It sends no Discord message.
3. Each merge produces one #merged message. `notify-merge-digest` needs the checklist job, runs under `always()` behind the merged and same-repo guard, and sends even when a needed job failed.
4. The digest pings when `manual_count > 0`, when `auto_failed_count > 0`, when any count fails `^[0-9]+$`, or when any needed job's result is not `success`. `pmr_ping_decision` computes this from job outputs and never from PR text. The workflow's `ping:` input compares against `'false'`, so an empty output pings.
5. The ping line and the directions link are the first lines of the message, ahead of the 1900-character cut in `.github/actions/notify-discord/send.sh`. The link is the sticky comment URL, with the PR URL as fallback.
6. Unchanged: `allowed_mentions` names only the owner ID. `@` in PR-sourced text gets a zero-width space. Both jobs check out the default branch. PR text travels by env var or file. No command is ever taken from PR text. Auto rows are only counted and labelled here. The executors that run them get their own ADRs, and the digest tells the owner that Mac step failures ping in #dev.

## Alternatives Considered

- Keep the second #merged message and ping on it. Rejected because it leaves two posts per merge, and the owner asked for the mention on the digest.
- Ping on any After-merge section. Rejected because auto rows and passive watch notes would ping for nothing.
- Compute the ping in a workflow expression. Rejected because only a live merge could exercise it, and an expression cannot fail closed on a non-numeric count as readably as a tested shell function.
- Parse mentions or keywords from the PR body. Rejected because PR text is attacker-influenced.

## Consequences

- Positive: the owner is told about a manual or failed post-merge step without remembering to look.
- Positive: a red, cancelled or garbled checklist job pings with the Actions run link instead of failing silently.
- Negative: the digest waits for the checklist job, up to its 5-minute timeout.
- Negative: legacy untagged rows (the #3264 and #3286 shape) ping, because they are manual by definition.
- Negative: `bin/test-discord-dm`'s call-site table drops from 17 to 16 sites, and its merge-digest row carries the computed ping.

## References

- `ibl5/docs/decisions/0198-post-merge-manual-rows-after-merge-section.md`
- `ibl5/docs/decisions/0146-discord-dev-webhook-notify.md`
- `bin/lib/post-merge-rows.sh`
- `bin/after-merge-checklist`
- `bin/digest-dm-build`
- `.github/workflows/merge-digest-notify.yml`
- `.github/actions/notify-discord/send.sh`
