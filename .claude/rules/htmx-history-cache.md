---
description: htmx snapshots the DOM into its history cache mid-request, so transient request-time DOM state gets frozen into Back navigation — how to avoid baking it in.
last_verified: 2026-08-16
paths:
  - "ibl5/jslib/**"
  - "ibl5/themes/**"
---

# htmx History Cache

Every page on this site is boosted: `ibl5/themes/IBL/theme.php` wraps all content in
`<div id="site-content" hx-boost="true" …>`, and `hx-boost` is **inherited**, so every
form and link under it is submitted as an htmx XHR — including forms that carry no
htmx attributes of their own. Boosted navigations push history entries, and htmx keeps
its own DOM snapshot of each one in `localStorage` (`htmx-history-cache`).

## The ordering that bites

For a boosted request, htmx does this, in order:

1. fires `htmx:beforeRequest`
2. **serializes the current DOM into the history cache**
3. swaps in the response
4. fires `htmx:afterRequest`

Step 2 sits *between* the two handlers. So **any DOM mutation made in a pre-request
handler is frozen into the snapshot, and the undo in `htmx:afterRequest` only ever
reaches the live DOM** — it cannot reach the cached copy. Browser Back then restores
the page mid-request, permanently.

That is exactly how a GM ended up unable to vote: the submit-button disable in
`htmx-init.js`'s `htmx:beforeRequest` handler was baked into the snapshot, so Back
from a validation-error page returned an All-Star ballot whose Submit button was
disabled forever and still read "Submitting…". Only a fresh navigation cleared it.

## Rules

- **Undo transient request-time DOM state in `htmx:historyRestore`, not just in
  `htmx:afterRequest`.** The restore handler in `ibl5/jslib/htmx-init.js` is the
  single place that owns this repair; extend it rather than adding a parallel one.
- **Mark what you mutated with a `data-*` attribute**, and repair only marked
  elements. `data-*` attributes serialize into the snapshot, so the marker survives
  Back — and scoping to it keeps the repair from clobbering state the server rendered
  deliberately (e.g. `#trade-submit-btn` ships `disabled` and is enabled by
  `ibl5/jslib/trade-submit-guard.js` only once a valid trade is composed).
- **Do not repair in `htmx:beforeHistorySave`.** It fires while the request is still
  in flight and hands you the live node, not a clone — undoing there would drop the
  double-submit guard for the rest of the request. It also cannot heal snapshots
  already sitting in a user's `localStorage` from before a fix ships; `historyRestore`
  can.
- **Know what does and does not survive serialization.** The snapshot is `innerHTML`,
  so only *reflected* state persists: `disabled`, `style`, `class`, `value` on
  submit inputs, and every `data-*` attribute all survive. JS-set `input.checked`
  does **not** (only `defaultChecked` maps to the content attribute), so a restored
  ballot comes back with every checkbox cleared. Never assume a restored form still
  holds what the user picked.
- **`htmx:afterSwap` does not fire on history restore.** Anything a swap needs
  re-run must be listed in the `htmx:historyRestore` handler too.

## Testing it

A Back-navigation test can pass for the wrong reason: on a history **cache miss**
htmx refetches from the server, which returns a clean page and hides the bug. Assert
a witness that the restore path was actually taken —
`ibl5/tests/e2e/flows/voting-submission.spec.ts` sets a flag from an
`htmx:historyRestore` listener installed via `page.addInitScript`, then polls it
before asserting on the restored DOM.

## Why this rule exists

`ibl5/docs/decisions/0103-htmx-transient-dom-state-repair-on-history-restore.md` —
the alternatives weighed (`beforeHistorySave`, a blanket re-enable, a mechanical
gate) and why enforcement here is a rule doc plus review rather than a gate.
