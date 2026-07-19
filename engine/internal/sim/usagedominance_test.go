package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

// ocRL builds an on-court player at the given slot using real-life stats. When
// RealLifeORB and RealLifeFTA are 0, twoPtBucketWeight(p) == twoPARate(p), making
// the ratio arithmetic exact and verifiable without floating-point approximation.
func ocRL(slot, min, fga, threeGA int) onCourt {
	return oc(slot, bundle.Player{
		RealLifeMIN: min, RealLifeFGA: fga, RealLife3GA: threeGA,
		FGA: 60, ORB: 20, FTA: 20, Stamina: 50,
	})
}

// rateOf returns per48Min(fga-threeGA, min), the exact twoPARate when using the
// ocRL helper (ORB=0 FTA=0 path: twoPtBucketWeight == twoPARate).
func rateOf(min, fga, threeGA int) float64 {
	twoPA := fga - threeGA
	if twoPA < 0 {
		twoPA = 0
	}
	return float64(twoPA) / float64(min) * 48.0
}

// TestUsageDominanceFlags_DominantSlot: one player has a large 2PA rate; the rest
// are low. Exactly one flag should be set.
func TestUsageDominanceFlags_DominantSlot(t *testing.T) {
	// Dominant PG: MIN=48, FGA=25, 3GA=0 → rate = 25.0
	// Others: MIN=48, FGA=2, 3GA=0 → rate = 2.0 each
	// denom = 25 + 4*2 = 33.0; ratios: 25/33≈0.758 (>0.5 ✓), others=2/33≈0.061 (false)
	players := []onCourt{
		ocRL(slotPG, 48, 25, 0),
		ocRL(slotSG, 48, 2, 0),
		ocRL(slotSF, 48, 2, 0),
		ocRL(slotPF, 48, 2, 0),
		ocRL(slotC, 48, 2, 0),
	}
	flags := computeUsageDominanceFlags(players)
	if !flags[slotPG] {
		t.Errorf("dominant PG slot should be true")
	}
	for _, s := range []int{slotSG, slotSF, slotPF, slotC} {
		if flags[s] {
			t.Errorf("slot %d should be false (low ratio)", s)
		}
	}
	// Confirm exactly one flag set.
	count := 0
	for i := 1; i <= 5; i++ {
		if flags[i] {
			count++
		}
	}
	if count != 1 {
		t.Errorf("expected exactly 1 flag set, got %d", count)
	}
}

// TestUsageDominanceFlags_BoundaryStrict: verify strict > at threshold 0.5.
func TestUsageDominanceFlags_BoundaryStrict(t *testing.T) {
	// Exact 0.5: dominant MIN=48 FGA=20 → rate=20.0; 4 others MIN=48 FGA=5 → rate=5.0
	// denom=20+20=40; ratio=20/40=0.5 → must be false (strict >)
	playersAt := func(domFGA int) []onCourt {
		return []onCourt{
			ocRL(slotPG, 48, domFGA, 0),
			ocRL(slotSG, 48, 5, 0),
			ocRL(slotSF, 48, 5, 0),
			ocRL(slotPF, 48, 5, 0),
			ocRL(slotC, 48, 5, 0),
		}
	}

	// Exactly 0.5 → false
	flagsAt := computeUsageDominanceFlags(playersAt(20))
	if flagsAt[slotPG] {
		domRate := rateOf(48, 20, 0)
		denom := domRate + 4*rateOf(48, 5, 0)
		t.Errorf("ratio exactly 0.5 (%.4f/%.4f) should be false; flags[PG]=%v", domRate, denom, flagsAt[slotPG])
	}

	// Just above 0.5: FGA=21 → rate=21.0; denom=21+20=41; ratio=21/41≈0.512 → true
	flagsAbove := computeUsageDominanceFlags(playersAt(21))
	if !flagsAbove[slotPG] {
		domRate := rateOf(48, 21, 0)
		denom := domRate + 4*rateOf(48, 5, 0)
		t.Errorf("ratio just above 0.5 (%.4f/%.4f) should be true; flags[PG]=%v", domRate, denom, flagsAbove[slotPG])
	}
}

// TestUsageDominanceFlags_SubFloor: all equal usage → ratio ≈ 0.2 < 0.3, all false.
func TestUsageDominanceFlags_SubFloor(t *testing.T) {
	players := []onCourt{
		ocRL(slotPG, 48, 5, 0),
		ocRL(slotSG, 48, 5, 0),
		ocRL(slotSF, 48, 5, 0),
		ocRL(slotPF, 48, 5, 0),
		ocRL(slotC, 48, 5, 0),
	}
	flags := computeUsageDominanceFlags(players)
	for i := 1; i <= 5; i++ {
		if flags[i] {
			t.Errorf("slot %d should be false (equal usage, ratio≈0.2 < floor 0.3)", i)
		}
	}
}

// TestUsageDominanceFlags_BetweenFloorAndThreshold: ratio in (0.3, 0.5] → all false.
// Confirms the 0.3 floor never independently sets a flag (both gates must pass).
func TestUsageDominanceFlags_BetweenFloorAndThreshold(t *testing.T) {
	// dominant FGA=12: rate=12.0; others 4×FGA=5: rate=5.0; denom=12+20=32
	// ratio=12/32=0.375 → in (0.3, 0.5] → false
	players := []onCourt{
		ocRL(slotPG, 48, 12, 0),
		ocRL(slotSG, 48, 5, 0),
		ocRL(slotSF, 48, 5, 0),
		ocRL(slotPF, 48, 5, 0),
		ocRL(slotC, 48, 5, 0),
	}
	flags := computeUsageDominanceFlags(players)
	for i := 1; i <= 5; i++ {
		if flags[i] {
			t.Errorf("slot %d should be false (ratio≈0.375 between floor and threshold)", i)
		}
	}
}

// TestUsageDominanceFlags_ZeroDenominator: zero-producing stats → denom=0 → all false, no panic.
func TestUsageDominanceFlags_ZeroDenominator(t *testing.T) {
	// All players with FGA=0 3GA=0 in real-life path → twoPtBucketWeight = 0 for each
	// (guard: d88=0 → return d88=0), so denom=0.
	players := []onCourt{
		oc(slotPG, bundle.Player{RealLifeMIN: 100, RealLifeFGA: 0, Stamina: 50}),
		oc(slotSG, bundle.Player{RealLifeMIN: 100, RealLifeFGA: 0, Stamina: 50}),
		oc(slotSF, bundle.Player{RealLifeMIN: 100, RealLifeFGA: 0, Stamina: 50}),
		oc(slotPF, bundle.Player{RealLifeMIN: 100, RealLifeFGA: 0, Stamina: 50}),
		oc(slotC, bundle.Player{RealLifeMIN: 100, RealLifeFGA: 0, Stamina: 50}),
	}
	flags := computeUsageDominanceFlags(players)
	for i := 1; i <= 5; i++ {
		if flags[i] {
			t.Errorf("slot %d should be false (zero denom)", i)
		}
	}

	// Empty slice: also zero denom → all false, no panic.
	flagsEmpty := computeUsageDominanceFlags(nil)
	for i := 1; i <= 5; i++ {
		if flagsEmpty[i] {
			t.Errorf("slot %d should be false (empty slice)", i)
		}
	}
}

// TestUsageDominanceFlags_ShortLineup: fewer than 5 on-court players. Only present
// slots are candidates; absent slots must stay false.
func TestUsageDominanceFlags_ShortLineup(t *testing.T) {
	// 3 players: PG dominant (rate=25), SG/SF low (rate=2). PF and C slots absent.
	// denom = 25+2+2 = 29; PG ratio=25/29≈0.862 → true; SG/SF ratio=2/29≈0.069 → false
	players := []onCourt{
		ocRL(slotPG, 48, 25, 0),
		ocRL(slotSG, 48, 2, 0),
		ocRL(slotSF, 48, 2, 0),
	}
	flags := computeUsageDominanceFlags(players)
	if !flags[slotPG] {
		t.Errorf("dominant PG slot should be true in short lineup")
	}
	for _, s := range []int{slotSG, slotSF, slotPF, slotC} {
		if flags[s] {
			t.Errorf("slot %d should be false (low ratio or absent)", s)
		}
	}
}

// TestUsageDominanceFlags_CorruptRecord: player with 3GA > FGA has twoPARate = 0
// (guard clamped). Slot stays false, no panic.
func TestUsageDominanceFlags_CorruptRecord(t *testing.T) {
	// Corrupt PG: RealLifeFGA=3, RealLife3GA=10 → guard → twoPARate=0 → flag false
	// Other 4 players normal but low-ratio (rate=5.0 each; denom=20; corrupt ratio=0/20=0)
	players := []onCourt{
		oc(slotPG, bundle.Player{RealLifeMIN: 100, RealLifeFGA: 3, RealLife3GA: 10, Stamina: 50}),
		ocRL(slotSG, 48, 5, 0),
		ocRL(slotSF, 48, 5, 0),
		ocRL(slotPF, 48, 5, 0),
		ocRL(slotC, 48, 5, 0),
	}
	flags := computeUsageDominanceFlags(players)
	if flags[slotPG] {
		t.Errorf("corrupt-record PG (guard → twoPARate=0) should be false")
	}
	// Other slots also false (ratio=5/20=0.25 < floor)
	for _, s := range []int{slotSG, slotSF, slotPF, slotC} {
		if flags[s] {
			t.Errorf("slot %d should be false (ratio<floor)", s)
		}
	}
}
