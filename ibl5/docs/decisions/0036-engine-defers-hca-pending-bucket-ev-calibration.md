---
description: The Go sim engine defers home-court advantage until play-outcome bucket EVs are corpus-calibrated; ASG mode is an in-engine no-op until then.
last_verified: 2026-05-31
---

# ADR-0036: Engine defers home-court advantage pending bucket-EV calibration

**Status:** Accepted
**Date:** 2026-05-31

## Context

PR7 ("game-type modes") set out to gate playoff and All-Star (ASG) behavior in the native Go sim engine (ADR-0035). JSB's only *in-engine, per-game* ASG effect is zeroing the home-court-advantage (HCA) magnitude — so a meaningful ASG mode requires HCA to exist first. A reverse-engineering trace of `jumpshot.exe` established that HCA cannot be ported faithfully onto the engine's current PR3a play-outcome model: HCA's three live sites are a ±0.2 nudge on **O(1)-scale** play-outcome buckets (the foul bucket is a ~0.6 floor; the 3pt/foul rating composites `+0xDB0`/`+0xDE0` are dead-zero, so JSB's foul weight is floor-driven and 3pt comes from a separate shot-type gate). The PR3a engine uses **O(100s)-scale** stand-in buckets (`leagueBaseline=233`, `fgpToPermille=9` — both flagged "refined when validated against the corpus"), against which a literal ±0.2 is a no-op, and a scaled version is *anti*-home because foul-path EV (~1.5) exceeds 2pt-path EV (~0.96). HCA's correct sign is therefore entangled with bucket EVs that do not yet exist.

## Decision

The engine ships **playoff gating only** (net advantage ×1.25 and the fast-break `special_sub`, both `game_type==4`, decompile-confirmed and golden-stable). **HCA is deferred** until the play-outcome bucket EVs are corpus-calibrated — at which point HCA is implemented as the JSB ±0.2 bucket nudge on the then-O(1) buckets, correctly signed by construction. Until then **ASG (game_type 5/6) is a deliberate in-engine no-op**: it is accepted, validated, and tagged on output (`SimGameType`), but triggers no per-game behavioral change (its only effect, HCA-zeroing, is moot while HCA is unmodeled; coaching is already neutral). This deferral is enforced by the absence of any HCA code path, not by a rule.

## Alternatives Considered

- **Implement HCA now with the literal JSB ±0.2** — Rejected because: on the O(100s) PR3a buckets it is a statistically undetectable no-op (measured home win-rate 0.4975→0.5105).
- **Implement HCA now with a magnitude scaled to the PR3a buckets** — Rejected because: it produces a *wrong-signed* effect (home win-rate dropped to 0.3875), since boosting the 2pt path while cutting the higher-EV foul path lowers home scoring under the uncalibrated stand-in EVs.
- **Build the off-quality `+0xD78/+0xDE0/…` infrastructure to host site-3 HCA** — Rejected because: large, speculative, and unverified as sufficient; the foul bucket is a floor in JSB, not that composite, so it would not reproduce the mechanism.

## Consequences

- Positive: PR7's shipped behavior (playoff ×1.25, `special_sub`) is faithful, deterministic, and golden byte-stable — no calibration dependency.
- Positive: the deferral is recorded with the precise unblock (corpus-calibrate bucket EVs, then add the ±0.2 nudge), so a future PR has a concrete spec rather than a rediscovery.
- Negative: ASG games simulate identically to regular-season games in-engine until HCA lands; consumers must not read "ASG mode" as behaviorally distinct yet.

## References

- `ibl5/docs/decisions/0035-native-go-sim-engine.md` — the engine this defers within.
- `engine/internal/sim/gametype.go` — the playoff gating that did ship; documents the ASG no-op.
- `engine/internal/sim/netadvantage.go` — `playoffNetMultiplier` (×1.25) application site.
- The RE trace establishing the bucket-scale/EV entanglement lives in the JSB decompile notes (`COMPOSITE_DOUBLES_TRACE.md`), outside this repo.
