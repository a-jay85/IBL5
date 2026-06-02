package validate

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/backup"
	"github.com/a-jay85/IBL5/engine/internal/result"
)

// Row #2: teamStatFromBox normalizes the engine TeamBox to the comparison basis
// — points = quarters + every OT period, total FG = 2GM+3GM (and 2GA+3GA),
// rebounds = ORB+DRB, threes isolated.
func TestTeamStatFromBox(t *testing.T) {
	tb := result.TeamBox{
		TeamID: 7,
		Q1:     20, Q2: 22, Q3: 18, Q4: 25, OT: []int{8, 6},
		Game2GM: 30, Game2GA: 60, GameFTM: 14, GameFTA: 18,
		Game3GM: 9, Game3GA: 24, GameORB: 12, GameDRB: 33,
		GameAST: 21, GameSTL: 8, GameTOV: 13, GameBLK: 5, GamePF: 19,
	}
	got := teamStatFromBox(tb)
	want := TeamStat{
		Points: 20 + 22 + 18 + 25 + 8 + 6, // quarters + both OT periods
		FGM:    30 + 9, FGA: 60 + 24,
		FTM: 14, FTA: 18,
		TGM: 9, TGA: 24,
		REB: 12 + 33,
		AST: 21, STL: 8, TOV: 13, BLK: 5, PF: 19,
	}
	if got != want {
		t.Errorf("teamStatFromBox =\n %+v\nwant\n %+v", got, want)
	}
}

// Row #2: teamStatFromSco sums the team's PLAYER rows on the same basis, takes
// points from the authoritative header score, and SKIPS the PlayerID==0
// team-total row so player stats are never double-counted (advisor blind-spot).
func TestTeamStatFromSco_SkipsTeamTotalRow(t *testing.T) {
	sg := backup.ScoGame{
		VisitorTeamID: 7, HomeTeamID: 3,
		VisitorScore: 101, HomeScore: 99,
		Boxes: []backup.ScoBox{
			// Two visitor player rows.
			{TeamID: 7, PlayerID: 11, TwoGM: 8, TwoGA: 15, FTM: 4, FTA: 5, ThreeGM: 2, ThreeGA: 6, ORB: 3, DRB: 5, AST: 6, STL: 2, TOV: 3, BLK: 1, PF: 4},
			{TeamID: 7, PlayerID: 12, TwoGM: 6, TwoGA: 12, FTM: 2, FTA: 2, ThreeGM: 1, ThreeGA: 3, ORB: 2, DRB: 7, AST: 4, STL: 1, TOV: 2, BLK: 0, PF: 3},
			// Visitor TEAM-TOTAL row (PID==0): must be ignored, not summed.
			{TeamID: 7, PlayerID: 0, TwoGM: 999, TwoGA: 999, FTM: 999, ThreeGM: 999, ORB: 999, AST: 999},
			// A home player row — must not leak into the visitor total.
			{TeamID: 3, PlayerID: 21, TwoGM: 50, TwoGA: 99, ThreeGM: 9, ORB: 9, AST: 9},
		},
	}
	got := teamStatFromSco(sg, 7)
	want := TeamStat{
		Points: 101, // from header, not box sum
		FGM:    (8 + 2) + (6 + 1), FGA: (15 + 6) + (12 + 3),
		FTM: 4 + 2, FTA: 5 + 2,
		TGM: 2 + 1, TGA: 6 + 3,
		REB: (3 + 5) + (2 + 7),
		AST: 6 + 4, STL: 2 + 1, TOV: 3 + 2, BLK: 1 + 0, PF: 4 + 3,
	}
	if got != want {
		t.Errorf("teamStatFromSco(visitor) =\n %+v\nwant\n %+v", got, want)
	}
	if home := teamStatFromSco(sg, 3); home.Points != 99 {
		t.Errorf("home points = %d, want 99 (header)", home.Points)
	}
}

func TestMean(t *testing.T) {
	samples := []TeamStat{
		{Points: 100, FGM: 40},
		{Points: 110, FGM: 42},
	}
	m := mean(samples)
	if m["points"] != 105 || m["fgm"] != 41 {
		t.Errorf("mean points=%v fgm=%v, want 105 / 41", m["points"], m["fgm"])
	}
	if got := mean(nil); len(got) != 0 {
		t.Errorf("mean(nil) = %v, want empty", got)
	}
}

// pts builds a slice of TeamStats carrying only points, one per seeded run.
func pts(points ...int) []TeamStat {
	out := make([]TeamStat, len(points))
	for i, p := range points {
		out[i] = TeamStat{Points: p}
	}
	return out
}

func TestHomeWinFraction(t *testing.T) {
	cases := []struct {
		name      string
		home, vis []TeamStat
		want      float64
	}{
		{"all home wins", pts(110, 100, 95), pts(100, 90, 80), 1.0},
		{"all visitor wins", pts(90, 80), pts(100, 95), 0.0},
		{"three of five home", pts(101, 101, 101, 99, 99), pts(100, 100, 100, 100, 100), 0.6},
		// run0 ties (100==100 → 0.5), run1 home wins (110>100 → 1.0) → 1.5/2.
		{"tie counts half", pts(100, 110), pts(100, 100), 0.75},
		// n==0 guard (unreachable in the harness, runs>=1): no information → 0.5.
		{"empty guard", nil, nil, 0.5},
		// mismatched lengths pair by the shorter (only run0 compared: 110>100).
		{"pairs by min length", pts(110, 90), pts(100), 1.0},
	}
	for _, c := range cases {
		t.Run(c.name, func(t *testing.T) {
			if got := homeWinFraction(c.home, c.vis); got != c.want {
				t.Errorf("homeWinFraction = %v, want %v", got, c.want)
			}
		})
	}
}
