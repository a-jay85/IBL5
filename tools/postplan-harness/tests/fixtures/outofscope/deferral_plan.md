# Plan: fixture for the out-of-scope sweep

## Approach

We could file it separately later, but this Approach line must never hit.

## Out of Scope

- Rewriting the legacy importer is a separate plan because it touches every season table.
- Moving the nightly cache warmer to the new scheduler
  should be filed separately once the scheduler stabilizes.

```
- A follow-up PR for the fenced thing
```

- Per-team export formats were a resolved decision, so a separate PR is not wanted.
- Retiring the old CSV endpoint is tracked in a-jay85/IBL5-backlog#12 as a follow-up issue.
- The ranking service and the stats service are separate plans, separate services.

## Verification Matrix

Nothing here.
