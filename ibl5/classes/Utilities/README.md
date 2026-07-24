---
description: Miscellaneous cross-cutting utilities including HTMX detection, UUID generation, legacy PHP-Nuke compatibility, and URL building.
last_verified: 2026-07-24
---

# Utilities

A collection of cross-cutting helper classes used across the application. `NukeCompat` is a typed, injectable adapter for legacy PHP-Nuke global functions, eliminating bare globals from modern classes. `HtmxHelper` detects HTMX request headers (`HX-Request`, `HX-Boosted`) and provides an HTMX-aware redirect helper. `UuidGenerator` generates UUID v4 identifiers for database records. `BoxScoreUrlBuilder` constructs URLs to individual box score pages.

| Class | Role |
|---|---|
| `NukeCompat` | Injectable adapter for legacy PHP-Nuke global functions |
| `HtmxHelper` | HTMX request detection and redirect helper |
| `UuidGenerator` | UUID v4 generation for database records |
| `BoxScoreUrlBuilder` | Builds URLs to box score pages |
| `ScheduleHighlighter` | Highlights schedule entries for display |
| `TestCookieOverrides` | Test environment cookie override support |
