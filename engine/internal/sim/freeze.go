package sim

import (
	"fmt"
	"sort"

	"github.com/a-jay85/IBL5/engine/internal/result"
)

// Freeze-override infrastructure for the empty-FGA source-isolation diagnostic
// (ADR-0043). The team-scoring coupling defect (ADR-0042) is a wrong-signed
// Cov(lnFGA,lnPPS); the channel built in #974 could not flip it. To NAME which
// within-possession mechanism carries the empty/miss-driven FGA, a counterfactual
// freeze lattice substitutes a league-mean scalar at one mechanism's derived-rate
// output point — removing that mechanism's cross-team variance — and measures how
// the covariance responds. Four mechanisms (arms) are freezable: the offensive-
// rebound continuation probability, the steal-driven turnover probability, the
// foul-only bucket weight, and the 2pt make-value. Injection is at the
// derived-VALUE output (not the
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
	ORB  bool // freeze P(offensive rebound)             — orebProbability output
	TVR  bool // freeze the steal-driven turnover prob.  — turnoverProb output
	Foul bool // freeze the foul-only bucket weight      — foulBucketWeight output
	Make bool // freeze the 2pt make-value               — shotValue2pt output (pre-clutch)

	// MakePutback / MakePutbackHalf are the ADR-0053 shots-per-possession decoupling
	// A/B arms. Unlike the four freeze arms above they are ORIGIN-SCOPED: they route
	// only OriginOffReb (putback) 2pt make-value to the league mean, leaving the
	// initial and transition attempts on the live value. This removes the team-quality
	// variance feeding the putback efficiency↔volume coupling (the surviving suspect in
	// the engine's wrong-signed Cov(ln(FGA/POSS),lnPPS)). Both consume
	// FreezeMeans.MakeVal2pt:
	//   - MakePutback:     putback 2pt make-value → full league mean.
	//   - MakePutbackHalf: putback 2pt make-value → halfway blend (live + mean)/2 (the
	//     hedge if the full substitution over-narrows Var(lnPPS) below real).
	// Off by default → a zero Options stays byte-identical to Simulate.
	MakePutback     bool
	MakePutbackHalf bool

	// BranchB enables the JSB +0xD90-stage Branch-B usage-shrink (bucketweights.go /
	// branchBShrink). It is NOT a FreezeMeans arm (it is a derived-value transform, not
	// a league-mean substitution): it consumes no Means and validate() ignores it. The
	// zero value (false) is the live engine — Branch-A cold-start only — so a zero
	// Options stays byte-identical to Simulate. Enabled only by the Phase-6/7 A/B
	// measurement; never combined with the four freeze arms above (separate diagnostics).
	BranchB bool

	// UnfaithfulPutback is an INVERTED-POLARITY escape hatch — the ONLY FreezeConfig
	// flag whose zero value is NOT "live engine." Default false = the FAITHFUL JSB
	// 5.60 putback resolution (ADR-0055): OriginOffReb 2pt make-value uses the net-free
	// boosted putbackValue2pt form, and putback 3pt is suppressed. Set true ONLY by the
	// ADR-0055 archive A/B's OFF walk to RESTORE master's old net-coupled,
	// 3pt-reachable putback behavior as the diagnostic baseline. It consumes no Means
	// (validate() ignores it, mirroring BranchB). Production NEVER sets it.
	UnfaithfulPutback bool

	// UnfaithfulOreb is an INVERTED-POLARITY escape hatch — zero value is NOT "live
	// engine." Default false = the FAITHFUL JSB 5.60 offensive-rebound continuation
	// (ADR-0058): gs.orebProb resolves the single determination roll via the sqrt
	// gate-1 team-pick (gate1Probability, port of FUN_004e22a0) against the league
	// rebound baseline. Set true ONLY by the ADR-0058 archive A/B's OFF walk to RESTORE
	// the old linear gate-2 orebProbability path as the diagnostic baseline. It consumes
	// no Means (validate() ignores it, mirroring UnfaithfulPutback/BranchB). Production
	// NEVER sets it.
	UnfaithfulOreb bool

	Means FreezeMeans // per-season-bucket league means substituted for frozen arms
}

// FreezeMeans are the per-mechanism per-season-bucket league-mean derived values
// (harvested by a no-freeze baseline pass via FreezeAccum). Each is the mean of the
// ACTUAL derived quantity at its call site, never an event-rate proxy:
//   - OrebProb:   mean live ORB-continuation P (faithful sqrt gate1Probability by
//     default; linear orebProbability under the UnfaithfulOreb hatch)
//   - TurnProb:   mean per-possession steal-driven turnoverProb output ∈ [0, maxTurnoverProb]
//   - FoulWeight: mean foulBucketWeight output
//   - MakeVal2pt: mean PRE-clutch shotValue2pt output (per-mille)
//
// The 3pt make-value is team-invariant within a snapshot (shotValue3pt =
// gs.shotBaseline×1.5, league-constant per bundle), so it carries no cross-team
// variance and the Make arm freezes only the 2pt channel.
type FreezeMeans struct {
	OrebProb   float64
	TurnProb   float64
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
		m.TurnProb = a.turnSum / float64(a.turnN)
	}
	if a.foulN > 0 {
		m.FoulWeight = a.foulSum / float64(a.foulN)
	}
	if a.makeN > 0 {
		m.MakeVal2pt = a.makeSum / float64(a.makeN)
	}
	return m
}

// BranchBAccum harvests the Branch-B engagement instrument across a run (branchBShrink):
// Taken counts possessions where the usage-shrink engaged; Fallback counts Branch-A
// cold-starts (2pt<=0 OR usage<=0). SumS/MinS/MaxS summarize the shrink factor s over the
// Taken set (MeanS = SumS/Taken). The caller owns it (passed via Options.BranchBAccum),
// shares ONE instance across a run's games, and reads it after. It distinguishes a real
// null (Taken≈N, s materially <1) from a never-engaged no-op (all Fallback / s≈1 — a
// unit or ΣD mis-pin). NOT concurrency-safe: the A/B harness sims sequentially.
type BranchBAccum struct {
	Taken    int     `json:"taken"`
	Fallback int     `json:"fallback"`
	SumS     float64 `json:"sum_s"`
	MinS     float64 `json:"min_s"`
	MaxS     float64 `json:"max_s"`
}

// MeanS is Σs / Taken, or 0 when Branch-B never engaged.
func (a *BranchBAccum) MeanS() float64 {
	if a.Taken == 0 {
		return 0
	}
	return a.SumS / float64(a.Taken)
}

// GateContAccum harvests the L1 gate-1 decomposition instrument (ADR-0057/0058),
// read-only. At every offensive-rebound RESOLUTION (gs.rebound, before the continuation
// outcome roll), it records — keyed by the OFFENSIVE team ID — the gate-1 sqrt team-pick
// probability (gate1Probability, the live continuation roll since ADR-0058), the linear
// gate-2 probability (orebProbability, the old pre-ADR-0058 path), their product (the
// faithful two-gate continuation probability), and the off/def rebound strengths that
// feed the curvature-coupling read. It issues NO rng draw and is never serialized, so
// attaching it stays byte-identical to a plain run. The caller (validate harness) owns one instance PER
// GAME, pooled across that game's runs, and reads the per-team sums after. NOT
// concurrency-safe: the harness sims a game's runs sequentially.
type GateContAccum struct {
	perTeam map[int]*gateTeamAcc
}

// gateTeamAcc is one offensive team's accumulated gate samples (Σ + count) over a
// game's offensive-rebound resolutions. Means are sum/n; n is the resolution count.
type gateTeamAcc struct {
	n         int     // offensive-rebound resolutions observed
	sumG1     float64 // Σ gate-1 P(offense wins board) (live continuation roll since ADR-0058)
	sumG2     float64 // Σ linear gate-2 P (orebProbability, the old pre-ADR-0058 path)
	sumProd   float64 // Σ gate-1 × gate-2 (faithful two-gate continuation P)
	sumOffStr float64 // Σ offensive rebound strength (curvature-coupling input)
	sumDefStr float64 // Σ defensive rebound strength
}

// NewGateContAccum allocates an empty gate accumulator ready to harvest a game.
func NewGateContAccum() *GateContAccum {
	return &GateContAccum{perTeam: map[int]*gateTeamAcc{}}
}

// TeamIDs returns the offensive team IDs with at least one sample, ascending, for
// deterministic iteration by the harness.
func (a *GateContAccum) TeamIDs() []int {
	ids := make([]int, 0, len(a.perTeam))
	for id := range a.perTeam {
		ids = append(ids, id)
	}
	sort.Ints(ids)
	return ids
}

// Team returns the accumulated resolution count and sums for offensive team id (all
// zero when id was never seen). The harness divides the sums by n for per-trip means
// and n by the run count for the per-game resolution rate.
func (a *GateContAccum) Team(id int) (n int, sumG1, sumG2, sumProd, sumOffStr, sumDefStr float64) {
	t := a.perTeam[id]
	if t == nil {
		return 0, 0, 0, 0, 0, 0
	}
	return t.n, t.sumG1, t.sumG2, t.sumProd, t.sumOffStr, t.sumDefStr
}

// Options configures a SimulateWith run. The zero value is the live engine: no
// frozen arms, no accumulation — identical to Simulate.
type Options struct {
	Freeze       FreezeConfig
	Accum        *FreezeAccum  // non-nil only during a baseline accumulation pass
	BranchBAccum *BranchBAccum // non-nil only when harvesting the Branch-B engagement instrument

	// OffVolumeScale overrides the package const offVolumeScale (tempo.go) for the
	// ADR-0054 possession-count dispersion sweep. nil → use the const (a zero Options
	// stays byte-identical to Simulate); non-nil → use *OffVolumeScale (0 is a valid
	// sweep value — it disables the volume→count channel, so a plain float default-0
	// could not mean "unset"; the pointer distinguishes the two). The override is
	// always a valid float, so validate() needs no zero-mean guard for it (unlike the
	// freeze arms). The only knob this seam moves.
	OffVolumeScale *float64

	// GateCont, when non-nil, harvests the L1 gate-1 decomposition instrument
	// (ADR-0057/0058) across the run: at every offensive-rebound resolution it records
	// the gate-1 P (live since ADR-0058), the linear gate-2 P, and their product, keyed
	// by offensive team. Read-only (no rng draw), never serialized — attaching it stays
	// byte-identical to a plain run. The validate harness owns one per game.
	GateCont *GateContAccum
	// GateBaseline overrides the gate-1 baseline term (the league offensive-rebound
	// share × 100). nil → leagueReboundBaseline(bundle), the faithful bundle-derived
	// value; non-nil → *GateBaseline, used by the archive instrument's baseline
	// sensitivity sweep (the exact loader value is unpinned in the static decompile).
	// Read on EVERY run (ADR-0058: the live faithful ORB roll consumes gs.gateBaseline),
	// so gameloop.go populates it unconditionally.
	GateBaseline *float64
}

// validate rejects a config that freezes an arm with no precomputed (zero) mean — a
// misconfiguration that would otherwise silently substitute 0, which is degenerate
// for every arm (orebProb 0 disables offensive rebounds; makeVal 0 makes every shot
// miss; foulWeight 0 removes the foul path; turnProb 0 removes steal-driven
// turnovers). Every real derived value is strictly positive, so a zero frozen mean
// can only mean "unset".
func (o Options) validate() error {
	if o.Freeze.ORB && o.Freeze.Means.OrebProb == 0 {
		return fmt.Errorf("sim: freeze ORB requested but Means.OrebProb is unset (0)")
	}
	if o.Freeze.TVR && o.Freeze.Means.TurnProb == 0 {
		return fmt.Errorf("sim: freeze TVR requested but Means.TurnProb is unset (0)")
	}
	if o.Freeze.Foul && o.Freeze.Means.FoulWeight == 0 {
		return fmt.Errorf("sim: freeze Foul requested but Means.FoulWeight is unset (0)")
	}
	if o.Freeze.Make && o.Freeze.Means.MakeVal2pt == 0 {
		return fmt.Errorf("sim: freeze Make requested but Means.MakeVal2pt is unset (0)")
	}
	if (o.Freeze.MakePutback || o.Freeze.MakePutbackHalf) && o.Freeze.Means.MakeVal2pt == 0 {
		return fmt.Errorf("sim: freeze MakePutback/MakePutbackHalf requested but Means.MakeVal2pt is unset (0)")
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
	// FAITHFUL by default (ADR-0058): the live continuation roll is the sqrt gate-1
	// team-determination (FUN_004e22a0) against the league rebound baseline, resolved
	// once per game into gs.gateBaseline (gameloop.go). The UnfaithfulOreb escape hatch
	// restores the old linear gate-2 path for the archive A/B's OFF walk only.
	var p float64
	if gs.freeze.UnfaithfulOreb {
		p = orebProbability(off, def)
	} else {
		p = gate1Probability(off, def, gs.gateBaseline)
	}
	if gs.accum != nil {
		gs.accum.orebSum += p
		gs.accum.orebN++
	}
	if gs.freeze.ORB {
		return gs.freeze.Means.OrebProb
	}
	return p
}

// accumulateGateCont records one offensive-rebound RESOLUTION into the L1 gate-1
// decomposition instrument (ADR-0057/0058). It is a no-op unless gs.gateCont is set,
// so it is inert on every normal/golden run. It computes the linear gate-2 probability
// (orebProbability) and the gate-1 sqrt team-pick (gate1Probability, against
// gs.gateBaseline — the live continuation roll since ADR-0058), and their product, and
// folds them into the OFFENSIVE team's accumulator. It issues NO rng draw and mutates no
// game state, so attaching the instrument is byte-identical to a plain run (it only reads
// the same probabilities gs.orebProb already resolves). Called once per rebound resolution
// (the half-court and transition paths share gs.rebound), so it covers every
// offensive-rebound trip exactly once.
func (gs *gameState) accumulateGateCont(offTeamID int, off, def float64) {
	if gs.gateCont == nil {
		return
	}
	t := gs.gateCont.perTeam[offTeamID]
	if t == nil {
		t = &gateTeamAcc{}
		gs.gateCont.perTeam[offTeamID] = t
	}
	g2 := orebProbability(off, def)
	g1 := gate1Probability(off, def, gs.gateBaseline)
	t.n++
	t.sumG1 += g1
	t.sumG2 += g2
	t.sumProd += g1 * g2
	t.sumOffStr += off
	t.sumDefStr += def
}

// turnoverProb returns the per-possession steal-driven turnover probability from
// offensive carelessness × defensive steal pressure (steal.go), scaled by
// stealTurnoverScale and clamped to [0, maxTurnoverProb]. Frozen (TVR arm) → the
// league-mean probability, making the turnover rate league-uniform so the freeze
// lattice can measure how collapsing the STL→steal→fast-break coupling moves
// Cov(lnFGA,lnPPS) (the ADR-0045 Cov re-run). The caller draws its gs.rng.Float64()
// roll unconditionally, so live and frozen passes consume the RNG identically.
func (gs *gameState) turnoverProb(careless, pressure float64) float64 {
	p := stealTurnoverScale * careless * pressure
	if p < 0 {
		p = 0
	}
	if p > maxTurnoverProb {
		p = maxTurnoverProb
	}
	if gs.accum != nil {
		gs.accum.turnSum += p
		gs.accum.turnN++
	}
	if gs.freeze.TVR {
		return gs.freeze.Means.TurnProb
	}
	return p
}

// foulWeight returns the foul-only bucket weight; frozen → the league-mean foul
// weight. It takes the ball handler, offense, and defense lineups plus the per-team
// hca delta (raw ±0.2 half-court, 0 transition/ASG): the foul BASE (leg B) and offQ
// (leg C) carry the RAW HCA — see foulBucketWeight. hca=0 recovers the symmetric path.
// mq is the possession's matchupQuality, the :97164 shrink operand (J18 item 6).
func (gs *gameState) foulWeight(bh onCourt, offense, defenders []onCourt, hca, mq float64) float64 {
	w := foulBucketWeight(bh, offense, defenders, hca, mq, gs.rng)
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
//
// origin selects the substitution scope. The Make arm freezes EVERY 2pt make-value;
// the ADR-0053 MakePutback/MakePutbackHalf arms substitute ONLY when origin is
// OriginOffReb (the putback continuation), so an OriginInitial/OriginTransition shot
// keeps its live value — the origin-scoped decoupling. MakePutbackHalf returns the
// halfway blend (live + mean)/2 instead of the full mean. The accumulator write is
// ALWAYS on the live value v (so a baseline harvest sees the real distribution
// regardless of which arm is on).
func (gs *gameState) makeValue2pt(net float64, fgp int, origin result.ShotOrigin) float64 {
	// Faithful putback make-value (ADR-0055): a half-court putback (OriginOffReb)
	// uses the net-free 4/3-boosted putbackValue2pt form (decompile 93880-93883),
	// computed BEFORE the accum capture so the Make/MakePutback/MakePutbackHalf arms
	// freeze against the NEW baseline. The UnfaithfulPutback escape hatch restores the
	// old net-coupled value for the ADR-0055 OFF walk only. OriginInitial and
	// OriginTransition are unchanged (transition putback faithfulness is OOS — ADR-0055).
	v := shotValue2pt(net, fgp, false, gs.shotBaselineOrFallback())
	if origin == result.OriginOffReb && !gs.freeze.UnfaithfulPutback {
		v = putbackValue2pt(fgp)
	}
	if gs.accum != nil {
		gs.accum.makeSum += v
		gs.accum.makeN++
	}
	if gs.freeze.Make {
		return gs.freeze.Means.MakeVal2pt
	}
	if origin == result.OriginOffReb {
		if gs.freeze.MakePutback {
			return gs.freeze.Means.MakeVal2pt
		}
		if gs.freeze.MakePutbackHalf {
			return (v + gs.freeze.Means.MakeVal2pt) / 2
		}
	}
	return v
}

// branchBShrink applies the JSB 5.60 +0xD90-stage Branch-B usage-shrink to the three
// live PRE-HCA play-outcome bucket composites (2pt/3pt/foul — the engine analogs of
// JSB's D90/DB0/DE0). Called ONLY from the BranchB-on assembly path (possession.go /
// transition.go), so the OFF path is byte-untouched and the golden fixture is stable.
//
// Faithful port of jsb560_decompiled.c:91072-99 (COMPOSITE_DOUBLES_TRACE.md §4):
//
//	usage  = transOff × (drbRate + astRate) × 0.2 × 0.04          // the per-player target
//	ΣD     = raw2pt + raw3pt + rawFoul                            // sum of the live buckets
//	s      = (ΣD − usage) / ΣD                                    // proportional shrink factor
//	each bucket ×= s
//
// In JSB DB0/DE0/D78 are dead (always 0), so the literal 4-bucket shrink reduces to a
// 2pt-only subtraction there; the engine's 3pt/foul analogs ARE live, so the literal
// FORM shrinks all three proportionally (same s, ratios preserved). D78 (turnover) is
// excluded from ΣD: the engine has no commensurate pick-bucket weight for it (turnovers
// route through the independent sqrt check in outcome.go), and JSB's D78 is 0 anyway, so
// excluding it is faithful to the effective ΣD. And-one is excluded (handoff A2).
//
// Branch-A cold-start is the LITERAL decompile gate (:91072-86): when the 2pt composite
// (+0xD90) ≤ 0 OR usage ≤ 0, the buckets are returned unchanged (no shrink). A numerical
// guard on ΣD ≤ 0 prevents divide-by-zero. No artificial clamp on s: an over-shrink
// (usage > ΣD) yields a negative s and negative weights, already clamped to 0 by
// outcomeInputs.weight(). Each call records engagement (taken vs fallback) + the s value
// for the Phase-7 engagement instrument.
func (gs *gameState) branchBShrink(raw2pt, raw3pt, rawFoul, drbRate, astRate float64, transOff int) (s2pt, s3pt, sFoul float64) {
	usage := float64(transOff) * (drbRate + astRate) * branchBTeamScale * branchBPlayerScale
	sigmaD := raw2pt + raw3pt + rawFoul
	if raw2pt <= 0 || usage <= 0 || sigmaD <= 0 {
		if gs.branchB != nil {
			gs.branchB.Fallback++ // Branch-A cold-start: no shrink
		}
		return raw2pt, raw3pt, rawFoul
	}
	s := (sigmaD - usage) / sigmaD
	if a := gs.branchB; a != nil {
		if a.Taken == 0 || s < a.MinS {
			a.MinS = s
		}
		if a.Taken == 0 || s > a.MaxS {
			a.MaxS = s
		}
		a.Taken++
		a.SumS += s
	}
	return raw2pt * s, raw3pt * s, rawFoul * s
}
