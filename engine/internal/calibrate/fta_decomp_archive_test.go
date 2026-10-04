//go:build archive

// J27 FTA-undershoot decomposition over the REAL JSB backup archive. Thin
// driver: all arithmetic lives in the untagged ftadecomp.go (CI-tested). This
// test only gathers raw team-game sums from the engine and the .sco corpus,
// summarises them, selects the advisory RE branch, and writes the artifact.
//
// Invoke manually (run in the background; do not poll):
//
//	cd engine && JSB_ARCHIVE_DIR=/Users/ajaynicolas/GitHub/IBL5/ibl5/backups \
//	  JSB_FTA_PHASE=before go test -tags archive ./internal/calibrate \
//	  -run TestRealArchive_FTADecomp -v -timeout 1800s
//
// JSB_FTA_GAMES caps per-snapshot sim cost (default 60). JSB_FTA_SNAP_STRIDE
// thins the snapshot corpus (default 1 = all). JSB_FTA_SEED (default 20240601).
// JSB_FTA_PHASE is "before" (default) or "after"; JSB_3PT_AB selects the shared
// FreezeConfig A/B arm. Without JSB_ARCHIVE_DIR (or the dir absent) the test
// SKIPS — always green on CI.
//
// Degenerate 0-0 / 0-FTA engine games are excluded (isDegenerateFTAGame). On
// the default arm of the 113-snapshot corpus that drops 78 games: the artifact
// reads engine_side.team_games 13284 and excluded_degenerate_games 78, and
// engine_summary.fta_per_g moves from 14.0861 to about 14.2515.
package calibrate

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/sim"
)

// ftaDecompArtifact is the committed decomposition from one archive pass. It
// carries the raw FTASide sums so every ratio is recomputable from the JSON.
type ftaDecompArtifact struct {
	Generated      string `json:"generated"`
	Snapshots      int    `json:"snapshots"`
	GamesCap       int    `json:"games_cap"`
	SnapshotStride int    `json:"snapshot_stride"`
	Seed           uint64 `json:"seed"`
	ABArm          string `json:"ab_arm"`

	EngineSide    FTASide    `json:"engine_side"`
	ScoSide       FTASide    `json:"sco_side"`
	EngineSummary FTASummary `json:"engine_summary"`
	ScoSummary    FTASummary `json:"sco_summary"`

	// Engine-only ending-mix diagnostics (no .sco analogue).
	EngineOnlyPossessions int `json:"engine_only_possessions"`
	EngineOnlyAndOneSeqs  int `json:"engine_only_and_one_seqs"`
	EngineOnlyEndFT       int `json:"engine_only_end_ft"`
	EngineOnlyFouls       int `json:"engine_only_fouls"`
	EngineOnlyFTA         int `json:"engine_only_fta"`

	// ExcludedDegenerateGames counts engine games dropped by isDegenerateFTAGame
	// (0-0 score and 0 FTA on both teams) before any FTASide or ending-mix sum.
	ExcludedDegenerateGames int `json:"excluded_degenerate_games"`

	Branch    string `json:"branch"`
	RETarget  string `json:"re_target"`
	Rationale string `json:"rationale"`
}

// ftaTeamPoints sums a TeamBox's regulation and overtime points.
func ftaTeamPoints(tb result.TeamBox) int {
	p := tb.Q1 + tb.Q2 + tb.Q3 + tb.Q4
	for _, ot := range tb.OT {
		p += ot
	}
	return p
}

func ftaAbs(n int) int {
	if n < 0 {
		return -n
	}
	return n
}

func TestRealArchive_FTADecomp(t *testing.T) {
	dir := os.Getenv("JSB_ARCHIVE_DIR")
	if dir == "" {
		dir = "/Users/ajaynicolas/GitHub/IBL5/ibl5/backups"
	}
	if _, err := os.Stat(dir); err != nil {
		t.Skipf("archive dir %q not available: %v", dir, err)
	}

	suffix, err := ftaPhaseSuffix(os.Getenv("JSB_FTA_PHASE"))
	if err != nil {
		t.Fatalf("%v", err)
	}

	zips, _, err := listArchiveZips(dir)
	if err != nil {
		t.Fatalf("listArchiveZips: %v", err)
	}
	if len(zips) == 0 {
		t.Fatal("no zips in archive")
	}

	gameCap := possEnvInt("JSB_FTA_GAMES", 60)
	snapStride := possEnvInt("JSB_FTA_SNAP_STRIDE", 1)
	if snapStride < 1 {
		snapStride = 1
	}
	seed := uint64(possEnvInt("JSB_FTA_SEED", 20240601))

	var engSide, scoSide FTASide
	var mix sim.EndingMixCounts
	excludedDegenerate := 0
	snapshots := 0

	seasonZips := make([]string, 0, len(zips))
	for _, zp := range zips {
		if isOlympicsPath(zp) {
			continue
		}
		if recentEra05to08[seasonName(dir, zp)] {
			seasonZips = append(seasonZips, zp)
		}
	}
	t.Logf("recent-era 05-08 snapshots matched: %d (stride %d, games-cap %d, phase%s)", len(seasonZips), snapStride, gameCap, suffix)

	for si := 0; si < len(seasonZips); si += snapStride {
		zp := seasonZips[si]
		b, scoGames, cleanup, skip := loadTripleWithSco(zp)
		if skip != nil {
			t.Logf("skip %s: %s", filepath.Base(zp), skip.Reason)
			continue
		}

		// Engine side. The seed MUST vary per game (seed+gi): a constant seed
		// restarts the PCG stream per game and amplifies one fixed prefix's bias
		// (see threept_undershoot_archive_test.go).
		for gi, g := range b.Schedule {
			if gi >= gameCap {
				break
			}
			sub := bundle.Bundle{LeagueID: b.LeagueID, Teams: b.Teams, Players: b.Players, Schedule: []bundle.Game{g}}
			res, err := sim.SimulateWith(sub, seed+uint64(gi), sim.Options{Freeze: abFreeze()})
			if err != nil {
				cleanup()
				t.Fatalf("SimulateWith: %v", err)
			}
			gr := res.Games[0]
			if len(gr.TeamBoxes) == 2 {
				pts0, pts1 := ftaTeamPoints(gr.TeamBoxes[0]), ftaTeamPoints(gr.TeamBoxes[1])
				if isDegenerateFTAGame(pts0, pts1, gr.TeamBoxes[0].GameFTA, gr.TeamBoxes[1].GameFTA) {
					excludedDegenerate++
					continue // skip AddTeamGame AND the ending-mix loop below
				}
				margin := ftaAbs(pts0 - pts1)
				for ti, tb := range gr.TeamBoxes {
					mine, theirs := pts0, pts1
					if ti == 1 {
						mine, theirs = pts1, pts0
					}
					engSide.AddTeamGame(
						float64(tb.GameFTA), float64(tb.GameFTM), float64(tb.GamePF),
						float64(tb.Game2GA+tb.Game3GA), float64(tb.GameTOV), float64(tb.GameORB),
						float64(mine), tb.IsHome, margin, mine > theirs, mine == theirs,
					)
				}
			}

			// Engine-only ending-mix diagnostics: split events on possession start.
			mix.Games++
			var cur []result.Event
			for _, e := range gr.Events {
				if e.Kind == result.EventPossessionStart {
					sim.ClassifyPossession(cur, &mix)
					cur = cur[:0]
					continue
				}
				cur = append(cur, e)
			}
			sim.ClassifyPossession(cur, &mix)
		}

		// Real-life side: sum .sco player rows per team, skipping the PID-0
		// team-total row (sibling idiom).
		for gi, sg := range scoGames {
			if gi >= gameCap {
				break
			}
			var vis, home FTASide // per-game one-team accumulators (TeamGames==1 each)
			for _, bx := range sg.Boxes {
				if bx.PlayerID == 0 {
					continue
				}
				var dst *FTASide
				switch bx.TeamID {
				case sg.VisitorTeamID:
					dst = &vis
				case sg.HomeTeamID:
					dst = &home
				default:
					continue
				}
				dst.FTA += float64(bx.FTA)
				dst.FTM += float64(bx.FTM)
				dst.PF += float64(bx.PF)
				dst.FGA += float64(bx.TwoGA + bx.ThreeGA)
				dst.TOV += float64(bx.TOV)
				dst.ORB += float64(bx.ORB)
			}
			margin := ftaAbs(sg.HomeScore - sg.VisitorScore)
			tied := sg.HomeScore == sg.VisitorScore
			scoSide.AddTeamGame(vis.FTA, vis.FTM, vis.PF, vis.FGA, vis.TOV, vis.ORB,
				float64(sg.VisitorScore), false, margin, sg.VisitorScore > sg.HomeScore, tied)
			scoSide.AddTeamGame(home.FTA, home.FTM, home.PF, home.FGA, home.TOV, home.ORB,
				float64(sg.HomeScore), true, margin, sg.HomeScore > sg.VisitorScore, tied)
		}
		cleanup()
		snapshots++
	}

	if snapshots == 0 {
		t.Fatal("no recent-era snapshots aggregated — corpus empty (check JSB_ARCHIVE_DIR / season dirs)")
	}
	engSum, err := SummarizeFTASide(engSide)
	if err != nil {
		t.Fatalf("engine SummarizeFTASide: %v", err)
	}
	scoSum, err := SummarizeFTASide(scoSide)
	if err != nil {
		t.Fatalf("sco SummarizeFTASide: %v", err)
	}
	branch, target, rationale := SelectFTABranch(engSum, scoSum)

	art := ftaDecompArtifact{
		Generated:      time.Now().Format(time.RFC3339),
		Snapshots:      snapshots,
		GamesCap:       gameCap,
		SnapshotStride: snapStride,
		Seed:           seed,
		ABArm:          os.Getenv("JSB_3PT_AB"),

		EngineSide:    engSide,
		ScoSide:       scoSide,
		EngineSummary: engSum,
		ScoSummary:    scoSum,

		EngineOnlyPossessions:   mix.Possessions,
		EngineOnlyAndOneSeqs:    mix.AndOneSeqs,
		EngineOnlyEndFT:         mix.EndFT,
		EngineOnlyFouls:         mix.Fouls,
		EngineOnlyFTA:           mix.FTA,
		ExcludedDegenerateGames: excludedDegenerate,

		Branch:    branch,
		RETarget:  target,
		Rationale: rationale,
	}

	t.Logf("FTA_DECOMP branch=%s target=%q rationale=%s", branch, target, rationale)
	t.Logf("FTA_DECOMP_EXCLUDED degenerate=%d", excludedDegenerate)
	logSummary := func(label string, s FTASummary) {
		t.Logf("  %s: fta/g=%.3f pf/g=%.3f fta/pf=%.4f fta/100poss=%.3f pace/g=%.3f pps=%.4f home/away=%.3f winner_fta/g=%.3f loser_fta/g=%.3f bucket_fta/g=%.3f/%.3f/%.3f",
			label, s.FTAPerG, s.PFPerG, s.FTAPerPF, s.FTAPer100Poss, s.PacePerG, s.PPS,
			s.HomeAwayRatio, s.WinnerFTAPerG, s.LoserFTAPerG,
			s.BucketFTAPerG[0], s.BucketFTAPerG[1], s.BucketFTAPerG[2])
	}
	logSummary("engine", engSum)
	logSummary("sco   ", scoSum)
	t.Logf("  engine-only: possessions=%d and_one_seqs=%d end_ft=%d fouls=%d fta=%d",
		mix.Possessions, mix.AndOneSeqs, mix.EndFT, mix.Fouls, mix.FTA)

	out, err := json.MarshalIndent(art, "", "  ")
	if err != nil {
		t.Fatalf("marshal artifact: %v", err)
	}
	date := time.Now().Format("20060102")
	path := filepath.Join("..", "validate", "testdata",
		fmt.Sprintf("calibration-5.60-%s-fta-decomp%s%s.json", date, suffix, abSuffix()))
	if err := os.WriteFile(path, append(out, '\n'), 0o644); err != nil {
		t.Fatalf("write %s: %v", path, err)
	}
	t.Logf("wrote %s", path)
}
