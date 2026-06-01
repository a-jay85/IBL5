package calibrate

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

// Row #4: inferGameType maps real archive path shapes to the right game type.
func TestInferGameType_Mapping(t *testing.T) {
	cases := []struct {
		path string
		want bundle.GameType
	}{
		{"/b/02-03/02-03_01_pre-heat.zip", bundle.GameTypeRegular},
		{"/b/02-03/02-03_05_heat-rr3.zip", bundle.GameTypeRegular},
		{"/b/02-03/02-03_08_reg-sim01.zip", bundle.GameTypeRegular},
		{"/b/02-03/02-03_42_playoffs-rd1-gm1-3.zip", bundle.GameTypePlayoff},
		{"/b/02-03/02-03_46_conf-finals-gm1-3.zip", bundle.GameTypePlayoff},
		{"/b/02-03/02-03_47_finals.zip", bundle.GameTypePlayoff},
	}
	for _, c := range cases {
		got, ok := inferGameType(c.path, false)
		if !ok {
			t.Errorf("inferGameType(%q) skipped, want process", c.path)
			continue
		}
		if got != c.want {
			t.Errorf("inferGameType(%q) = %d, want %d", c.path, int(got), int(c.want))
		}
	}
}

// Row #5 (boundary): an unknown/ambiguous path defaults to regular, and an
// Olympics path is skipped when olympics are off, processed as all-star when on.
func TestInferGameType_DefaultsAndOlympics(t *testing.T) {
	if got, ok := inferGameType("/b/weird/random-snapshot.zip", false); !ok || got != bundle.GameTypeRegular {
		t.Errorf("ambiguous path = (%d, %v), want (regular, true)", int(got), ok)
	}

	if _, ok := inferGameType("/b/olympics/2003/oly_03.zip", false); ok {
		t.Error("olympics path with includeOlympics=false must be skipped")
	}

	got, ok := inferGameType("/b/olympics/2003/oly_03.zip", true)
	if !ok || got != bundle.GameTypeAllStarB {
		t.Errorf("olympics path with includeOlympics=true = (%d, %v), want (all-star-B, true)", int(got), ok)
	}
}
