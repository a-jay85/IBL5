package validate

import (
	"math"
	"sort"

	"github.com/a-jay85/IBL5/engine/internal/backup"
)

// statDef names one of the 13 counting stats Check A reconciles.
type statDef struct {
	Name string
	Get  func(b backup.ScoBox) int
}

func countingStats() []statDef {
	return []statDef{
		{"TwoGM", func(b backup.ScoBox) int { return b.TwoGM }},
		{"TwoGA", func(b backup.ScoBox) int { return b.TwoGA }},
		{"FTM", func(b backup.ScoBox) int { return b.FTM }},
		{"FTA", func(b backup.ScoBox) int { return b.FTA }},
		{"ThreeGM", func(b backup.ScoBox) int { return b.ThreeGM }},
		{"ThreeGA", func(b backup.ScoBox) int { return b.ThreeGA }},
		{"ORB", func(b backup.ScoBox) int { return b.ORB }},
		{"DRB", func(b backup.ScoBox) int { return b.DRB }},
		{"AST", func(b backup.ScoBox) int { return b.AST }},
		{"STL", func(b backup.ScoBox) int { return b.STL }},
		{"TOV", func(b backup.ScoBox) int { return b.TOV }},
		{"BLK", func(b backup.ScoBox) int { return b.BLK }},
		{"PF", func(b backup.ScoBox) int { return b.PF }},
	}
}

// AMismatch records a team-total vs player-sum discrepancy for one stat in one game.
type AMismatch struct {
	GameIndex int
	TeamID    int
	Stat      string
	TeamTotal int // value on the PlayerID==0 row
	PlayerSum int // sum over PlayerID>0 rows
	Delta     int // PlayerSum - TeamTotal
}

// ANegative records a negative counting stat on a player row.
type ANegative struct {
	GameIndex, TeamID, PlayerID int
	Name, Stat                  string
	Value                       int
}

// ADominance records a player whose share of team points exceeds 60%.
type ADominance struct {
	GameIndex, TeamID, PlayerID int
	Name                        string
	PlayerPoints, TeamPoints    int
	Share                       float64 // PlayerPoints / TeamPoints
}

// AReport is the aggregate result of CheckA over a corpus.
type AReport struct {
	Games       int
	GamesPassed int // no mismatch AND no negative (dominance is a flag, not a fail)
	Mismatches  []AMismatch
	Negatives   []ANegative
	Dominances  []ADominance
}

// ScoPoints returns points scored in a box: 2-pointers, 3-pointers, free throws.
func ScoPoints(b backup.ScoBox) int { return b.TwoGM*2 + b.ThreeGM*3 + b.FTM }

// CheckA reconciles each game's player rows against its team-total row and
// flags negative stats and single-player scoring dominance (>60% of team points).
func CheckA(games []backup.ScoGame) AReport {
	if len(games) == 0 {
		return AReport{}
	}
	stats := countingStats()
	var rep AReport
	for gi, game := range games {
		rep.Games++
		// Partition boxes by team.
		type teamRows struct {
			total   *backup.ScoBox
			players []backup.ScoBox
		}
		teams := map[int]*teamRows{}
		for i := range game.Boxes {
			box := game.Boxes[i]
			tr := teams[box.TeamID]
			if tr == nil {
				tr = &teamRows{}
				teams[box.TeamID] = tr
			}
			if box.PlayerID == 0 {
				b := box
				tr.total = &b
			} else {
				tr.players = append(tr.players, box)
			}
		}
		gameFailed := false
		for _, tr := range teams {
			teamID := 0
			if len(tr.players) > 0 {
				teamID = tr.players[0].TeamID
			} else if tr.total != nil {
				teamID = tr.total.TeamID
			}
			// Summation check.
			for _, sd := range stats {
				playerSum := 0
				for _, p := range tr.players {
					playerSum += sd.Get(p)
				}
				if tr.total == nil {
					// Missing team-total row with data: surface only when playerSum != 0.
					// A genuinely empty team (all zero stats) stays silent.
					if playerSum != 0 {
						rep.Mismatches = append(rep.Mismatches, AMismatch{
							GameIndex: gi, TeamID: teamID, Stat: sd.Name,
							TeamTotal: 0, PlayerSum: playerSum, Delta: playerSum,
						})
						gameFailed = true
					}
				} else {
					teamTotal := sd.Get(*tr.total)
					if playerSum != teamTotal {
						rep.Mismatches = append(rep.Mismatches, AMismatch{
							GameIndex: gi, TeamID: teamID, Stat: sd.Name,
							TeamTotal: teamTotal, PlayerSum: playerSum,
							Delta: playerSum - teamTotal,
						})
						gameFailed = true
					}
				}
			}
			// Negative check.
			for _, p := range tr.players {
				for _, sd := range stats {
					v := sd.Get(p)
					if v < 0 {
						rep.Negatives = append(rep.Negatives, ANegative{
							GameIndex: gi, TeamID: p.TeamID, PlayerID: p.PlayerID,
							Name: p.Name, Stat: sd.Name, Value: v,
						})
						gameFailed = true
					}
				}
			}
			// Dominance flag (not a failure).
			teamPoints := 0
			for _, p := range tr.players {
				teamPoints += ScoPoints(p)
			}
			if teamPoints > 0 {
				for _, p := range tr.players {
					pp := ScoPoints(p)
					share := float64(pp) / float64(teamPoints)
					if share > 0.60 {
						rep.Dominances = append(rep.Dominances, ADominance{
							GameIndex: gi, TeamID: p.TeamID, PlayerID: p.PlayerID,
							Name: p.Name, PlayerPoints: pp, TeamPoints: teamPoints,
							Share: share,
						})
					}
				}
			}
		}
		if !gameFailed {
			rep.GamesPassed++
		}
	}
	return rep
}

// -- Check B --

// BNegative records a team-season with a negative rank correlation.
type BNegative struct {
	TeamID int
	Rho    float64
	N      int // rankable players on the team
}

// BReport summarizes the distribution of per-team scoring-rank correlations.
type BReport struct {
	TeamSeasons       int       // teams that yielded a finite rho
	Correlations      []float64 // one finite rho per qualifying team
	Mean              float64
	StdDev            float64  // population std dev of Correlations
	FractionAboveHalf float64  // fraction with rho > 0.5
	NegativeTeams     []BNegative
	Skipped           int     // teams dropped: < minPlayers, or degenerate (zero variance)
	MaxPlayerAvg      float64 // max per-player .sco scoring average (cumulative-misread guardrail)
}

// scoringProxy is a monotone estimate of a player's expected points built from
// the only scoring ratings the bundle exposes. Ranks are scale-invariant —
// the only thing Spearman consumes.
func scoringProxy(p backup.PlrPlayer) float64 {
	return float64(p.RatingFGA*p.RatingFGP*2 + p.Rating3GA*p.Rating3GP + p.RatingFTA*p.RatingFTP)
}

// rank returns 1-based average ranks for v, averaging over ties.
func rank(v []float64) []float64 {
	n := len(v)
	idx := make([]int, n)
	for i := range idx {
		idx[i] = i
	}
	sort.Slice(idx, func(a, b int) bool { return v[idx[a]] < v[idx[b]] })
	ranks := make([]float64, n)
	for i := 0; i < n; {
		j := i + 1
		for j < n && v[idx[j]] == v[idx[i]] {
			j++
		}
		avg := float64(i+1+j) / 2.0 // average of 1-based positions i+1 .. j
		for k := i; k < j; k++ {
			ranks[idx[k]] = avg
		}
		i = j
	}
	return ranks
}

// spearman returns the Spearman rank correlation of x and y (equal length).
// Returns NaN if either variable has zero rank-variance (all tied).
func spearman(x, y []float64) float64 {
	rx := rank(x)
	ry := rank(y)
	return pearson(rx, ry)
}

func pearson(x, y []float64) float64 {
	n := float64(len(x))
	if n == 0 {
		return math.NaN()
	}
	var mx, my float64
	for i := range x {
		mx += x[i]
		my += y[i]
	}
	mx /= n
	my /= n
	var num, dx2, dy2 float64
	for i := range x {
		dx := x[i] - mx
		dy := y[i] - my
		num += dx * dy
		dx2 += dx * dx
		dy2 += dy * dy
	}
	if dx2 == 0 || dy2 == 0 {
		return math.NaN()
	}
	return num / math.Sqrt(dx2*dy2)
}

// CheckB correlates, per team, each player's scoring-rating rank against their
// per-game .sco scoring-average rank, then summarizes the distribution.
// minPlayers is the minimum rankable players a team needs (recommended: 5).
func CheckB(players []backup.PlrPlayer, games []backup.ScoGame, minPlayers int) BReport {
	type key struct{ team, pid int }

	// Build per-player .sco scoring averages (avg points per game where Min > 0).
	type acc struct{ sum, gp int }
	scoAcc := map[key]*acc{}
	for _, game := range games {
		for _, box := range game.Boxes {
			if box.PlayerID <= 0 || box.Min <= 0 {
				continue
			}
			k := key{box.TeamID, box.PlayerID}
			a := scoAcc[k]
			if a == nil {
				a = &acc{}
				scoAcc[k] = a
			}
			a.sum += ScoPoints(box)
			a.gp++
		}
	}
	scoAvg := make(map[key]float64, len(scoAcc))
	var maxAvg float64
	for k, a := range scoAcc {
		if a.gp > 0 {
			avg := float64(a.sum) / float64(a.gp)
			scoAvg[k] = avg
			if avg > maxAvg {
				maxAvg = avg
			}
		}
	}

	// Build rating proxies.
	proxy := make(map[key]float64, len(players))
	for _, p := range players {
		proxy[key{p.TeamID, p.PID}] = scoringProxy(p)
	}

	// Collect team IDs.
	teamSet := map[int]bool{}
	for k := range proxy {
		teamSet[k.team] = true
	}
	teamIDs := make([]int, 0, len(teamSet))
	for tid := range teamSet {
		teamIDs = append(teamIDs, tid)
	}
	sort.Ints(teamIDs)

	var rep BReport
	rep.MaxPlayerAvg = maxAvg

	for _, tid := range teamIDs {
		var xs, ys []float64
		for k, px := range proxy {
			if k.team != tid {
				continue
			}
			avg, ok := scoAvg[k]
			if !ok {
				continue
			}
			xs = append(xs, px)
			ys = append(ys, avg)
		}
		if len(xs) < minPlayers {
			rep.Skipped++
			continue
		}
		rho := spearman(xs, ys)
		if math.IsNaN(rho) {
			rep.Skipped++
			continue
		}
		rep.Correlations = append(rep.Correlations, rho)
		rep.TeamSeasons++
		if rho < 0 {
			rep.NegativeTeams = append(rep.NegativeTeams, BNegative{TeamID: tid, Rho: rho, N: len(xs)})
		}
	}

	if rep.TeamSeasons == 0 {
		return rep
	}

	// Compute summary statistics.
	var sum float64
	var aboveHalf int
	for _, rho := range rep.Correlations {
		sum += rho
		if rho > 0.5 {
			aboveHalf++
		}
	}
	rep.Mean = sum / float64(rep.TeamSeasons)
	var varSum float64
	for _, rho := range rep.Correlations {
		d := rho - rep.Mean
		varSum += d * d
	}
	rep.StdDev = math.Sqrt(varSum / float64(rep.TeamSeasons))
	rep.FractionAboveHalf = float64(aboveHalf) / float64(rep.TeamSeasons)
	return rep
}
