// Package validate is the offline distributional fidelity harness (PR9b). It
// takes a JSB 5.60 backup triple (.plr/.sch/.sco, read via the backup package
// from PR9a), runs the native engine N seeded times per corpus game, aggregates
// per-team/per-stat distributions, and compares the observed engine means
// against the .sco ground-truth aggregates within tolerance bands.
//
// The bar is statistical, not byte-exact: the Go engine's RNG differs from
// jumpshot.exe, so a single game never reproduces a specific .sco line. A stat
// PASSES when the .sco value lies inside the tolerance band around the observed
// engine mean (see compare.go / bands.go). The whole harness is reproducible
// from a base seed.
//
// The aggregation/comparison/driver code in this file (and bands.go, compare.go,
// harness.go) is normally compiled and unit-tested by `go test ./...`. Only the
// large real-corpus suite (validate_test.go) is build-tag-gated behind
// `//go:build validate`.
package validate

import (
	"github.com/a-jay85/IBL5/engine/internal/backup"
	"github.com/a-jay85/IBL5/engine/internal/result"
)

// statNames is the fixed, ordered set of compared aggregates. Iterating this
// slice (never a map) keeps every report deterministic. Team points is the
// headline; the rest catch a model that gets points right by a wrong shot mix.
//
// "fgm"/"fga" are TOTAL field goals on BOTH sides: the engine emits 2-point-only
// makes (Game2GM) plus threes (Game3GM), and the .sco likewise stores 2-point
// makes (TwoGM) separate from threes (ThreeGM) — there is no pre-summed total-FG
// field in either, so total FG is reconstructed by summing 2pt+3pt on each side.
// "tgm"/"tga" isolate the three-point component so a wrong 2pt/3pt mix that
// nets the same total FG still surfaces.
var statNames = []string{
	"points", "fgm", "fga", "ftm", "fta", "tgm", "tga",
	"reb", "ast", "stl", "tov", "blk", "pf",
}

// StatNames returns a copy of the fixed, ordered set of compared aggregates, so
// downstream consumers (e.g. the jsbcalibrate harness) can iterate stats in the
// same deterministic order the reports use without depending on map iteration.
func StatNames() []string {
	return append([]string(nil), statNames...)
}

// TeamStat is one team's compared aggregates for one game, normalized to a
// single basis so the engine side and the .sco side are directly comparable.
type TeamStat struct {
	Points int
	FGM    int // total field goals made = 2pt makes + 3pt makes
	FGA    int // total field goals attempted = 2pt att + 3pt att
	FTM    int
	FTA    int
	TGM    int // three-point makes
	TGA    int // three-point attempts
	REB    int // total rebounds = ORB + DRB
	ORB    int // offensive rebounds only — NOT in statNames (never compared/banded);
	// carried for the ADR-0049 true-possession proxy FGA+0.44·FTA+TOV−ORB, which the
	// collapsed REB total cannot supply. Read directly off the raw box on both sides.
	AST int
	STL int
	TOV int
	BLK int
	PF  int
}

// value returns the named stat. The name must be one of statNames; an unknown
// name returns 0 (the caller only ever passes statNames entries).
func (s TeamStat) value(name string) int {
	switch name {
	case "points":
		return s.Points
	case "fgm":
		return s.FGM
	case "fga":
		return s.FGA
	case "ftm":
		return s.FTM
	case "fta":
		return s.FTA
	case "tgm":
		return s.TGM
	case "tga":
		return s.TGA
	case "reb":
		return s.REB
	case "ast":
		return s.AST
	case "stl":
		return s.STL
	case "tov":
		return s.TOV
	case "blk":
		return s.BLK
	case "pf":
		return s.PF
	default:
		return 0
	}
}

// teamStatFromBox normalizes one engine TeamBox to the comparison basis: points
// are the quarter totals plus every overtime period, total FG is 2GM+3GM (and
// 2GA+3GA), and rebounds are ORB+DRB.
func teamStatFromBox(tb result.TeamBox) TeamStat {
	points := tb.Q1 + tb.Q2 + tb.Q3 + tb.Q4
	for _, ot := range tb.OT {
		points += ot
	}
	return TeamStat{
		Points: points,
		FGM:    tb.Game2GM + tb.Game3GM,
		FGA:    tb.Game2GA + tb.Game3GA,
		FTM:    tb.GameFTM,
		FTA:    tb.GameFTA,
		TGM:    tb.Game3GM,
		TGA:    tb.Game3GA,
		REB:    tb.GameORB + tb.GameDRB,
		ORB:    tb.GameORB,
		AST:    tb.GameAST,
		STL:    tb.GameSTL,
		TOV:    tb.GameTOV,
		BLK:    tb.GameBLK,
		PF:     tb.GamePF,
	}
}

// teamStatFromSco normalizes the .sco ground truth for one team to the same
// basis. Points come from the authoritative summed quarter scores in the
// game-info header (VisitorScore/HomeScore); the shooting/rebound/etc. totals
// are summed over that team's PLAYER rows. The PlayerID==0 team-total row is
// deliberately skipped so the player rows are not double-counted.
func teamStatFromSco(sg backup.ScoGame, teamID int) TeamStat {
	ts := TeamStat{}
	switch teamID {
	case sg.VisitorTeamID:
		ts.Points = sg.VisitorScore
	case sg.HomeTeamID:
		ts.Points = sg.HomeScore
	}
	for _, b := range sg.Boxes {
		if b.TeamID != teamID || b.PlayerID == 0 {
			continue // other team, or the team-total row (avoid double-count)
		}
		ts.FGM += b.TwoGM + b.ThreeGM
		ts.FGA += b.TwoGA + b.ThreeGA
		ts.FTM += b.FTM
		ts.FTA += b.FTA
		ts.TGM += b.ThreeGM
		ts.TGA += b.ThreeGA
		ts.REB += b.ORB + b.DRB
		ts.ORB += b.ORB
		ts.AST += b.AST
		ts.STL += b.STL
		ts.TOV += b.TOV
		ts.BLK += b.BLK
		ts.PF += b.PF
	}
	return ts
}

// mean returns the per-stat mean across the N sampled TeamStats (one per seeded
// run). The result is keyed by statNames; an empty input yields a zero map.
func mean(samples []TeamStat) map[string]float64 {
	out := make(map[string]float64, len(statNames))
	if len(samples) == 0 {
		return out
	}
	for _, name := range statNames {
		sum := 0
		for _, s := range samples {
			sum += s.value(name)
		}
		out[name] = float64(sum) / float64(len(samples))
	}
	return out
}

// homeWinFraction returns the fraction of seeded runs in which the home team
// outscored the visitor, pairing samples by run index (homeSamples[i] and
// visSamples[i] come from the same seed). A tied run counts 0.5 so the estimate
// stays unbiased — a tie is reachable only after the 20-overtime termination
// ceiling (sim/gameloop.go), so it is rare but possible. n == 0 is unreachable
// (the harness guards runs >= 1) and yields 0.5 (no information).
func homeWinFraction(homeSamples, visSamples []TeamStat) float64 {
	n := len(homeSamples)
	if len(visSamples) < n {
		n = len(visSamples)
	}
	if n == 0 {
		return 0.5
	}
	wins := 0.0
	for i := 0; i < n; i++ {
		switch {
		case homeSamples[i].Points > visSamples[i].Points:
			wins++
		case homeSamples[i].Points == visSamples[i].Points:
			wins += 0.5
		}
	}
	return wins / float64(n)
}
