---
description: Why the bug-pipeline hunter runs injection-exposed with no ship authority in a credential-starved worktree, and the trusted cron alone opens a held PR.
last_verified: 2026-07-10
---

# ADR-0081: Hunter trust-split & credential-starved env sandbox

**Status:** Accepted
**Date:** 2026-07-10
**Deciders:** A-Jay

## Context

The Discord bug pipeline (ADR-0080) ends at a `queued`, human-classified bug. PR #5a wired the classifier but deliberately left the claim-into-`hunting` step unbuilt: shipping that wiring without an agent to consume the claim would strand every classified bug in `hunting` forever — a deadlock. Closing it means running an autonomous AI agent that reads GM-authored report text and Discord transcripts (untrusted, injection-carrying data) and then *authors code*. An agent that both ingests attacker-influenced text and holds push / `gh` / PR credentials is a direct prompt-injection-to-supply-chain path: a crafted report could talk the agent into pushing an arbitrary branch or opening/merging a PR.

## Decision

Split trust between two processes. The **hunter** (injection-exposed) localizes, reproduces read-only against `main.localhost`, authors and verifies a fix inside its own disposable worktree stack, writes a single `hunt-result.json`, and STOPS at a dirty worktree — it has **no** push, `gh`, or PR authority by construction. The **cron** (trusted, never sees a prompt) alone fires `bin/post-plan-now --auto` to open the PR. Authority is removed mechanically, not by instruction: `run_under_starved_env` (in `bin/bug-pipeline-tick`) blanks every credential vector before exec — HTTPS tokens (`GH_TOKEN`/`GITHUB_TOKEN`/`GH_ENTERPRISE_TOKEN`/`GITHUB_ENTERPRISE_TOKEN` blank, `GH_CONFIG_DIR` → empty dir), the macOS keychain (`GIT_CONFIG_GLOBAL` → `ibl5/scripts/bug-pipeline/hunter.gitconfig`, a helper-less identity; `GIT_CONFIG_SYSTEM=/dev/null`), SSH (`GIT_SSH_COMMAND` with no identity, `SSH_AUTH_SOCK` unset), and `DB_*` / `ANTHROPIC_API_KEY`. The opened PR carries `auto_merge: false` + the `pipeline-authored` label, so the human-signoff required check blocks merge — a human is always the last gate. The security test in `bin/test-bug-pipeline-hunt` sources the driver and runs a real `git push` / `gh pr create` under the scrubbed env, asserting non-zero.

## Alternatives Considered

- **Give the hunter push + PR credentials, rely on the prompt's "never push" rule** — one process, simpler. Rejected because: a prompt rule is not a boundary; an injection that overrides it reaches straight into the code repo.
- **Fully jail the hunter (no `HOME`, no ambient `claude` auth)** — a real sandbox, not defense-in-depth. Rejected because: the `claude` CLI needs its `HOME`-based auth to run at all, so `HOME` is preserved; the starve is therefore defense-in-depth and the human-signoff gate is the real guarantee, which is sufficient.
- **Auto-merge the hunter's PR when CI is green** — no human in the loop, faster throughput. Rejected because: it removes the only backstop against an injection-authored or subtly-wrong fix reaching master.

## Consequences

- Positive: an injection that fully captures the hunter still cannot push, open, or merge anything — the worst case is a dirty worktree the cron declines to ship.
- Positive: closing the #5a deadlock and the hunter land together, so `hunting` is never a reachable dead-end state.
- Negative: every fix waits on a human signoff even when green — throughput is capped by human review, deliberately.

## Lineage

Builds directly on ADR-0080 (the mac-local cron topology and the classifier that produces `queued` rows). This ADR does not supersede 0080; it consumes its output and adds the hunter + trust split #5a intentionally deferred.

## References

- `bin/bug-pipeline-tick` — `run_under_starved_env`, `run_hunter_agent`, `ship_via_cron` (the trust split).
- `ibl5/scripts/bug-pipeline/hunter.gitconfig` — the helper-less committing identity.
- `bin/bug-pipeline-hunter-prompt` — untrusted-data framing and the no-push/`gh`/PR hard limits.
- `bin/test-bug-pipeline-hunt` — the security test exercising real `git push` under the scrubbed env.
- `ibl5/docs/decisions/0080-mac-local-discord-bug-pipeline-cron-topology.md` — the pipeline this extends.
