package sim

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/result"
)

// This file characterizes the SHIPPED (J23-faithful) pace/possession-count
// behavior — ROUND-HALF-UP of the base_time -> possession-step mapping (tempo.go
// possessionTime, FUN_004e42e0 / _DAT_00669ef0 = 0.5) coupled with the re-centered
// baseTimeMid (tempo.go const — ADR-0085 Update, J23). The pins lock that shipped
// state; a later tempo-step or re-center change fires them for re-baselining.
//
//	Pin A (TestPossessionStepDistributionPin_Current) — the UNIT step
//	  distribution of possessionTime() across a base_time sweep of [13,16].
//	    - shipped round-half-up: the round boundaries at 13.5/14.5/15.5 partition
//	      [13,16] into a half-width [13,13.5) for bucket 13, full widths for 14 and
//	      15, and a half-width [15.5,16] for bucket 16 — mass ~1/6, 1/3, 1/3, 1/6.
//	    - the RETIRED int() truncation (pre-J23) put ~1/3 in each of {13,14,15}
//	      and only the closed endpoint (bt==16) in 16; J21 held round-half-up
//	      (ADR-0085) because shipped ALONE it regressed mean pace — J23 shipped it
//	      COUPLED with the baseTimeMid re-center, and this pin now locks that
//	      faithful state.
//
//	Pin B (TestPossessionCountLoopPin_Current) — the full-loop per-team
//	  possession COUNT over richBundle. This is a characterization + permanent
//	  invariants, NOT a step-rule tripwire, and deliberately so:
//	    richBundle's two teams carry IDENTICAL volume ratings (only FGP differs),
//	    so teamBaseTimeWith yields ONE base_time for the matchup; gameloop.go
//	    averages the two (identical) team base_times and steps the clock by the
//	    shared value, and strict offense/defense alternation then hands BOTH
//	    teams the SAME possession count every seed. Empirically that count is
//	    exactly 104/team every game (var = 0) — cross-team possession-count
//	    dispersion is STRUCTURALLY ZERO in this fixed fixture and cannot be
//	    pinned as a tripwire here. The real cross-team Var(lnPOSS) gate lives
//	    in the archive test TestRealArchive_PossessionCoupling (internal/calibrate),
//	    which measures it on the multi-team corpus. So Pin B locks the loop-level
//	    count characterization (mean 104/team) plus the permanent sanity invariants.
//
// See plan jsb-j21-pace-dispersion-fidelity.md Phase 1 (characterization pins) and
// ADR-0085 (the round-vs-truncate fidelity finding + the J23 Update these pins'
// centers record).

// possessionStepSweep evaluates possessionTime() at `samples` evenly-spaced
// base_time points across the [baseTimeLow, baseTimeHigh] clamp range and
// returns the share of results landing in each integer step bucket. Deterministic
// and self-contained so the pinned shares are reproducible.
func possessionStepSweep(samples int) map[int]float64 {
	counts := map[int]int{}
	for i := 0; i < samples; i++ {
		bt := baseTimeLow + (baseTimeHigh-baseTimeLow)*float64(i)/float64(samples-1)
		counts[possessionTime(bt)]++
	}
	shares := map[int]float64{}
	for step, c := range counts {
		shares[step] = float64(c) / float64(samples)
	}
	return shares
}

func TestPossessionStepDistributionPin_Current(t *testing.T) {
	const samples = 3001
	shares := possessionStepSweep(samples)

	const band = 0.02
	// PIN: re-baseline if a later tempo-step change moves these. Centers read off the
	// shipped round-half-up sweep (J23, ADR-0085 Update): the .5 boundaries partition
	// [13,16] into 13->[13,13.5) (half width), 14->[13.5,14.5), 15->[14.5,15.5), and
	// 16->[15.5,16] (half width + the closed endpoint) — 1/6, 1/3, 1/3, 1/6. Exact
	// shares at 3001 samples: 500/3001, 1000/3001, 1000/3001, 501/3001.
	type bucket struct {
		step   int
		center float64
	}
	for _, b := range []bucket{
		{13, 0.166611},
		{14, 0.333222},
		{15, 0.333222},
		{16, 0.166944},
	} {
		if got := shares[b.step]; math.Abs(got-b.center) > band {
			t.Errorf("step %d share drifted: got %.6f, want %.6f ± %.2f", b.step, got, b.center, band)
		}
	}

	// Permanent invariants (not pinned/re-baselined): the int-returning mapping
	// yields exactly the four integers in the [13,16] clamp, and the shares
	// partition the sweep. Under the shipped round-half-up all four buckets carry
	// real mass (13 and 16 each a half-width — unlike the retired truncation,
	// where 16 held only the closed endpoint).
	var sum float64
	for step, share := range shares {
		if step < int(baseTimeLow) || step > int(baseTimeHigh) {
			t.Errorf("possessionTime returned step %d outside [%d,%d]", step, int(baseTimeLow), int(baseTimeHigh))
		}
		sum += share
	}
	if math.Abs(sum-1.0) > 1e-9 {
		t.Errorf("step shares do not sum to 1.0: got %.9f", sum)
	}
	if len(shares) != 4 {
		t.Errorf("possessionTime must yield exactly 4 integer buckets {13,14,15,16}, got %d: %v", len(shares), shares)
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
		// dominate the other implausibly (strict alternation keeps them equal).
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
		// Permanent invariant: total possessions per game in an NBA-plausible band.
		if total < 150 || total > 230 {
			t.Errorf("seed %d: total possessions %d outside plausible [150,230]", seed, total)
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
	// This is NOT a step-rule tripwire (richBundle's identical-volume rosters make
	// cross-team count dispersion structurally zero — see file header; the archive
	// test owns the real Var(lnPOSS) gate). Re-baseline if a change intentionally
	// moves the loop-level count on this fixture. Re-baselined 96 → 104 for J23:
	// the baseTimeMid re-center moved richBundle's shared step 15s → 14s.
	const (
		center = 104.0
		band   = 2.0 // ~2% of 104
	)
	if math.Abs(mean-center) > band {
		t.Errorf("mean per-team possessions/game drifted: got %.4f, want %.1f ± %.1f", mean, center, band)
	}
}
