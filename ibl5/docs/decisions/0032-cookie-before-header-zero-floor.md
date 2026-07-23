---
description: Zero-floor ratchet for ibl.cookieBeforeHeader PHPStan rule, preventing baseline regression in cleaned controllers
last_verified: 2026-07-22
---

# ADR-0032: Zero-Floor Ratchet for `ibl.cookieBeforeHeader`

## Status

Accepted

## Context

The `PageLayoutHeaderBeforeCookieRule` (identifier `ibl.cookieBeforeHeader`) flags `$cookie[...]` reads that appear before `PageLayout::header()` in the same method body. Four controllers carried baseline suppressions masking violations that were either stale (code already fixed) or false positives (local `$cookie` from `cookieDecode()` unrelated to the global).

After fixing the rule's namespace detection (it wasn't matching `\PageLayout\PageLayout::header()`, only the bare `PageLayout::header()`) and resolving the one genuine false positive (renaming a local `$cookie` variable in SeriesRecordsController), all four baseline entries were removed.

Without a ratchet, future baseline regeneration could silently re-suppress a new violation in these files.

## Decision

Apply the zero-floor pattern established in [ADR-0031](0031-xss-zero-floor-ratchet.md) to `PageLayoutHeaderBeforeCookieRule`:

- A `ZERO_FLOOR_FILES` constant lists the four cleaned controllers. (Update 2026-07-22: WaiversController was subsequently added; the list now covers five controllers.)
- Errors in zero-floor files are emitted with `->nonIgnorable()`, bypassing baseline suppression.
- A tip message identifies the file as zero-floored.

## Consequences

- New `$cookie[...]` reads before `PageLayout::header()` in these five files will fail PHPStan unconditionally. (Update 2026-07-22: WaiversController was added after the initial decision; the list grew from four to five.)
- Other files not in the zero-floor list remain baseline-suppressible (backwards-compatible).
- Future controller cleanups can add files to the list incrementally.
