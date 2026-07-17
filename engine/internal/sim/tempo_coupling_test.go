package sim

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
)

// volTeam builds a 5-starter team whose every starter carries the given per-48
// shot-volume rates (FGA/TGA/FTA) and FGP, all other ratings at the mkPlayer
// defaults (so only volume + efficiency differ across teams). Defense is held at
// the mkPlayer default (OD/DD/PD/TD = 5) so the offensive channel is isolated.
func volTeam(teamID, fga, tga, fta, fgp, pidBase int) []bundle.Player {
	ps := make([]bundle.Player, 0, 5)
	for slot := slotPG; slot <= slotC; slot++ {
		p := mkPlayer(pidBase+slot, teamID, slot, fgp)
		p.FGA, p.TGA, p.FTA = fga, tga, fta
		ps = append(ps, p)
	}
	return ps
}

func vsNeutralBundle(team []bundle.Player) bundle.Bundle {
	// Neutral common opponent N (id 9): real-mean offensive volume (≈161) and a
	// middling FGP, so each test team is graded against the same baseline.
	neutral := volTeam(9, 95, 40, 26, 48, 900)
	return bundle.Bundle{
		Teams:   []bundle.Team{{TeamID: team[0].TeamID, Name: "T"}, {TeamID: 9, Name: "N"}},
		Players: append(append([]bundle.Player{}, team...), neutral...),
		Schedule: []bundle.Game{
			{HomeTeamID: 9, VisitorTeamID: team[0].TeamID, Date: "1988-11-04", GameType: bundle.GameTypeRegular},
		},
	}
}

// teamFGA returns a team's field-goal attempts (2pt + 3pt) from its box.
func teamFGA(tb result.TeamBox) int { return tb.Game2GA + tb.Game3GA }

// boxForTeam returns the TeamBox for the given team id in a game result.
func boxForTeam(g result.GameResult, teamID int) result.TeamBox {
	for _, tb := range g.TeamBoxes {
		if tb.TeamID == teamID {
			return tb
		}
	}
	panic("team not found in result")
}

// meanFGAandPPS runs a team vs the neutral opponent over seeds [1,n] and returns
// the team's mean FGA/game and mean points-per-shot.
func meanFGAandPPS(t *testing.T, team []bundle.Player, n int) (meanFGA, meanPPS float64) {
	t.Helper()
	b := vsNeutralBundle(team)
	var sumFGA, sumPPS float64
	for seed := uint64(1); seed <= uint64(n); seed++ {
		g := Simulate(b, seed).Games[0]
		tb := boxForTeam(g, team[0].TeamID)
		fga := teamFGA(tb)
		sumFGA += float64(fga)
		if fga > 0 {
			sumPPS += float64(teamPoints(tb)) / float64(fga)
		}
	}
	return sumFGA / float64(n), sumPPS / float64(n)
}

// TestVolumeCountChannel_CouplingSign — REBASED at J24 Phase 1. The ADR-0042
// base_time volume→count channel is RETIRED (5.60's base_time is constant — the
// composite ratio is dead code, u = 0; tempo.go const block), so the old
// hFGA > lFGA assertion has no mechanism to enforce it: with a constant shared
// step both teams draw equal possessions and FGA differs only by within-possession
// noise. What remains locked here is the efficiency half (higher FGP → higher
// PPS). The faithful count/pace coupling is re-established by the possession-type
// mix (J24 Phases 2-4: steal transitions, DRB pushes, half-court jitter), and its
// SIGN gate lives at the corpus level (TestRealArchive_PossessionCoupling /
// the J24 Phase 5 GO/NO-GO), not in this two-team fixture.
func TestVolumeCountChannel_CouplingSign(t *testing.T) {
	const n = 40
	// H: high volume + high FGP. L: low volume + low FGP — coupled, mirroring the
	// real corr(VOL,FGP) = +0.265.
	hFGA, hPPS := meanFGAandPPS(t, volTeam(1, 110, 45, 30, 54, 100), n)
	lFGA, lPPS := meanFGAandPPS(t, volTeam(1, 82, 35, 23, 44, 200), n)

	if !(hPPS > lPPS) {
		t.Fatalf("coupling: high-efficiency mean PPS (%.4f) must exceed low (%.4f)", hPPS, lPPS)
	}
	t.Logf("H: FGA=%.2f PPS=%.4f   L: FGA=%.2f PPS=%.4f", hFGA, hPPS, lFGA, lPPS)
}

// pearsonR is a plain Pearson correlation coefficient, used only by the
// fixture-level sanity check below (no external dependency needed for one
// caller).
func pearsonR(xs, ys []float64) float64 {
	n := float64(len(xs))
	var sx, sy, sxy, sxx, syy float64
	for i := range xs {
		sx += xs[i]
		sy += ys[i]
		sxy += xs[i] * ys[i]
		sxx += xs[i] * xs[i]
		syy += ys[i] * ys[i]
	}
	num := n*sxy - sx*sy
	den := math.Sqrt((n*sxx - sx*sx) * (n*syy - sy*sy))
	if den == 0 {
		return 0
	}
	return num / den
}

// TestFastArmCountCorrelatesWithPossessionCount is a CHEAP, fixture-level
// sanity check for the J24 Phase 2-4 pace/possession-count coupling this
// file's docblock (TestVolumeCountChannel_CouplingSign) says is re-established
// by the possession-type mix. It is NOT the authoritative coupling gate — that
// is TestRealArchive_PossessionCoupling (internal/calibrate), which measures
// Cov(lnPOSS,lnPPS) on the real multi-team, 53GB local corpus this repo must
// not touch from a unit test. This is a mechanism-level smoke check on
// richBundle only: within a single game, more possessions ending in a steal or
// a defensive rebound (segmentOutcome != possNormal — the two endings that ARM
// a next-possession fast-class draw: steal unconditionally, DRB when the
// shared Stage-2 gate fires) should mean more fast (short) steps drawn, which
// should mean MORE total possessions fit in the same clock. Measured directly
// (not part of this pin, ad hoc verification before adding this test): Pearson
// r over richBundle seeds 1-150 was 0.78-0.82 stable across n∈{40,60,100} — a
// strong, non-flaky positive correlation. The threshold below (>0.3) is set
// well under that observed range for headroom; if this ever proves noisy or
// flaky in CI it should be removed rather than loosened further, since the
// archive test is the real gate.
func TestFastArmCountCorrelatesWithPossessionCount(t *testing.T) {
	const n = 60
	b := richBundle()
	var arms, totals []float64
	for seed := uint64(1); seed <= n; seed++ {
		g := Simulate(b, seed).Games[0]
		segs := possessionSegments(g.Events)
		var armed, total int
		for _, e := range g.Events {
			if e.Kind == result.EventPossessionStart {
				total++
			}
		}
		for _, s := range segs {
			if segmentOutcome(s.events) != possNormal {
				armed++
			}
		}
		arms = append(arms, float64(armed))
		totals = append(totals, float64(total))
	}
	r := pearsonR(arms, totals)
	t.Logf("fast-arm-count vs possession-count Pearson r = %.4f over %d seeds", r, n)
	if r <= 0.3 {
		t.Errorf("Pearson r = %.4f, want > 0.3 (steal/DRB-armed possessions should positively "+
			"correlate with total possession count via the fast step classes)", r)
	}
}

// TestVolumeCountChannel_ZeroVolumeDegenerate is the boundary: a team with
// all-zero volume rates (and zero FGP) vs the neutral opponent does not crash the
// channel: the game terminates with a winner, FGA is a valid non-negative count
// (it CAN be 0 — an all-zero-rated team's possessions can all end in turnovers),
// and the points-per-shot computation is finite (no 0/0 NaN) whenever FGA > 0.
func TestVolumeCountChannel_ZeroVolumeDegenerate(t *testing.T) {
	b := vsNeutralBundle(volTeam(1, 0, 0, 0, 0, 100))
	for seed := uint64(1); seed <= 10; seed++ {
		g := Simulate(b, seed).Games[0]
		v := boxForTeam(g, 1)
		h := boxForTeam(g, 9)
		if fga := teamFGA(v); fga > 0 {
			if pps := float64(teamPoints(v)) / float64(fga); math.IsNaN(pps) || math.IsInf(pps, 0) || pps < 0 {
				t.Fatalf("seed %d: zero-volume team PPS non-finite: %v (fga=%d)", seed, pps, fga)
			}
		}
		if teamPoints(v) == teamPoints(h) {
			// A persisted tie is legal ONLY via the engine's documented
			// termination design: maxOvertime (gameloop.go) hard-caps OT so a
			// game between mutually starved teams (here: zero-rated visitor +
			// a neutral five whose players all foul out, ADR-0082's larger
			// foul bucket) always terminates. Verify the cap was actually
			// exhausted — a tie WITHOUT the full OT run is a real loop bug.
			maxPeriod := 0
			for _, e := range g.Events {
				if e.Kind == result.EventPeriodBoundary && e.Period > maxPeriod {
					maxPeriod = e.Period
				}
			}
			if maxPeriod < 4+maxOvertime {
				t.Fatalf("seed %d: tie persisted after only %d periods — OT loop exited before the %d-OT cap",
					seed, maxPeriod, maxOvertime)
			}
		}
	}
}
