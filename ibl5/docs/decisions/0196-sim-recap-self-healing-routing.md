---
description: Sim-recap failures are routed by cause in the prod queue across two hosts (GitHub Actions and the Mac poller), with one #admin-chat post per state change.
last_verified: 2026-10-15
---

# ADR-0196: Self-healing sim-recap routing across GitHub Actions and the Mac

**Status:** Accepted
**Date:** 2026-10-15
**Deciders:** a-jay85

## Context

A failed recap used to park with a 30-minute backoff and became terminal after two tries. Usage limits, a bad Claude login on one host, and ssh outages all ended the same way. Nobody heard about it until a GM asked.

## Decision

The tick names a failure cause (flaky, usage_limit, auth, transport, prompt). The prod queue (`ParkPolicy`, `SimSummaryRepository::parkByCause`) picks the route: retry now on the same host, wait for the usage reset, hand to the other host, back off, or stop. Claims are host-scoped in SQL. GitHub takes back a Mac row after 15 minutes. The Mac claims an unrouted row after 45 minutes without GitHub progress. A 24-hour window and a 20-claim backstop bound every loop. Prod posts to #admin-chat through `Discord::postToChannel`, using a compare-and-set on `notified_state` so each state posts once across both hosts. The cron runs every 10 minutes. A run out of time asks for a re-dispatch with `actions: write`. The forced-command ssh gate accepts only the new fixed-order enum flags.

## Alternatives Considered

- Routing in bash on each host. Rejected: two copies of the state machine could disagree, and only SQL keeps the claim atomic.
- Callers send message text to a notify verb. Rejected: either host could then post anything to #admin-chat across the ADR-0093 boundary.
- A webhook secret on each host. Rejected: prod already holds the admin-chat webhook.
- A delayed re-dispatch or a prod crontab nudge for future-time runs. Rejected: Actions has no delayed dispatch, and a prod crontab is outside this repo's deploy.

## Consequences

A parked Actions run is green unless the sim is terminal. A red run now means a human is needed. The Mac poller is a health-checked job again. A usage limit never hands off, because both hosts share one Claude account.

## References

ADR-0093, ADR-0154.
