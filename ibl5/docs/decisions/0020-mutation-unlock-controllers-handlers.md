---
description: Rationale for removing the broad *Controller* and *Handler* mutation testing exclusions and refining overly broad module-level excludes.
last_verified: 2026-05-06
---

# ADR-0020: Mutation Testing Unlock — Controllers + Handlers

**Status:** Accepted
**Date:** 2026-05-06

## Context

`infection.json5` excluded `*Controller*` and `*Handler*` from mutation testing via broad glob patterns, exempting 41 Controller/Handler implementations from the 100% MSI gate. Controllers and Handlers are the HTTP-facing seam — they translate request data into service calls and service responses into HTTP output. Mutations here can silently flip default values, invert conditionals, corrupt redirect URLs, or change HTTP status codes.

27 of the 41 files already have companion `*ControllerTest.php` or `*HandlerTest.php` test files (Pass 1). The remaining 14 (including the 291-line `PlayerPageController`) lack direct tests and are deferred to Pass 2.

## Decision

1. **Remove the broad `*Controller*` and `*Handler*` glob excludes** from `infection.json5`.

2. **Add 14 specific per-file excludes** for Controllers/Handlers lacking direct test coverage (Pass 2 deferred): `PlayerPageController`, `UpdaterController`, 10 Api/Controllers, `NextSimTabApiHandler`, `TeamApiHandler`.

3. **Refine overly broad module-level excludes** that accidentally shadowed Pass 1 files:
   - `"Cache"` → `"Cache/PageCache.php"`, `"Cache/DatabaseCache.php"` (the broad name matched `Api/Cache/ETagHandler.php`)
   - `"SiteStatistics"` → 3 specific non-Controller files (the broad name blocked `StatisticsController.php` which has a test)
   - `"Discord"` → `"Discord/Discord.php"` (the broad name matched `Logging/DiscordWebhookHandler.php` via `notPath`)

4. **Add `mutation:controllers-handlers` composer script** for focused local mutation testing on just the Pass 1 files.

5. **No separate CI job needed.** The existing weekly `mutation` job and per-PR `mutation-pr` job already use `infection.json5`, so they naturally pick up the newly included files.

## Consequences

- 27 Controllers + Handlers (998 mutations) are now under the 100% MSI gate.
- All 998 mutations are killed by existing tests — no backfill was needed.
- The `Cache`, `SiteStatistics`, and `Discord` module exclusions are now file-specific, avoiding accidental shadowing of files in other modules.
- Pass 2 (14 untested files) remains deferred with specific per-file excludes.
