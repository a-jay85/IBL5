---
description: Player movement history display — trades, free agency signings, and waiver transactions across seasons.
last_verified: 2026-07-24
---

# PlayerMovement

Displays a player's movement history across seasons, covering trades, free agency signings, and waiver transactions. Uses a Repository/View pattern without a Service layer — `PlayerMovementRepository` fetches transaction records and `PlayerMovementView` renders the history.
