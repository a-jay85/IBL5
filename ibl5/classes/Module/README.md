---
description: Module registry and access-control logic for URL routing and season-phase availability.
last_verified: 2026-07-24
---

# Module

Controls which application modules are accessible at any given time. `ModuleRegistry` is the canonical list of valid module names used by the URL router. `ModuleAccessControl` derives module availability from the current season phase, site settings (e.g., trivia mode), and league context (IBL vs Olympics), ensuring modules are only reachable when appropriate.
