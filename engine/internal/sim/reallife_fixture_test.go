package sim

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

// loadRealLifeFixture decodes testdata/bundle_reallife.json, the committed
// full-league bundle cmd/reallife-fixture builds from the current-season .plr.
func loadRealLifeFixture(t *testing.T) bundle.Bundle {
	t.Helper()
	in, err := os.ReadFile(filepath.Join("testdata", "bundle_reallife.json"))
	if err != nil {
		t.Fatalf("read real-life fixture: %v", err)
	}
	b, err := bundle.Decode(in)
	if err != nil {
		t.Fatalf("decode real-life fixture: %v", err)
	}
	return b
}

// TestRealLifeFixture_LeagueCalibration is the committed form of the
// cmd/reallife-fixture -calibrate gate. The fixture's league rates must equal
// the J18 pins bit-for-bit: PF/TOV/FTA are the d70LeagueScalar inputs (the
// bucketweights.go comment records them; they reproduce d70LeagueScalar
// exactly), and FGA is leagueFGA48. Every team carries a DF0 and some player
// carries real-life PF.
func TestRealLifeFixture_LeagueCalibration(t *testing.T) {
	const (
		pinPF48  = 4.294130331651356
		pinTOV48 = 3.3531432843116113
		pinFTA48 = 6.116607485995505
	)
	b := loadRealLifeFixture(t)
	r := b.LeagueRate48
	if r == nil {
		t.Fatal("fixture league_rate48 is missing")
	}
	if r.PF != pinPF48 || r.TOV != pinTOV48 || r.FTA != pinFTA48 {
		t.Errorf("fixture PF/TOV/FTA per 48 = %.17g/%.17g/%.17g, want %.17g/%.17g/%.17g",
			r.PF, r.TOV, r.FTA, pinPF48, pinTOV48, pinFTA48)
	}
	if r.FGA != leagueFGA48 {
		t.Errorf("fixture FGA/48 = %.17g, want leagueFGA48 %.17g", r.FGA, leagueFGA48)
	}
	if s := (r.PF*5 - r.TOV*0.5) / (r.FTA * 5); s != d70LeagueScalar {
		t.Errorf("fixture d70 league scalar = %.17g, want d70LeagueScalar %.17g", s, d70LeagueScalar)
	}
	for _, tm := range b.Teams {
		if tm.DF0 == nil {
			t.Errorf("team %d has no DF0", tm.TeamID)
		}
	}
	anyPF := false
	for _, p := range b.Players {
		if p.RealLifePF > 0 {
			anyPF = true
			break
		}
	}
	if !anyPF {
		t.Error("no fixture player carries RealLifePF > 0")
	}
}
