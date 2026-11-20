---
description: Bug-pipeline tracking issues live in the public a-jay85/IBL5 repo; a one-off owner-run script, bin/bug-issues-migrate, moves the old issues there, and the 180 s tick posts one Discord close notice per issue.
last_verified: 2026-11-18
---

> This ADR was drafted by the post-plan harness for this PR. A human must review and approve it before merging.

# ADR-0264: Bug-Pipeline Issues Live in the Public IBL5 Repo

**Status:** Accepted
**Date:** 2026-11-18
**Deciders:** post-plan harness (auto-draft)

## Context

The Discord bug pipeline (ADR-0080) filed its tracking issues in the private repo `a-jay85/ibl5-bugs`. GMs could not open those links, and they never heard when their report was fixed. GitHub cannot transfer an issue out of a private repo into a public one. `bin/adr-check` flagged two new scripts as new developer tools of at least 50 lines: `bin/bug-issues-migrate` and its test, `bin/test-bug-issues-migrate`.

## Decision

New bug issues go to the public repo `a-jay85/IBL5`. The owner runs `bin/bug-issues-migrate` once to move issues 1 to 29. The script flips the old repo public, transfers each issue, flips the old repo private again and archives it. It then rewrites the Buggsy links in Discord and records the new issue numbers in the pipeline DB. The script is a dry run by default. `--live` needs a typed confirmation line and refuses to run under `CLAUDE_HEADLESS`. Each step records its progress, so a rerun resumes after a failed step.

The 180 s tick posts a close notice. It polls `gh issue view --json state,stateReason` for each row of the new local-only table `ibl_bug_issue_notices`. Each issue gets exactly one notice: the row moves from `pending` to `claimed` before the post, and the tick never re-posts a claimed row. Issues already closed at migration time are marked `seeded` and get no notice. The Discord text uses fixed strings, the issue number, the close reason and the URL. The bot sends `allowedMentions` limited to the reporter, and it rejects any mention token inside the text, because anyone can edit a public issue.

## Alternatives Considered

- Keep the issues private and send GMs a status digest. Rejected because GMs still could not open the issue link.
- Recreate the old issues in the public repo by hand. Rejected because the Discord links and the DB rows would point at the old numbers, and 29 issues by hand invites mistakes.
- Receive GitHub webhooks for close events. Rejected because the pipeline has no inbound surface (ADR-0080), and the tick already runs every 180 s.

## Consequences

- Positive: GMs can read their issue and get one Discord notice when it closes.
- Positive: the migration is resumable and dry-run by default, so a failed step costs a rerun.
- Negative: for a few minutes during the migration the old repo is public.
- Negative: the issue text is public and untrusted, so the notice builder must keep issue titles and comments out of the Discord text.
- Negative: a crash between claim and post leaves the row `claimed`. The tick logs it, and the owner has to decide whether to post by hand.

## References

- ADR-0080 (addendum 2026-11-18)
- `bin/bug-issues-migrate`
- `bin/test-bug-issues-migrate`
- `bin/bug-pipeline-tick`
- `ibl5/migrations/221_create_ibl_bug_issue_notices.sql`
