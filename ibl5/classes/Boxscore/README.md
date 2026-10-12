---
description: Processes and renders game boxscore data with pluggable progress reporting.
last_verified: 2026-10-07
---

# Boxscore

Processes game boxscore data and renders it as HTML. `BoxscoreProcessor` handles the data processing logic. `BoxscoreRepository` reads, writes, and deletes box score rows. `AllStarTeamRepository` reads and renames All-Star team rows. `BoxscoreAuditRepository` runs the data-integrity checks and records schedule-guard rejects. `BoxscoreView` renders the output. Long-running operations use a pluggable progress reporter: `FlushProgressReporter` streams progress to the browser, while `NoOpProgressReporter` discards it silently.

| Class | Purpose |
|-------|---------|
| `BoxscoreProcessor` | Processes raw game boxscore data |
| `BoxscoreRepository` | Database reads and writes for boxscore records |
| `AllStarTeamRepository` | All-Star game team names, rosters, and renames |
| `BoxscoreAuditRepository` | Orphan box scores, unscored schedule rows, duplicate games, and the reject log |
| `BoxscoreView` | Renders boxscore HTML |
| `FlushProgressReporter` | Streams progress output during long operations |
| `NoOpProgressReporter` | Discards progress output silently |
