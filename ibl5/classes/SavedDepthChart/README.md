---
description: Persists and retrieves GM-saved depth chart configurations via a JSON API.
last_verified: 2026-07-24
---

# SavedDepthChart

Provides a JSON API (no page view) for saving and loading GM-defined depth chart configurations. `SavedDepthChartApiHandler` handles incoming HTMX/API requests and delegates to `SavedDepthChartService`, which coordinates persistence through `SavedDepthChartRepository`. There is no HTML view class — all responses are JSON.

| Class | Role |
|---|---|
| `SavedDepthChartApiHandler` | Handles API requests; routes to service |
| `SavedDepthChartService` | Orchestrates save/retrieve logic |
| `SavedDepthChartRepository` | Database persistence for depth chart configs |
