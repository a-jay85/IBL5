---
description: Ingests externally-generated sim recap documents and queues them for the sim recap pipeline; SimRecapContextRepository precomputes roster context for generation.
last_verified: 2026-07-30
---

# SimRecap

Handles the ingest of externally-generated simulation recap documents into the application's pipeline. `SimRecapPayload` parses and validates incoming recap documents at the trust boundary, failing closed on structural errors. `SimSummaryRepository` implements atomic conditional-UPDATE queue logic mirroring the BugPipeline pattern to prevent duplicate processing. `SimSummariesView` renders the recap summaries for display. Entry script: `ibl5/scripts/storeSimRecap.php`. The `simRecapContext.php` script precomputes current rosters, active injuries, and in-window trades via `SimRecapContextRepository`, providing roster context for the sim-recap generation pipeline.

| Class | Role |
|---|---|
| `SimRecapPayload` | Parses and validates incoming recap documents |
| `SimSummaryRepository` | Atomic queue operations for the recap pipeline |
| `SimSummariesView` | Renders sim recap summaries |
| `RecapDocument` | Assembles the postable document from intro + game rows + outro, shaped like `bin/lib/sim-recap-exemplar.txt` |
| `SimRecapContextRepository` | Precomputes current rosters, active injuries, and in-window trades for a sim |
