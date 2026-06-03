package sim

import "fmt"

// Freeze-override infrastructure for the empty-FGA source-isolation diagnostic
// (ADR-0043). The team-scoring coupling defect (ADR-0042) is a wrong-signed
// Cov(lnFGA,lnPPS); the channel built in #974 could not flip it. To NAME which
// within-possession mechanism carries the empty/miss-driven FGA, a counterfactual
// freeze lattice substitutes a league-mean scalar at one mechanism's derived-rate
// output point — removing that mechanism's cross-team variance — and measures how
// the covariance responds. Four mechanisms (arms) are freezable: the offensive-
// rebound continuation probability, the turnover threshold, the foul-only bucket
// weight, and the 2pt make-value. Injection is at the derived-VALUE output (not the
// rating inputs), so freezing one arm cannot spill into a sibling mechanism (e.g.
// clamping rebound ratings would also alter defensive rebounding).
//
// The freeze-lattice attribution harness lives in internal/calibrate; this file is
// only the sim-side override + the baseline accumulators it reads.

// FreezeConfig selects which mechanism arms are frozen and supplies the league-mean
// scalar substituted at each frozen arm's derived-rate output point. The zero value
// (all arms false) is the no-freeze baseline — behaviorally identical to the live
// engine.
type FreezeConfig struct {
	ORB  bool // freeze P(offensive rebound)        — orebProbability output
	TVR  bool // freeze the turnover LINEAR threshold — turnoverThreshold output
	Foul bool // freeze the foul-only bucket weight   — foulBucketWeight output
	Make bool // freeze the 2pt make-value            — shotValue2pt output (pre-clutch)

	Means FreezeMeans // per-season-bucket league means substituted for frozen arms
}

// FreezeMeans are the per-mechanism per-season-bucket league-mean derived values
// (harvested by a no-freeze baseline pass via FreezeAccum). Each is the mean of the
// ACTUAL derived quantity at its call site, never an event-rate proxy:
//   - OrebProb:   mean orebProbability output ∈ [0.25, 0.75]
//   - TurnThresh: mean turnoverThreshold(TVR) LINEAR threshold (before squaring)
//   - FoulWeight: mean foulBucketWeight output
//   - MakeVal2pt: mean PRE-clutch shotValue2pt output (per-mille)
//
// The 3pt make-value is a team-invariant constant (shotValue3pt = leagueBaseline×1.5),
// so it carries no cross-team variance and the Make arm freezes only the 2pt channel.
type FreezeMeans struct {
	OrebProb   float64
	TurnThresh float64
	FoulWeight float64
	MakeVal2pt float64
}

// FreezeAccum accumulates Σ + count of each derived quantity during a no-freeze
// baseline pass. The caller owns it (passed in via Options.Accum), shares ONE
// instance across every game in a season bucket, and reads Means() after the pass.
// It is NOT concurrency-safe: the baseline harvest must run single-threaded (the
// calibrate harness simulates a bucket's games sequentially).
type FreezeAccum struct {
	orebSum float64
	orebN   int
	turnSum float64
	turnN   int
	foulSum float64
	foulN   int
	makeSum float64
	makeN   int
}

// Means folds the accumulated sums into per-mechanism league means. A mechanism
// with zero samples yields a zero mean for that field (an arm unreached in the
// bucket); validate rejects freezing an arm whose mean is zero.
func (a *FreezeAccum) Means() FreezeMeans {
	var m FreezeMeans
	if a.orebN > 0 {
		m.OrebProb = a.orebSum / float64(a.orebN)
	}
	if a.turnN > 0 {
		m.TurnThresh = a.turnSum / float64(a.turnN)
	}
	if a.foulN > 0 {
		m.FoulWeight = a.foulSum / float64(a.foulN)
	}
	if a.makeN > 0 {
		m.MakeVal2pt = a.makeSum / float64(a.makeN)
	}
	return m
}

// Options configures a SimulateWith run. The zero value is the live engine: no
// frozen arms, no accumulation — identical to Simulate.
type Options struct {
	Freeze FreezeConfig
	Accum  *FreezeAccum // non-nil only during a baseline accumulation pass
}

// validate rejects a config that freezes an arm with no precomputed (zero) mean — a
// misconfiguration that would otherwise silently substitute 0, which is degenerate
// for every arm (orebProb 0 disables offensive rebounds; makeVal 0 makes every shot
// miss; foulWeight 0 removes the foul path; turnThresh 0 removes turnovers). Every
// real derived value is strictly positive, so a zero frozen mean can only mean
// "unset".
func (o Options) validate() error {
	if o.Freeze.ORB && o.Freeze.Means.OrebProb == 0 {
		return fmt.Errorf("sim: freeze ORB requested but Means.OrebProb is unset (0)")
	}
	if o.Freeze.TVR && o.Freeze.Means.TurnThresh == 0 {
		return fmt.Errorf("sim: freeze TVR requested but Means.TurnThresh is unset (0)")
	}
	if o.Freeze.Foul && o.Freeze.Means.FoulWeight == 0 {
		return fmt.Errorf("sim: freeze Foul requested but Means.FoulWeight is unset (0)")
	}
	if o.Freeze.Make && o.Freeze.Means.MakeVal2pt == 0 {
		return fmt.Errorf("sim: freeze Make requested but Means.MakeVal2pt is unset (0)")
	}
	return nil
}

// The four arm wrappers below are the SOLE injection points. Each computes the live
// derived value (so a baseline pass accumulates the real distribution), then returns
// the frozen league mean when its arm is frozen. Each wrapper responds ONLY to its
// own arm's flag — freezing one arm never alters another's returned value (the
// no-cross-confound property the attribution depends on).

// orebProb returns P(offensive rebound). Shared by the half-court and transition
// rebound paths (gs.rebound), so one injection covers both.
func (gs *gameState) orebProb(off, def float64) float64 {
	p := orebProbability(off, def)
	if gs.accum != nil {
		gs.accum.orebSum += p
		gs.accum.orebN++
	}
	if gs.freeze.ORB {
		return gs.freeze.Means.OrebProb
	}
	return p
}

// turnThreshLinear returns the LINEAR turnover threshold for a TVR rating (the value
// the outcome selector squares, then sqrt-recovers). Frozen → the league-mean linear
// threshold, making the per-possession turnover probability league-uniform. The
// caller squares the return exactly as before so selectOutcome's sqrt is unchanged.
func (gs *gameState) turnThreshLinear(tvr int) float64 {
	t := turnoverThreshold(tvr)
	if gs.accum != nil {
		gs.accum.turnSum += t
		gs.accum.turnN++
	}
	if gs.freeze.TVR {
		return gs.freeze.Means.TurnThresh
	}
	return t
}

// foulWeight returns the foul-only bucket weight; frozen → the league-mean foul
// weight. (Freezing the post-HCA output also removes HCA's small foul-bucket effect,
// which is negligible against cross-team scoring dispersion — the target here.)
func (gs *gameState) foulWeight(offense, defenders []onCourt, hca float64) float64 {
	w := foulBucketWeight(offense, defenders, hca)
	if gs.accum != nil {
		gs.accum.foulSum += w
		gs.accum.foulN++
	}
	if gs.freeze.Foul {
		return gs.freeze.Means.FoulWeight
	}
	return w
}

// makeValue2pt returns the PRE-clutch 2pt make-value; frozen → the league-mean make-
// value (clutch is still applied by the caller). Injecting here, at the shot-value
// assembly, leaves rollMake — and therefore the free-throw make roll that shares it
// (freethrow.go) — structurally untouched.
func (gs *gameState) makeValue2pt(net float64, fgp int) float64 {
	v := shotValue2pt(net, fgp, false)
	if gs.accum != nil {
		gs.accum.makeSum += v
		gs.accum.makeN++
	}
	if gs.freeze.Make {
		return gs.freeze.Means.MakeVal2pt
	}
	return v
}
