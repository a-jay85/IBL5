---
description: Cross-cutting HTML sanitization and CSRF protection utilities used across all form-processing modules.
last_verified: 2026-07-24
---

# Security

Cross-cutting security utilities with no module entry point of their own. `HtmlSanitizer` is a static utility for safe HTML output — it handles mixed PHP types (string, int, float, bool, array, null) via `htmlspecialchars` with `ENT_QUOTES|ENT_HTML5`. `CsrfGuard` generates and validates form-scoped CSRF tokens stored in the PHP session with a 4-hour expiration; it is used by every POST handler in the application.

| Class | Role |
|---|---|
| `HtmlSanitizer` | Static output escaping for all HTML-rendered values |
| `CsrfGuard` | Session-backed CSRF token generation and validation |
