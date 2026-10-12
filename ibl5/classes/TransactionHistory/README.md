---
description: Displays league transaction history including trades, free agent signings, waiver claims, and releases.
last_verified: 2026-10-03
---

# TransactionHistory

Provides the league transaction log, showing trades, free agent signings, waiver claims, and player releases in reverse-chronological order. `TransactionHistoryService` assembles the transaction feed from `TransactionHistoryRepository` and `TransactionHistoryView` renders the list. Entry point: `ibl5/modules/TransactionHistory/index.php`. For a season-over-season view of which players changed teams, without the individual transactions, see [SeasonRosterChanges](../SeasonRosterChanges/README.md).

| Class | Role |
|---|---|
| `TransactionHistoryRepository` | Queries the league transaction log |
| `TransactionHistoryService` | Assembles transaction feed data |
| `TransactionHistoryView` | Renders the transaction history list |
