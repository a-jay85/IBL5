// Package sim is the JSB-compatible basketball simulation core.
//
// ⚠️ PLACEHOLDER (PR1 — the "walking skeleton"). This does NOT simulate
// basketball. It emits structurally-valid, deterministic output so the full
// input→output pipeline, the PHP↔Go JSON contract, and the golden-master
// harness are real and tested end-to-end. The actual possession loop, lineup
// selection, energy/fatigue, stat aggregation, injuries, and game-type modes
// are implemented in later PRs (PR3+), each validated against the historical
// .sco corpus by the harness this scaffolding establishes. Do not mistake these
// degenerate stat lines for accurate output.
package sim

import (
	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// Simulate runs the (placeholder) engine over every game in the bundle and
// returns a deterministic Result keyed to the given seed.
func Simulate(b bundle.Bundle, seed uint64) result.Result {
	r := rng.New(seed)
	res := result.Result{Seed: seed, Games: make([]result.GameResult, 0, len(b.Schedule))}
	for _, g := range b.Schedule {
		res.Games = append(res.Games, simGame(b, g, r))
	}
	return res
}

// simGame produces a deterministic placeholder result for one game. The tip-off
// is seed-dependent so that changing the seed genuinely changes output (which
// makes the seed-flow and golden-master tests meaningful), but the stat lines
// themselves are fixed/degenerate.
func simGame(b bundle.Bundle, g bundle.Game, r *rng.RNG) result.GameResult {
	gr := result.GameResult{
		Date:          g.Date,
		HomeTeamID:    g.HomeTeamID,
		VisitorTeamID: g.VisitorTeamID,
		GameOfThatDay: 1,
		SimGameType:   int(g.GameType),
		Events:        []result.Event{},
		PlayerBoxes:   []result.PlayerBox{},
	}

	tipTeam := g.VisitorTeamID
	if r.IntN(2) == 1 {
		tipTeam = g.HomeTeamID
	}
	gr.Events = append(gr.Events, result.Event{
		Kind: result.EventPossessionStart, Period: 1, Clock: 720, TeamID: tipTeam,
	})

	vLines := linesFor(b.Players, g.VisitorTeamID)
	hLines := linesFor(b.Players, g.HomeTeamID)

	// One representative scoring sequence so the event stream exercises the
	// shot_attempt/shot_make kinds and the ShotType field.
	if len(vLines) > 0 {
		pid := vLines[0].PID
		gr.Events = append(gr.Events,
			result.Event{Kind: result.EventShotAttempt, Period: 1, Clock: 700, TeamID: g.VisitorTeamID, PlayerID: pid, ShotType: result.ShotTwoPoint},
			result.Event{Kind: result.EventShotMake, Period: 1, Clock: 700, TeamID: g.VisitorTeamID, PlayerID: pid, ShotType: result.ShotTwoPoint},
		)
	}
	gr.Events = append(gr.Events, result.Event{Kind: result.EventPeriodBoundary, Period: 4, Clock: 0})

	gr.PlayerBoxes = append(gr.PlayerBoxes, vLines...)
	gr.PlayerBoxes = append(gr.PlayerBoxes, hLines...)
	gr.TeamBoxes = []result.TeamBox{
		teamBox(g.VisitorTeamID, false, vLines),
		teamBox(g.HomeTeamID, true, hLines),
	}
	return gr
}

// linesFor returns placeholder stat lines for every player on the given team,
// in bundle order (so output is deterministic).
func linesFor(players []bundle.Player, teamID int) []result.PlayerBox {
	out := []result.PlayerBox{}
	for _, p := range players {
		if p.TeamID == teamID {
			out = append(out, placeholderLine(p))
		}
	}
	return out
}

// placeholderLine returns a degenerate, deterministic stat line. A player
// flagged dc_can_play_in_game == 0 gets a DNP line (GameMIN == 0, all stats
// zero).
func placeholderLine(p bundle.Player) result.PlayerBox {
	box := result.PlayerBox{PID: p.PID, Pos: posOf(p)}
	if p.DCCanPlayInGame == 0 {
		return box // DNP
	}
	min := p.DCMinutes
	if min < 0 {
		min = 0
	}
	if min > 48 {
		min = 48
	}
	box.GameMIN = min
	box.Game2GM, box.Game2GA = 1, 2
	box.Game3GM, box.Game3GA = 0, 1
	box.GameFTM, box.GameFTA = 1, 2
	box.GameORB, box.GameDRB = 1, 2
	box.GameAST, box.GameSTL, box.GameTOV, box.GameBLK, box.GamePF = 1, 1, 1, 0, 1
	return box
}

// posOf picks the position with the best (lowest, 1=starter) depth-chart slot.
// A slot value of 0 or below means the player is not in the rotation there.
func posOf(p bundle.Player) string {
	slots := []struct {
		pos   string
		depth int
	}{
		{"PG", p.DCPGDepth}, {"SG", p.DCSGDepth}, {"SF", p.DCSFDepth},
		{"PF", p.DCPFDepth}, {"C", p.DCCDepth},
	}
	best := ""
	bestDepth := 0
	for _, s := range slots {
		if s.depth <= 0 {
			continue
		}
		if best == "" || s.depth < bestDepth {
			best, bestDepth = s.pos, s.depth
		}
	}
	return best
}

// teamBox sums the player lines into team totals and splits points across
// quarters (placeholder: even split, remainder into Q4, no overtime).
func teamBox(teamID int, isHome bool, lines []result.PlayerBox) result.TeamBox {
	tb := result.TeamBox{TeamID: teamID, IsHome: isHome, OT: []int{}}
	for _, l := range lines {
		tb.Game2GM += l.Game2GM
		tb.Game2GA += l.Game2GA
		tb.GameFTM += l.GameFTM
		tb.GameFTA += l.GameFTA
		tb.Game3GM += l.Game3GM
		tb.Game3GA += l.Game3GA
		tb.GameORB += l.GameORB
		tb.GameDRB += l.GameDRB
		tb.GameAST += l.GameAST
		tb.GameSTL += l.GameSTL
		tb.GameTOV += l.GameTOV
		tb.GameBLK += l.GameBLK
		tb.GamePF += l.GamePF
	}
	pts := 2*tb.Game2GM + tb.GameFTM + 3*tb.Game3GM
	q := pts / 4
	tb.Q1, tb.Q2, tb.Q3 = q, q, q
	tb.Q4 = pts - 3*q
	return tb
}
