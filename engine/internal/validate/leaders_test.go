package validate

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/backup"
)

// boxOf constructs a ScoBox for test cases.
func boxOf(team, pid, twoGM, threeGM, ftm int) backup.ScoBox {
	return backup.ScoBox{
		TeamID:   team,
		PlayerID: pid,
		TwoGM:    twoGM,
		ThreeGM:  threeGM,
		FTM:      ftm,
		Min:      20,
	}
}

// boxFull constructs a ScoBox with all 13 counting stats set.
func boxFull(team, pid int, vals [13]int) backup.ScoBox {
	return backup.ScoBox{
		TeamID:   team,
		PlayerID: pid,
		Min:      20,
		TwoGM:    vals[0],
		TwoGA:    vals[1],
		FTM:      vals[2],
		FTA:      vals[3],
		ThreeGM:  vals[4],
		ThreeGA:  vals[5],
		ORB:      vals[6],
		DRB:      vals[7],
		AST:      vals[8],
		STL:      vals[9],
		TOV:      vals[10],
		BLK:      vals[11],
		PF:       vals[12],
	}
}

// gameOf wraps boxes into a ScoGame.
func gameOf(boxes ...backup.ScoBox) backup.ScoGame {
	return backup.ScoGame{Boxes: boxes}
}

// sumVals sums the 13 counting stat values for a team-total row.
func sumVals(a, b [13]int) [13]int {
	var out [13]int
	for i := range out {
		out[i] = a[i] + b[i]
	}
	return out
}

func TestCheckA(t *testing.T) {
	t.Run("happy_consistent", func(t *testing.T) {
		// Two players per team, team-total == sum of players.
		p1v := [13]int{3, 6, 2, 3, 1, 2, 1, 2, 3, 1, 2, 0, 2}
		p2v := [13]int{2, 4, 1, 2, 0, 1, 0, 1, 2, 0, 1, 1, 1}
		tv := sumVals(p1v, p2v)
		p3v := [13]int{4, 8, 3, 4, 2, 3, 2, 3, 4, 2, 3, 1, 3}
		p4v := [13]int{1, 2, 0, 1, 0, 0, 1, 0, 1, 0, 0, 0, 1}
		tv2 := sumVals(p3v, p4v)
		game := gameOf(
			boxFull(1, 0, tv), boxFull(1, 1, p1v), boxFull(1, 2, p2v),
			boxFull(2, 0, tv2), boxFull(2, 3, p3v), boxFull(2, 4, p4v),
		)
		rep := CheckA([]backup.ScoGame{game})
		if rep.Games != 1 {
			t.Fatalf("Games=%d want 1", rep.Games)
		}
		if rep.GamesPassed != 1 {
			t.Fatalf("GamesPassed=%d want 1", rep.GamesPassed)
		}
		if len(rep.Mismatches) != 0 {
			t.Fatalf("Mismatches=%v want none", rep.Mismatches)
		}
		if len(rep.Negatives) != 0 {
			t.Fatalf("Negatives=%v want none", rep.Negatives)
		}
	})

	t.Run("mismatch_single_stat", func(t *testing.T) {
		p1v := [13]int{3, 6, 2, 3, 1, 2, 1, 2, 5, 1, 2, 0, 2} // AST=5
		p2v := [13]int{2, 4, 1, 2, 0, 1, 0, 1, 3, 0, 1, 1, 1} // AST=3
		tv := sumVals(p1v, p2v)
		// Bump team-total AST by 1 (index 8).
		tv[8]++
		game := gameOf(
			boxFull(1, 0, tv), boxFull(1, 1, p1v), boxFull(1, 2, p2v),
		)
		rep := CheckA([]backup.ScoGame{game})
		if rep.GamesPassed != 0 {
			t.Fatalf("GamesPassed=%d want 0", rep.GamesPassed)
		}
		astMismatches := 0
		for _, m := range rep.Mismatches {
			if m.Stat == "AST" {
				astMismatches++
				// Delta = PlayerSum - TeamTotal = 8 - 9 = -1
				if m.Delta != -1 {
					t.Errorf("AST Delta=%d want -1", m.Delta)
				}
			}
		}
		if astMismatches != 1 {
			t.Fatalf("AST mismatch count=%d want 1; all mismatches: %v", astMismatches, rep.Mismatches)
		}
	})

	t.Run("negative_stat", func(t *testing.T) {
		p1v := [13]int{3, 6, 2, 3, 1, 2, 1, 2, 3, -1, 2, 0, 2} // STL=-1
		p2v := [13]int{2, 4, 1, 2, 0, 1, 0, 1, 2, 2, 1, 1, 1}
		tv := sumVals(p1v, p2v)
		// Correct the team total for STL (use the raw sum which includes -1).
		// tv[9] = -1+2 = 1 — leave it so no mismatch, only the negative check fires.
		game := gameOf(
			boxFull(1, 0, tv), boxFull(1, 1, p1v), boxFull(1, 2, p2v),
		)
		rep := CheckA([]backup.ScoGame{game})
		if rep.GamesPassed != 0 {
			t.Fatalf("GamesPassed=%d want 0", rep.GamesPassed)
		}
		if len(rep.Negatives) != 1 {
			t.Fatalf("Negatives=%v want 1", rep.Negatives)
		}
		if rep.Negatives[0].Stat != "STL" || rep.Negatives[0].Value != -1 {
			t.Errorf("Negative=%+v want STL=-1", rep.Negatives[0])
		}
	})

	t.Run("dominance_over_threshold", func(t *testing.T) {
		// Player scores 7 pts (2+2+2+1=7), team 11 pts — 7/11 = 0.636 > 0.60.
		// Player 1: 3*2=6 2pt pts, 0 3pt, 1 FT = 7 total
		// Player 2: 0 2pt, 0 3pt, 4 FT = 4 total
		// Team total = 11
		p1 := backup.ScoBox{TeamID: 1, PlayerID: 1, TwoGM: 3, ThreeGM: 0, FTM: 1, Min: 20}
		p2 := backup.ScoBox{TeamID: 1, PlayerID: 2, TwoGM: 0, ThreeGM: 0, FTM: 4, Min: 20}
		// Team total row with matching stats.
		tot := backup.ScoBox{TeamID: 1, PlayerID: 0, TwoGM: 3, ThreeGM: 0, FTM: 5}
		game := gameOf(tot, p1, p2)
		rep := CheckA([]backup.ScoGame{game})
		// Game still passes (dominance is a flag, not a fail).
		if rep.GamesPassed != 1 {
			t.Fatalf("GamesPassed=%d want 1", rep.GamesPassed)
		}
		if len(rep.Dominances) != 1 {
			t.Fatalf("Dominances=%v want 1", rep.Dominances)
		}
		if rep.Dominances[0].Share <= 0.60 {
			t.Errorf("Share=%f want >0.60", rep.Dominances[0].Share)
		}
	})

	t.Run("dominance_boundary_exactly_60pct", func(t *testing.T) {
		// Player 1: 6 pts (TwoGM=3), Player 2: 4 pts (FTM=4) — 6/10 = 0.60 exactly.
		p1 := backup.ScoBox{TeamID: 1, PlayerID: 1, TwoGM: 3, Min: 20}
		p2 := backup.ScoBox{TeamID: 1, PlayerID: 2, FTM: 4, Min: 20}
		tot := backup.ScoBox{TeamID: 1, PlayerID: 0, TwoGM: 3, FTM: 4}
		game := gameOf(tot, p1, p2)
		rep := CheckA([]backup.ScoGame{game})
		if rep.GamesPassed != 1 {
			t.Fatalf("GamesPassed=%d want 1", rep.GamesPassed)
		}
		if len(rep.Dominances) != 0 {
			t.Fatalf("Dominances=%v want none at exactly 60%%", rep.Dominances)
		}
	})

	t.Run("missing_team_total_with_data", func(t *testing.T) {
		// No PlayerID==0 row; player rows have non-zero stats.
		p1 := backup.ScoBox{TeamID: 1, PlayerID: 1, TwoGM: 3, AST: 2, Min: 20}
		p2 := backup.ScoBox{TeamID: 1, PlayerID: 2, TwoGM: 2, AST: 1, Min: 20}
		game := gameOf(p1, p2)
		rep := CheckA([]backup.ScoGame{game})
		if rep.GamesPassed != 0 {
			t.Fatalf("GamesPassed=%d want 0", rep.GamesPassed)
		}
		if len(rep.Mismatches) == 0 {
			t.Fatal("want mismatches for missing team-total with data")
		}
	})

	t.Run("empty_and_nil", func(t *testing.T) {
		r1 := CheckA(nil)
		if r1.Games != 0 {
			t.Errorf("nil: Games=%d want 0", r1.Games)
		}
		r2 := CheckA([]backup.ScoGame{})
		if r2.Games != 0 {
			t.Errorf("empty: Games=%d want 0", r2.Games)
		}
	})
}

func TestSpearman(t *testing.T) {
	t.Run("perfect_positive", func(t *testing.T) {
		got := spearman([]float64{1, 2, 3}, []float64{1, 2, 3})
		if math.Abs(got-1.0) > 1e-9 {
			t.Errorf("got %v want 1.0", got)
		}
	})
	t.Run("perfect_negative", func(t *testing.T) {
		got := spearman([]float64{1, 2, 3}, []float64{3, 2, 1})
		if math.Abs(got-(-1.0)) > 1e-9 {
			t.Errorf("got %v want -1.0", got)
		}
	})
	t.Run("tie", func(t *testing.T) {
		// x=[1,1,2], y=[5,6,7]. x-ranks=[1.5,1.5,3], y-ranks=[1,2,3].
		// Pre-derived: rho = sqrt(3)/2 ≈ 0.8660254
		got := spearman([]float64{1, 1, 2}, []float64{5, 6, 7})
		want := math.Sqrt(3) / 2
		if math.Abs(got-want) > 1e-9 {
			t.Errorf("got %v want sqrt(3)/2≈%v", got, want)
		}
	})
	t.Run("zero_variance", func(t *testing.T) {
		got := spearman([]float64{2, 2, 2}, []float64{1, 2, 3})
		if !math.IsNaN(got) {
			t.Errorf("got %v want NaN", got)
		}
	})
}

// plrOf constructs a PlrPlayer with monotone scoring ratings.
func plrOf(pid, teamID, fga, fgp, tga, tgp, fta, ftp int) backup.PlrPlayer {
	return backup.PlrPlayer{
		PID: pid, TeamID: teamID,
		RatingFGA: fga, RatingFGP: fgp,
		Rating3GA: tga, Rating3GP: tgp,
		RatingFTA: fta, RatingFTP: ftp,
	}
}

func TestCheckB(t *testing.T) {
	t.Run("perfect_positive", func(t *testing.T) {
		// 5 players: proxy ranks match scoAvg ranks exactly.
		players := []backup.PlrPlayer{
			plrOf(1, 10, 10, 50, 2, 30, 5, 70), // proxy = 10*50*2+2*30+5*70 = 1000+60+350=1410
			plrOf(2, 10, 8, 50, 2, 30, 4, 70),  // proxy ≈ 800+60+280=1140
			plrOf(3, 10, 6, 50, 2, 30, 3, 70),  // proxy ≈ 600+60+210=870
			plrOf(4, 10, 4, 50, 2, 30, 2, 70),  // proxy ≈ 400+60+140=600
			plrOf(5, 10, 2, 50, 2, 30, 1, 70),  // proxy ≈ 200+60+70=330
		}
		// Build games where scoring avg matches proxy order.
		boxes := []backup.ScoBox{
			{TeamID: 10, PlayerID: 1, TwoGM: 10, Min: 20},
			{TeamID: 10, PlayerID: 2, TwoGM: 8, Min: 20},
			{TeamID: 10, PlayerID: 3, TwoGM: 6, Min: 20},
			{TeamID: 10, PlayerID: 4, TwoGM: 4, Min: 20},
			{TeamID: 10, PlayerID: 5, TwoGM: 2, Min: 20},
		}
		games := []backup.ScoGame{{Boxes: boxes}}
		rep := CheckB(players, games, 5)
		if rep.TeamSeasons != 1 {
			t.Fatalf("TeamSeasons=%d want 1", rep.TeamSeasons)
		}
		if math.Abs(rep.Correlations[0]-1.0) > 1e-9 {
			t.Errorf("rho=%v want 1.0", rep.Correlations[0])
		}
		if math.Abs(rep.FractionAboveHalf-1.0) > 1e-9 {
			t.Errorf("FractionAboveHalf=%v want 1.0", rep.FractionAboveHalf)
		}
		if len(rep.NegativeTeams) != 0 {
			t.Errorf("NegativeTeams=%v want none", rep.NegativeTeams)
		}
	})

	t.Run("perfect_negative", func(t *testing.T) {
		// Proxy order is the reverse of scoAvg order.
		players := []backup.PlrPlayer{
			plrOf(1, 10, 10, 50, 0, 0, 0, 0), // highest proxy
			plrOf(2, 10, 8, 50, 0, 0, 0, 0),
			plrOf(3, 10, 6, 50, 0, 0, 0, 0),
			plrOf(4, 10, 4, 50, 0, 0, 0, 0),
			plrOf(5, 10, 2, 50, 0, 0, 0, 0), // lowest proxy
		}
		// scoAvg order: pid=5 highest scorer, pid=1 lowest.
		boxes := []backup.ScoBox{
			{TeamID: 10, PlayerID: 1, TwoGM: 1, Min: 20}, // lowest avg
			{TeamID: 10, PlayerID: 2, TwoGM: 2, Min: 20},
			{TeamID: 10, PlayerID: 3, TwoGM: 3, Min: 20},
			{TeamID: 10, PlayerID: 4, TwoGM: 4, Min: 20},
			{TeamID: 10, PlayerID: 5, TwoGM: 5, Min: 20}, // highest avg
		}
		games := []backup.ScoGame{{Boxes: boxes}}
		rep := CheckB(players, games, 5)
		if rep.TeamSeasons != 1 {
			t.Fatalf("TeamSeasons=%d want 1", rep.TeamSeasons)
		}
		if math.Abs(rep.Correlations[0]-(-1.0)) > 1e-9 {
			t.Errorf("rho=%v want -1.0", rep.Correlations[0])
		}
		if len(rep.NegativeTeams) != 1 {
			t.Fatalf("NegativeTeams=%v want 1", rep.NegativeTeams)
		}
	})

	t.Run("too_few_players", func(t *testing.T) {
		players := []backup.PlrPlayer{
			plrOf(1, 10, 10, 50, 0, 0, 0, 0),
			plrOf(2, 10, 8, 50, 0, 0, 0, 0),
			plrOf(3, 10, 6, 50, 0, 0, 0, 0),
			plrOf(4, 10, 4, 50, 0, 0, 0, 0), // only 4 rankable
		}
		boxes := []backup.ScoBox{
			{TeamID: 10, PlayerID: 1, TwoGM: 4, Min: 20},
			{TeamID: 10, PlayerID: 2, TwoGM: 3, Min: 20},
			{TeamID: 10, PlayerID: 3, TwoGM: 2, Min: 20},
			{TeamID: 10, PlayerID: 4, TwoGM: 1, Min: 20},
		}
		games := []backup.ScoGame{{Boxes: boxes}}
		rep := CheckB(players, games, 5)
		if rep.TeamSeasons != 0 {
			t.Errorf("TeamSeasons=%d want 0", rep.TeamSeasons)
		}
		if rep.Skipped < 1 {
			t.Errorf("Skipped=%d want >=1", rep.Skipped)
		}
	})

	t.Run("degenerate_ties", func(t *testing.T) {
		// All players have identical ratings — proxy ties → spearman NaN → skipped.
		players := []backup.PlrPlayer{
			plrOf(1, 10, 5, 50, 0, 0, 0, 0),
			plrOf(2, 10, 5, 50, 0, 0, 0, 0),
			plrOf(3, 10, 5, 50, 0, 0, 0, 0),
			plrOf(4, 10, 5, 50, 0, 0, 0, 0),
			plrOf(5, 10, 5, 50, 0, 0, 0, 0),
		}
		boxes := []backup.ScoBox{
			{TeamID: 10, PlayerID: 1, TwoGM: 5, Min: 20},
			{TeamID: 10, PlayerID: 2, TwoGM: 4, Min: 20},
			{TeamID: 10, PlayerID: 3, TwoGM: 3, Min: 20},
			{TeamID: 10, PlayerID: 4, TwoGM: 2, Min: 20},
			{TeamID: 10, PlayerID: 5, TwoGM: 1, Min: 20},
		}
		games := []backup.ScoGame{{Boxes: boxes}}
		rep := CheckB(players, games, 5)
		if rep.TeamSeasons != 0 {
			t.Errorf("TeamSeasons=%d want 0", rep.TeamSeasons)
		}
		if rep.Skipped < 1 {
			t.Errorf("Skipped=%d want >=1 (degenerate NaN)", rep.Skipped)
		}
	})

	t.Run("unmatched_pid_excluded", func(t *testing.T) {
		// pid=99 in .sco but not in players; pid=88 in players but not in .sco.
		// Correlation computed only over the 5-player intersection.
		players := []backup.PlrPlayer{
			plrOf(1, 10, 10, 50, 0, 0, 0, 0),
			plrOf(2, 10, 8, 50, 0, 0, 0, 0),
			plrOf(3, 10, 6, 50, 0, 0, 0, 0),
			plrOf(4, 10, 4, 50, 0, 0, 0, 0),
			plrOf(5, 10, 2, 50, 0, 0, 0, 0),
			plrOf(88, 10, 1, 50, 0, 0, 0, 0), // in players, absent from .sco
		}
		boxes := []backup.ScoBox{
			{TeamID: 10, PlayerID: 1, TwoGM: 5, Min: 20},
			{TeamID: 10, PlayerID: 2, TwoGM: 4, Min: 20},
			{TeamID: 10, PlayerID: 3, TwoGM: 3, Min: 20},
			{TeamID: 10, PlayerID: 4, TwoGM: 2, Min: 20},
			{TeamID: 10, PlayerID: 5, TwoGM: 1, Min: 20},
			{TeamID: 10, PlayerID: 99, TwoGM: 6, Min: 20}, // in .sco, absent from players
		}
		games := []backup.ScoGame{{Boxes: boxes}}
		rep := CheckB(players, games, 5)
		// Should get exactly 1 team-season from the 5-player intersection.
		if rep.TeamSeasons != 1 {
			t.Errorf("TeamSeasons=%d want 1 (intersection of 5)", rep.TeamSeasons)
		}
	})

	t.Run("min_zero_excluded", func(t *testing.T) {
		// pid=6 appears only with Min==0 — must be excluded from scoAvg map.
		players := []backup.PlrPlayer{
			plrOf(1, 10, 10, 50, 0, 0, 0, 0),
			plrOf(2, 10, 8, 50, 0, 0, 0, 0),
			plrOf(3, 10, 6, 50, 0, 0, 0, 0),
			plrOf(4, 10, 4, 50, 0, 0, 0, 0),
			plrOf(5, 10, 2, 50, 0, 0, 0, 0),
			plrOf(6, 10, 1, 50, 0, 0, 0, 0), // will appear only in Min==0 boxes
		}
		boxes := []backup.ScoBox{
			{TeamID: 10, PlayerID: 1, TwoGM: 5, Min: 20},
			{TeamID: 10, PlayerID: 2, TwoGM: 4, Min: 20},
			{TeamID: 10, PlayerID: 3, TwoGM: 3, Min: 20},
			{TeamID: 10, PlayerID: 4, TwoGM: 2, Min: 20},
			{TeamID: 10, PlayerID: 5, TwoGM: 1, Min: 20},
			{TeamID: 10, PlayerID: 6, TwoGM: 9, Min: 0}, // Min==0 → excluded
		}
		games := []backup.ScoGame{{Boxes: boxes}}
		rep := CheckB(players, games, 5)
		// pid=6 excluded → only 5-player intersection → 1 team-season.
		if rep.TeamSeasons != 1 {
			t.Errorf("TeamSeasons=%d want 1", rep.TeamSeasons)
		}
	})

	t.Run("distribution_math", func(t *testing.T) {
		// Two teams: team 10 → perfect positive (rho=1.0), team 20 → perfect negative (rho=-1.0).
		// Expected: Mean=0.0, StdDev=1.0, FractionAboveHalf=0.5 (only team 10 is >0.5).
		players := []backup.PlrPlayer{
			// team 10: ascending proxy
			plrOf(1, 10, 10, 50, 0, 0, 0, 0),
			plrOf(2, 10, 8, 50, 0, 0, 0, 0),
			plrOf(3, 10, 6, 50, 0, 0, 0, 0),
			plrOf(4, 10, 4, 50, 0, 0, 0, 0),
			plrOf(5, 10, 2, 50, 0, 0, 0, 0),
			// team 20: ascending proxy
			plrOf(11, 20, 10, 50, 0, 0, 0, 0),
			plrOf(12, 20, 8, 50, 0, 0, 0, 0),
			plrOf(13, 20, 6, 50, 0, 0, 0, 0),
			plrOf(14, 20, 4, 50, 0, 0, 0, 0),
			plrOf(15, 20, 2, 50, 0, 0, 0, 0),
		}
		boxes := []backup.ScoBox{
			// team 10: scoAvg matches proxy order → rho=1
			{TeamID: 10, PlayerID: 1, TwoGM: 5, Min: 20},
			{TeamID: 10, PlayerID: 2, TwoGM: 4, Min: 20},
			{TeamID: 10, PlayerID: 3, TwoGM: 3, Min: 20},
			{TeamID: 10, PlayerID: 4, TwoGM: 2, Min: 20},
			{TeamID: 10, PlayerID: 5, TwoGM: 1, Min: 20},
			// team 20: scoAvg reverses proxy order → rho=-1
			{TeamID: 20, PlayerID: 11, TwoGM: 1, Min: 20},
			{TeamID: 20, PlayerID: 12, TwoGM: 2, Min: 20},
			{TeamID: 20, PlayerID: 13, TwoGM: 3, Min: 20},
			{TeamID: 20, PlayerID: 14, TwoGM: 4, Min: 20},
			{TeamID: 20, PlayerID: 15, TwoGM: 5, Min: 20},
		}
		games := []backup.ScoGame{{Boxes: boxes}}
		rep := CheckB(players, games, 5)
		if rep.TeamSeasons != 2 {
			t.Fatalf("TeamSeasons=%d want 2", rep.TeamSeasons)
		}
		if math.Abs(rep.Mean-0.0) > 1e-9 {
			t.Errorf("Mean=%v want 0.0", rep.Mean)
		}
		if math.Abs(rep.StdDev-1.0) > 1e-9 {
			t.Errorf("StdDev=%v want 1.0", rep.StdDev)
		}
		if math.Abs(rep.FractionAboveHalf-0.5) > 1e-9 {
			t.Errorf("FractionAboveHalf=%v want 0.5", rep.FractionAboveHalf)
		}
	})
}
