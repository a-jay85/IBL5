---
description: Email delivery with three transports (SMTP, PHP native, log) and header-injection sanitization.
last_verified: 2026-07-24
---

# Mail

Handles outbound email delivery for the application. `MailService` supports three transports: `smtp` via PHPMailer, `mail` via PHP's native `mail()` function, and `log` which writes to `error_log` for local development (the default). `EmailSanitizer` sanitizes email headers to prevent header injection attacks before messages are sent.
