---
description: Monolog-based structured logging with Discord webhook alerts, PII redaction, and user context injection.
last_verified: 2026-07-24
---

# Logging

Provides application-wide structured logging built on Monolog. `LoggerFactory` creates `Logger` instances configured with a rotating file handler and an optional Discord webhook; it supports per-channel retention settings. `DiscordWebhookHandler` posts ERROR-level and above records to a Discord channel. `PiiRedactionProcessor` scrubs sensitive fields (passwords, tokens, secrets, raw keys) and patterns (emails, API keys) from log records before they are written. `UserContextProcessor` injects the authenticated username and team into every log record for easier triage.

| Class | Role |
|---|---|
| `LoggerFactory` | Creates per-channel Monolog Logger instances |
| `DiscordWebhookHandler` | Posts ERROR+ records to Discord |
| `PiiRedactionProcessor` | Scrubs sensitive fields and patterns |
| `UserContextProcessor` | Injects user/team context into records |
| `LoggingBootstrap` | Wires logging during application bootstrap |
