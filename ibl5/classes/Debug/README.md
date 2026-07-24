---
description: Manages the debug overlay toggle and debug session state for admin use.
last_verified: 2026-07-24
---

# Debug

Manages the application debug overlay. `DebugController` handles toggle requests, requiring A-Jay admin authentication and CSRF validation before activating. `DebugSession` manages the "view all extensions" debug session state and only activates for A-Jay on localhost or in E2E test environments.
