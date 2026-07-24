---
description: Processes and renders game boxscore data with pluggable progress reporting.
last_verified: 2026-07-24
---

# Boxscore

Processes game boxscore data and renders it as HTML. `BoxscoreProcessor` handles the data processing logic, `BoxscoreRepository` handles database operations, and `BoxscoreView` renders the output. Long-running operations use a pluggable progress reporter: `FlushProgressReporter` streams progress to the browser, while `NoOpProgressReporter` discards it silently.

| Class | Purpose |
|-------|---------|
| `BoxscoreProcessor` | Processes raw game boxscore data |
| `BoxscoreRepository` | Database reads and writes for boxscore records |
| `BoxscoreView` | Renders boxscore HTML |
| `FlushProgressReporter` | Streams progress output during long operations |
| `NoOpProgressReporter` | Discards progress output silently |
