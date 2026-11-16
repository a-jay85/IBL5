package sim

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
)

// TestEveryFoulCarriesSite runs the golden fixture at its golden seed and
// checks the J27 pass-3 foul-site tag: no EventFoul is left at
// FoulSiteUnknown, and both a 2pt shooting foul and a non-shooting foul
// appear, so the callers pass distinct sites rather than one constant.
func TestEveryFoulCarriesSite(t *testing.T) {
	in, err := os.ReadFile(filepath.Join("testdata", "bundle.json"))
	if err != nil {
		t.Fatalf("read fixture: %v", err)
	}
	b, err := bundle.Decode(in)
	if err != nil {
		t.Fatalf("decode fixture: %v", err)
	}
	res := Simulate(b, b.Seed)

	counts := map[result.FoulSite]int{}
	fouls := 0
	for _, g := range res.Games {
		for _, e := range g.Events {
			if e.Kind != result.EventFoul {
				continue
			}
			fouls++
			counts[e.FoulSite]++
		}
	}
	if fouls == 0 {
		t.Fatal("golden fixture emitted no fouls")
	}
	if n := counts[result.FoulSiteUnknown]; n != 0 {
		t.Errorf("%d of %d fouls carry FoulSiteUnknown", n, fouls)
	}
	for _, want := range []result.FoulSite{result.FoulSiteShootingTwo, result.FoulSiteNonShooting} {
		if counts[want] == 0 {
			t.Errorf("no foul tagged %s on the golden fixture (counts %v)", want, counts)
		}
	}
}

// TestFoulSiteFor pins the flag-to-site mapping resolveFoulShot applies.
func TestFoulSiteFor(t *testing.T) {
	cases := []struct {
		name                              string
		shooting, andOne, threePt, forced bool
		want                              result.FoulSite
	}{
		{"forced late-game foul", false, false, false, true, result.FoulSiteLateGame},
		{"fouled missed 3", true, false, true, false, result.FoulSiteShootingThree},
		{"and-one", true, true, false, false, result.FoulSiteAndOne},
		{"2pt shooting foul", true, false, false, false, result.FoulSiteShootingTwo},
		{"non-shooting foul", false, false, false, false, result.FoulSiteNonShooting},
	}
	for _, c := range cases {
		if got := foulSiteFor(c.shooting, c.andOne, c.threePt, c.forced); got != c.want {
			t.Errorf("%s: foulSiteFor = %s, want %s", c.name, got, c.want)
		}
	}
}

// TestFoulSiteTagsMadeTwoAndOne pins the J27 rate-port split of the two
// and-one sites. A foul from the half-court made-2pt roll (andOneMadeTwoCut)
// carries FoulSiteAndOneMadeTwo, and an outcome-bucket and-one keeps
// FoulSiteAndOne. Transition shots carry OriginTransition, so a made-2 roll
// and-one after a non-transition make can only come from the half-court call
// in possession.go.
func TestFoulSiteTagsMadeTwoAndOne(t *testing.T) {
	in, err := os.ReadFile(filepath.Join("testdata", "bundle.json"))
	if err != nil {
		t.Fatalf("read fixture: %v", err)
	}
	b, err := bundle.Decode(in)
	if err != nil {
		t.Fatalf("decode fixture: %v", err)
	}
	var halfCourtMadeTwo, bucket int
	for i := uint64(0); i < 100; i++ {
		for _, g := range Simulate(b, b.Seed+i).Games {
			var lastMake *result.Event
			for j := range g.Events {
				e := &g.Events[j]
				if e.Kind == result.EventShotMake {
					lastMake = e
				}
				if e.Kind != result.EventFoul {
					continue
				}
				switch e.FoulSite {
				case result.FoulSiteAndOneMadeTwo:
					if lastMake != nil && lastMake.Origin != result.OriginTransition {
						halfCourtMadeTwo++
					}
				case result.FoulSiteAndOne:
					bucket++
				}
			}
		}
	}
	if halfCourtMadeTwo == 0 {
		t.Errorf("no half-court made-2 roll and-one tagged %s over 100 seeds", result.FoulSiteAndOneMadeTwo)
	}
	if bucket == 0 {
		t.Errorf("no outcome-bucket and-one tagged %s over 100 seeds", result.FoulSiteAndOne)
	}
}
