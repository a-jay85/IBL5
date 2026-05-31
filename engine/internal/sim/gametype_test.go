package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// isPlayoff is true ONLY for game_type 4; every other valid type is false.
func TestIsPlayoff(t *testing.T) {
	cases := []struct {
		gt   bundle.GameType
		want bool
	}{
		{bundle.GameTypeRegular, false},    // 2
		{bundle.GameTypeRegularAlt, false}, // 3
		{bundle.GameTypePlayoff, true},     // 4
		{bundle.GameTypeAllStarA, false},   // 5
		{bundle.GameTypeAllStarB, false},   // 6
	}
	for _, c := range cases {
		if got := isPlayoff(c.gt); got != c.want {
			t.Errorf("isPlayoff(%d) = %v, want %v", int(c.gt), got, c.want)
		}
	}
}

// gameWithType returns the richBundle plus a Game scheduled with the given type.
func gameWithType(gt bundle.GameType) (bundle.Bundle, bundle.Game) {
	b := richBundle()
	g := b.Schedule[0]
	g.GameType = gt
	return b, g
}

// TestGameType_GatingWiredEndToEnd proves game_type threads from bundle.Game →
// gameState → the gated decisions (net×1.25, special_sub): a playoff game and a
// regular game on the SAME fixture and SAME seed must diverge. (Unit tests cover
// the gated functions in isolation; this confirms the integration.)
func TestGameType_GatingWiredEndToEnd(t *testing.T) {
	const seed = 1988
	bReg, gReg := gameWithType(bundle.GameTypeRegular)
	bPo, gPo := gameWithType(bundle.GameTypePlayoff)

	reg, regTrans, regV, regH := simGame(bReg, gReg, rng.New(seed))
	po, poTrans, poV, poH := simGame(bPo, gPo, rng.New(seed))

	if regV.score == poV.score && regH.score == poH.score && regTrans == poTrans &&
		len(reg.Events) == len(po.Events) {
		t.Errorf("playoff and regular games are identical on the same seed — game_type not wired "+
			"(reg %d-%d trans=%d events=%d; po %d-%d trans=%d events=%d)",
			regV.score, regH.score, regTrans, len(reg.Events),
			poV.score, poH.score, poTrans, len(po.Events))
	}
}

// TestGameType_SameTypeDeterministic confirms threading game_type adds no
// nondeterminism: identical type + seed → byte-identical event stream.
func TestGameType_SameTypeDeterministic(t *testing.T) {
	const seed = 1988
	b, g := gameWithType(bundle.GameTypePlayoff)
	a1, _, _, _ := simGame(b, g, rng.New(seed))
	a2, _, _, _ := simGame(b, g, rng.New(seed))
	if len(a1.Events) != len(a2.Events) {
		t.Fatalf("nondeterministic: event counts %d vs %d", len(a1.Events), len(a2.Events))
	}
	for i := range a1.Events {
		if a1.Events[i] != a2.Events[i] {
			t.Fatalf("nondeterministic at event %d: %+v vs %+v", i, a1.Events[i], a2.Events[i])
		}
	}
}
