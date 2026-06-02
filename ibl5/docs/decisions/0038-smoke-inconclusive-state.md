---
description: The production smoke test distinguishes an INCONCLUSIVE result (prober blocked by the WAF, or a docs-only deploy) from a real FAILURE, so transient prober-side issues no longer trigger an auto-rollback of healthy production.
last_verified: 2026-06-02
---

# ADR-0038: Smoke "Inconclusive" State Gates Auto-Rollback

**Status:** Accepted
**Date:** 2026-06-02

## Context

`smoke-prod.yml` runs `bin/smoke-prod` from a GitHub-hosted runner after every production deploy. A failing run triggers an **auto-rollback**: the `rollback-and-notify` job reverts the deployed commit and redeploys. The gate is binary — `bin/smoke-prod` exits `0` (pass) or `1` (fail), and any `1` is treated as "the deploy broke production."

That binary is too coarse, because a smoke failure is not always the deploy's fault. The prober and the app are reached over different paths:

- The runner's egress IP is subject to LiteSpeed's per-IP WAF. When that counter trips (e.g. back-to-back deploys, or the daily scheduled run sharing a banned IP range), LiteSpeed returns a blanket **HTTP 415** to *every* request — homepage, dynamic pages, and even the static CSS asset — while real browsers and other IPs get clean `200`s.

On 2026-06-02 this misfired: a runner-IP WAF block returned uniform `415` on all 7 IBL5 checks, the gate read it as a deploy failure, and it auto-reverted an innocent **docs-only** commit (`66d572331 docs(jsb): document .eng league tuning file format`). A scheduled run ~10h earlier — with no deploy — had the identical `415` signature, and the reverted diff was pure markdown that cannot affect any HTTP response. Both facts prove the failure was prober-side, not the app.

The discriminator is the *shape* of the failure. A real regression is **heterogeneous** (some endpoints `200`, others `5xx`, or a `200` with a PHP-error body). A prober-side WAF block is **uniform**: every check fails with the same non-`5xx` WAF-class status. The gate had no way to express that difference.

## Decision

Add a third smoke outcome — **INCONCLUSIVE** — that is notify-only and never gates auto-rollback. Two independent mitigations:

### A. Uniform WAF-class status ⇒ `bin/smoke-prod` exit code 3

A scope is inconclusive iff **all** of: it ran ≥2 checks; **zero** passed; **every** failure was an HTTP-status failure (no body/PHP-error/missing-content failure); and all those statuses are **identical** and in the WAF-class set `{403, 415, 429}`. Such a run exits **3**. The predicate is deliberately conservative — a single passing check, a `5xx`, a `200`-with-bad-body, or two distinct status codes all break uniformity and fall back to a real `exit 1`. (IBL6 has a single check, so its scope can never be inconclusive; its existing notify-only behavior is unchanged.)

The `smoke-prod.yml` IBL5 step captures the exit code: on `3` it sets an `ibl5_inconclusive=true` output and **exits 0**, so `smoke.result == 'success'` and the rollback gate's `needs.smoke.result == 'failure'` condition is false *by construction*. A new `notify-inconclusive` job (mirroring the existing `notify-ibl6-degradation` notify-only job) DMs the owner.

### B. Docs-only deploys never gate a revert (defense-in-depth)

In `rollback-and-notify`'s "Check revert eligibility" step, after the existing `^Revert ` loop-guard, the deployed commit's changed files are diffed against its parent. If **every** changed path matches `(\.md$|^docs/|^ibl5/docs/)`, the revert is skipped (`skip_reason=docs-only`) and the owner is DM'd. Any single non-docs path (added, modified, or deleted) keeps the revert eligible; an empty diff is never treated as docs-only.

## Alternatives Considered

- **Widen the retry/backoff in `bin/smoke-prod`.** Rejected: the existing retry is same-IP, and a per-IP WAF ban persists for the whole run window (the 10h-prior scheduled run had the identical `415`). More retries only delay the same false positive and risk masking a real slow-burn outage.
- **Exempt the prober from the WAF** via a shared-secret header LiteSpeed allowlists, or by probing from on-box `localhost`. These address the WAF root cause and remain the right long-term fix, but each requires server-side / deploy-host coordination and is higher blast-radius. Deferred as follow-ups; the inconclusive classification here is purely prober-side, single-PR, and makes the root-cause work non-urgent.

## Consequences

- A transient WAF block now produces an owner DM and **no rollback** — production stays on the deployed commit. If production were genuinely down, the failure would be heterogeneous or `5xx` and would still gate a rollback.
- The classifier is one-shot-verified (curl-stub exit-code scenarios) rather than CI-gated regression; a `bats` harness for `bin/smoke-prod` is a deferred follow-up. CI `shellcheck` continues to guard syntax/bash-3.2 compatibility.
