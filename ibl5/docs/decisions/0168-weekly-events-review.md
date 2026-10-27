---
description: A weekly tool-less model run reads an aggregate digest of ibl_events through the SELECT-only credential and DMs recommendations to the owner. Raw URIs, user agents and identities never leave the database.
last_verified: 2026-10-27
---

# 0168. Weekly product-analytics review from ibl_events

## Status

Accepted

## Context

`ibl_events` has collected per-request analytics since migration 154 and gained `traffic_class`, `session_id`, `http_status`, and `action` in migration 159, but nothing reads it. ADR-0016 removed an earlier analytics layer because it accrued as write-only overhead. The table holds attacker-controllable strings (`request_uri`, `referer`, `route_name` minted from `?name=`) and per-GM identity (`username`, `session_id`). Any reader that hands those rows to a model or to Discord is both a privacy surface and a prompt-injection surface.

## Decision

One script, `bin/events-review`, reads `ibl_events` weekly through the ADR-0093 SELECT-only credential over an SSH tunnel and builds an aggregate digest. Raw `request_uri` and `user_agent` are never selected. `referer` leaves the database only as one of four literals, and identities leave only as counts. An awk allow-list prints a route only when an authenticated GM hit it that week, and folds every other token into `other`. A `claude -p` run with `--tools ""` and `--strict-mcp-config`, from a temp directory outside the repo, drafts 3 to 5 recommendations. The output is defanged and sent only to the owner through `bin/discord-dm`. Nothing is applied automatically. The job runs from launchd on the owner's Mac on the subscription, so the repo gains no API key. A week below the minimum human-event count sends one line and spawns no model.

Enforcement lives in `bin/test-events-review`, which runs in CI and pins the SQL block, the allow-list, the tool lockdown, and the DM defanging.

## Alternatives Considered

- **Extend `bin/sim-recap-tick`.** One fewer script. Rejected because that tick drives a queue and an agent with `Bash(mysql:*)`. This job needs no queue and no tools, so sharing a host would widen the privileged surface.
- **Let the model query the database itself.** Fresher answers. Rejected because it hands a model a production credential and attacker-controlled rows at once. The two-stage digest keeps the model tool-less.
- **A dashboard or stored digest history.** Easier trend views. Rejected because it rebuilds the write-only analytics layer that ADR-0016 removed. The DM is the only output.

## Consequences

- Positive: the owner gets a weekly read of GM behavior and data quality with no manual SQL.
- Positive: the model has no tools, so a prompt-injection in a route name can at worst change the wording of one DM.
- Negative: one Sonnet call per week on the owner's subscription.
- Negative: a new `bin/` script and test harness, accepted under the extend-before-add bar because no existing host owns a read-only analytics surface.
- Negative: the process runs under the owner's account and inherits the Mac's ambient SSH identity. Having no tools at all is the mitigation.

## References

- `bin/events-review`
- `bin/test-events-review`
- `ibl5/classes/EventLog/README.md`
- ADR-0016 (removed analytics layer) and ADR-0093 (SELECT-only production credential)

## Addendum — channel delivery and Sunday schedule (2026-10-20) <!-- slop-ok -->

The Decision above says the output is sent only to the owner through `bin/discord-dm`. That changed. The defanged output is now posted to the #reviews channel through `bin/discord-dm` and the `~/.iblbot-reviews-webhook-url` webhook. Failure notices stay on the default #dev webhook.

The job also gained a fixed schedule. It fires on Sunday at 16:03 UTC, the weekly Claude usage reset. launchd times are local only, so the plist holds two Sunday entries (08:03 and 09:03 local) and passes `--at-reset`. At any other UTC hour the script exits 0, so exactly one of the two fires runs in both PDT and PST. The exception is a catch-up: if launchd ran a fire late (the Mac was asleep) and no review went out in the last 6 days, the script runs at that hour, except on Sunday before 16 UTC, which waits for the 16:xx fire.

Everything else in the Decision is unchanged, including the SELECT-only credential, the tool-less model run, and the defanging.

## Addendum (2026-10-27): 4xx routes, distinct GMs per route, blank-UA crawlers

The digest gained two breakdowns.

Each authenticated route line now carries its distinct-GM count, for example `40  trade (1 GM)`. One GM produced 68 of 69 `trading` views on 2026-10-23, and a raw view count let that one user read as a trend. The count is `COUNT(DISTINCT username)`; `ibl_events` uses `utf8mb4_unicode_ci`, so case variants of one username count once.

A 4xx section reports, per human class, 4xx counts split into 404, 403, and other 4xx; the number of distinct route names that drew a 4xx; and the top 10 of those route names. Route names in this section follow the existing rule: a name renders only if it is one of the week's top 25 GM-viewed routes, and every other name counts into `other`. Probe names are attacker-mintable, so they always fold. The status split and the distinct-name count are the signal that survives the fold. Writing a bot rule still needs the URI or user agent, which the digest never carries; the owner reads `ibl_events` directly for that.

`TrafficClassifier` now files an anonymous request with a missing or blank user agent as `crawler`, and recognizes more HTTP-library user agents (`go-http-client`, `axios`, `scrapy`, `httpclient`, `java/`, `okhttp`, `node-fetch`, `libwww`, `headless`). Existing rows keep their stored class. The human bucket the digest reads gets cleaner from the deploy date on.

The privacy contract is unchanged. Every new statement is a SELECT under the same credential and selects neither `request_uri` nor `user_agent`. Usernames leave the database only as counts.
