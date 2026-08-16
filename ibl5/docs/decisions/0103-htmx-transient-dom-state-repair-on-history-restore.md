---
description: Transient request-time DOM state must be undone in htmx:historyRestore and scoped to a data-* marker, because htmx snapshots the DOM between beforeRequest and the swap.
last_verified: 2026-08-16
---

# ADR-0103: Repair Transient htmx Request-Time DOM State on History Restore

**Status:** Accepted
**Date:** 2026-08-16

## Context

Every page is boosted — `ibl5/themes/IBL/theme.php` wraps content in `<div id="site-content" hx-boost="true">`, and `hx-boost` is inherited — so every form submits as an htmx XHR and every navigation pushes an htmx history entry with a serialized DOM snapshot in `localStorage`. htmx takes that snapshot *between* `htmx:beforeRequest` and the swap. The double-submit guard in `ibl5/jslib/htmx-init.js` disables submit buttons in `htmx:beforeRequest` and re-enables them in `htmx:afterRequest`, so the disable was frozen into the snapshot while the undo only ever reached the live DOM. A league GM hit the consequence: browser Back from a ballot validation error returned an All-Star ballot whose Submit button was permanently disabled and still read "Submitting…", with no way to vote short of a fresh navigation.

## Decision

Any DOM mutation made in an htmx pre-request handler must be undone in **`htmx:historyRestore`** as well as in `htmx:afterRequest`, and the mutation must tag the element with a `data-*` attribute so the restore handler repairs only elements it owns. `ibl5/jslib/htmx-init.js` holds the single `htmx:historyRestore` handler that owns this repair; extend it rather than adding a parallel listener. The convention and its serialization caveats are documented for agents in `.claude/rules/htmx-history-cache.md`; there is no mechanical gate (see Alternatives).

## Alternatives Considered

- **Repair in `htmx:beforeHistorySave`** — snapshot-time hook that could undo the mutation just before serialization. Rejected because: it fires with the request still in flight and hands you the live node rather than a clone, so undoing there drops the double-submit guard for the rest of the request — and it cannot heal snapshots already sitting in users' `localStorage`.
- **Blanket `disabled = false` on every submit button at restore** — simplest possible repair. Rejected because: it would prematurely enable buttons the server ships disabled on purpose, e.g. `#trade-submit-btn`, which `ibl5/jslib/trade-submit-guard.js` enables only once a valid trade is composed.
- **A PHPStan rule or `bin/check-*` grep gate** — mechanical enforcement of the class. Rejected because: PHPStan only sees PHP, and the pattern ("a pre-request handler mutates DOM state") has no grep-matchable signature that would not fire on every unrelated htmx listener. A rule doc plus PR review is the cheaper sufficient rung (`.claude/rules/meta-tooling-bar.md`).
- **Drop the double-submit guard entirely** — no mutation, no snapshot poisoning. Rejected because: the guard is what stops a GM double-submitting a ballot or a trade on a slow connection.

## Consequences

- Positive: Back navigation returns usable forms, and the marker-scoped repair also heals snapshots poisoned before this change shipped — GMs do not have to clear `localStorage`.
- Positive: naming the class (not just the ballot symptom) gives future htmx work a stated convention, and `ibl5/tests/e2e/flows/voting-submission.spec.ts` pins it with a restore-path witness so a history cache miss cannot pass the test for the wrong reason.
- Negative: enforcement is documentation plus review, not a gate — a future pre-request handler can reintroduce the class and only a reviewer will catch it.
- Negative: the repair is coupled to a `data-*` naming convention (`data-original-text`); a handler that mutates state without tagging it is silently not repaired.

## References

- `.claude/rules/htmx-history-cache.md` — the agent-facing rule this ADR justifies.
- `ibl5/jslib/htmx-init.js` — the `htmx:beforeRequest` / `htmx:afterRequest` / `htmx:historyRestore` handlers.
- `ibl5/jslib/trade-submit-guard.js` — the server-disabled button the marker scoping protects.
- `ibl5/tests/e2e/flows/voting-submission.spec.ts` — regression test pinning the reported defect.
- `ibl5/docs/backlog/voting-csrf-single-use-post-redisplay.md` — the adjacent CSRF dead-end surfaced by the same report, deferred to a plan.
