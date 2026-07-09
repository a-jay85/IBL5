# Phase 7 — CI Monitoring (post-plan reference)

Purpose: the Opus-escalation procedure for Phase 7 CI fixes.

   **Escalate to Opus when out of depth.** Failures in the Opus row of agent-tiering — failing-check `name` matching `mutation|MSI|engine|golden|migration` (case-insensitive), or any FK-ordering / cross-track failure you can't localize from the log in one read. Triggers: category match → Opus on attempt 1; otherwise Sonnet does attempts 1–2, Opus takes attempt 3 instead of giving up.

   Capture context to temp files and pass the **paths** — the agent `Read`s them; never summarize the log/diff into the prompt (summarizing → garbage fix, per the Phase 4 review-agent rule):
   ```bash
   gh run view <id> --log-failed > /tmp/post-plan-ci-fail-$PPID.log
   git -C <worktree> diff origin/master...HEAD > /tmp/post-plan-diff-$PPID.patch
   ```
   Spawn **one** `Agent(model: "opus")` with: the two paths, PR number, worktree path, plan path, failing check names, what Sonnet tried. It fixes, runs the relevant track locally if it can, **commits and pushes itself**, returns a one-line summary. Don't forward CLAUDE.md (auto-loaded). Loop back to step 1.

   The Opus attempt **counts toward** the 3-iteration ceiling; after 3 total, report surviving failures in a PR comment and continue to Phase 8. Keep it inside the Phase 7 budget — one bounded Opus attempt fits.
