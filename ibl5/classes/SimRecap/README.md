---
description: Ingests externally-generated sim recap documents and queues them for the sim recap pipeline.
last_verified: 2026-07-24
---

# SimRecap

Handles the ingest of externally-generated simulation recap documents into the application's pipeline. `SimRecapPayload` parses and validates incoming recap documents at the trust boundary, failing closed on structural errors. `SimSummaryRepository` implements atomic conditional-UPDATE queue logic mirroring the BugPipeline pattern to prevent duplicate processing. `SimSummariesView` renders the recap summaries for display. Entry script: `ibl5/scripts/storeSimRecap.php`.

| Class | Role |
|---|---|
| `SimRecapPayload` | Parses and validates incoming recap documents |
| `SimSummaryRepository` | Atomic queue operations for the recap pipeline |
| `SimSummariesView` | Renders sim recap summaries |
