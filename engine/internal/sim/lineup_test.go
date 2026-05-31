package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

// --- matrix #3: starter selection + constant-energy fatigue ----------------

func TestSelectStarters_LowestPositiveDepthPerSlot(t *testing.T) {
	players := []bundle.Player{
		{PID: 1, TeamID: 7, DCPGDepth: 2, Stamina: 50, DCCanPlayInGame: 1}, // backup PG
		{PID: 2, TeamID: 7, DCPGDepth: 1, Stamina: 50, DCCanPlayInGame: 1}, // starting PG
		{PID: 3, TeamID: 7, DCSGDepth: 1, Stamina: 0, DCCanPlayInGame: 1},
		{PID: 4, TeamID: 7, DCSFDepth: 1, Stamina: 99, DCCanPlayInGame: 1},
		{PID: 5, TeamID: 7, DCPFDepth: 1, Stamina: 50, DCCanPlayInGame: 1},
		{PID: 6, TeamID: 7, DCCDepth: 1, Stamina: 50, DCCanPlayInGame: 1},
		{PID: 7, TeamID: 7, DCCDepth: 3, Stamina: 50, DCCanPlayInGame: 0},   // DNP flag
		{PID: 99, TeamID: 3, DCPGDepth: 1, Stamina: 50, DCCanPlayInGame: 1}, // other team
	}
	starters := selectStarters(players, 7)
	if len(starters) != 5 {
		t.Fatalf("starters = %d, want 5", len(starters))
	}
	wantPID := map[int]int{slotPG: 2, slotSG: 3, slotSF: 4, slotPF: 5, slotC: 6}
	for _, s := range starters {
		if wantPID[s.slot] != s.PID {
			t.Errorf("slot %d: starter PID = %d, want %d", s.slot, s.PID, wantPID[s.slot])
		}
		// Constant energy (= base stamina) → fatigue is exactly 1.0 for all.
		if s.fatigue != 1.0 {
			t.Errorf("PID %d: fatigue = %v, want 1.0", s.PID, s.fatigue)
		}
	}
	// The depth-2 PG and the can't-play player must not be starters.
	for _, s := range starters {
		if s.PID == 1 || s.PID == 7 {
			t.Errorf("PID %d should not start", s.PID)
		}
	}
}

// --- matrix #4: boundary — equal/missing depths, < 5 eligible --------------

func TestSelectStarters_Boundaries(t *testing.T) {
	// Two players tie at PG depth 1: the bundle-order-first one wins, the other
	// is not double-assigned.
	tie := []bundle.Player{
		{PID: 10, TeamID: 7, DCPGDepth: 1, DCCanPlayInGame: 1},
		{PID: 11, TeamID: 7, DCPGDepth: 1, DCCanPlayInGame: 1},
		{PID: 12, TeamID: 7, DCSGDepth: 1, DCCanPlayInGame: 1},
	}
	starters := selectStarters(tie, 7)
	if len(starters) != 2 { // only PG + SG slots fillable → < 5 eligible
		t.Fatalf("starters = %d, want 2 (short lineup tolerated)", len(starters))
	}
	if starters[0].slot != slotPG || starters[0].PID != 10 {
		t.Errorf("PG starter = PID %d (slot %d), want PID 10", starters[0].PID, starters[0].slot)
	}
	for _, s := range starters {
		if s.PID == 11 {
			t.Error("PID 11 should not start (tie loser, no other open slot)")
		}
	}

	// Empty roster must not panic.
	if got := selectStarters(nil, 7); len(got) != 0 {
		t.Errorf("empty roster starters = %d, want 0", len(got))
	}
}

// --- matrix #3, #7: pure qualityScore bands --------------------------------

func TestQualityScore_Bands(t *testing.T) {
	cases := []struct {
		name    string
		dc      int
		minutes int
		pm      bool
		want    int
	}{
		// Bonus path (dc > 0); posmatch is ignored.
		{"dc<5 min>=12 → +192", 1, 12, false, 204},
		{"dc<5 min<12 → +144", 1, 5, false, 149},
		{"dc>=5 → no bonus", 5, 30, false, 30},
		{"dc>=5 min=0 → 0 (eligible, not unqualified)", 5, 0, false, 0},
		// Fallback path (dc <= 0): only posmatch scores. These two ARE the
		// mandated "fallback-path position-match when dc<=0" verification,
		// exercised at helper level since the end-to-end path is unreachable.
		{"fallback posmatch min>=12 → +48", 0, 30, true, 78},
		{"fallback posmatch min<12 → +0", 0, 5, true, 5},
		{"fallback no posmatch → 0", 0, 30, false, 0},
	}
	for _, c := range cases {
		if got := qualityScore(c.dc, c.minutes, c.pm); got != c.want {
			t.Errorf("%s: qualityScore(%d,%d,%v) = %d, want %d", c.name, c.dc, c.minutes, c.pm, got, c.want)
		}
	}
}

// --- matrix #8: dc-ASC beats score ------------------------------------------

func TestSelectStarters_DcAscBeatsScore(t *testing.T) {
	// A (dc=1, low minutes) and B (dc=2, high minutes) compete for PG. B's score
	// (30+192=222) exceeds A's (10+192=202), but dc is primary so A starts.
	players := []bundle.Player{
		{PID: 1, TeamID: 7, DCPGDepth: 1, DCMinutes: 10, DCCanPlayInGame: 1},
		{PID: 2, TeamID: 7, DCPGDepth: 2, DCMinutes: 30, DCCanPlayInGame: 1},
	}
	starters := selectStarters(players, 7)
	if len(starters) != 1 || starters[0].slot != slotPG || starters[0].PID != 1 {
		t.Fatalf("PG starter = %+v, want PID 1 (dc=1 beats dc=2)", starters)
	}
}

// --- matrix #9: minutes tie-break (equal dc → higher minutes wins) ----------

func TestSelectStarters_MinutesTieBreak(t *testing.T) {
	// Equal dc at PG; higher dc_minutes yields the higher band/score and wins.
	players := []bundle.Player{
		{PID: 1, TeamID: 7, DCPGDepth: 1, DCMinutes: 10, DCCanPlayInGame: 1},
		{PID: 2, TeamID: 7, DCPGDepth: 1, DCMinutes: 30, DCCanPlayInGame: 1},
	}
	starters := selectStarters(players, 7)
	if len(starters) != 1 || starters[0].PID != 2 {
		t.Fatalf("PG starter = %+v, want PID 2 (higher minutes wins tie)", starters)
	}
}

// --- matrix #10: multi-slot player consumed by earliest pass ----------------

func TestSelectStarters_MultiSlotGreedyRemoval(t *testing.T) {
	// PID 1 is depth-1 at both PG and SG. The PG pass consumes it; the SG slot
	// then falls to PID 2, and PID 1 is not double-assigned.
	players := []bundle.Player{
		{PID: 1, TeamID: 7, DCPGDepth: 1, DCSGDepth: 1, DCMinutes: 30, DCCanPlayInGame: 1},
		{PID: 2, TeamID: 7, DCSGDepth: 2, DCMinutes: 20, DCCanPlayInGame: 1},
	}
	starters := selectStarters(players, 7)
	if len(starters) != 2 {
		t.Fatalf("starters = %d, want 2", len(starters))
	}
	wantPID := map[int]int{slotPG: 1, slotSG: 2}
	for _, s := range starters {
		if wantPID[s.slot] != s.PID {
			t.Errorf("slot %d: PID = %d, want %d", s.slot, s.PID, wantPID[s.slot])
		}
	}
}

// --- matrix #11: all can't-play → empty lineup, no panic --------------------

func TestSelectStarters_AllCantPlay(t *testing.T) {
	players := []bundle.Player{
		{PID: 1, TeamID: 7, DCPGDepth: 1, DCCanPlayInGame: 0},
		{PID: 2, TeamID: 7, DCSGDepth: 1, DCCanPlayInGame: 0},
	}
	if got := selectStarters(players, 7); len(got) != 0 {
		t.Errorf("starters = %d, want 0 (all DCCanPlayInGame==0)", len(got))
	}
}

// --- matrix #12: dead dc_bh/di/oi/df/of fields are ignored -------------------

func TestSelectStarters_DeadFieldsIgnored(t *testing.T) {
	base := []bundle.Player{
		{PID: 1, TeamID: 7, DCPGDepth: 1, DCMinutes: 30, DCCanPlayInGame: 1},
		{PID: 2, TeamID: 7, DCSGDepth: 1, DCMinutes: 20, DCCanPlayInGame: 1},
		{PID: 3, TeamID: 7, DCSFDepth: 1, DCMinutes: 25, DCCanPlayInGame: 1},
	}
	withDead := make([]bundle.Player, len(base))
	copy(withDead, base)
	for i := range withDead {
		withDead[i].DCBh = 9
		withDead[i].DCDi = 9
		withDead[i].DCOi = 9
		withDead[i].DCDf = 9
		withDead[i].DCOf = 9
	}

	a := selectStarters(base, 7)
	b := selectStarters(withDead, 7)
	if len(a) != len(b) {
		t.Fatalf("dead fields changed lineup length: %d vs %d", len(a), len(b))
	}
	for i := range a {
		if a[i].PID != b[i].PID || a[i].slot != b[i].slot {
			t.Errorf("slot %d: dead-field selection differs: PID %d vs %d", a[i].slot, a[i].PID, b[i].PID)
		}
	}
}

// --- matrix #13: ranked-rotation per-slot ordering --------------------------

func TestRankedRotation_PerSlotOrdering(t *testing.T) {
	players := []bundle.Player{
		{PID: 1, TeamID: 7, DCPGDepth: 1, DCMinutes: 36, DCCanPlayInGame: 1}, // starter
		{PID: 2, TeamID: 7, DCPGDepth: 2, DCMinutes: 20, DCCanPlayInGame: 1}, // backup A: dc2 score 212
		{PID: 3, TeamID: 7, DCPGDepth: 3, DCMinutes: 30, DCCanPlayInGame: 1}, // backup B: dc3 score 222
		{PID: 4, TeamID: 7, DCPGDepth: 2, DCMinutes: 10, DCCanPlayInGame: 1}, // backup C: dc2 score 154
	}
	starters := selectStarters(players, 7)
	if len(starters) != 1 || starters[0].PID != 1 {
		t.Fatalf("PG starter = %+v, want PID 1", starters)
	}
	rotation := rankedRotation(players, 7, starters)
	pg := rotation[slotPG-1]
	// Comparator: dc ASC primary → dc2 backups (2,4) before dc3 (3); among dc2,
	// score DESC → A(212) before C(154). Want order: 2, 4, 3.
	wantPG := []int{2, 4, 3}
	if len(pg) != len(wantPG) {
		t.Fatalf("PG rotation = %d entries, want %d", len(pg), len(wantPG))
	}
	for i, want := range wantPG {
		if pg[i].PID != want {
			t.Errorf("PG rotation[%d] = PID %d, want %d", i, pg[i].PID, want)
		}
	}
	// The starter must not appear in its own slot's rotation.
	for _, oc := range pg {
		if oc.PID == 1 {
			t.Error("starter PID 1 should not appear in PG rotation")
		}
	}
}
