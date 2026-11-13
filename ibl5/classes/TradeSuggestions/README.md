---
description: Trade suggestions module notes, starting with the unit 3 engine (league snapshot, deal shapes, two-sided gain scoring).
last_verified: 2026-11-11
---

# Trade Suggestions

## Engine (unit 3)

`Engine\CachedLeagueSnapshotProvider` caches one `LeagueSnapshot` per league in the `cache` table, keyed by sim, phase, roster digest, trade marker and resolved modes. Per-GM work (`CandidateGenerator::generate`, `DealScorer::scoreAll`) runs on every request and is never cached.

| Shape | Fires when |
|-------|------------|
| `OneForOne` | both sides hold a surplus that matches the other side's deficit |
| `TwoForOne` / `OneForTwo` | the receiving side has a weak slot and the giver can send two players |
| `StarForYoungAndPicks` | a Rebuild giver with a star meets a WinNow partner |
| `DumpPaidWithPick` | the giver's offset-1 payroll rank is in the bottom tier |
| `ExpiringForLong` | one side has an expiring player and the other a long contract |
| `PickSwap` | both sides own a round-1 pick and their modes differ |
| `ChangeOfScenery` | a buried player on one side meets a weak slot on the other |

`CandidateBudget` caps the search at 6 players per partner, 2 assets per side, 2 pick rounds and 4000 raw candidates. `SideGain::KEYS` lists the nine gain components: starter slots, bench slots, profile dimensions, balance, payroll, cap room, picks, roster spots and contracts.
