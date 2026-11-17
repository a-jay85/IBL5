package sim

import (
	"bytes"
	"os"
	"path/filepath"
	"sort"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

// pathShareGames is the game count for the live-map path-share tally.
const pathShareGames = 200

// loadPathShareBundle decodes a sim testdata bundle by file name.
func loadPathShareBundle(t *testing.T, name string) bundle.Bundle {
	t.Helper()
	in, err := os.ReadFile(filepath.Join("testdata", name))
	if err != nil {
		t.Fatalf("read %s: %v", name, err)
	}
	b, err := bundle.Decode(in)
	if err != nil {
		t.Fatalf("decode %s: %v", name, err)
	}
	return b
}

// pathShares plays games seeded games (the TestFoulRatesByTypeFixture seed
// loop: one game per Simulate, seed foulRateSeed+g) and tallies every path the
// picker returns. A two-team bundle plays its own schedule. A bundle with more
// teams plays game g as ids[(g+1)%n] at ids[g%n] over the sorted team IDs, so
// every team plays. share3 is the and-one path (andOneWeight) and share4 the
// foul-only path (foulOnlyWeight), as fractions of all picks.
func pathShares(t *testing.T, bundleName string, games int) (share1, share2, share3, share4 float64, total int) {
	t.Helper()
	b := loadPathShareBundle(t, bundleName)
	ids := make([]int, 0, len(b.Teams))
	for _, tm := range b.Teams {
		ids = append(ids, tm.TeamID)
	}
	sort.Ints(ids)
	counts := map[outcomeCode]int{}
	pathObserver = func(code outcomeCode, _ bool) { counts[code]++ }
	t.Cleanup(func() { pathObserver = nil })
	tmpl := b.Schedule[0]
	n := len(ids)
	for g := 0; g < games; g++ {
		if n > 2 {
			game := tmpl
			game.HomeTeamID = ids[g%n]
			game.VisitorTeamID = ids[(g+1)%n]
			b.Schedule = []bundle.Game{game}
		}
		Simulate(b, foulRateSeed+uint64(g))
	}
	for _, c := range counts {
		total += c
	}
	if total == 0 {
		t.Fatalf("%s: observer saw no paths", bundleName)
	}
	f := func(c outcomeCode) float64 { return float64(counts[c]) / float64(total) }
	return f(outcome2pt), f(outcome3pt), f(outcomeFoul), f(outcomeTurnover), total
}

// TestPathShares_LiveMap tallies the picker's path shares on the real-life
// fixture, and logs the bundle.json rating-path shares beside them.
func TestPathShares_LiveMap(t *testing.T) {
	for _, name := range []string{"bundle.json", "bundle_reallife.json"} {
		s1, s2, s3, s4, n := pathShares(t, name, pathShareGames)
		t.Logf("PATH_SHARES bundle=%s share1=%.4f%% share2=%.4f%% share3=%.4f%% share4=%.4f%% total=%d",
			name, 100*s1, 100*s2, 100*s3, 100*s4, n)
	}
}

// TestPathObserver_DrawFree: one seeded game plays byte-identically with the
// observer nil and with a counting observer, and the observer sees calls.
func TestPathObserver_DrawFree(t *testing.T) {
	b := loadPathShareBundle(t, "bundle.json")
	t.Cleanup(func() { pathObserver = nil })

	pathObserver = nil
	want := encode(t, Simulate(b, b.Seed))

	calls := 0
	pathObserver = func(outcomeCode, bool) { calls++ }
	got := encode(t, Simulate(b, b.Seed))
	pathObserver = nil

	if !bytes.Equal(got, want) {
		t.Error("game output differs with the path observer set; the observer must not draw")
	}
	if calls == 0 {
		t.Error("path observer saw no calls")
	}
}
