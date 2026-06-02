package sim

import (
	"math"

	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// outcomeCode is the shot-PATH the play-outcome selector returns (the two-stage
// model: this picks the path; shot_decision later rolls make/miss inside the
// 2pt/3pt paths). It is NOT a make/miss result.
type outcomeCode int

const (
	outcome2pt      outcomeCode = 1 // 2-point attempt (make/miss rolled after)
	outcome3pt      outcomeCode = 2 // 3-point attempt (make/miss rolled after)
	outcomeAndOne   outcomeCode = 3 // made 2pt + 1 FT
	outcomeFoulOnly outcomeCode = 4 // FTs, no field-goal attempt
	outcomeTurnover outcomeCode = 5 // change of possession (no stealer in PR3a)
)

const turnoverDenom = 1793.0 // rand_int(1,1793) ≤ sqrt(def_value) → turnover

// outcomeInputs holds the four play-outcome bucket weights plus the turnover
// defensive value. In JSB these are copied ball-handler per-game doubles
// (+0xD90 / +0xDB0 / +0xDE0 / +0xDF8) populated by per-half setup. The engine has
// no per-game doubles, so each is a documented rating-derived stand-in on a
// comparable O(1) basis (assembled in possession.go via bucketweights.go):
// 2pt ≈0.75 net-free FGA/ORB/FTA composite (dominant — field goals stay the
// majority path), 3pt ≈0.17 folded 3pt-propensity, and-one ≈0.035 matchup_quality
// ×0.25 + made-rate (floored 0.03), foul ≈0.05 floor + net term (the only bucket
// consuming net — where a future HCA nudge lands). The four-bucket total is O(1),
// which is what makes a ±0.2 HCA perturbation expressible (see bucketweights.go).
// The turnover value derives from ball-handler ball-security (unchanged).
type outcomeInputs struct {
	twoPtWeight      float64
	threePtWeight    float64
	andOneWeight     float64
	foulOnlyWeight   float64
	turnoverDefValue float64
}

func (in outcomeInputs) weight(c outcomeCode) float64 {
	var w float64
	switch c {
	case outcome2pt:
		w = in.twoPtWeight
	case outcome3pt:
		w = in.threePtWeight
	case outcomeAndOne:
		w = in.andOneWeight
	case outcomeFoulOnly:
		w = in.foulOnlyWeight
	}
	if w < 0 {
		return 0
	}
	return w
}

// allowedPaths returns the candidate path codes for the given forced mode.
// PR3a implements the decompile's reject-retry loop by restricting the bucket
// set up front — an equivalent, guaranteed-terminating formulation. Per spec:
// forced_make forces {2pt-attempt(1), and-one(3)} (a high-percentage look);
// shot_clock forces {3pt-attempt(2), foul-only(4)} (a heave or a clock-expiry
// foul); a steal/transition play cannot be a 3pt attempt(2).
func allowedPaths(forcedMake, shotClock, stealPlay bool) []outcomeCode {
	switch {
	case forcedMake:
		return []outcomeCode{outcome2pt, outcomeAndOne}
	case shotClock:
		return []outcomeCode{outcome3pt, outcomeFoulOnly}
	case stealPlay:
		return []outcomeCode{outcome2pt, outcomeAndOne, outcomeFoulOnly}
	default:
		return []outcomeCode{outcome2pt, outcome3pt, outcomeAndOne, outcomeFoulOnly}
	}
}

// selectOutcome picks the shot path by weighted random over the allowed buckets,
// then applies the independent turnover override (suppressed under forced_make).
func selectOutcome(in outcomeInputs, forcedMake, shotClock, stealPlay bool, r *rng.RNG) outcomeCode {
	allowed := allowedPaths(forcedMake, shotClock, stealPlay)

	var total float64
	for _, c := range allowed {
		total += in.weight(c)
	}
	path := allowed[0]
	if total > 0 {
		roll := r.Float64() * total
		var acc float64
		for _, c := range allowed {
			acc += in.weight(c)
			if roll <= acc {
				path = c
				break
			}
		}
	}

	// Independent turnover check (overrides the path roll). forced_make never
	// turns over. PR3a credits no stealer — the possession just flips.
	if !forcedMake {
		tRoll := r.IntN(int(turnoverDenom)) + 1 // 1..1793
		if float64(tRoll) <= math.Sqrt(in.turnoverDefValue) {
			return outcomeTurnover
		}
	}
	return path
}
