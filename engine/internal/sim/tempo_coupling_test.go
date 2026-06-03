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

// TestVolumeCountChannel_CouplingSign verifies the ADR-0042 channel restores the
// positive volume↔scoring coupling SIGN (not a magnitude target): a high-volume,
// high-efficiency team H takes MORE shots per game than a low-volume,
// low-efficiency team L (the volume→count channel), AND H is more efficient
// (higher PPS). Together the higher-FGA team is also the higher-PPS team — the
// positive FGA↔PPS coupling the engine previously inverted.
func TestVolumeCountChannel_CouplingSign(t *testing.T) {
	const n = 40
	// H: high volume (offAvg ≈ 185, near real max) + high FGP. L: low volume
	// (offAvg ≈ 140, near real min) + low FGP. Volume and efficiency are coupled,
	// mirroring the real corr(VOL,FGP) = +0.265.
	hFGA, hPPS := meanFGAandPPS(t, volTeam(1, 110, 45, 30, 54, 100), n)
	lFGA, lPPS := meanFGAandPPS(t, volTeam(1, 82, 35, 23, 44, 200), n)

	if !(hFGA > lFGA) {
		t.Fatalf("count channel: high-volume mean FGA (%.2f) must exceed low-volume (%.2f)", hFGA, lFGA)
	}
	if !(hPPS > lPPS) {
		t.Fatalf("coupling: high-efficiency mean PPS (%.4f) must exceed low (%.4f)", hPPS, lPPS)
	}
	t.Logf("H: FGA=%.2f PPS=%.4f   L: FGA=%.2f PPS=%.4f", hFGA, hPPS, lFGA, lPPS)
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
			t.Fatalf("seed %d: game did not resolve to a winner (tie persisted)", seed)
		}
	}
}
