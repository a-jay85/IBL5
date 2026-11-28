---
description: A detached shell script, bin/pr-refire-after-job, waits for a /pr-burndown prep job and re-fires post-plan, so a wait whose next step needs the owner wakes no Claude session.
last_verified: 2026-11-27
---

> This ADR was drafted by the post-plan harness for this PR. A human must review and approve it before merging.

# ADR-0263: Hand Prep-Job Follow-Up to bin/pr-refire-after-job Instead of a Waking Session

**Status:** Accepted
**Date:** 2026-11-17
**Deciders:** post-plan harness (auto-draft)

## Context

`/pr-burndown` starts headless prep jobs on PRs that wait on the owner. When a job ends, someone has to check its result and re-fire post-plan. Before this PR, `.claude/skills/pr-burndown/SKILL.md` told the session to hand-write a shell waiter, five numbered steps long, for each job. When the step after post-plan was a sign-off card, the skill sent the follow-up to a Monitor or a new T3 thread instead. That woke a Claude session after the one-hour cache window had expired, and the session paid full price to rebuild context only to build a card the owner would read later anyway. Hand-written waiters also drifted. On 2026-11-14 a job on a `BEHIND` PR committed its fix but could not push, and the watcher stopped without telling the owner. `bin/adr-check` flagged the new script `bin/pr-refire-after-job` as a new developer tool of at least 50 lines.

## Decision

When a wait ends in a step that needs the owner, a detached script does the follow-up and DMs the owner. The session does not wake. For a `/pr-burndown` prep job, the session runs `bin/pr-refire-after-job N --pid P --result FILE --pre-sha SHA` once and moves on. The script detaches with `nohup`, waits until the result file is non-empty or the job's pid is gone, then waits 30 seconds more. It skips and DMs the owner through `bin/discord-dm` when the result starts with `blocked`, when it cannot read the PR head, or when the head did not move off the pre-job SHA. It skips with no DM when a post-plan run for the branch is already live in `launchctl`, since that run DMs when it ends. Otherwise it runs `bin/post-plan-now --auto` in the PR worktree and DMs the owner on a non-zero exit, which covers exits 9 and 10 that send no DM of their own. `.claude/rules/work-triage.md` states the rule under repeat-polling. A wait-guard hook in the home Claude hooks directory blocks a Monitor or background Bash wait on a `/tmp/pr-burndown/` job file unless the command ends with a `# wake-ok: <reason>` marker. The new T3 thread route stays for follow-up that needs judgment and no owner call, such as choosing between a re-fire and a fix job.

## Alternatives Considered

- Keep the hand-written waiter recipe in the skill, so each session writes its own `wN.sh` from the five steps. Rejected because every copy can drift from the recipe, and the 2026-11-14 silent stop showed a missed step costs the owner a notice. One script with one header keeps every case in one place.
- Wake a session with a Monitor or a T3 thread to build the sign-off card. Rejected because the owner reads the card only when they sit down, and the next `/pr-burndown` run builds it then. A wake after the cache window pays full context price for no earlier decision.
- Leave the choice to the session with no hook. Rejected because the skill text alone had already let sessions pick a Monitor for these waits. The wait-guard hook makes the cheap path the default, and the `# wake-ok:` marker keeps an explicit way out.

## Consequences

- Positive: a prep-job follow-up costs zero tokens, and the owner gets a DM on every skip but a live post-plan run.
- Positive: the skip and DM cases live in the header of `bin/pr-refire-after-job`, so the skill and the rule point at one source.
- Negative: the sign-off card waits for the next `/pr-burndown` run. The owner sees the post-plan DM first and the card later.
- Negative: the live-run check matches `launchctl` labels by branch name and PR number. A run started under another label is missed, and the script fires a second post-plan.
- Negative: the script reads the PR head through `gh`. A network or auth failure ends in a skip and a DM, and nothing retries.

## References

- `bin/pr-refire-after-job`
- `bin/post-plan-now`
- `bin/discord-dm`
- `bin/pr-wt-resolve`
- `.claude/rules/work-triage.md` (repeat-polling section)
- `.claude/rules/work-triage-detail.md` (the readiness predicate)
- `.claude/skills/pr-burndown/SKILL.md` (follow-up carriers)

## Addendum (2026-11-27): the waiter detaches through launchd

The Decision section records `nohup`. A `nohup` child dies with the Bash tool's process group, as the `bin/post-plan-now` header explains. `bin/pr-refire-after-job` now submits launchd job `com.ibl5.pr-refire-N`. Its `--then-finish` flag chains `bin/owner-approve N --finish --detach` after a re-fire that exits 0. The script refuses that flag under `CLAUDE_HEADLESS`.
