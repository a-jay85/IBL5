package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/rng"
)

func TestDayStartScaledPassTruncatesScaledDraw(t *testing.T) {
	r, err := rng.NewReplay([]int{32767})
	if err != nil {
		t.Fatalf("NewReplay: %v", err)
	}
	_, draws, err := dayStartScaledPass([]dayStartBlock{{}}, []int{9}, r)
	if err != nil {
		t.Fatalf("dayStartScaledPass: %v", err)
	}
	if len(draws) != 1 {
		t.Fatalf("len(draws) = %d, want 1", len(draws))
	}
	// 32767/32768*9 = 8.9997: truncation gives 8, rounding would give 9.
	if draws[0] != 8 {
		t.Errorf("draws[0] = %d, want 8", draws[0])
	}
}

func TestDayStartScaledPassRejectsLengthMismatch(t *testing.T) {
	r, err := rng.NewReplay([]int{1, 2})
	if err != nil {
		t.Fatalf("NewReplay: %v", err)
	}
	back, draws, err := dayStartScaledPass([]dayStartBlock{{}, {}}, []int{3}, r)
	if err == nil {
		t.Fatal("expected an error for 2 blocks and 1 scale")
	}
	if back != nil {
		t.Errorf("back = %v, want nil", back)
	}
	if draws != nil {
		t.Errorf("draws = %v, want nil", draws)
	}
	if n := len(r.Draws()); n != 0 {
		t.Errorf("took %d draws before the length check, want 0", n)
	}
}

func TestDayStartScaledPassWritesBackUnchangedBlocks(t *testing.T) {
	var a, b dayStartBlock
	a[0] = 7
	a[17] = 42
	b[5] = 13
	keepA, keepB := a, b
	in := []dayStartBlock{a, b}

	r, err := rng.NewReplay([]int{100, 200})
	if err != nil {
		t.Fatalf("NewReplay: %v", err)
	}
	back, _, err := dayStartScaledPass(in, []int{4, 6}, r)
	if err != nil {
		t.Fatalf("dayStartScaledPass: %v", err)
	}
	if len(back) != 2 {
		t.Fatalf("len(back) = %d, want 2", len(back))
	}
	if back[0] != a {
		t.Errorf("back[0] = %v, want %v", back[0], a)
	}
	if back[1] != b {
		t.Errorf("back[1] = %v, want %v", back[1], b)
	}
	if back[0] == (dayStartBlock{}) || back[1] == (dayStartBlock{}) {
		t.Errorf("write-back stored a zeroed block")
	}
	if in[0] != keepA || in[1] != keepB {
		t.Errorf("input blocks were modified")
	}
	if n := len(r.Draws()); n != 2 {
		t.Errorf("took %d draws, want 2", n)
	}
}

func TestGameStartPassTakesSixtyRawDraws(t *testing.T) {
	values := make([]int, 61)
	for i := range values {
		values[i] = i * 541 % 32768
	}
	r, err := rng.NewReplay(values)
	if err != nil {
		t.Fatalf("NewReplay: %v", err)
	}
	if err := GameStartPass(r); err != nil {
		t.Fatalf("GameStartPass: %v", err)
	}
	draws := r.Draws()
	if len(draws) != 60 {
		t.Fatalf("len(draws) = %d, want 60", len(draws))
	}
	for i, d := range draws {
		if d.API != "raw" {
			t.Errorf("draw %d api = %q, want raw", i, d.API)
		}
		if d.Caller != "sim.dayStartScaledPass" {
			t.Errorf("draw %d caller = %q, want sim.dayStartScaledPass", i, d.Caller)
		}
		if d.Value != values[i] {
			t.Errorf("draw %d value = %d, want %d", i, d.Value, values[i])
		}
	}
}

func TestGameStartPassExhaustsOnShortList(t *testing.T) {
	values := make([]int, 59)
	for i := range values {
		values[i] = i * 541 % 32768
	}
	r, err := rng.NewReplay(values)
	if err != nil {
		t.Fatalf("NewReplay: %v", err)
	}
	defer func() {
		got := recover()
		if _, ok := got.(*rng.ExhaustedError); !ok {
			t.Fatalf("recovered %v (%T), want *rng.ExhaustedError", got, got)
		}
	}()
	_ = GameStartPass(r)
	t.Fatal("GameStartPass returned without exhausting a 59-value list")
}

// TestTracePinnedDayStartPassDrawCount pins that the pass takes one draw per
// record without needing the private capture, which CI does not have.
func TestTracePinnedDayStartPassDrawCount(t *testing.T) {
	r, err := rng.NewReplay([]int{1, 2, 3})
	if err != nil {
		t.Fatalf("NewReplay: %v", err)
	}
	_, draws, err := dayStartScaledPass(make([]dayStartBlock, 3), []int{1, 1, 1}, r)
	if err != nil {
		t.Fatalf("dayStartScaledPass: %v", err)
	}
	if len(draws) != 3 || len(r.Draws()) != 3 {
		t.Errorf("%d truncated draws and %d logged draws, want 3 of each", len(draws), len(r.Draws()))
	}
}
