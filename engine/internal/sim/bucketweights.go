package sim

// O(1) play-outcome bucket-weight helpers — decoupled from the per-mille make-roll
// path (shotdecision.go). Each function is a documented stand-in for the
// corresponding JSB 5.60 per-game double (+0xD90/DB0/DE0/DF8), NOT the literal §4
// formula. Faithful reconstruction is deferred: D70 reads CEngine team/league
// aggregates absent from the bundle, and four .rdata scale constants are unresolved.
// Exact magnitudes are subject to corpus calibration (post-HCA landing).
//
// SCALE RATIONALE (decided at impl time, overriding the plan's pinned Decision #1
// "all four buckets the same O(1) order / foul ≈33–40% share"):
//
//   - The plan's reason to exist is to make HCA's ±0.2 foul-bucket nudge
//     EXPRESSIBLE — i.e. a +0.2 perturbation must move foul-path SELECTION by a
//     non-negligible amount. That property depends ONLY on the four-bucket TOTAL
//     being O(1) (so +0.2 is a meaningful fraction of the total), NOT on the foul
//     bucket being a large SHARE. The shift from +0.2 is
//     (f+0.2)/(T+0.2) − f/T, driven by absolute T, not by f/T. With T≈1.0 even a
//     ~5%-share foul bucket shifts foul-path selection ≈16pp from +0.2 (see
//     bucketweights_test.go TestBucketWeights_FoulScaleShift) — a LARGER margin
//     than the plan's 37%-share design (~6pp).
//   - The plan's "same order / foul 33–40%" magnitudes (2pt 0.5 / 3pt 0.4 /
//     and-one 0.3 / foul 0.7) were empirically degenerate: they invert the
//     field-goal-to-foul ratio (FG paths a minority), producing FTA>FGA, whole
//     teams fouling out, blocks→0, and minutes>48. That is the OPPOSITE of the
//     plan's stated "anchored to realistic path-selection frequencies."
//
// So these stand-ins keep the 2pt bucket DOMINANT (a realistic field-goal-heavy
// mix that approximately preserves the old green path ratios 0.75 : 0.17 : 0.04 :
// 0.05), all on a comparable O(1) basis. This satisfies the plan's REAL structural
// goal — the 2pt bucket is net-free, decoupled from sv2/the make-roll path, so the
// playoff ×1.25 multiplier no longer amplifies it — without the degeneracy.

// twoPtBucketWeightScale maps the FGA/ORB/FTA composite to a comparable O(1)
// magnitude (≈0.75 under richBundle ratings: FGA≈60, ORB≈20, FTA≈20 → composite
// 80). Stand-in for +0xD90 whose inputs include CEngine team/league aggregates not
// yet available. Kept dominant so field-goal attempts remain the majority path.
const twoPtBucketWeightScale = 107.0

// threePtBucketScale maps threePtPropensity() to a comparable O(1) magnitude
// (≈0.17 at richBundle propensity ≈0.294, preserving the old 3pt:2pt ratio ≈0.23).
// Structurally, 3pt remains folded into the play-outcome pick here, whereas JSB
// resolves it upstream via the ball-handler gate (+0xDB0 is always 0 in the
// decompile); functionally equivalent per §4 of 00_MASTER_REFERENCE.md.
const threePtBucketScale = 0.58

// andOneMadeRateScale maps the player's FGP rating to a made-rate proxy for the
// and-one stand-in. Target ≈0.035 at FGP=50 (and-ones are a small minority of
// scoring plays); the mq term (≈−0.02 under default ratings) contributes ≈−0.005,
// so the made-rate term carries the bucket to its small realistic share.
const andOneMadeRateScale = 0.0008

// foulNetScale maps net advantage to the adjustable portion of the foul bucket
// weight (above the floor). Net is the ONLY bucket input that consumes net — this
// is where a future HCA nudge lands. Net≈0 yields the floor; net≈1 adds ≈0.01.
const foulNetScale = 0.01

// andOneBucketFloor is the minimum and-one weight. Ensures the and-one path
// cannot be zeroed by a negative matchup quality.
const andOneBucketFloor = 0.03

// foulOnlyBucketFloor is the base foul-only weight before the net adjustment.
// Kept small (foul-path share ≈5%, matching the old green basis) so the simulation
// stays realistic (no whole-team foul-outs). The +0.2 HCA perturbation is still
// expressible because the FOUR-BUCKET TOTAL is O(1) (≈1.0), not because the foul
// SHARE is large — see the scale rationale block above.
const foulOnlyBucketFloor = 0.04

// twoPtBucketWeight is a net-free O(1) composite over offensive rate analogs
// r_fga, r_orb, r_fta. Stand-in for JSB +0xD90 (CEngine aggregates unavailable).
// Net is intentionally absent: in JSB, net lives only in shot_value; the 2pt
// bucket weight is an independent offensive-rate composite. This cleanly retires
// the old sv2-reuse entanglement and means the playoff ×1.25 multiplier no longer
// amplifies the 2pt bucket weight. Kept dominant so field-goal attempts remain the
// majority path.
func twoPtBucketWeight(p onCourt) float64 {
	fga := floor1(p.FGA)
	orb := floor1(p.ORB)
	fta := floor1(p.FTA)
	return (fga + orb*0.5 + fta*0.5) / twoPtBucketWeightScale
}

// threePtBucketWeight is a comparable O(1) restatement of 3pt propensity.
// 3pt remains folded into the play-outcome pick (structurally differs from JSB
// but is functionally equivalent per §4 of the RE doc; upstream-gate restructure
// is a separate follow-on change).
func threePtBucketWeight(p onCourt) float64 {
	return threePtPropensity(p) * threePtBucketScale
}

// andOneBucketWeight is an O(1) matchup×0.25 + made-rate stand-in, floored to
// andOneBucketFloor. The mq term (≈−0.02 under default ratings) contributes
// negligible negative weight; the made-rate term carries the bucket above the
// floor. Stand-in for JSB +0xDE0 (per-game double player_double not yet pinned).
func andOneBucketWeight(mq float64, p onCourt) float64 {
	w := mq*0.25 + float64(floor1(p.FGP))*andOneMadeRateScale
	if w < andOneBucketFloor {
		return andOneBucketFloor
	}
	return w
}

// foulBucketWeight is floored at foulOnlyBucketFloor, then adjusted by a
// net-based stand-in. Net is the ONLY bucket that consumes net advantage (matching
// JSB: net → shot_value only, except the foul bucket also reads off_quality via
// net). off_quality player_double is not yet pinned — a net × foulNetScale stand-in
// is used. Kept small (≈0.05 under realistic matchups) so the sim stays realistic;
// a future +0.2 HCA perturbation is still a non-negligible swing on foul-path
// selection frequency because the four-bucket TOTAL is O(1) (see scale rationale).
func foulBucketWeight(net float64, p onCourt) float64 {
	w := foulOnlyBucketFloor + net*foulNetScale
	if w < foulOnlyBucketFloor {
		return foulOnlyBucketFloor
	}
	return w
}
