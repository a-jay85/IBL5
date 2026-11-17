package sim

import (
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// outcomeCode is the shot-PATH the play-outcome selector returns (the two-stage
// model: this picks the path; shot_decision later rolls make/miss inside the
// 2pt/3pt paths). It is NOT a make/miss result.
type outcomeCode int

// The live JSB 5.60 path-code map (bucket loop jsb560_decompiled.c:97179-97196,
// live trace ~/claude-plans/_reports/2026-11-16-jsb-tov-trace.md): 1 = 2pt
// attempt, 2 = 3pt attempt, 3 = foul (every return goes to the foul handler at
// 0x4d9ea2), 4 = turnover (10% offensive fouls, the rest split by the steal roll).
// JSB's code 5 is a 0.08% override that makes no turnover; it is not ported.
// And-ones come only from a fouled made shot: the code-1 made-2 roll
// (FoulSiteAndOneMadeTwo) or the code-2 made-3 1% roll (FoulSiteAndOne).
const (
	outcome2pt      outcomeCode = 1 // 2-point attempt (make/miss rolled after)
	outcome3pt      outcomeCode = 2 // 3-point attempt (make/miss rolled after)
	outcomeFoul     outcomeCode = 3 // foul handler: shooting or non-shooting FTs, no field-goal attempt
	outcomeTurnover outcomeCode = 4 // turnover path: offensive foul, steal, or plain turnover
)

// outcomeInputs holds the four play-outcome bucket weights. They are assembled in possession.go / transition.go from the
// faithful bucket-weight helpers (bucketweights.go, teamquality.go): 2pt = the
// recovered +0xD90 Branch-A offensive-rate composite (O(10s), net-free, dominant
// so field goals stay the majority path); 3pt = the 2pt composite × 3pt propensity;
// foul (code 3) = matchup×0.25 + made-rate, floored to 0.03; turnover (code 4) =
// foulBucketWeight, the 0.6 floor modulated by the team-quality divisor (foul/offQ)×(defQ − teamDef×5/6) and the
// site-2 HCA nudge. Home-court advantage is applied at the two modeled JSB sites in
// the assembly: site 2 adds +hcaDelta to the 2pt bucket and (inside
// foulBucketWeight) subtracts it from the foul bucket; site 3 shrinks the home
// offQuality divisor, growing the home foul bucket — the dominant home-favorable
// term. weight() clamps any negative bucket to 0; the selector and allowedPaths
// are HCA-agnostic (the deltas live in the assembly, as in JSB's
// selector).
type outcomeInputs struct {
	twoPtWeight    float64
	threePtWeight  float64
	foulWeight     float64
	turnoverWeight float64
}

func (in outcomeInputs) weight(c outcomeCode) float64 {
	var w float64
	switch c {
	case outcome2pt:
		w = in.twoPtWeight
	case outcome3pt:
		w = in.threePtWeight
	case outcomeFoul:
		w = in.foulWeight
	case outcomeTurnover:
		w = in.turnoverWeight
	}
	if w < 0 {
		return 0
	}
	return w
}

// allowedPaths returns the candidate path codes for the given forced mode.
// PR3a implements the decompile's reject-retry loop by restricting the bucket
// set up front — an equivalent, guaranteed-terminating formulation. Per spec:
// forced_make forces {2pt-attempt(1), foul(3)}; shot_clock forces
// {3pt-attempt(2), turnover(4)}; stealPlay (no longer used by the transition
// path, see J24 adjudication) restricts to {2pt, foul, turnover}.
func allowedPaths(forcedMake, shotClock, stealPlay bool) []outcomeCode {
	switch {
	case forcedMake:
		return []outcomeCode{outcome2pt, outcomeFoul}
	case shotClock:
		return []outcomeCode{outcome3pt, outcomeTurnover}
	case stealPlay:
		return []outcomeCode{outcome2pt, outcomeFoul, outcomeTurnover}
	default:
		return []outcomeCode{outcome2pt, outcome3pt, outcomeFoul, outcomeTurnover}
	}
}

// selectOutcome picks the path by weighted random over the allowed buckets.
// pathObserver, when non-nil, receives every path the picker returns and
// whether the trip is a transition trip. Tests set it; production leaves it nil.
// It must never draw from the RNG.
var pathObserver func(code outcomeCode, transition bool)

// pathEnd is how a picked path ended, as reported to pathEndObserver.
type pathEnd uint8

const (
	pathEndFoul          pathEnd = iota + 1 // code 3 reached the foul handler
	pathEndOffensiveFoul                    // code 4 became an offensive foul
	pathEndSteal                            // code 4 became a steal
	pathEndTurnover                         // code 4 became a plain turnover
	pathEndAndOne                           // an and-one foul was charged under this pick
)

// pathEndObserver, when non-nil, receives the current pick's code and how it
// ended. Tests set it; production leaves it nil. It must never draw from the RNG.
var pathEndObserver func(code outcomeCode, end pathEnd)

// observePathEnd reports a path end to pathEndObserver when a test installed one.
func observePathEnd(code outcomeCode, end pathEnd) {
	if pathEndObserver != nil {
		pathEndObserver(code, end)
	}
}

func selectOutcome(in outcomeInputs, forcedMake, shotClock, stealPlay, suppressW4Rescale bool, r *rng.RNG) outcomeCode {
	allowed := allowedPaths(forcedMake, shotClock, stealPlay)

	// JSB 5.60 @0x4e1e93–0x4e1ebf: in shot-clock mode the code-4 bucket is
	// rescaled by the 3pt/2pt weight ratio BEFORE the weighted sum, making the
	// effective 3pt share w1/(w1+w4) — independent of w2's magnitude.
	// 5.60 gates only on (param_8 == 1 && w2 > 0); the `in.twoPtWeight > 0` term is a
	// DELIBERATE Go divergence: twoPtBucketWeight can legitimately return 0
	// (bucketweights.go, the d88 <= 0 early return), where x87 fdiv yields +Inf and the
	// subsequent roll becomes NaN. See the plan's Architectural trade-offs.
	// `in` is a value copy, so this mutation is local to this call.
	if shotClock && !suppressW4Rescale && in.threePtWeight > 0 && in.twoPtWeight > 0 {
		in.turnoverWeight *= in.threePtWeight / in.twoPtWeight
	}

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

	return path
}
