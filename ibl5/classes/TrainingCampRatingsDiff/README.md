---
description: Displays player rating changes from training camp for GMs.
last_verified: 2026-07-24
---

# TrainingCampRatingsDiff

Shows GMs how each player's ratings changed during training camp. `RatingDelta` is a value object representing the change in a single rating attribute; `RatingRow` is a value object for a player's full set of rating changes across all attributes. `TrainingCampRatingsDiffService` assembles the deltas from the repository and `TrainingCampRatingsDiffView` renders the comparison table. Entry point: `ibl5/modules/TrainingCampRatingsDiff/index.php`.

| Class | Role |
|---|---|
| `TrainingCampRatingsDiffRepository` | Queries before/after rating snapshots |
| `TrainingCampRatingsDiffService` | Assembles rating change data |
| `TrainingCampRatingsDiffView` | Renders the ratings diff table |
| `RatingDelta` | Value object: change in one rating attribute |
| `RatingRow` | Value object: all rating changes for one player |
