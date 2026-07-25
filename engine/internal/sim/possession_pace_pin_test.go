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
//	    class, code 6). Pin A now locks that jittered draw's geometry at the
//	    FAITHFUL baseTimeMid = 16.0 center (J25 walkback from the provisional
//	    17.7 — tempo.go const block): sample mean ~15.81, support [3,24], the
//	    pre-redraw floor at bucket 8, and the redraw-drained trunc(pt)=16
//	    bucket. It supersedes the retired four-bucket (13/14/15/16,
//	    ~1/6-1/3-1/3-1/6) deterministic-sweep pin.
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
//	    count characterization (mean ~109/team at the faithful 16.0 center)
//	    plus the permanent sanity invariants.
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
	// the theoretical pt=16.0 the jitter targets in expectation — the {3..23}
	// redraw drains the trunc(pt)=16 bucket into a mean-13 uniform, pulling the
	// sample below pt).
	// Re-baselined for the J25 faithful walkback (17.7 -> 16.0, tempo.go):
	// 17.46 -> 15.8072 (measured directly, seed=1 n=100000; the analytic
	// post-redraw mean at pt=16 is 15.8125). Re-baseline again if a later
	// tempo-step change moves the observed mean outside the band.
	const center = 15.8072
	const band = 0.15
	if math.Abs(mean-center) > band {
		t.Errorf("jittered step mean drifted: got %.4f, want %.2f ± %.2f", mean, center, band)
	}

	// Permanent invariant: support ⊂ [3,24]. At the faithful baseTimeMid=16.0,
	// pt=16.0 and the pre-redraw round-half-up(pt/2 + U[0,pt)) maps raw draws in
	// [8,24) onto steps {8..24}; the {3..23} redraw contributes its own floor of
	// 3. So the lower bound comes from the redraw and the upper bound (24) from
	// the pre-redraw draw. Narrowed from [3,27] for the J25 walkback: the faster
	// center lowers the pre-redraw ceiling 27 -> 24, while the redraw ceiling
	// (23) is unchanged and still below it.
	for step := range counts {
		if step < 3 || step > 24 {
			t.Errorf("possessionTime returned step %d outside support [3,24]", step)
		}
	}
	// Permanent invariant: every ordinary bucket {9..23} except the
	// redraw-drained trunc(pt)=16 bucket carries real mass. Buckets 8 and 24
	// are deliberately EXCLUDED from this loop: they are the pre-redraw
	// round-half-up mapping's edge buckets (partial rounding width — 8 covers
	// raw draws in [8,8.5) and 24 covers [23.5,24), width 0.5 each vs the
	// interior buckets' full width 1.0) and so carry real but thinner mass
	// (~3.4% and ~3.1% of draws respectively, vs the interior's ~6.55% each —
	// 8 also collects a redraw share, 24 does not since the redraw tops out at
	// 23). They get their own nonzero assertion immediately below.
	for step := 9; step <= 23; step++ {
		if step == 16 {
			continue
		}
		if counts[step] == 0 {
			t.Errorf("bucket %d carries no mass across %d draws", step, n)
		}
	}
	// Permanent invariant: the two thin edge buckets still carry mass — they
	// are the pre-redraw draw's floor and ceiling, so an empty one means the
	// round-half-up mapping's range moved.
	for _, step := range []int{8, 24} {
		if counts[step] == 0 {
			t.Errorf("edge bucket %d carries no mass across %d draws", step, n)
		}
	}
	// Permanent invariant: the trunc(pt)=16 bucket is DRAINED, not empty. Every
	// pre-redraw 16 is redrawn into {3..23}, so bucket 16 keeps only the
	// redraw's own 1/21 share of that mass (~0.30% of draws) — an order of
	// magnitude below its interior neighbours (~6.55%). Stated as a RATIO
	// against the interior mean so it is independent of n: if the redraw ever
	// stops firing, bucket 16 jumps to full interior height and this trips; if
	// the redraw ever loops instead of drawing once, bucket 16 empties and the
	// nonzero half trips.
	var interiorSum, interiorN float64
	for step := 9; step <= 23; step++ {
		if step == 16 {
			continue
		}
		interiorSum += float64(counts[step])
		interiorN++
	}
	interiorMean := interiorSum / interiorN
	if counts[16] == 0 {
		t.Errorf("trunc(pt)=16 bucket is empty across %d draws — the single {3..23} redraw should still land back on 16 ~1/21 of the time", n)
	}
	if float64(counts[16]) > interiorMean/5.0 {
		t.Errorf("trunc(pt)=16 bucket not drained: got %d, want <= %.0f (interior mean %.0f / 5) — the redraw may not be firing",
			counts[16], interiorMean/5.0, interiorMean)
	}
	// Permanent invariant: redraw evidence. Steps below the pre-redraw floor
	// (8) can ONLY come from the {3..23} redraw firing on a trunc(pt)=16 hit,
	// so at least one such step must appear across n=100000 draws.
	//
	// NOTE the strict `< 8`, tightened from `< 9` in the J25 walkback: at
	// baseTimeMid=16.0 the pre-redraw floor moved 9 -> 8, and bucket 8 carries
	// ~3.4% ORDINARY (non-redraw) mass. Leaving the old `< 9` bound would make
	// sawBelowFloor trivially true on ordinary draws and stop testing the
	// redraw at all.
	sawBelowFloor := false
	for step := 3; step < 8; step++ {
		if counts[step] > 0 {
			sawBelowFloor = true
			break
		}
	}
	if !sawBelowFloor {
		t.Errorf("no below-pre-redraw-floor step (3..7) observed across %d draws — redraw may not be firing", n)
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
		// band. RE-BASELINED for J24 §1d steal-gating partition: steal-armed
		// gate-FAIL possessions (≈65% of steal-followed possessions) switch from
		// r.IntN(3) ∈ {0,1,2}s (mean 1s) to possessionTime() ∈ [3,27]s (mean
		// ~17.46s), a much longer drain that reduces total possessions per game.
		// Measured directly (seeds 1-40, richBundle) at the J25 faithful
		// baseTimeMid=16.0: total possessions/game ranged [202,243], comfortably
		// inside the band. The [160,300] bounds are UNCHANGED across the J25
		// walkback — they are a permanent NBA-plausibility gate, not a
		// characterization, so they were re-confirmed rather than re-baselined.
		// (At the retired 17.7 center the same sweep measured [178,218].)
		if total < 160 || total > 300 {
			t.Errorf("seed %d: total possessions %d outside plausible [160,300]", seed, total)
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
	//
	// Re-baselined AGAIN for J24 §1d steal-gating partition: 104.4625 -> 97.9000.
	// Steal-armed gate-FAIL possessions (≈65% of steal-followed) switch from
	// r.IntN(3) ∈ {0,1,2}s (mean 1s) to possessionTime() ∈ [3,27]s (mean ~17.46s).
	// Gate-pass steal possessions keep r.IntN(3)+2 ∈ {2,3,4}s (mean 3s, same draw
	// count as before). Since steals are a common turnover source (ADR-0045), the
	// gate-fail path's longer draw pulls mean step UP and possession count DOWN
	// substantially — measured directly at seed=1..40 on richBundle: 97.9000.
	//
	// Re-baselined AGAIN for the J25 faithful walkback (tempo.go baseTimeMid
	// 17.7 -> 16.0): 97.9000 -> 109.0000. The half-court jittered step is the
	// dominant class, so speeding it up (Pin A mean 17.46 -> 15.81, a 1.104×
	// ratio) raises the per-team count; the observed 97.90 -> 109.00 is 1.113×,
	// slightly above the step-mean ratio because the unchanged fast classes
	// occupy a marginally smaller share of a longer possession list. Measured
	// directly at seed=1..40 on richBundle: 109.0000.
	const (
		center = 109.0000
		band   = 3.0
	)
	if math.Abs(mean-center) > band {
		t.Errorf("mean per-team possessions/game drifted: got %.4f, want %.1f ± %.1f", mean, center, band)
	}
}
