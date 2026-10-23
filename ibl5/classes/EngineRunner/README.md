---
description: Runs the compiled native sim binary as a stdin/stdout transform, streaming game results as NDJSON.
last_verified: 2026-10-23
---

# EngineRunner

Executes the compiled native sim binary as a stdin/stdout transform: bundle JSON goes in, NDJSON game results come out. Spools stdout to a temp file rather than accumulating it in a PHP string to maintain constant memory usage during large sim runs. `EngineRunnerException` is thrown on non-zero exit, on an exit-0 run that did not read the full stdin bundle, or on process failure. A nonzero exit is reported first, with its stderr, even when the stdin write also failed.
