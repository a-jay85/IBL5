---
description: Displays league transaction history including trades, free agent signings, waiver claims, and releases.
last_verified: 2026-07-24
---

# TransactionHistory

Provides the league transaction log, showing trades, free agent signings, waiver claims, and player releases in reverse-chronological order. `TransactionHistoryService` assembles the transaction feed from `TransactionHistoryRepository` and `TransactionHistoryView` renders the list. Entry point: `ibl5/modules/TransactionHistory/index.php`.

| Class | Role |
|---|---|
| `TransactionHistoryRepository` | Queries the league transaction log |
| `TransactionHistoryService` | Assembles transaction feed data |
| `TransactionHistoryView` | Renders the transaction history list |
