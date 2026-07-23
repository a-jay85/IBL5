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

	// SuppressPutback3pt is a NORMAL-polarity diagnostic arm — zero value IS the live
	// engine. Default false = the faithful JSB 5.60 behaviour: an OriginOffReb
	// continuation reaches the FULL four-bucket outcome set, 3pt included. Set true to
	// restore the pre-2026-07-22 zeroing of threePtW on OriginOffReb as an A/B baseline.
	// Decoupled from UnfaithfulPutback on 2026-07-22: that hatch still gates the putback
	// 2pt make-value (a separate, still-faithful ADR-0055 mechanism), and conflating the
	// two made the 3PA measurement non-attributable. Proof the zeroing is unfaithful:
	// jsb-native/re-artifacts/jsb-j24-oreb-3pt-eligibility-20260722.md. It consumes no
	// Means (validate() must ignore it, mirroring BranchB). Production NEVER sets it.
	SuppressPutback3pt bool

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

// ThreePtDiagAccum harvests the per-3pt-attempt make-value decomposition across a run:
// at every case-outcome3pt attempt (possession.go) it records the three ADDITIVE
// components of shotValue3pt — the d80 real-life base, the net-advantage term
// net*netToShotValue/(baseline*1.5), and the block-modifier term — all in per-mille (‰).
// The caller owns it (passed via Options.ThreePtDiag), shares ONE instance across every
// game in a run, and reads the means after the pass. It issues NO rng draw and alters no
// decision (pure arithmetic on values shotValue3pt already computes), so attaching it
// stays byte-identical to a plain run. NOT concurrency-safe: the archive harness sims
// sequentially. Count is the exact 3pt-attempt count and equals Σ box-score Game3GA.
type ThreePtDiagAccum struct {
	SumD80       float64 `json:"sum_d80"`        // Σ d80 base (‰) over 3pt attempts
	SumNetTerm   float64 `json:"sum_net_term"`   // Σ net*netToShotValue/(baseline*1.5) (‰)
	SumBlockTerm float64 `json:"sum_block_term"` // Σ blockMod(baseline*1.5,...) (‰)
	Count        int     `json:"count"`          // 3pt attempts observed (== Σ Game3GA)

	// Clamp / dispersion instrument (advisor 2026-07-21). rollMake makes when
	// effective ≥ rand_int(1,1000), with effective = sv×fatigue and fatigue ≡ 1.0 in
	// PR3a (fatigueFactor caps to 1.0 for energy ≥ 0 — state.go), so the realized
	// per-attempt make probability is NOT sv/1000 but clamp(sv/1000) to [0,1]:
	// sv ≥ 1000‰ makes at 100% (not sv/1000 > 1), sv ≤ 0 misses at 100% (not sv/1000 < 0).
	// The additive value model therefore over-counts the upper tail and under-counts the
	// lower tail, and the reconstruction residual (E[sv/1000] − realized 3P%) is EXACTLY
	// the net clamp loss E[(sv/1000−1)⁺] − E[(−sv/1000)⁺]. A null modifier MEAN does not
	// exonerate a modifier: a zero-mean, high-VARIANCE term pushes mass past 1000 and
	// bleeds value to the clamp. These fields split that residual and — among the
	// upper-clamped (sv ≥ 1000) attempts — record the component means that name WHICH feed
	// (d80 vs net) drives the excess. sv = d80+net+block; all sums in ‰.
	CountSvGe1000 int     `json:"count_sv_ge_1000"` // attempts with sv ≥ 1000‰ (upper-clamped, make 100%)
	CountSvLe0    int     `json:"count_sv_le_0"`    // attempts with sv ≤ 0‰ (lower-clamped, miss 100%)
	SumClampLoss  float64 `json:"sum_clamp_loss"`   // Σ max(0, sv−1000) (‰) — value discarded above the ceiling
	SumClampGain  float64 `json:"sum_clamp_gain"`   // Σ max(0, −sv) (‰) — negative value floored at 0
	SumD80GeClamp float64 `json:"sum_d80_ge_clamp"` // Σ d80 over sv ≥ 1000 attempts (‰)
	SumNetGeClamp float64 `json:"sum_net_ge_clamp"` // Σ net-term over sv ≥ 1000 attempts (‰)
	SumBlkGeClamp float64 `json:"sum_blk_ge_clamp"` // Σ block-term over sv ≥ 1000 attempts (‰)

	// Direct make-realization instrument (advisor 2026-07-21, clamp hypothesis REFUTED).
	// The clamp fields above showed ≈0 clamping yet the reconstruction still overshoots
	// realized 3P% by ~9pp — so the drag is neither tail-clamp nor (statically) fatigue.
	// These two record the roll's OWN verdict and fatigue on the EXACT diag population,
	// so E[sv/1000]×100 (recon) vs MadePct() discriminates directly:
	//   MadePct ≈ recon (≈40%)  ⇒ rollMake is faithful; the drag is downstream box
	//                              crediting/aggregation (gm3 undercounts rollMake==true).
	//   MadePct ≈ realized 3P% (≈31%) AND MeanFatigue ≈ 0.77 ⇒ fatigue is NOT 1.0 at
	//                              runtime; the static read is fed something unexpected.
	//   MadePct ≈ 31% AND MeanFatigue = 1.0 ⇒ impossible given sv computed once — a bug.
	MadeCount  int     `json:"made_count"`  // Σ rollMake==true — realized makes on the diag population
	SumFatigue float64 `json:"sum_fatigue"` // Σ fatigueFactor(stamina) at each attempt (proves ≡1.0, or not)
	// SumSvActual is Σ of the ACTUAL shotValue passed into rollMake.
	//
	// SCOPE — read this before treating recon-vs-svActual as evidence. shotValue3pt
	// (shotdecision.go:160) IS `d80 + net*netToShotValue/b + blockMod(b, …)`, and the diag
	// call site recomputes those same three terms from the same b. So recon ≡ svActual
	// *by construction*: the observed gap is float64 associativity noise (~1e-13), and no
	// value of the inputs can make it anything else. This comparison is an identity check
	// on the diag's OWN arithmetic — it catches a transcription slip (wrong baseline, a
	// dropped term, a unit error in this file) and nothing more. It canNOT detect a term
	// missing from the *model*, because the diag mirrors the model rather than deriving
	// sv independently. Do not cite a ~0 gap as evidence that the value model is right.
	//
	// A real independent check needs sv reconstructed from a different source than
	// shotValue3pt (e.g. the realized make rate against E[sv]/1000, which is what actually
	// closed residual (7) — P(make) is linear in sv, so the realized rate is forced to
	// equal E[sv]/1000 and cannot be bent by distribution shape).
	SumSvActual float64 `json:"sum_sv_actual"`
}

// Add records one 3pt attempt's three make-value components (all ‰), the roll's realized
// make verdict, and the fatigue applied. Nil-safe so the call site can guard with
// `if gs.threePtDiag != nil`. It also folds the attempt into the clamp/dispersion tallies
// from the total shot value sv = d80+net+block. Add is called AFTER the roll so `made` and
// `fatigue` are the actual runtime values (it issues no rng draw, so post-roll ordering
// leaves the GameResult byte-identical — the diag-on/off non-perturbation invariant holds).
func (a *ThreePtDiagAccum) Add(d80Base, netTerm, blockTerm float64, made bool, fatigue, svActual float64) {
	a.SumD80 += d80Base
	a.SumNetTerm += netTerm
	a.SumBlockTerm += blockTerm
	a.Count++
	if made {
		a.MadeCount++
	}
	a.SumFatigue += fatigue
	a.SumSvActual += svActual

	sv := d80Base + netTerm + blockTerm
	switch {
	case sv >= 1000:
		a.CountSvGe1000++
		a.SumClampLoss += sv - 1000
		a.SumD80GeClamp += d80Base
		a.SumNetGeClamp += netTerm
		a.SumBlkGeClamp += blockTerm
	case sv <= 0:
		a.CountSvLe0++
		a.SumClampGain += -sv
	}
}

// MeanD80Pp is the per-attempt mean d80 base in pp (‰/10). By construction this is the
// Game3GA-weighted d80 (one add per attempt), so it must reconcile with the archive
// test's sim_weighted_d80_pct on the SAME population — the accum-wiring cross-check.
func (a *ThreePtDiagAccum) MeanD80Pp() float64 {
	if a.Count == 0 {
		return 0
	}
	return a.SumD80 / float64(a.Count) / 10.0
}

// MeanNetTermPp is the per-attempt mean net-advantage term in pp (‰/10). The Phase-6
// branch discriminator: < -5pp ⇒ net-advantage FEED is the prime suspect.
func (a *ThreePtDiagAccum) MeanNetTermPp() float64 {
	if a.Count == 0 {
		return 0
	}
	return a.SumNetTerm / float64(a.Count) / 10.0
}

// MeanBlockTermPp is the per-attempt mean block-modifier term in pp (‰/10).
func (a *ThreePtDiagAccum) MeanBlockTermPp() float64 {
	if a.Count == 0 {
		return 0
	}
	return a.SumBlockTerm / float64(a.Count) / 10.0
}

// MeanClampLossPp is the per-attempt mean value discarded above the make ceiling, in pp:
// E[(sv/1000−1)⁺]×100. MeanClampLossPp − MeanClampGainPp is logged against ReconResidualPp
// in the archive test (t.Logf, not asserted — logged because the MC closure is diagnostic).
func (a *ThreePtDiagAccum) MeanClampLossPp() float64 {
	if a.Count == 0 {
		return 0
	}
	return a.SumClampLoss / float64(a.Count) / 10.0
}

// MeanClampGainPp is the per-attempt mean value floored at 0 below the make floor, in pp:
// E[(−sv/1000)⁺]×100.
func (a *ThreePtDiagAccum) MeanClampGainPp() float64 {
	if a.Count == 0 {
		return 0
	}
	return a.SumClampGain / float64(a.Count) / 10.0
}

// FracSvGe1000 is the fraction of 3pt attempts whose total shot value clamps at the make
// ceiling (sv ≥ 1000‰ → 100% make). A material fraction here IS the make-realization drag.
func (a *ThreePtDiagAccum) FracSvGe1000() float64 {
	if a.Count == 0 {
		return 0
	}
	return float64(a.CountSvGe1000) / float64(a.Count)
}

// FracSvLe0 is the fraction of 3pt attempts whose total shot value clamps at the make
// floor (sv ≤ 0‰ → 0% make).
func (a *ThreePtDiagAccum) FracSvLe0() float64 {
	if a.Count == 0 {
		return 0
	}
	return float64(a.CountSvLe0) / float64(a.Count)
}

// MeanD80InClampPp / MeanNetInClampPp / MeanBlkInClampPp are the component means (pp) over
// ONLY the upper-clamped (sv ≥ 1000) attempts. Whichever is large names the feed pushing
// shots past the ceiling: a large net mean here (vs the ~0 all-attempt net mean) is the
// net-term-dispersion signature — the 3pt net-term SCALE is then the RE target.
func (a *ThreePtDiagAccum) MeanD80InClampPp() float64 {
	if a.CountSvGe1000 == 0 {
		return 0
	}
	return a.SumD80GeClamp / float64(a.CountSvGe1000) / 10.0
}

func (a *ThreePtDiagAccum) MeanNetInClampPp() float64 {
	if a.CountSvGe1000 == 0 {
		return 0
	}
	return a.SumNetGeClamp / float64(a.CountSvGe1000) / 10.0
}

func (a *ThreePtDiagAccum) MeanBlkInClampPp() float64 {
	if a.CountSvGe1000 == 0 {
		return 0
	}
	return a.SumBlkGeClamp / float64(a.CountSvGe1000) / 10.0
}

// MadePct is the roll's OWN realized 3pt make rate (%) over the diag population — the
// direct measurement that reconciles (or not) with the reconstructed E[sv/1000]×100. If it
// tracks the recon but the box-score 3P% is lower, the drag is downstream of the roll.
func (a *ThreePtDiagAccum) MadePct() float64 {
	if a.Count == 0 {
		return 0
	}
	return float64(a.MadeCount) / float64(a.Count) * 100.0
}

// MeanFatigue is the mean fatigue multiplier applied at the 3pt make roll. Proves whether
// fatigueFactor(stamina) is ≡1.0 at runtime (the static-read claim) or something less.
func (a *ThreePtDiagAccum) MeanFatigue() float64 {
	if a.Count == 0 {
		return 0
	}
	return a.SumFatigue / float64(a.Count)
}

// MeanSvActualPp is the mean of the ACTUAL shotValue fed to rollMake, in pp (‰/10). Compare
// to the recon (MeanD80Pp+MeanNetTermPp+MeanBlockTermPp): a gap means the diag's component
// reconstruction does not equal the value the roll sees — the drag is a reconstruction error.
func (a *ThreePtDiagAccum) MeanSvActualPp() float64 {
	if a.Count == 0 {
		return 0
	}
	return a.SumSvActual / float64(a.Count) / 10.0
}

// FastClassAccum accumulates per-class possession-step counts across all games
// in a run. DRBPushClass counts the code-7 {2,3,4}s class — gated fast-break
// survivors from BOTH steal-sourced AND DRB-sourced possessions (J24 §1d: the
// 5.60 binary routes steal and DRB survivors through the SAME code-7 clock step,
// so they share one counter); HalfCourt counts the half-court jitter class,
// which now also absorbs steal-armed possessions that FAIL the Stage-2 gate
// (J24 Phase 2). TotalPossessions is the exhaustive cross-check sum —
// DRBPushClass + HalfCourt must equal TotalPossessions and the game's
// EventPossessionStart count.
//
// The caller owns it (passed via Options.FastClassAccum), shares ONE instance
// across every game in a run, and reads the counters after the pass. The
// instrument issues NO rng draw and alters no game decision, so attaching it
// stays byte-identical to a plain run. NOT concurrency-safe.
type FastClassAccum struct {
	DRBPushClass     int // code-7 {2,3,4}s gated survivors (steal- AND DRB-sourced)
	HalfCourt        int // half-court jitter class (incl. steal-armed gate failures)
	TotalPossessions int // DRBPushClass + HalfCourt — exhaustive cross-check
}

// OutcomeDiagAccum harvests, per play-outcome shot-DECISION (one Add per
// selectOutcome call on BOTH the half-court and transition assembly sites), the
// four bucket weights and the 3pt-eligibility/feed flags the J24 3PA-gap
// localization instrument decomposes. Accumulation-ONLY: no rng, no field the
// engine reads — proven byte-identical by TestOutcomeDiagAccum_NonPerturbationAndReachability.
// The caller owns it (Options.OutcomeDiag) and shares ONE instance across games.
type OutcomeDiagAccum struct {
	ShotDecisions int // total Add() calls (the denominator)
	Eligible3pt   int // decisions where 3pt was an allowed path (half-court, non-OReb)
	Suppressed    int // decisions where 3pt was forced out (transition OR OReb continuation)
	Transition    int // subset of Suppressed: fired fast-break decisions (allow3pt=false)
	RealMinZero   int // decisions whose ball handler had RealLifeMIN==0 (stand-in bucket path)
	// Sums over the ELIGIBLE subset only (a suppressed decision has threePtW==0
	// by construction and must not dilute the denominator-dilution ratio).
	Sum2ptW           float64 // Σ twoPtWeight
	Sum3ptW           float64 // Σ threePtWeight
	SumFoulW          float64 // Σ foulOnlyWeight
	SumAndOneW        float64 // Σ andOneWeight
	SumThreeShare2    float64 // Σ threePtW / (twoPtW+threePtW)                      — 2-bucket ratio (candidate b)
	SumThreeShareFull float64 // Σ threePtW / (twoPtW+threePtW+foulW+andOneW)        — full ratio (foul/andOne lever)
}

// Add records one shot-decision. Guards mirror selectOutcome's own total>0 guard
// so a degenerate all-zero-weight decision cannot inject a NaN into the means.
func (a *OutcomeDiagAccum) Add(twoPtW, threePtW, foulW, andOneW float64, eligible3pt, transition, realMinZero bool) {
	a.ShotDecisions++
	if realMinZero {
		a.RealMinZero++
	}
	if !eligible3pt {
		a.Suppressed++
		if transition {
			a.Transition++
		}
		return
	}
	a.Eligible3pt++
	a.Sum2ptW += twoPtW
	a.Sum3ptW += threePtW
	a.SumFoulW += foulW
	a.SumAndOneW += andOneW
	if twoPtW+threePtW > 0 {
		a.SumThreeShare2 += threePtW / (twoPtW + threePtW)
	}
	if full := twoPtW + threePtW + foulW + andOneW; full > 0 {
		a.SumThreeShareFull += threePtW / full
	}
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

	// BaseTimeMid overrides the package const baseTimeMid (tempo.go) — the constant
	// per-game base_time (J24 Phase 0: 5.60's composite ratio is dead code) — for
	// the J23 mean-pace re-center sweep. nil → use the const (a zero Options stays
	// byte-identical to Simulate); non-nil → use *BaseTimeMid. Always a valid float
	// when set, so validate() needs no zero-mean guard (unlike the freeze arms).
	//
	// NOT retired at J24 Phase 5 — Phase 5 was a NO-GO (tempo.go const block):
	// the faithful 16.0 center could not be installed because the engine arms
	// fast possession classes at ~29% vs real ~11.5%, so 16.0 overshoots mean
	// pace (114.68 vs real ~104.6). This seam is RETAINED as the sweep
	// instrument's re-center knob (baseTimeMid now provisionally 17.7) until
	// that fast-class arming-share gap closes and the center can walk back
	// down to the faithful 16.0. See the tempo.go NO-GO block and ADR-0085.
	BaseTimeMid *float64

	// StealTurnoverScale / NonStealTurnoverScale override the package consts
	// stealTurnoverScale / nonStealTurnoverScale (steal.go) for the J14 research
	// turnover-scale sweep. nil → use the const (a zero Options stays byte-identical
	// to Simulate); non-nil → use the pointed-to value. Always a valid float when set.
	StealTurnoverScale    *float64
	NonStealTurnoverScale *float64

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
	// FastClassAccum, when non-nil, harvests per-class possession-step counts
	// across the run: one tally per class in the three-way step-routing switch
	// (gameloop.go). No rng draw is issued; the instrument is read-only and
	// does not alter any game outcome. The caller owns it and shares one
	// instance across a run's games. nil (a zero Options) leaves counting inert,
	// so a zero Options stays byte-identical to Simulate. NOT concurrency-safe.
	FastClassAccum *FastClassAccum
	// ThreePtDiag, when non-nil, harvests the per-3pt-attempt make-value decomposition
	// (d80 base, net term, block term in ‰) across the run. Read-only, no rng draw, so a
	// zero Options stays byte-identical to Simulate. The caller owns one instance across a
	// run's games (nil outside the 3pt-undershoot archive instrument). NOT concurrency-safe.
	ThreePtDiag *ThreePtDiagAccum
	// OutcomeDiag, when non-nil, harvests one Add per play-outcome shot-decision
	// (both assembly sites) for the J24 3PA-gap localization instrument. Nil in
	// every shipped path; a zero-value run is byte-identical to Simulate.
	OutcomeDiag *OutcomeDiagAccum
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
	p := gs.stealTurnoverScale * careless * pressure
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
func (gs *gameState) makeValue2pt(net float64, bh onCourt, mq float64, origin result.ShotOrigin, leagueBlk48, defBlkSum float64) float64 {
	// Faithful putback make-value (ADR-0055): a half-court putback (OriginOffReb)
	// uses the net-free 4/3-boosted putbackValue2pt form (decompile 93880-93883),
	// computed BEFORE the accum capture so the Make/MakePutback/MakePutbackHalf arms
	// freeze against the NEW baseline. The UnfaithfulPutback escape hatch restores the
	// old net-coupled value for the ADR-0055 OFF walk only. OriginInitial and
	// OriginTransition are unchanged (transition putback faithfulness is OOS — ADR-0055).
	v := shotValue2pt(net, bh, mq, false, gs.shotBaselineOrFallback(), leagueBlk48, defBlkSum)
	if origin == result.OriginOffReb && !gs.freeze.UnfaithfulPutback {
		v = putbackValue2pt(bh)
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
