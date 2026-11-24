---
description: ADR-0284. bin/post-plan-now runs the compiled harness only. A harness crash posts the failure comment, sends the crash DM and stops. The Sonnet /post-plan skill fallback and POST_PLAN_SKILL are retired.
last_verified: 2026-11-24
---

# ADR-0284: Retire the /post-plan skill fallback

**Status:** Accepted
**Supersedes:** the harness-versus-skill engine split in ADR-0092. The rest of ADR-0092 stands.
**Date:** 2026-11-24
**Deciders:** repo owner

## Context

ADR-0092 put the post-plan phases in a compiled harness and kept a fresh Sonnet `/post-plan` skill session as the fallback for a harness crash. The skill never commits, pushes, opens a PR or arms auto-merge. Four runs crashed between 2026-11-19 and 2026-11-24. Only the session for PR #4090 took a real action: it ran the full local E2E suite on a stale stack and reported 44 data-gap failures. The other three printed status. The failure comment and the crash DM already come from `bin/post-plan-now` itself, so the session added cost and a second place for a crash to hide.

## Decision

`bin/post-plan-now` ships every run through the compiled harness and starts no `claude -p` session.

- A harness exit other than 0, 3, 7 and 76 posts the failure PR comment, sends the crash DM and exits with the harness rc. In `--foreground` mode it prints `postplan-rc=<N> harness-rc=<N>` with the same value for both. Exit codes 3, 7, 8, 9, 75 and 76 keep their meaning.
- A missing `$HARNESS/run` refuses with exit 1 before any drain token, worktree lock or launchd job exists. `bin/automouse/run` already treats exit 1 as a retryable failure.
- A `--resume-paused` replay reruns the harness from the start. The old replay launched a skill-only session that shipped nothing.
- `POST_PLAN_SKILL`, `should_fallback`, `postplan_ci_resume_clause` and the skill prompt strings are deleted.
- `.claude/skills/post-plan/SKILL.md` becomes a stub that tells a loading session to stop. Its sibling files stay, because tests and harness comments cite them.

## Alternatives Considered

- **Keep the fallback and shrink it further.** Rejected because the 2026-11-19..24 logs show it acted on one of four crashes, and that action ran E2E on a stale stack.
- **Delete SKILL.md.** Rejected because ADR bodies are append-only and cite the path, and `bin/check-docs` fails on a dead repo path in a doc body.
- **Fail a missing harness with a DM.** Rejected because no detached job exists at that point and the caller reads stderr directly.

## Consequences

- Positive: a crash costs no model tokens, and one code path decides the exit code.
- Positive: the usage-gate resume leg and its skill-session machinery are gone from `bin/post-plan-now`.
- Negative: the out-of-scope backlog sweep no longer runs after a crash. The re-run command in the crash DM runs it, deduped against every backlog issue, so the sweep is delayed and not lost.
- Negative: `bin/automouse/run` keeps its pricer branch for harness rcs other than 0 and 3. It is dead weight now and is left for a follow-up.

## References

- `bin/post-plan-now`
- `tools/postplan-harness/tests/test_post_plan_now_fallback.py`
- `.claude/rules/workflow-continuity-detail.md`
- `ibl5/docs/decisions/0092-postplan-harness-in-repo-with-python-ci.md`
