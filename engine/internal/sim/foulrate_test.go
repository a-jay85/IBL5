package sim

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
)

// foulRateGames and foulRateSeed fix the J27 rate-port fixture run: the
// golden bundle (one game per Simulate) over foulRateGames consecutive seeds.
const (
	foulRateGames = 200
	foulRateSeed  = 20240601
)

// foulRateBands holds [lo, hi] PF per team-game for each foul site on the
// fixture run. Each band is a value captured from one run of the tree after
// the live-outcome-map port (engine/docs/j27-live-outcome-map-port.md), ±10%
// with a floor of ±0.05. Re-capture them only on an
// intended output change.
var foulRateBands = map[result.FoulSite][2]float64{
	result.FoulSiteUnknown:           foulRateBand(0, 0.10),
	result.FoulSiteShootingTwo:       foulRateBand(0.755, 0.10),
	result.FoulSiteShootingThree:     foulRateBand(0.233, 0.10),
	result.FoulSiteAndOne:            foulRateBand(0, 0.10),
	result.FoulSiteNonShooting:       foulRateBand(0.797, 0.10),
	result.FoulSiteLateGame:          foulRateBand(0.025, 0.10),
	result.FoulSiteAndOneMadeTwo:     foulRateBand(2.330, 0.10),
	result.FoulSiteNonShootingBonus:  foulRateBand(0.018, 0.10),
	result.FoulSiteOffensiveTurnover: foulRateBand(1.357, 0.10),
}

// foulRateBand widens a captured rate by frac of itself, with a floor of ±0.05.
func foulRateBand(v, frac float64) [2]float64 {
	d := max(frac*v, 0.05)
	return [2]float64{v - d, v + d}
}

// TestFoulRatesByTypeFixture pins per-type foul rates on a fast fixture run,
// so a revert of a J27 rate-port knob fails here without the archive walk.
func TestFoulRatesByTypeFixture(t *testing.T) {
	in, err := os.ReadFile(filepath.Join("testdata", "bundle.json"))
	if err != nil {
		t.Fatalf("read fixture: %v", err)
	}
	b, err := bundle.Decode(in)
	if err != nil {
		t.Fatalf("decode fixture: %v", err)
	}
	counts := map[result.FoulSite]int{}
	games := 0
	for i := uint64(0); i < foulRateGames; i++ {
		for _, g := range Simulate(b, foulRateSeed+i).Games {
			games++
			for _, e := range g.Events {
				if e.Kind == result.EventFoul {
					counts[e.FoulSite]++
				}
			}
		}
	}
	if games == 0 {
		t.Fatal("fixture run simulated no games")
	}
	teamGames := float64(2 * games)
	for _, site := range result.FoulSites {
		rate := float64(counts[site]) / teamGames
		t.Logf("FOUL_RATE site=%s per_team_game=%.3f", site, rate)
		band, ok := foulRateBands[site]
		if !ok {
			continue
		}
		if rate < band[0] || rate > band[1] {
			t.Errorf("site %s: %.3f PF per team-game, want within [%.3f, %.3f]", site, rate, band[0], band[1])
		}
	}
	if len(foulRateBands) == 0 {
		t.Error("foulRateBands is empty")
	}
}
