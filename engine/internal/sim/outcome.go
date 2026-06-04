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
	outcomeTurnover outcomeCode = 5 // independent unforced change of possession (no stealer)
)

const turnoverDenom = 1793.0 // rand_int(1,1793) ≤ sqrt(energyCeiling) → unforced turnover

// outcomeInputs holds the four play-outcome bucket weights plus the turnover
// defensive value. They are assembled in possession.go / transition.go from the
// faithful bucket-weight helpers (bucketweights.go, teamquality.go): 2pt = the
// recovered +0xD90 Branch-A offensive-rate composite (O(10s), net-free, dominant
// so field goals stay the majority path); 3pt = the 2pt composite × 3pt propensity;
// and-one = matchup×0.25 + made-rate, floored to 0.03; foul = the 0.6 floor
// modulated by the team-quality divisor (foul/offQ)×(defQ − teamDef×5/6) and the
// site-2 HCA nudge. Home-court advantage is applied at the two modeled JSB sites in
// the assembly: site 2 adds +hcaDelta to the 2pt bucket and (inside
// foulBucketWeight) subtracts it from the foul bucket; site 3 shrinks the home
// offQuality divisor, growing the home foul bucket — the dominant home-favorable
// term. turnoverDefValue is the per-player [2,5] energy ceiling (JSB +0xDF8,
// energyCeiling) feeding the negligible INDEPENDENT turnover check — the dominant
// steal-driven turnover is rolled separately (steal.go), before this selector.
// weight() clamps any negative bucket to 0; the selector, allowedPaths, and
// turnover override are HCA-agnostic (the deltas live in the assembly, as in JSB's
// selector).
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
	// turns over. This is the negligible [2,5]-energy unforced flip; it credits no
	// stealer (the dominant steal-driven turnover is handled before this selector).
	if !forcedMake {
		tRoll := r.IntN(int(turnoverDenom)) + 1 // 1..1793
		if float64(tRoll) <= math.Sqrt(in.turnoverDefValue) {
			return outcomeTurnover
		}
	}
	return path
}
