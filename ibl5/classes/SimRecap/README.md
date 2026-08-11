---
description: Ingests externally-generated sim recap documents and queues them for the sim recap pipeline; RecapPhasePolicy gates generation to Regular Season only; SimRecapContextRepository precomputes roster context for generation.
last_verified: 2026-08-10
---

# SimRecap

Handles the ingest of externally-generated simulation recap documents into the application's pipeline. `SimRecapPayload` parses and validates incoming recap documents at the trust boundary, failing closed on structural errors. `SimSummaryRepository` implements atomic conditional-UPDATE queue logic mirroring the BugPipeline pattern to prevent duplicate processing. It also validates at write time that every game row in a payload joins to an archived box score on date + teams + game_of_that_day, so a payload that could never render is refused before it becomes a row. `SimSummariesView` renders the recap summaries for display. Entry script: `ibl5/scripts/storeSimRecap.php`. The `simRecapContext.php` script precomputes current rosters, active injuries, and in-window trades via `SimRecapContextRepository`, providing roster context for the sim-recap generation pipeline. `RecapPhasePolicy` is the single source of truth for which season phases allow sim-recap generation; it gates both `QueueSimSummaryStep` and the `sim-recap-tick` self-unload path.

| Class | Role |
|---|---|
| `RecapPhasePolicy` | Single source of truth for enabled phases (`ENABLED_PHASES = ['Regular Season']`); `isEnabled(string $phase): bool` |
| `SimRecapPayload` | Parses and validates incoming recap documents |
| `SimSummaryRepository` | Atomic queue operations, plus the write-time box-score join validation |
| `SimSummariesView` | Renders sim recap summaries |
| `RecapDocument` | Assembles the postable document from intro + game rows + outro, shaped like `bin/lib/sim-recap-exemplar.txt`; prefers the stored text when the parts are degraded or incomplete |
| `SimRecapContextRepository` | Precomputes current rosters, active injuries, and in-window trades for a sim |
