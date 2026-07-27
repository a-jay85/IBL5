---
description: Displays historical season archives including stats, standings, and awards for past seasons.
last_verified: 2026-07-26
---

# SeasonArchive

Presents an indexed and detail view of past IBL seasons, including final standings, statistical leaders, and award winners. `SeasonArchiveService` assembles the data for both the index and the per-season detail page, delegating award extraction to `SeasonAwardExtractor` and playoff-bracket assembly to `SeasonPlayoffBracketBuilder`. Views are rendered by `SeasonArchiveIndexView` and `SeasonDetailView` respectively. `SeasonArchiveRenderHelpers` provides shared rendering utilities used by both views. Entry point: `ibl5/modules/SeasonArchive/index.php`.

| Class | Role |
|---|---|
| `SeasonArchiveService` | Assembles archive data for index and detail pages |
| `SeasonAwardExtractor` | Extracts award winners, GM/coach honors, and parsed team awards |
| `SeasonPlayoffBracketBuilder` | Groups playoff rows into a bracket and reads the IBL Finals |
| `SeasonArchiveRepository` | Queries historical season records |
| `SeasonArchiveIndexView` | Renders the season list index |
| `SeasonDetailView` | Renders a single season's historical detail |
| `SeasonArchiveRenderHelpers` | Shared rendering utilities for archive views |
