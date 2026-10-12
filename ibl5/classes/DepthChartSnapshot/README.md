---
description: Persists and retrieves GM-saved depth chart configurations via a JSON API.
last_verified: 2026-10-07
---

# DepthChartSnapshot

Provides a JSON API (no page view) for saving and loading GM-defined depth chart configurations. `DepthChartSnapshotApiHandler` handles incoming HTMX/API requests and delegates to `DepthChartSnapshotService`, which coordinates persistence through `DepthChartSnapshotRepository`. There is no HTML view class. All responses are JSON.

| Class | Role |
|---|---|
| `DepthChartSnapshotApiHandler` | Handles API requests; routes to service |
| `DepthChartSnapshotService` | Orchestrates save/retrieve logic |
| `DepthChartLabelBuilder` | Builds dropdown and "Current (Live)" label strings from pre-fetched rows and records |
| `DepthChartSnapshotRepository` | Database persistence for depth chart configs |
