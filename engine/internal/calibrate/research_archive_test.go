//go:build archive

// TestResearchSelfValidation is an archive-gated self-validation test for
// RunResearch. It checks that the registered stand-ins move the fidelity terms
// they are hypothesized to move (above the empirical noise floor), and that
// orthogonal stand-ins do NOT move unrelated terms above noise (isolation arm).
//
// Run manually (archive dir required):
//
//	cd engine && JSB_ARCHIVE_DIR=/Users/ajaynicolas/GitHub/IBL5/ibl5/backups \
//	  go test -tags archive ./internal/calibrate -run TestResearchSelfValidation -v
//
// Adjust JSB_ARCHIVE_STRIDE (default 500) and JSB_ARCHIVE_SEED (default 20240601)
// to trade runtime for fidelity.
package calibrate

import (
	"os"
	"strconv"
	"testing"
)

// envIntArchive reads an integer from the named environment variable, returning
// def when the variable is unset or cannot be parsed. Named to avoid collision
// with envInt (realarchive_test.go, same build tag, same package).
func envIntArchive(name string, def int) int {
	if v := os.Getenv(name); v != "" {
		if n, err := strconv.Atoi(v); err == nil && n > 0 {
			return n
		}
	}
	return def
}

// findPoints filters rep.Points to those matching both standInID and term.
func findPoints(rep ResearchReport, standInID, term string) []LeveragePoint {
	var out []LeveragePoint
	for _, p := range rep.Points {
		if p.StandInID == standInID && p.Term == term {
			out = append(out, p)
		}
	}
	return out
}

func TestResearchSelfValidation(t *testing.T) {
	dir := os.Getenv("JSB_ARCHIVE_DIR")
	if dir == "" {
		t.Skip("JSB_ARCHIVE_DIR not set — archive dir not available")
	}
	if _, err := os.Stat(dir); err != nil {
		t.Skipf("archive dir %q not available: %v", dir, err)
	}

	stride := envIntArchive("JSB_ARCHIVE_STRIDE", 500)
	seed := uint64(envIntArchive("JSB_ARCHIVE_SEED", 20240601))

	opts := Options{
		Seed:         seed,
		SampleStride: stride,
		Runs:         4,
	}

	rep, err := RunResearch(dir, opts)
	if err != nil {
		t.Fatalf("RunResearch: %v", err)
	}

	// Log full report for diagnostic visibility.
	t.Logf("=== ResearchReport: noise floors ===")
	for term, nf := range rep.NoiseFloor {
		t.Logf("  noise floor %s: %g", term, nf)
	}
	t.Logf("=== ResearchReport: leverage points ===")
	for _, p := range rep.Points {
		tag := "[sub-noise]"
		if p.AboveNoise {
			tag = "[ABOVE NOISE]"
		}
		t.Logf("  %s %g %s: delta=%+g noise=%g %s",
			p.StandInID, p.Value, p.Term, p.Delta, p.NoiseFloor, tag)
	}

	// --- base_time arm ---
	// The base_time_mid stand-in should move cov_poss_pps above noise (it is
	// the pace lever — PR #1495 / ADR-0087 §4).
	btPoss := findPoints(rep, "base_time_mid", "cov_poss_pps")
	if len(btPoss) == 0 {
		t.Fatal("base_time arm: no LeveragePoints for (base_time_mid, cov_poss_pps) — " +
			"stand-in missing or sweep produced no non-baseline values")
	}
	var btPossAbove bool
	for _, p := range btPoss {
		if p.AboveNoise {
			btPossAbove = true
			break
		}
	}
	if !btPossAbove {
		t.Errorf("base_time arm: expected at least one (base_time_mid, cov_poss_pps) point "+
			"AboveNoise==true (base_time sweep must move possession↔efficiency covariance above "+
			"empirical noise floor), got %d point(s) all sub-noise", len(btPoss))
	}

	// Isolation arm: base_time_mid should NOT move steal_share above noise
	// (pace change ≠ steal-rate change).
	for _, p := range findPoints(rep, "base_time_mid", "steal_share") {
		if p.AboveNoise {
			t.Errorf("base_time isolation arm: (base_time_mid, steal_share) point "+
				"value=%g has AboveNoise==true — base_time changing pace should not "+
				"move steal-share fraction above noise (possible isolation failure or "+
				"noise floor too low); delta=%+g noise=%g", p.Value, p.Delta, p.NoiseFloor)
		}
	}

	// --- steal-scale arm ---
	// The steal_turnover_scale stand-in should move steal_share above noise.
	stPts := findPoints(rep, "steal_turnover_scale", "steal_share")
	if len(stPts) == 0 {
		t.Fatal("steal-scale arm: no LeveragePoints for (steal_turnover_scale, steal_share) — " +
			"stand-in missing or sweep produced no non-baseline values")
	}
	var stAbove bool
	for _, p := range stPts {
		if p.AboveNoise {
			stAbove = true
			break
		}
	}
	if !stAbove {
		t.Errorf("steal-scale arm: expected at least one (steal_turnover_scale, steal_share) "+
			"point AboveNoise==true (steal-scale sweep must move steal-share fraction above "+
			"empirical noise floor), got %d point(s) all sub-noise", len(stPts))
	}

	// Isolation arm: steal_turnover_scale should NOT move cov_poss_pps above noise
	// (steal rate changes TO rate, not pace — should not couple into possession↔efficiency covariance).
	for _, p := range findPoints(rep, "steal_turnover_scale", "cov_poss_pps") {
		if p.AboveNoise {
			t.Errorf("steal-scale isolation arm: (steal_turnover_scale, cov_poss_pps) point "+
				"value=%g has AboveNoise==true — steal-scale changes TO rate, not pace, and "+
				"should not move possession-count coupling above noise; delta=%+g noise=%g",
				p.Value, p.Delta, p.NoiseFloor)
		}
	}
}
