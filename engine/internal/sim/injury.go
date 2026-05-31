package sim

import (
	"math"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
)

// injuryProbability is the per-turnover injury chance. JSB fires an injury on
// every type-5 turnover (a rare turnover subtype); the native engine collapses
// that to a single calibrated probability gate per turnover. 0.007 reproduces
// the historical .trn corpus rate of ~0.2 injuries/game across both teams
// (~213–247 injuries over a ~1150-game season). Disasm-recovered and corpus-
// validated ground truth (memory reference_jsb_injury_formula); PR9's offline
// harness may retune this single constant.
const injuryProbability = 0.007

// injurySeverityCap is the faithful-but-dead upper severity clamp. The formula's
// real maximum is ~78 (the ×81 band at E=5, u≈0.4299), so 160 is never reached
// from a real draw; it is ported for fidelity and as a defensive bound, mirroring
// the documented-dead fatigueFactor / dc_bh patterns elsewhere in the engine.
const injurySeverityCap = 160

// energyDurationScale is _DAT_0066d358 = 2.25 exact: games-missed ≈ severity ×
// this, minus sub-game jitter.
const energyDurationScale = 2.25

// clampInt confines v to [lo, hi].
func clampInt(v, lo, hi int) int {
	if v < lo {
		return lo
	}
	if v > hi {
		return hi
	}
	return v
}

// clampFloat confines v to [lo, hi].
func clampFloat(v, lo, hi float64) float64 {
	if v < lo {
		return lo
	}
	if v > hi {
		return hi
	}
	return v
}

// energyParam is the JSB per-player injury energy parameter (+0xDF8), clamped to
// [2, 5]: (48 − min(dc_minutes, 28)) × 0.03 × ((skill+1) + (talent+1)) × 0.125 +
// 1.0. High-minute, low-skill/talent players floor at 2; low-minute, high-rated
// players cap at 5. (This same value doubles as the play-outcome turnover
// def-value in outcome.go — see memory reference_jsb_injury_formula.)
func energyParam(p bundle.Player) float64 {
	minutes := math.Min(float64(p.DCMinutes), 28)
	raw := (48-minutes)*0.03*float64((p.Skill+1)+(p.Talent+1))*0.125 + 1.0
	return clampFloat(raw, 2, 5)
}

// severityBandMultiplier returns the JSB severity band multiplier for a draw u.
// The band edges (_DAT_0066d380/378/370/368 = 0.222/0.148/0.049/0.011) accumulate
// to 0.222 / 0.370 / 0.419 / 0.430; the rarest, highest band (×81) sits in the
// narrow 0.419–0.430 slice, producing the corpus's ~1% career-altering injuries.
func severityBandMultiplier(u float64) int {
	switch {
	case u < 0.222:
		return 3
	case u < 0.370:
		return 9
	case u < 0.419:
		return 27
	case u < 0.430:
		return 81
	default:
		return 1
	}
}

// severityFromU derives an injury severity in [1, injurySeverityCap] from a draw
// u and the player's energy param E: floor(u × sqrt(E) × bandMult) + 1. The upper
// clamp is unreachable from real draws (max ≈ 78); see injurySeverityCap.
func severityFromU(u, E float64) int {
	raw := u * math.Sqrt(E)
	sev := int(math.Floor(raw*float64(severityBandMultiplier(u)))) + 1
	return clampInt(sev, 1, injurySeverityCap)
}

// gamesMissedFromU derives the games-missed duration from a severity and a second
// draw u2: max(floor(severity × 2.25 − u2), 1). Near-deterministic given severity
// (±1 jitter); always ≥ 1 for any severity ≥ 1.
func gamesMissedFromU(severity int, u2 float64) int {
	gm := int(math.Floor(float64(severity)*energyDurationScale - u2))
	if gm < 1 {
		return 1
	}
	return gm
}

// maybeInjure runs the per-turnover injury check for the turnover-committing
// ball-handler. It is the single shared entry point called identically from both
// turnover branches (possession.go, transition.go), so the two paths cannot
// diverge. It does its RNG draws in FIXED ORDER inside the turnover branch (where
// gs.rng is already in scope): exactly 1 draw when no injury, exactly 3 when one
// fires. checkSubstitutions never draws, so the marked-injured player's forced
// removal stays deterministic.
func (gs *gameState) maybeInjure(team *teamState, bh onCourt) {
	if gs.rng.Float64() >= injuryProbability {
		return // no injury: exactly 1 draw consumed
	}
	E := energyParam(bh.Player)
	sev := severityFromU(gs.rng.Float64(), E)
	gm := gamesMissedFromU(sev, gs.rng.Float64())
	team.injured[bh.PID] = true
	gs.emit(result.Event{
		Kind: result.EventInjury, Period: gs.period, Clock: gs.clock,
		TeamID: team.teamID, PlayerID: bh.PID, Severity: sev, GamesMissed: gm,
	})
}
