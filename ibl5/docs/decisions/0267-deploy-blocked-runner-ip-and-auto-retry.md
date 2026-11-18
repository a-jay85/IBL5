---
description: A new deploy-auto-retry.yml workflow reruns a production deploy once when its runner could not reach prod over SSH before anything changed on the server, and setup-ssh reports the blocked runner's public IP so the host can search its firewall log.
last_verified: 2026-11-18
---

> This ADR was drafted by the post-plan harness for this PR. A human must review and approve it before merging.

# ADR-0267: Auto-Retry a Production Deploy Once When the Runner Cannot Reach Prod over SSH

**Status:** Accepted
**Date:** 2026-11-18
**Deciders:** post-plan harness (auto-draft)

## Context

On 2026-11-18 the host's firewall shut out one GitHub runner at a time for about 7 minutes, three times in one hour. Other runners connected fine during each block. Every block failed a production deploy at "Fetch latest code on server" or failed the pre-flight rehearsal at "Setup SSH". Neither step changes anything on prod. The owner then got a Discord alert and had to run `gh run rerun --failed` by hand, and the rerun passed because GitHub gave it a new runner with a new IP. The host could not say why a runner was blocked without that runner's IP, and the old keyscan error did not print it. `bin/adr-check` flagged the new workflow `.github/workflows/deploy-auto-retry.yml` as a process decision.

## Decision

A new workflow, `.github/workflows/deploy-auto-retry.yml`, listens for a completed "Build and Deploy" run. It acts only on a failed run of attempt 1 from a push to `production`. It reads the run's jobs and retries only when the deploy job failed at "Fetch latest code on server" or a pre-flight job failed at "Setup SSH". Before it retries, it checks that the `production` branch head still equals the run's commit. When production has moved on, it skips the retry and DMs the owner, because the newer push starts its own deploy that ships the same commit. Otherwise it calls the `rerun-failed-jobs` API, which needs `actions: write`. When any of its steps fails, it DMs the owner the manual rerun command. The workflow uses its own concurrency group per run id, so it never joins the shared production-deploy group, where a new pending run would replace a queued deploy.

`.github/actions/setup-ssh/action.yml` now records the runner's public IP from `api.ipify.org` when every keyscan try comes back empty. It writes the IP to the step summary, the error annotation, and a `blocked-ip` output. `.github/workflows/main.yml` passes that output to the failure alert. On attempt 1 the alert says a retry is under way. On later attempts it prints the manual rerun command.

## Alternatives Considered

- **Loop in main.yml.** A loop around the SSH steps inside the deploy run. Rejected because a retry on the same runner keeps the same blocked IP, and a run cannot rerun its own jobs while one of them is still in progress.
- **Retry every failure.** Rerun any failed deploy. Rejected because a failure after step 1 may have changed prod, and a blind rerun could apply migrations or code twice. Only the two steps that touch nothing on prod are safe to repeat.
- **No attempt cap.** Retry until a run passes. Rejected because a host that stays blocked would loop. The attempt 1 check limits the cost to one retry.
- **IP only.** Keep the manual rerun and only add the IP to the alert. Rejected because each block costs the owner a context switch for a fix that a new runner applies every time.

## Consequences

- Positive: a deploy blocked by a single runner's firewall block finishes with no owner action.
- Positive: the host gets the blocked runner's IP and the UTC time, which lets them search their firewall log.
- Positive: the stale-commit check keeps an older commit from being deployed over a newer one.
- Negative: the retry workflow holds `actions: write` on the repo token.
- Negative: the step names "Fetch latest code on server" and "Setup SSH" and the job names "Build and Deploy" and "Pre-flight" are matched as strings. Renaming one in `main.yml` turns the retry off with no error.
- Negative: the IP lookup calls an outside service. When it fails, the alert prints `unknown` for the IP.

## References

- `.github/workflows/deploy-auto-retry.yml`
- `.github/workflows/main.yml`
- `.github/actions/setup-ssh/action.yml`
- `.github/actions/notify-discord/action.yml`
