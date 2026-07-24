---
description: Runs the compiled native sim binary as a stdin/stdout transform, streaming game results as NDJSON.
last_verified: 2026-07-24
---

# EngineRunner

Executes the compiled native sim binary as a stdin/stdout transform: bundle JSON goes in, NDJSON game results come out. Spools stdout to a temp file rather than accumulating it in a PHP string to maintain constant memory usage during large sim runs. `EngineRunnerException` is thrown on non-zero exit or process failure.
