---
description: Act on the nightly stale-docs audit automatically — on a NEW/CHANGED stale set, a self-hosted macOS runner fires a headless Claude run that refreshes exactly the stale docs and opens a PR (held for human merge) that Closes the tracker issue.
last_verified: 2026-07-04
---

# ADR-0079: Autonomous stale-docs remediation via a self-hosted macOS runner

**Status:** Accepted
**Date:** 2026-07-04

## Context

`.github/workflows/doc-freshness-audit.yml` runs nightly, computes the in-scope docs whose
`last_verified` is past the 60-day window, and files/updates ONE idempotent `stale-docs` GitHub
issue (carrying a `<!-- stale-set: <sig> -->` marker), setting `notify=='true'` only when the
stale set is NEW or CHANGED. Today that is where the pipeline stops: the issue tracks the drift
but nothing acts on it, so stale docs linger until a human notices the issue. We want the audit
to REMEDIATE, not just report.

## Decision

On `notify=='true'`, a second job `docfix` (`needs: audit`, `runs-on: [self-hosted, macOS]`)
runs one step — `bin/docfix-run "<issue-num>" "<sig>"` — on the owner's Mac. `bin/docfix-run`
creates a worktree off master, seeds a hold plan, and launches a **detached headless Claude run**
that refreshes exactly the docs in `<sig>`, then opens a PR that `Closes #<issue-num>`.

1. **Transport = self-hosted macOS runner** (chosen over Tailscale-SSH). The remediation must run
   on the owner's Mac (that is where the `claude` CLI, its auth, and the dev worktree tooling
   live). A self-hosted runner is **pull-based**: the Mac polls GitHub over an outbound HTTPS
   connection, so there is **no inbound networking, no SSH key/ACL to rotate, and no listening
   service** to secure. Tailscale-SSH (push a command into the Mac from the Actions runner) was
   rejected: it needs a tailnet, an auth key with rotation, and an ACL granting the CI runner SSH
   into the dev machine — strictly more attack surface for the same effect.
2. **The GitHub issue stays the tracker.** Its idempotency (one open issue, marker-diffed stale
   set) already works; we do not replace it. The only new coupling is that the produced doc-fix
   PR body carries `Closes #<issue-num>` so merging it closes the issue automatically.
3. **The fix runs headless via the `bin/post-plan-now` launchd-detach pattern.** A plain
   `nohup … &` spawned from a runner job shares the job's process group and dies when the job
   ends; a launchd `RunAtLoad` one-shot (bootstrapped into the user GUI domain) survives the job,
   exactly as `bin/post-plan-now` and `bin/automouse-run` already rely on. `bin/docfix-run` clones
   that idiom, differing only in that it first creates the worktree and points the run at it.
4. **The produced PR is HELD for human merge** (`auto_merge` NOT armed). `bin/docfix-run` seeds a
   minimal plan at `$HOME/.claude/plans/<slug>.md` with line-1 frontmatter `auto_merge: false`;
   the fix run ends with `bin/post-plan-now` (NOT `--auto`), and `/post-plan` Phase 6.5 condition
   (7) reads that frontmatter and refuses to arm auto-merge. A machine-authored doc edit gets a
   human read before it lands.

## Consequences

- **Positive:** stale docs are refreshed autonomously within a day of the audit flagging them,
  with a human only in the merge loop. No new inbound network surface; reuses proven launchd and
  post-plan machinery.
- **Cost:** the pipeline now depends on the owner's Mac being online and registered as a runner;
  when it is offline the `docfix` job simply queues/expires and the `stale-docs` issue remains as
  the fallback tracker (no data loss — the next nightly re-fires on the unchanged set only if it
  changed, per the `notify` gate).
- **Security posture:** a self-hosted runner executes workflow code, so it is only acceptable on a
  **private, single-owner repo** where all workflow code is trusted. Containment: the `docfix` job
  is reachable ONLY via the `audit` job, which triggers on `schedule`/`workflow_dispatch` — never
  on `pull_request` — so no untrusted fork PR code ever reaches the runner. The detached run uses
  the Mac user's own persistent `gh auth login` credential (not the ephemeral Actions
  `GITHUB_TOKEN`), and every PR it opens is HELD for human merge.

## Operational setup (one-time, OUTSIDE the repo)

Register the Mac as a self-hosted runner and its supporting tools. These are machine-local steps,
not repo artifacts:

1. Repo → Settings → Actions → Runners → **New self-hosted runner (macOS)**; copy the registration
   token.
2. On the Mac, in a runner dir, download the `actions/runner` release and configure it with the
   labels this workflow selects on:
   - `./config.sh --url https://github.com/<owner>/<repo> --token <TOKEN> --labels self-hosted,macOS --name ibl5-mac`
3. Run the runner **as the logged-in user inside the GUI session** (not a root LaunchDaemon), so
   its jobs inherit the user's login keychain, `PATH`, and `gui/$(id -u)` domain — which
   `bin/docfix-run`'s `launchctl bootstrap "gui/$(id -u)"` and the detached run's keychain-backed
   `gh`/`claude` all require. Use the actions/runner service installer under the login user
   (`./svc.sh install <login-user> && ./svc.sh start`) or launch `./run.sh` in the user session.
4. Prerequisites on the Mac PATH: the `claude` CLI (authenticated), `gh` (persistent
   `gh auth login`, able to open PRs), `git` (worktree support), and `caffeinate` — the same tools
   `bin/post-plan-now` assumes.
