# Workflow Continuity Rule

When executing the post-plan-approval workflow (Phases 1-8), skill completions are NOT stopping points. After ANY skill invocation returns output, IMMEDIATELY proceed to the next phase in the same response.

| Skill completed | Next action |
|----------------|-------------|
| `/simplify` | Phase 4: `/commit-commands:commit-push-pr` |
| `/commit-commands:commit-push-pr` | Phase 5: `/code-review:code-review` + `/security-audit` |
| `/code-review:code-review` | Continue to `/security-audit` or Phase 6 |
| `/security-audit` | Phase 6: Full test suite + PHPStan |
| Phase 6 verification | Phase 7: CI monitoring |
| Phase 7 CI pass | Phase 8: Retrospective (update memory/docs if needed) |

Never wait for user input between phases. Treat skill completion like a function return — the caller continues executing.
