package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// --- matrix #19: per-attempt FTP make/miss; FTM ≤ FTA; 1 vs 2 attempts -----

func TestShootFreeThrows(t *testing.T) {
	r := rng.New(9)

	// A 90% shooter over two attempts: made ∈ [0,2], usually high.
	good := oc(slotPG, bundle.Player{FTP: 90, Stamina: 50})
	totalMade, trials := 0, 5000
	for i := 0; i < trials; i++ {
		made := shootFreeThrows(good, 2, r)
		if made < 0 || made > 2 {
			t.Fatalf("made = %d, out of [0,2]", made)
		}
		totalMade += made
	}
	pct := float64(totalMade) / float64(trials*2)
	if pct < 0.85 || pct > 0.95 {
		t.Errorf("FT%% = %.3f, want ≈ 0.90", pct)
	}

	// And-one is a single attempt.
	if made := shootFreeThrows(good, 1, r); made < 0 || made > 1 {
		t.Errorf("and-one made = %d, want 0 or 1", made)
	}

	// A 0-rated shooter never makes one.
	zero := oc(slotC, bundle.Player{FTP: 0, Stamina: 50})
	for i := 0; i < 1000; i++ {
		if made := shootFreeThrows(zero, 2, r); made != 0 {
			t.Fatalf("FTP 0 made = %d, want 0", made)
		}
	}
}
