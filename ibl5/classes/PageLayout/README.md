---
description: Static header and footer rendering for legacy PHP-Nuke module pages.
last_verified: 2026-07-24
---

# PageLayout

Single class providing the `header()` and `footer()` static methods that wrap every module page. `header()` populates cookie and user globals and handles HTMX boosted requests by emitting a partial header instead of a full page header. Every module's `index.php` calls `PageLayout::header()` as its first statement and `PageLayout::footer()` as its last.
