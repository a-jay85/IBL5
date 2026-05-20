---
description: Trading module naming convention — Trading* prefix vs Trade* prefix rule.
last_verified: 2026-05-20
---

# Trading Module Naming Convention

Within `Trading/`, the `Trading*` prefix is reserved for **module-level entry points** — the orchestration Service that composes repositories, the View that renders the user-facing pages, and any future TradingController. Domain objects (Repositories, Validators, Processors, Entity-like classes) use `Trade*` when they are scoped to a single trade or asset, or a concept-bearing prefix (e.g., `BuyoutLedger*`, `CashTransaction*`) when they cross trades.

## Current inventory

| Prefix | Files | Rationale |
|--------|-------|-----------|
| `Trading*` | `TradingService`, `TradingView`, `TradingController` | Module-level orchestration and rendering |
| `Trade*` | `TradeAssetRepository`, `TradeCashRepository`, `TradeExecutionRepository`, `TradeFormRepository`, `TradeItemType`, `TradeOffer`, `TradeOfferRepository`, `TradeProcessor`, `TradeRosterPreviewApiHandler`, `TradeValidator` | Single-trade-scoped domain objects |
| Concept-bearing | `BuyoutLedgerRepository`, `CashTransactionHandler` | Cross-trade cash obligations and transaction handling |

## Enforcement

`TradingPrefixConventionRule` (PHPStan, advisory severity) flags new classes under `Trading\` that use the `Trading*` prefix unless they implement one of `TradingServiceInterface`, `TradingViewInterface`, or `TradingControllerInterface`. Interfaces under `Trading\Contracts\` are exempt.
