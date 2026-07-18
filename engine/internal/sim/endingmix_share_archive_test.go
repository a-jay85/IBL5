//go:build archive

// J24 per-possession ENDING-MIX diagnostic over the REAL JSB backup archive.
//
// Measures how the engine's possessions END — steal, independent (non-steal)
// turnover, defensive rebound, made FG, FT sequence — plus the per-game box
// rates behind them (FGA/FG%, OREB/DREB, STL/TOV, FTA). This decomposes the
// J24 arming-share residual: the faithful 5.60 arming model (+0x4be4: steals
// unconditionally, DREBs at 94%) consumes this mix, so an ending mix over-
// weighted toward steals/DREBs over-produces code-7 possessions even with an
// exact gate. Comparison side: the real 5.60 ending mix from the J3 PBP corpus
// (jsb-native/re-artifacts/j3_out_full.txt, 22,797 real games, 100% template
// closure). No assertion failure — logs shares and writes a dated artifact.
//
// Classification is purely event-stream-side (result.Event, segmented on
// EventPossessionStart) — no engine instrumentation and no rng perturbation.
//
// Reuses listZipsP0, readSnapshotP0, envIntP0 from
// possessionclock_baseline_archive_test.go (same package sim, same build tag).
// Do NOT redefine them — duplicate symbol error under -tags archive.
//
// Invoke manually (run in the background; do not poll):
//
//	cd engine && JSB_ARCHIVE_DIR=/Users/ajaynicolas/GitHub/IBL5/ibl5/backups \
//	  JSB_ARCHIVE_STRIDE=100 JSB_ARCHIVE_RUNS=4 \
//	  go test -tags archive ./internal/sim \
//	  -run TestEndingMixBaseline -v -timeout 600s
//
// STRIDE=100 gives a fast smoke (~minutes); STRIDE=1 is the full pass (~hours).
// Without JSB_ARCHIVE_DIR set (or the dir absent), the test skips — always 0 on CI.
package sim

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/a-jay85/IBL5/engine/internal/backup"
	"github.com/a-jay85/IBL5/engine/internal/result"
)

// endingMixCounts is one accumulation bucket of per-possession endings and
// event tallies (both teams pooled, like the J3 corpus numbers).
type endingMixCounts struct {
	Games       int `json:"games"`
	Possessions int `json:"possessions"`

	// Possession endings (each possession classified exactly once).
	EndSteal   int `json:"end_steal"`    // EventSteal present (steal IS the turnover)
	EndTOInd   int `json:"end_to_ind"`   // EventTurnover without a steal (independent check)
	EndDReb    int `json:"end_dreb"`     // defensive rebound (always trip-terminal)
	EndMade    int `json:"end_made"`     // made FG, no FT sequence
	EndFT      int `json:"end_ft"`       // FT sequence last (and-one or foul-only; engine FTs never rebound)
	EndOther   int `json:"end_other"`    // OREB-cap exhaustion / empty possession
	ORebCont   int `json:"oreb_cont"`    // offensive-rebound CONTINUATIONS (not endings)
	AndOneSeqs int `json:"and_one_seqs"` // and-one FT sequences (FTAttempts==1) inside EndFT

	// Event tallies for the sub-model rate decomposition.
	FGA    int `json:"fga"` // all shot attempts (2pt+3pt, all origins)
	FGM    int `json:"fgm"`
	FGA3   int `json:"fga3"`
	FGM3   int `json:"fgm3"`
	OReb   int `json:"oreb"`
	DReb   int `json:"dreb"`
	Steals int `json:"steals"`
	TOs    int `json:"turnovers"` // all EventTurnover (steal-driven + independent)
	FTA    int `json:"fta"`
	FTM    int `json:"ftm"`
	Fouls  int `json:"fouls"`
}

// classifyPossession tallies one possession's events into c. Priority mirrors
// the trip structure: a steal is the dominant turnover and ends the trip; an
// independent EventTurnover (no steal) likewise; a defensive rebound is always
// trip-terminal; otherwise the possession ended on a make or an FT sequence.
func classifyPossession(evs []result.Event, c *endingMixCounts) {
	if len(evs) == 0 {
		return
	}
	c.Possessions++
	var hasSteal, hasTO, hasDReb, hasMake, hasFT bool
	var lastFTAttempts int
	for _, e := range evs {
		switch e.Kind {
		case result.EventShotAttempt:
			c.FGA++
			if e.ShotType == result.ShotThree {
				c.FGA3++
			}
		case result.EventShotMake:
			c.FGM++
			hasMake = true
			if e.ShotType == result.ShotThree {
				c.FGM3++
			}
		case result.EventRebound:
			if e.OffensiveRebound {
				c.OReb++
				c.ORebCont++
			} else {
				c.DReb++
				hasDReb = true
			}
		case result.EventSteal:
			c.Steals++
			hasSteal = true
		case result.EventTurnover:
			c.TOs++
			hasTO = true
		case result.EventFreeThrow:
			c.FTA += e.FTAttempts
			c.FTM += e.FTMade
			hasFT = true
			lastFTAttempts = e.FTAttempts
		case result.EventFoul:
			c.Fouls++
		}
	}
	switch {
	case hasSteal:
		c.EndSteal++
	case hasTO:
		c.EndTOInd++
	case hasDReb:
		c.EndDReb++
	case hasFT:
		c.EndFT++
		if lastFTAttempts == 1 {
			c.AndOneSeqs++
		}
	case hasMake:
		c.EndMade++
	default:
		c.EndOther++
	}
}

// endingMixArtifact is the committed diagnostic output from one archive pass.
type endingMixArtifact struct {
	Generated string          `json:"generated"`
	Stride    int             `json:"stride"`
	Runs      int             `json:"runs"`
	Seed      uint64          `json:"seed"`
	Snapshots int             `json:"snapshots"`
	Counts    endingMixCounts `json:"counts"`

	// Derived shares (% of possessions).
	StealSharePct float64 `json:"end_steal_share_pct"`
	TOIndSharePct float64 `json:"end_to_ind_share_pct"`
	DRebSharePct  float64 `json:"end_dreb_share_pct"`
	MadeSharePct  float64 `json:"end_made_share_pct"`
	FTSharePct    float64 `json:"end_ft_share_pct"`
	OtherSharePct float64 `json:"end_other_share_pct"`

	// Sub-model rates.
	FGPct        float64 `json:"fg_pct"`            // FGM/FGA
	ORebRatePct  float64 `json:"oreb_rate_pct"`     // OReb/(OReb+DReb)
	PossPerGame  float64 `json:"poss_per_game"`     // both teams pooled
	FGAPerGame   float64 `json:"fga_per_game"`      // both teams pooled
	StealPerGame float64 `json:"steal_per_game"`    // both teams pooled
	TOPerGame    float64 `json:"tov_per_game"`      // both teams pooled (incl. steals)
	DRebPerGame  float64 `json:"dreb_per_game"`     // both teams pooled
	ORebPerGame  float64 `json:"oreb_per_game"`     // both teams pooled
	FTAPerGame   float64 `json:"fta_per_game"`      // both teams pooled
	ArmedPct     float64 `json:"armed_pct"`         // 0.94×DRebShare + StealShare (faithful 5.60 arming over THIS mix)
	ImpliedCode7 float64 `json:"implied_code7_pct"` // ArmedPct × 0.300 (archive-mean gate (4.40+1)/18)
}

func TestEndingMixBaseline(t *testing.T) {
	dir := os.Getenv("JSB_ARCHIVE_DIR")
	if dir == "" {
		dir = "/Users/ajaynicolas/GitHub/IBL5/ibl5/backups"
	}
	if _, err := os.Stat(dir); err != nil {
		t.Skipf("archive dir %q not available: %v", dir, err)
	}
	stride := envIntP0("JSB_ARCHIVE_STRIDE", 100)
	if stride < 1 {
		stride = 1
	}
	runs := envIntP0("JSB_ARCHIVE_RUNS", 4)
	seed := uint64(envIntP0("JSB_ARCHIVE_SEED", 20240601))

	zips, err := listZipsP0(dir)
	if err != nil {
		t.Fatalf("list zips: %v", err)
	}
	if len(zips) == 0 {
		t.Skipf("no .zip snapshots under %q", dir)
	}

	var c endingMixCounts
	snapshots := 0

	for i := 0; i < len(zips); i += stride {
		players, sched, ok := readSnapshotP0(zips[i])
		if !ok {
			continue
		}
		b, err := backup.ToBundle(players, sched, backup.AssembleOptions{})
		if err != nil {
			continue
		}
		for run := 0; run < runs; run++ {
			res, err := SimulateWith(b, seed+uint64(run), Options{})
			if err != nil {
				continue
			}
			for _, g := range res.Games {
				c.Games++
				var cur []result.Event
				for _, e := range g.Events {
					if e.Kind == result.EventPossessionStart {
						classifyPossession(cur, &c)
						cur = cur[:0]
						continue
					}
					cur = append(cur, e)
				}
				classifyPossession(cur, &c)
			}
		}
		snapshots++
	}

	if c.Possessions == 0 {
		t.Fatal("no possessions counted over the archive pass — cannot measure ending mix")
	}
	tot := float64(c.Possessions)
	games := float64(c.Games)
	rebs := float64(c.OReb + c.DReb)
	art := endingMixArtifact{
		Generated: time.Now().Format(time.RFC3339),
		Stride:    stride,
		Runs:      runs,
		Seed:      seed,
		Snapshots: snapshots,
		Counts:    c,

		StealSharePct: 100 * float64(c.EndSteal) / tot,
		TOIndSharePct: 100 * float64(c.EndTOInd) / tot,
		DRebSharePct:  100 * float64(c.EndDReb) / tot,
		MadeSharePct:  100 * float64(c.EndMade) / tot,
		FTSharePct:    100 * float64(c.EndFT) / tot,
		OtherSharePct: 100 * float64(c.EndOther) / tot,

		FGPct:        100 * float64(c.FGM) / float64(c.FGA),
		ORebRatePct:  100 * float64(c.OReb) / rebs,
		PossPerGame:  tot / games,
		FGAPerGame:   float64(c.FGA) / games,
		StealPerGame: float64(c.Steals) / games,
		TOPerGame:    float64(c.TOs) / games,
		DRebPerGame:  float64(c.DReb) / games,
		ORebPerGame:  float64(c.OReb) / games,
		FTAPerGame:   float64(c.FTA) / games,
	}
	art.ArmedPct = 0.94*art.DRebSharePct + art.StealSharePct
	art.ImpliedCode7 = art.ArmedPct * 0.300

	out := filepath.Join("..", "validate", "testdata",
		fmt.Sprintf("calibration-5.60-%s-ending-mix.json", time.Now().Format("20060102")))
	blob, err := json.MarshalIndent(art, "", "  ")
	if err != nil {
		t.Fatalf("marshal artifact: %v", err)
	}
	if err := os.WriteFile(out, append(blob, '\n'), 0o644); err != nil {
		t.Fatalf("write artifact %q: %v", out, err)
	}
	t.Logf("wrote %s", out)

	t.Logf("J24 ENDING-MIX BASELINE (%d snapshots, %d runs, stride %d, %d games, %d poss):",
		snapshots, runs, stride, c.Games, c.Possessions)
	t.Logf("  steal endings:      %6.2f%%   (real J3: 8.55%% = 17.88/209.2)", art.StealSharePct)
	t.Logf("  indep-TO endings:   %6.2f%%   (real J3: 5.16%% = (7.97 turnover + 2.82 off-foul)/209.2)", art.TOIndSharePct)
	t.Logf("  DREB endings:       %6.2f%%   (real J3: 32.31%% = 67.58/209.2)", art.DRebSharePct)
	t.Logf("  made-FG endings:    %6.2f%%", art.MadeSharePct)
	t.Logf("  FT-seq endings:     %6.2f%%", art.FTSharePct)
	t.Logf("  other endings:      %6.2f%%", art.OtherSharePct)
	t.Logf("  ---")
	t.Logf("  FG%%: %.2f  OREB rate: %.2f%% (real 32.30%%)  poss/g: %.1f (real 209.2)", art.FGPct, art.ORebRatePct, art.PossPerGame)
	t.Logf("  per game: FGA %.1f  STL %.1f  TOV %.1f  DREB %.1f (real 67.6)  OREB %.1f (real 32.2)  FTA %.1f (real 41.4)",
		art.FGAPerGame, art.StealPerGame, art.TOPerGame, art.DRebPerGame, art.ORebPerGame, art.FTAPerGame)
	t.Logf("  armed (0.94×DREB + steal): %.2f%% (real ~38.9%%)  implied code-7 @0.300 gate: %.2f%% (target 11.7%%)",
		art.ArmedPct, art.ImpliedCode7)
}
