//go:build archive

// TestRealArchive_ThreePtAttemptRouting decomposes the 3pt attempt-share gap
// (sim vs real) into minute-allocation and per-player-rate effects via a
// minute-share Kitagawa/Oaxaca decomposition over the recent-era 05-08 corpus.
// Real minutes come from bundle.Player.RealLifeMIN (per-season); sim minutes are
// summed from PlayerBox.GameMIN across the capped games. Normalising both to
// minute SHARE within the shared player set (strict inner-join) makes the
// decomposition self-closing: A + B + AB == totalGap algebraically.
//
// Reuses listArchiveZips, seasonName, isOlympicsPath, loadTripleWithSco,
// possEnvInt, recentEra05to08 — same package calibrate, same build tag.
// Do NOT redefine them: duplicate-symbol error under -tags archive.
//
// Invoke manually:
//
//	cd engine && JSB_ARCHIVE_DIR=/Users/ajaynicolas/GitHub/IBL5/ibl5/backups \
//	  go test -tags archive ./internal/calibrate \
//	  -run TestRealArchive_ThreePtAttemptRouting -v -timeout 1800s
package calibrate

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/sim"
)

type threePtAttemptRoutingArtifact struct {
	Generated       string  `json:"generated"`
	Snapshots       int     `json:"snapshots"`
	GamesCap        int     `json:"games_cap"`
	Seed            uint64  `json:"seed"`
	ValidPlayers    int     `json:"valid_players"`
	ExcludedPlayers int     `json:"excluded_players"`
	SimPAPerMin     float64 `json:"sim_pa_per_min"`
	RealPAPerMin    float64 `json:"real_pa_per_min"`
	TotalGap        float64 `json:"total_gap"`
	RateEffect      float64 `json:"rate_effect"`      // B
	MinuteEffect    float64 `json:"minute_effect"`    // A
	Interaction     float64 `json:"interaction"`      // AB
	ClosureResidual float64 `json:"closure_residual"` // (A+B+AB) - gap, ~0
}

func TestRealArchive_ThreePtAttemptRouting(t *testing.T) {
	dir := os.Getenv("JSB_ARCHIVE_DIR")
	if dir == "" {
		dir = "/Users/ajaynicolas/GitHub/IBL5/ibl5/backups"
	}
	if _, err := os.Stat(dir); err != nil {
		t.Skipf("archive dir %q not available: %v", dir, err)
	}

	zips, _, err := listArchiveZips(dir)
	if err != nil {
		t.Fatalf("listArchiveZips: %v", err)
	}
	if len(zips) == 0 {
		t.Fatal("no zips in archive")
	}

	gameCap := possEnvInt("JSB_3PT_GAMES", 60)
	snapStride := possEnvInt("JSB_3PT_SNAP_STRIDE", 1)
	if snapStride < 1 {
		snapStride = 1
	}
	seed := uint64(possEnvInt("JSB_3PT_SEED", 20240601))

	seasonZips := make([]string, 0, len(zips))
	for _, zp := range zips {
		if isOlympicsPath(zp) {
			continue
		}
		if recentEra05to08[seasonName(dir, zp)] {
			seasonZips = append(seasonZips, zp)
		}
	}
	t.Logf("recent-era 05-08 snapshots matched: %d (stride %d, games-cap %d)", len(seasonZips), snapStride, gameCap)

	// Accumulate decomposition components across snapshots. Each snapshot
	// contributes one set of A/B/AB/gap values; the artifact reports means.
	var (
		sumSimPA, sumRealPA                            float64
		sumTotalGap                                    float64
		sumRateEffect, sumMinuteEffect, sumInteraction float64
		totalValidPlayers, totalExcludedPlayers        int
		snapshots                                      int
	)

	for si := 0; si < len(seasonZips); si += snapStride {
		zp := seasonZips[si]
		b, _, cleanup, skip := loadTripleWithSco(zp)
		if skip != nil {
			t.Logf("skip %s: %s", filepath.Base(zp), skip.Reason)
			continue
		}

		// Build real-side lookup from bundle players.
		realMINByPID := make(map[int]float64, len(b.Players))
		real3GAByPID := make(map[int]float64, len(b.Players))
		for _, p := range b.Players {
			realMINByPID[p.PID] = float64(p.RealLifeMIN)
			real3GAByPID[p.PID] = float64(p.RealLife3GA)
		}

		// Collect sim-side per-player sums across capped games.
		simMINByPID := make(map[int]float64)
		sim3GAByPID := make(map[int]float64)
		for gi, g := range b.Schedule {
			if gi >= gameCap {
				break
			}
			sub := bundle.Bundle{LeagueID: b.LeagueID, Teams: b.Teams, Players: b.Players, Schedule: []bundle.Game{g}}
			res := sim.Simulate(sub, seed)
			for _, pb := range res.Games[0].PlayerBoxes {
				simMINByPID[pb.PID] += float64(pb.GameMIN)
				sim3GAByPID[pb.PID] += float64(pb.Game3GA)
			}
		}
		cleanup()

		// Build shared player set: strict inner-join on simMIN > 0 AND realMIN > 0.
		type playerEntry struct{ simMIN, sim3GA, realMIN, real3GA float64 }
		var players []playerEntry
		var excluded int
		for pid, sm := range simMINByPID {
			rm := realMINByPID[pid]
			if sm <= 0 || rm <= 0 {
				excluded++
				continue
			}
			players = append(players, playerEntry{
				simMIN: sm, sim3GA: sim3GAByPID[pid],
				realMIN: rm, real3GA: real3GAByPID[pid],
			})
		}

		if len(players) == 0 {
			t.Fatal("no valid players (all had RealLifeMIN<=0 or GameMIN<=0) — attempt-routing instrument saw no usable population")
		}

		// Compute total minutes on each side for share normalisation.
		var totalSimMIN, totalRealMIN float64
		for _, p := range players {
			totalSimMIN += p.simMIN
			totalRealMIN += p.realMIN
		}

		// Minute-share Kitagawa/Oaxaca decomposition.
		// simPA = Σ_i simShare_i × simRate_i; realPA = Σ_i realShare_i × realRate_i.
		// B (rate effect) = Σ_i realShare_i × (simRate_i - realRate_i)
		// A (minute effect) = Σ_i (simShare_i - realShare_i) × realRate_i
		// AB (interaction) = Σ_i (simShare_i - realShare_i) × (simRate_i - realRate_i)
		// A + B + AB == totalGap algebraically.
		var simPA, realPA float64
		var rateEffect, minuteEffect, interaction float64
		for _, p := range players {
			simShare := p.simMIN / totalSimMIN
			realShare := p.realMIN / totalRealMIN
			simRate := p.sim3GA / p.simMIN
			realRate := p.real3GA / p.realMIN

			simPA += simShare * simRate
			realPA += realShare * realRate
			rateEffect += realShare * (simRate - realRate)
			minuteEffect += (simShare - realShare) * realRate
			interaction += (simShare - realShare) * (simRate - realRate)
		}
		totalGap := simPA - realPA

		if resid := (rateEffect + minuteEffect + interaction) - totalGap; resid < -1e-9 || resid > 1e-9 {
			t.Fatalf("decomposition does not close: A+B+AB=%.12f vs gap=%.12f (resid=%.2e)",
				rateEffect+minuteEffect+interaction, totalGap, resid)
		}

		sumSimPA += simPA
		sumRealPA += realPA
		sumTotalGap += totalGap
		sumRateEffect += rateEffect
		sumMinuteEffect += minuteEffect
		sumInteraction += interaction
		totalValidPlayers += len(players)
		totalExcludedPlayers += excluded
		snapshots++
	}

	if snapshots == 0 {
		t.Fatal("no recent-era snapshots aggregated — corpus empty (check JSB_ARCHIVE_DIR / season dirs)")
	}

	n := float64(snapshots)
	art := threePtAttemptRoutingArtifact{
		Generated:       time.Now().Format(time.RFC3339),
		Snapshots:       snapshots,
		GamesCap:        gameCap,
		Seed:            seed,
		ValidPlayers:    totalValidPlayers,
		ExcludedPlayers: totalExcludedPlayers,
		SimPAPerMin:     sumSimPA / n,
		RealPAPerMin:    sumRealPA / n,
		TotalGap:        sumTotalGap / n,
		RateEffect:      sumRateEffect / n,
		MinuteEffect:    sumMinuteEffect / n,
		Interaction:     sumInteraction / n,
		ClosureResidual: (sumRateEffect + sumMinuteEffect + sumInteraction - sumTotalGap) / n,
	}

	out := filepath.Join("..", "validate", "testdata",
		fmt.Sprintf("calibration-5.60-%s-3pt-attemptrouting.json", time.Now().Format("20060102")))
	blob, err := json.MarshalIndent(art, "", "  ")
	if err != nil {
		t.Fatalf("marshal artifact: %v", err)
	}
	if err := os.WriteFile(out, append(blob, '\n'), 0o644); err != nil {
		t.Fatalf("write artifact %q: %v", out, err)
	}
	t.Logf("wrote %s", out)

	t.Logf("3PT ATTEMPT-ROUTING DECOMPOSITION (%d snapshots, games-cap %d, valid-players %d, excluded %d):",
		snapshots, gameCap, art.ValidPlayers, art.ExcludedPlayers)
	t.Logf("  sim 3PA/min %.6f  real 3PA/min %.6f  gap %.6f", art.SimPAPerMin, art.RealPAPerMin, art.TotalGap)
	t.Logf("  rate-effect (B) %.6f  minute-effect (A) %.6f  interaction (AB) %.6f", art.RateEffect, art.MinuteEffect, art.Interaction)
}
