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
	if in[0] != keepA || in[1] != keepB {
		t.Errorf("input blocks were modified")
	}
	if n := len(r.Draws()); n != 2 {
		t.Errorf("took %d draws, want 2", n)
	}
}
