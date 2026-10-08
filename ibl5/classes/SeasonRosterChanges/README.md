---
description: Season roster changes. Lists players whose current team differs from their team in the previous season's ibl_hist row.
last_verified: 2026-10-03
---

# SeasonRosterChanges

Lists every player whose team in ibl_plr differs from his team in the previous season's ibl_hist row. It is a season-over-season roster delta, so it shows where a player ended up and not how he got there. For the dated log of individual trades, signings, waivers, and releases, see [TransactionHistory](../TransactionHistory/README.md).

Uses a Repository/View pattern without a Service layer. `SeasonRosterChangesRepository` fetches the roster changes and `SeasonRosterChangesView` renders the table.
