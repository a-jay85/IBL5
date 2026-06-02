---
description: The production smoke test's gating IBL5 probe runs on the production box over SSH and curls the public hostname from the box's own whitelisted IP, removing the per-IP WAF from the auto-rollback path; SSH-unreachable is treated as inconclusive (never rollback). Loopback probing was rejected because the box serves a non-matching default-vhost cert on 127.0.0.1.
last_verified: 2026-06-02
---

# ADR-0039: On-Box Smoke Gates Auto-Rollback

**Status:** Accepted
**Date:** 2026-06-02
**Supersedes:** the *gating mechanism* of [ADR-0038](0038-smoke-inconclusive-state.md) (its inconclusive classification is retained for the external IBL6/notify path).

## Context

ADR-0038 added an INCONCLUSIVE state so a *uniform* WAF-class block (HTTP 403/415/429 on every check) from the GitHub-runner egress IP no longer auto-reverts a healthy deploy. It explicitly named the root-cause fix it was deferring: probing "from on-box `localhost`… addresses the WAF root cause and remain[s] the right long-term fix." This ADR lands the on-box probe.

Two holes remained while the gating probe still originated from a WAF-eligible runner IP:

1. **Partial / mid-run ban.** If LiteSpeed's ban is rate-threshold-based, early checks return 200 and later ones 415. A non-zero pass count defeats the inconclusive predicate (which requires *zero* passes), so the run exits 1 and can still auto-revert a healthy deploy. The predicate cannot be safely loosened — a mix of pass and fail is exactly what a genuine partial outage looks like.
2. **Coverage hole.** During any WAF block the deploy ships with no real verdict; a genuinely-broken deploy that coincides with a block is masked as inconclusive.

Both share one cause: the probe was reached over the public internet from an IP the WAF judges by reputation.

## Decision

Run the **gating** IBL5 probe **on the production box**, over the SSH channel the notify jobs already use (`secrets.USERNAME@HOST`, key `secrets.PRIVATE_KEY`, `secrets.PORT`). `smoke-prod.yml` pipes the repo's `bin/smoke-prod` to `bash -s` on the box (the script is not deployed — `bin/` is host/CI-only):

```
ssh … "env SMOKE_INTERCHECK_DELAY=0 bash -s -- --scope=ibl5 $BASE_URL" < bin/smoke-prod
```

The box curls the **public hostname** (`https://www.iblhoops.net/…`), which resolves to the box's own IP. The request therefore originates from the box's own **whitelisted** egress IP — not a foreign datacenter/runner IP — so it is not subject to the per-IP/ASN-reputation WAF, while still exercising the real public vhost, TLS cert, ModSecurity, and PHP stack.

This removes the WAF from the rollback path: the gate is no longer subject to false-positive WAF blocks, and **every deploy is genuinely verified** (closing hole 2). A real regression still gates rollback because it remains heterogeneous to the existing classifier — uniform `5xx` or connection-refused (`000`) are not WAF-class codes, so `UNIFORM_WAF` is cleared and the script exits 1.

### Why not loopback (`--resolve 127.0.0.1`)

The original target was loopback (`curl --resolve www.iblhoops.net:443:127.0.0.1`), which avoids the public network path entirely and tests the origin deterministically. It was rejected after on-box verification (2026-06-02): the box **does** listen and complete a TLS handshake on `127.0.0.1:443`, but serves the **default-vhost cert**, whose SANs do not include `www.iblhoops.net` — so `curl` fails verification with error 60 (`no alternative certificate subject name matches`) and the smoke sees `000` on every check. Measured directly on the box:

```
public=200      # curl https://www.iblhoops.net/ibl5/index.php
loopback=000    # same, --resolve www.iblhoops.net:443:127.0.0.1  (curl 60: cert SAN mismatch)
```

Making loopback viable requires a server-side change (AutoSSL/cert covering the domain on the default vhost) and is out of scope. No `SMOKE_RESOLVE`/`--resolve` knob is introduced; if the box's default-vhost cert is fixed later, switching to loopback is a one-line workflow change.

### Exit-code contract

`bin/smoke-prod` only ever exits `0/1/2/3`, so an SSH exit of **255** is unambiguously SSH-level (box or network unreachable). The gating step maps:

| Code | Meaning | Action |
|------|---------|--------|
| 0 | pass | step succeeds |
| 1 | real failure (heterogeneous / 5xx / bad body / conn-refused) | propagate → **gates auto-rollback** |
| 3 | remote uniform WAF-class block | INCONCLUSIVE (`reason=waf`), notify-only, no rollback |
| 255 | box unreachable over SSH | INCONCLUSIVE (`reason=ssh-unreachable`), notify-only, no rollback |

**255 is non-gating by design.** Reverting cannot fix an unreachable box, and the recovery redeploy would itself need the box reachable — auto-reverting on SSH flakiness would re-introduce the false-revert class this work eliminates. One inline SSH retry (`ConnectTimeout=10`, 5 s backoff) absorbs transient blips before declaring inconclusive. The existing `notify-inconclusive` job is parameterized by `ibl5_inconclusive_reason` to DM the correct cause; no new job is added.

## Alternatives Considered

- **Stay prober-side (ADR-0038 only).** Rejected as insufficient: leaves the partial-ban false-revert and the verification coverage hole intact.
- **Loopback via `--resolve 127.0.0.1`.** Rejected on this box — default-vhost cert SAN mismatch (see above). Revisit if the cert is fixed server-side.
- **A dedicated remote-wrapper bin script** to make the SSH/exit mapping unit-testable. Rejected: it is a thin modification of ADR-0038's already-accepted inline exit-3 glue, and a new bin script of ≥50 lines would self-trigger `adr-check`'s `new-tool-script` rule. The workflow glue is verified by actionlint and the live `workflow_dispatch` against production.
- **Server-side WAF allowlist (cPanel ModSecurity / `.htaccess`).** Not pursued — the cPanel ModSec UI cannot be refined to allowlist GitHub's rotating IP ranges, and this design needs no server-side change.

## Consequences

- The deploy gate now depends on **SSH reachability to the production host**. An unreachable box yields an owner DM and no rollback (verdict unknown), not a silent pass and not a revert.
- IBL6 remains an external, notify-only check on the runner (single check, never gates rollback); moving it on-box is a possible future follow-up.
- **All IBL5 triggers now probe via the box, including the daily `schedule` run and `workflow_dispatch`** — the stale `guard` step only runs for `workflow_run`, so other triggers fall through to the on-box step. This is deliberate: the previous external scheduled run was itself WAF-false-positive-prone (ADR-0038 records a scheduled run with the identical 415), so moving it on-box trades a noisy public-path probe for a reliable origin check. The trade-off is that **no IBL5 automation now exercises the path from outside the box** (foreign-network DNS / CDN / edge firewall). True public-internet reachability ("can a real visitor load the site") is a distinct concern best served by a separate distributed/residential uptime monitor (out of scope here).
- The premise (the box can reach its own public hostname and gets 200) is validated end-to-end pre-merge via a `workflow_dispatch` run against production (which cannot trigger rollback — that requires a `workflow_run` event). `bin/smoke-prod` itself is unchanged by this PR; its existing behavior is covered by `SmokeProdCliTest.php`.
