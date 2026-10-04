---
description: A weekly tool-less model run reads an aggregate digest of ibl_events through the SELECT-only credential and DMs recommendations to the owner. Raw URIs, user agents and identities never leave the database.
last_verified: 2026-10-03
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
