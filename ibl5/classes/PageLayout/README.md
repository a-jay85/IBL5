---
description: Static header and footer rendering for legacy PHP-Nuke module pages.
last_verified: 2026-10-04
---

# PageLayout

Single class providing the `header()` and `footer()` static methods that wrap every module page. `header()` handles HTMX boosted requests by emitting a partial header instead of a full page header. It does not resolve identity or touch the `$cookie` global; callers read identity from `$authService` or the `auth.username` container entry. Every module's `index.php` calls `PageLayout::header()` as its first statement and `PageLayout::footer()` as its last.
