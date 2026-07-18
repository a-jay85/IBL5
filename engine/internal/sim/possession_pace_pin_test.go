package sim

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// This file characterizes the SHIPPED pace/possession-count behavior.
//
//	Pin A (TestPossessionStepDistributionPin_Current) — the UNIT step
//	  distribution of possessionTime() at the shipped baseTimeMid center.
//	    J24 Phase 2 retired the deterministic round-half-up(base_time) mapping
//	    (FUN_004e4150's composite ratio is dead code — tempo.go const block,
//	    J24 Phase 0/1) in favor of a PER-POSSESSION jittered draw off the
//	    constant base_time: round-half-up(pt/2 + U[0,pt)), with a single
//	    {3..23} redraw on the rare trunc(pt) hit (FUN_004e42e0 half-court step
//	    class, code 6). Pin A now locks that jittered draw's sample mean and
//	    support at the provisional baseTimeMid center, superseding the retired
//	    four-bucket (13/14/15/16, ~1/6-1/3-1/3-1/6) deterministic-sweep pin.
//
//	Pin B (TestPossessionCountLoopPin_Current) — the full-loop per-team
//	  possession COUNT over richBundle. This is a characterization + permanent
//	  invariants, NOT a step-rule tripwire, and deliberately so:
//	    base_time is CONSTANT (J24 Phase 1: 5.60's composite ratio is dead
//	    code — tempo.go const block); the jittered PER-POSSESSION step (J24
//	    Phase 2) means possession count can now vary slightly team-to-team and
//	    seed-to-seed (unlike the fully-deterministic pre-Phase-2 shared step),
//	    but the mean stays close to the retired deterministic-step center. The
//	    real cross-team Var(lnPOSS) gate lives in the archive test
//	    TestRealArchive_PossessionCoupling (internal/calibrate), which
//	    measures it on the multi-team corpus. So Pin B locks the loop-level
//	    count characterization (mean ~104/team) plus the permanent sanity
//	    invariants.
//
// See plan jsb-j21-pace-dispersion-fidelity.md Phase 1 (characterization pins),
// ADR-0085 (the round-vs-truncate fidelity finding), and the J24 Phase 2 port
// (FUN_004e42e0 half-court jitter) for the mechanics these pins record.

func TestPossessionStepDistributionPin_Current(t *testing.T) {
	r := rng.New(1)
	const n = 100000
	counts := map[int]int{}
	var sum float64
	for i := 0; i < n; i++ {
		step := possessionTime(baseTimeMid, r)
		counts[step]++
		sum += float64(step)
	}
	mean := sum / n

	// PIN: the observed sample mean at seed=1, n=100000 (measured directly, not
	// the theoretical pt=17.7 the jitter targets in expectation — the {3..23}
	// redraw floor pulls the sample slightly below pt at this sample size).
	// Re-baselined for the J24 Phase 5 NO-GO re-center (13.65 -> 17.7, tempo.go):
	// 13.63 -> 17.46 (measured directly, seed=1 n=100000). Re-baseline again if a
	// later tempo-step change moves the observed mean outside the band.
	const center = 17.46
	const band = 0.15
	if math.Abs(mean-center) > band {
		t.Errorf("jittered step mean drifted: got %.4f, want %.2f ± %.2f", mean, center, band)
	}

	// Permanent invariant: support ⊂ [3,27] (the {3..23} redraw floor/ceiling
	// and the pre-redraw round-half-up(pt/2 + U[0,pt)) ceiling both bound here
	// at baseTimeMid=17.7 — pt=17.7, pre-redraw step ∈ {9..27}). Widened from
	// [3,23] for the Phase 5 re-center: the pre-redraw ceiling now exceeds the
	// {3..23} redraw's own ceiling (27 > 23), so the full support's upper bound
	// comes from the pre-redraw draw, not the redraw.
	for step := range counts {
		if step < 3 || step > 27 {
			t.Errorf("possessionTime returned step %d outside support [3,27]", step)
		}
	}
	// Permanent invariant: every ordinary bucket {10..26} except the
	// redraw-drained trunc(pt)=17 bucket carries real mass. Buckets 9 and 27
	// are deliberately EXCLUDED from this loop: they are the pre-redraw
	// round-half-up mapping's edge buckets (partial rounding width — 9 covers
	// raw draws in [8.85,9.5), width 0.65 vs the interior buckets' full width
	// 1.0; 27 covers only [26.5,26.55), width 0.05) and so carry real but much
	// thinner mass (measured: bucket 9 ~4% of draws, bucket 27 ~0.27% —
	// nonzero, just far below the interior buckets' ~5-6% each). The
	// below-pre-redraw-floor assertion below still requires steps < 9 to
	// appear, which is the redraw's own evidence.
	for step := 10; step <= 26; step++ {
		if step == 17 {
			continue
		}
		if counts[step] == 0 {
			t.Errorf("bucket %d carries no mass across %d draws", step, n)
		}
	}
	// Permanent invariant: redraw evidence. Steps below the pre-redraw floor
	// (9) can ONLY come from the {3..23} redraw firing on a trunc(pt)=17 hit,
	// so at least one such step must appear across n=100000 draws.
	sawBelowFloor := false
	for step := 3; step < 9; step++ {
		if counts[step] > 0 {
			sawBelowFloor = true
			break
		}
	}
	if !sawBelowFloor {
		t.Errorf("no below-pre-redraw-floor step (3..8) observed across %d draws — redraw may not be firing", n)
	}
}

func TestPossessionCountLoopPin_Current(t *testing.T) {
	b := richBundle()
	// Per-team possession counts across the seed sweep (one count per team per game).
	var perTeam []int
	for seed := uint64(1); seed <= 40; seed++ {
		g := Simulate(b, seed).Games[0]
		byTeam := map[int]int{}
		for _, e := range g.Events {
			if e.Kind == result.EventPossessionStart {
				byTeam[e.TeamID]++
			}
		}
		if len(byTeam) != 2 {
			t.Fatalf("seed %d: expected 2 teams with possessions, got %d: %v", seed, len(byTeam), byTeam)
		}
		// Permanent invariant: both teams must run possessions, and neither may
		// dominate the other implausibly. Pre-Phase-2 this held under strict
		// equality (shared deterministic step kept both teams' counts equal);
		// the J24 Phase 2 per-possession jitter can now split a trailing
		// half-possession between teams, so the ratio bound (not exact
		// equality) is what's permanent.
		var lo, hi, total int
		first := true
		for _, c := range byTeam {
			if c <= 0 {
				t.Errorf("seed %d: a team ran 0 possessions: %v", seed, byTeam)
			}
			if first || c < lo {
				lo = c
			}
			if first || c > hi {
				hi = c
			}
			first = false
			total += c
			perTeam = append(perTeam, c)
		}
		if lo > 0 && float64(hi)/float64(lo) > 1.25 {
			t.Errorf("seed %d: per-team possession ratio implausible: %v", seed, byTeam)
		}
		// Permanent invariant: total possessions per game in an NBA-plausible
		// band. RE-BASELINED for the J24 Phase 5 NO-GO re-center (tempo.go
		// baseTimeMid 13.65 -> 17.7): the slower half-court jittered step
		// (mean ~17.46s vs ~13.63s, Pin A) pulls possession COUNT back down
		// substantially even with the fast steal/DRB-push classes still live.
		// Measured directly (seeds 1-40, richBundle): total possessions/game
		// ranged [204,236]; a 200-seed sweep of the same fixture (not part of
		// this pin, ad hoc verification) ranged [195,254]. [150,340] no longer
		// brackets this range tightly, so it's TIGHTENED to [180,300]: floor
		// 180 keeps headroom below the observed 195-204 low end, ceiling 300
		// keeps ~46 possessions of headroom above the observed 236-254 high
		// end for an overtime game (rare in these sweeps) or a double-OT game
		// (very rare) — a further re-baseline past 300 would not be a bug.
		if total < 180 || total > 300 {
			t.Errorf("seed %d: total possessions %d outside plausible [180,300]", seed, total)
		}
	}

	if len(perTeam) == 0 {
		t.Fatalf("no possession_start events observed across the sweep")
	}
	var mean float64
	for _, c := range perTeam {
		mean += float64(c)
	}
	mean /= float64(len(perTeam))

	// PIN: characterization of the current loop-level per-team possession count.
	// This is NOT a step-rule tripwire — re-baseline if a change intentionally
	// moves the loop-level count on this fixture.
	//
	// Re-baselined for J24 Phase 3 steal-transition step class: 107.0 -> 122.5.
	// gameloop.go now routes a possession's step draw off the PRIOR
	// possession's outcome (possOutcome, possession.go): following a steal
	// (possSteal) the step is r.IntN(3) ∈ {0,1,2}s (mean 1s) instead of the
	// half-court jittered possessionTime() draw (mean ~13.65s at baseTimeMid,
	// the then-shipped center). Steal-driven turnovers are the dominant
	// turnover source (ADR-0045), so a meaningful share of possessions now draw
	// the fast class, pulling the per-game mean step down and possession count
	// up substantially — measured directly at seed=1..40 on richBundle:
	// 122.4500 (matches a 200-seed sweep of the same fixture at 123.1000
	// within noise, ad hoc verification, not part of this pin).
	//
	// Re-baselined AGAIN for J24 Phase 4 DRB-push step class: 122.5 -> 138.3.
	// A possession following a DRB-ending possession (possDRB) now ALSO draws a
	// fast step (r.IntN(3)+2 ∈ {2,3,4}s, mean 3s) whenever the shared Stage-2
	// gate fires (gs.drbPushFired, captured once in possession.go's fbPending
	// branch — see transition.go's transitionTriggers docblock), instead of
	// falling through to the half-court jittered draw unconditionally. Since a
	// defensive rebound is a common non-scoring ending, a further share of
	// possessions now draw a fast step, pulling the mean step down and
	// possession count up again — measured directly at seed=1..40 on
	// richBundle: 138.3375. The total-possessions-per-game invariant above was
	// unaffected at that center: measured range over the same sweep was
	// [255,319], well inside the then-shipped [150,340].
	//
	// Re-baselined AGAIN for the J24 Phase 5 NO-GO tempo.go re-center
	// (baseTimeMid 13.65 -> 17.7): 138.3 -> 109.4. The slower half-court
	// jittered step (mean ~17.46s vs ~13.63s, Pin A) is the DOMINANT step class
	// (the fast steal/DRB-push classes only fire on the possession FOLLOWING
	// those outcomes, not every possession), so slowing it down pulls the
	// per-team count back down substantially even though the fast classes are
	// unchanged — measured directly at seed=1..40 on richBundle: 109.3750.
	//
	// Re-baselined AGAIN for J24 mix-fixes-2 steal split + nonStealTurnover:
	// 109.4 -> 104.4625. nonStealTurnover draws an unconditional Float64 per
	// possession, shifting the RNG stream and altering subsequent step draws.
	// Measured directly at seed=1..40 on richBundle: 104.4625.
	const (
		center = 104.4625
		band   = 3.0
	)
	if math.Abs(mean-center) > band {
		t.Errorf("mean per-team possessions/game drifted: got %.4f, want %.1f ± %.1f", mean, center, band)
	}
}
