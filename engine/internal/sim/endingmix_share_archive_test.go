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

// q4FTAByMargin is Q4 free-throw-attempt averages per team role (winner/loser)
// for games in a given final-margin bucket (J17 Phase 6b diagnostic).
type q4FTAByMargin struct {
	WinnerAvg float64 `json:"winner_avg"`
	LoserAvg  float64 `json:"loser_avg"`
	Games     int     `json:"games"`
}

// endingMixArtifact is the committed diagnostic output from one archive pass.
type endingMixArtifact struct {
	Generated string          `json:"generated"`
	Stride    int             `json:"stride"`
	Runs      int             `json:"runs"`
	Seed      uint64          `json:"seed"`
	Snapshots int             `json:"snapshots"`
	Counts    EndingMixCounts `json:"counts"`

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

	// J17 Phase 6b: Q4 FTA by winner/loser bucketed by final margin.
	// winner≥loser for margin≥4 buckets is asserted (hard gate) since J17c pinned
	// doForcedMakeMax=10 (2026-07-20); the margin 0-3 bucket stays logged-only.
	Q4FTAMargin0to3   q4FTAByMargin `json:"q4_fta_margin_0_3"`
	Q4FTAMargin4to7   q4FTAByMargin `json:"q4_fta_margin_4_7"`
	Q4FTAMargin8to15  q4FTAByMargin `json:"q4_fta_margin_8_15"`
	Q4FTAMargin16plus q4FTAByMargin `json:"q4_fta_margin_16plus"`
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

	var c EndingMixCounts
	snapshots := 0

	// J17 Phase 6b: Q4 FTA accumulation by margin bucket.
	type q4Accum struct {
		winnerTotal, loserTotal, games int
	}
	var q4m0to3, q4m4to7, q4m8to15, q4m16plus q4Accum

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
						ClassifyPossession(cur, &c)
						cur = cur[:0]
						continue
					}
					cur = append(cur, e)
				}
				ClassifyPossession(cur, &c)

				// Q4 FTA per team (period==4 EventFreeThrow).
				q4FTA := map[int]int{}
				for _, e := range g.Events {
					if e.Kind == result.EventFreeThrow && e.Period == 4 {
						q4FTA[e.TeamID] += e.FTAttempts
					}
				}

				// Determine winner/loser from TeamBoxes and add to margin bucket.
				if len(g.TeamBoxes) >= 2 {
					score := func(tb result.TeamBox) int {
						s := tb.Q1 + tb.Q2 + tb.Q3 + tb.Q4
						for _, ot := range tb.OT {
							s += ot
						}
						return s
					}
					s0, s1 := score(g.TeamBoxes[0]), score(g.TeamBoxes[1])
					if s0 == s1 {
						// Tie — skip (shouldn't happen with OT)
					} else {
						winTeam, loseTeam := g.TeamBoxes[0].TeamID, g.TeamBoxes[1].TeamID
						margin := s0 - s1
						if s1 > s0 {
							winTeam, loseTeam = g.TeamBoxes[1].TeamID, g.TeamBoxes[0].TeamID
							margin = s1 - s0
						}
						wFTA, lFTA := q4FTA[winTeam], q4FTA[loseTeam]
						switch {
						case margin <= 3:
							q4m0to3.winnerTotal += wFTA
							q4m0to3.loserTotal += lFTA
							q4m0to3.games++
						case margin <= 7:
							q4m4to7.winnerTotal += wFTA
							q4m4to7.loserTotal += lFTA
							q4m4to7.games++
						case margin <= 15:
							q4m8to15.winnerTotal += wFTA
							q4m8to15.loserTotal += lFTA
							q4m8to15.games++
						default:
							q4m16plus.winnerTotal += wFTA
							q4m16plus.loserTotal += lFTA
							q4m16plus.games++
						}
					}
				}
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

	// J17 Phase 6b: populate Q4 FTA diagnostic fields.
	q4avg := func(acc q4Accum) q4FTAByMargin {
		if acc.games == 0 {
			return q4FTAByMargin{}
		}
		return q4FTAByMargin{
			WinnerAvg: float64(acc.winnerTotal) / float64(acc.games),
			LoserAvg:  float64(acc.loserTotal) / float64(acc.games),
			Games:     acc.games,
		}
	}
	art.Q4FTAMargin0to3 = q4avg(q4m0to3)
	art.Q4FTAMargin4to7 = q4avg(q4m4to7)
	art.Q4FTAMargin8to15 = q4avg(q4m8to15)
	art.Q4FTAMargin16plus = q4avg(q4m16plus)

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

	// J17 Phase 6b: Q4 FTA winner/loser by margin.
	// Expected winner≥loser for margin≥4 from J4 corpus (23,525 games).
	t.Logf("  Q4 FTA winner/loser by margin (J17):")
	logQ4 := func(label string, b q4FTAByMargin) {
		dir := ""
		if b.Games > 0 && b.WinnerAvg >= b.LoserAvg {
			dir = " ✓ directional"
		} else if b.Games > 0 {
			dir = " ✗ unexpected"
		}
		t.Logf("    margin %s: winner=%.2f loser=%.2f (%d games)%s",
			label, b.WinnerAvg, b.LoserAvg, b.Games, dir)
	}
	logQ4("0-3 ", art.Q4FTAMargin0to3)
	logQ4("4-7 ", art.Q4FTAMargin4to7)
	logQ4("8-15", art.Q4FTAMargin8to15)
	logQ4("16+ ", art.Q4FTAMargin16plus)

	// J17c (doForcedMakeMax pinned to 10, objdump 2026-07-20): the winner≥loser
	// direction for margin≥4 is now a HARD gate (was logged-only pending the pin —
	// see jsb-native/re-artifacts/jsb-J17-forcing-gate-RE-20260720.md). Measured at
	// bound 10 the per-bucket effect is monotone in margin: +0.09 (4-7), +0.23
	// (8-15), +0.46 (16+) — logged above per bucket. The HARD gate pools all three
	// margin≥4 buckets into one games-weighted comparison rather than asserting the
	// thin 4-7 bucket alone (+0.09 ≈ 2 SE, could dip negative at another seed); the
	// pooled effect draws on ~all margin≥4 games and is robustly positive. The
	// margin 0-3 bucket is intentionally NOT included: in tight games the trailing
	// team fouls to extend, so loser≥winner there is the expected direction.
	poolWinner := q4m4to7.winnerTotal + q4m8to15.winnerTotal + q4m16plus.winnerTotal
	poolLoser := q4m4to7.loserTotal + q4m8to15.loserTotal + q4m16plus.loserTotal
	poolGames := q4m4to7.games + q4m8to15.games + q4m16plus.games
	if poolGames > 0 {
		pw := float64(poolWinner) / float64(poolGames)
		pl := float64(poolLoser) / float64(poolGames)
		if pw < pl {
			t.Errorf("Q4 FTA margin≥4 (pooled): winner=%.2f < loser=%.2f (%d games) — "+
				"expected winner≥loser for margin≥4 (J4 corpus)", pw, pl, poolGames)
		}
	}

	// --- Machine-verifiable gates (J24 matchupQuality Phase 3/4) ---
	// FG% band [47.5%, 48.9%] is now CLOSED and asserted. The closer is the J26
	// faithful +0xD58 penalty-minutes port (re-artifacts/jsb-J26-penalty-minutes-
	// 20260720.md, positionpenalty.go penaltyBaseMinutes): the binary feeds the
	// GM's Game-Plan minutes target (dc_minutes) or MPG=MIN/GP into the
	// position-penalty base, but Go used raw DCMinutes — always 0 in the test path
	// (.plb game-plan minutes unwired) — pinning base at 1.0 (maximal penalty). The
	// MPG fallback restores base≈1.29, cutting the penalty and lifting FG% from the
	// J25 baseline 46.42% into band. The binary reads the real .plb, so its faithful
	// target is ~48.5% (real dc_minutes wired); this gate asserts the committed
	// ~48.3% MPG-fallback approximation because the .plb reader is not yet
	// production-tested (wiring is a separate follow-on). CAVEAT: the
	// band closes at the AGGREGATE level; the empirical 2P/3P split shows 3P
	// undershooting the population's real-life 3P baseline by ~2.8pp (a separate,
	// still-open 3pt lever — see the J26 artifact). Per-component 2P faithfulness
	// vs the binary's output on these snapshots is unverified (binary not run on
	// this population), but the closure survives every candidate interpretation.
	assertBand(t, "FG%", art.FGPct, 47.5, 48.9)
	// Steal/indep-TO ARE hard regression guards — they currently pass and must not
	// drift when future work makes the matchupQuality flow term live.
	assertBand(t, "steal share%", art.StealSharePct, 8.0, 9.0)
	assertBand(t, "indep-TO share%", art.TOIndSharePct, 4.4, 5.4)
	// J17: foul-only endings increase from trailing-by-3 crunch-time shotClock.
	// Pre-J17 baseline 33.68/g (2026-07-19 artifact, stride=10). Band: +3.5/g max.
	assertBand(t, "fta/game", art.FTAPerGame, 33.0, 37.2)
}

// assertBand fails the test if val is outside [lo, hi]. Used as a hard
// regression guard for the ending-mix steal/indep-TO shares; the J24 FG%
// acceptance band is a known-open residual and is logged (not asserted) above.
func assertBand(t *testing.T, name string, val, lo, hi float64) {
	t.Helper()
	if val < lo || val > hi {
		t.Errorf("%s = %.2f%%, want within [%.2f%%, %.2f%%]", name, val, lo, hi)
	}
}
