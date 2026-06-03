# Team-Scoring Volume→Count Channel — Build + Calibration (ADR-0042 fix)

> **Type:** model change + instrumentation (the ADR-0042 fix PR). Implements the
> shot-volume-rate → shot-COUNT pathway the RE trace
> (`team-scoring-coupling-trace.md`) located as absent, and ships the by-origin
> FGA instrumentation the ADR scoped for the empty-FGA split. Every engine claim
> is grep-reproducible; the corpus numbers come from the archive diagnostic
> (`TestRealArchive_*`, build-tag `archive`, run against `ibl5/backups`).

## What this PR builds

1. **Lever 1 — the volume→count channel** (`internal/sim/tempo.go`). `teamBaseTime`
   was defense-only; it now restores the offensive side of JSB's `base_time`
   offensive/defensive stat ratio (FUN_004e4150): a higher offensive volume
   composite (Σ starters' `r_fga+r_3ga+r_fta`) SHORTENS `base_time` → more
   possessions → more FGA. Additive offensive-minus-defensive stand-in (only the
   `[13,16]` clamp, the `(2.0−factor)` form, and the `24.0` fallback are confirmed;
   the inputs are validation-phase). Neutral reference points are the real dev-DB
   per-starter composite means (offense 161, defense 24).

   `internal/sim/gameloop.go` is **unchanged**: under strict possession alternation
   a shared-average step and a per-team step give each team the same possession
   COUNT (`clock / avg(BT_v, BT_h)`), so the season-level channel emerges from a
   high-volume team's games averaging faster across its varied opponents — no
   per-possession-step refactor is needed.

2. **Instrumentation — by-origin FGA.** Every `EventShotAttempt` now carries a
   `ShotOrigin` (`initial` / `oreb_continuation` / `transition`,
   `internal/result/result.go`). The calibrate harness decomposes engine FGA
   variance by origin (`decomposeByOrigin`, an exact within-season covariance
   identity) and captures engine-only by-origin FGA/game through
   `validate.Report → SeasonAggregate.FGAOriginDecomp`.

## Orientation is correct (roster coupling)

`TestRealArchive_VolumeFGPCoupling` measures, over 484 archive team-seasons,
real-minutes-weighted `corr(offensive volume composite, FGP) = +0.55` (the dev-DB
snapshot the trace §3.1 used was +0.265). High-volume teams ARE more efficient in
the corpus, confirming the channel pushes the right direction and the trace's
cross-season-stability assumption holds.

## Observed effect, and why the scale is conservative (0.02)

The decomposition terms are `Var(lnPF) = Var(lnFGA) + Var(lnPPS) + 2·Cov(lnFGA,lnPPS)`.
The deficit (ADR-0041) is the covariance SIGN: real `Cov(lnFGA,lnPPS) > 0`, engine
`< 0`. An `offVolumeScale ∈ {0, 0.02, 0.04, 0.055}` sweep at fixed stride/runs
(regular season, gt 2):

| offVolumeScale | engine Var(lnFGA) | engine Cov(lnFGA,lnPPS) | engine Var(lnPF) |
|---|---|---|---|
| 0 (channel off) | 0.00399 | −0.00342 | 0.00070 |
| 0.02 (shipped)  | 0.00437 | −0.00363 | 0.00065 |
| 0.04            | 0.00562 | −0.00407 | 0.00109 |
| 0.055           | 0.00612 | −0.00419 | 0.00140 |

(real targets: Var(lnFGA) 0.00084, Cov +0.00061, Var(lnPF) 0.00366 — this
stride-thinned sample; the committed full-corpus artifact has real Var(lnFGA)
0.00133.)

**Observation, not mechanism:** at every tested scale the channel MONOTONICALLY
widens `Var(lnFGA)` and deepens the (still-negative) `Cov` — it ADDS a dispersion
source rather than REPLACING one, which is what ADR-0042 requires. The offsetting
empty-FGA reduction (which within-possession source — offensive-rebound
continuation, TVR, or foul-draw — carries the miss-driven FGA, so that cutting it
narrows `Var(lnFGA)` while the channel flips the sign) is **not isolated**. The
by-origin `Cov(FGA_o, PPS)` telemetry is dominated by each origin's FGA SIZE
(initial ≈ 68% of FGA), so it does not name the lever; tuning a constant against it
would be porting a guessed mechanism (the ADR-0040 error). This is exactly
ADR-0042's **bounded open item**, terminal for this PR.

**Landing:** `offVolumeScale = 0.02` is the largest scale whose marginal effect on
`Var(lnFGA)`/`Cov` stays within corpus sampling noise vs the scale-0 reference, so
the channel ships **present, directionally faithful, and fully instrumented**
without regressing the corpus. Raising it toward real `Var(lnPF)` is deferred until
the empty-FGA source is isolated (a follow-on, with sim instrumentation now in
place).

## Reproduce

| Claim | Command (from `engine/`) |
|---|---|
| Roster coupling +0.55 | `JSB_ARCHIVE_DIR=…/ibl5/backups go test -tags archive ./internal/calibrate -run VolumeFGPCoupling -v` |
| The scale sweep / Var/Cov readout | `JSB_ARCHIVE_DIR=…/ibl5/backups go test -tags archive ./internal/calibrate -run TestRealArchive_CalibrateEndToEnd -v` (vary `offVolumeScale` in `tempo.go`) |
| Origin tags exhaustive + by-site | `go test ./internal/sim -run TestShotOrigin -v` |
| Channel monotone + coupling sign | `go test ./internal/sim -run 'TestTeamBaseTime|TestVolumeCountChannel' -v` |
| By-origin decomposition identity | `go test ./internal/calibrate -run TestDecomposeByOrigin -v` |

## Source-of-record

- `internal/sim/tempo.go` — `teamBaseTime`, the channel + the calibrated stand-in scales.
- `internal/result/result.go`, `internal/sim/possession.go`, `internal/sim/transition.go` — `ShotOrigin` tagging.
- `internal/calibrate/standings.go` — `decomposeByOrigin`, `FGAOriginDecomp`.
- `internal/validate/{compare,harness}.go` — engine-only by-origin FGA capture.
- `ibl5/docs/decisions/0042-team-scoring-coupling-mechanism.md` — the scoped mechanism this implements.
- `engine/docs/team-scoring-coupling-trace.md` — the RE trace that located the missing channel.
