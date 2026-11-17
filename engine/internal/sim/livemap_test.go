package sim

import (
	"sort"
	"sync"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
)

// liveMapTally is one pass of pathShareGames seeded games on the real-life
// fixture, tallied by pick code, by (code, path end), by foul-site tag, and by
// box-score steals.
type liveMapTally struct {
	picks    map[outcomeCode]int
	ends     map[outcomeCode]map[pathEnd]int
	sites    map[result.FoulSite]int
	boxSTL   int
	pickSize int
}

var (
	liveMapOnce   sync.Once
	liveMapResult liveMapTally
)

// liveMap plays the tally once per test binary (the TestFoulRatesByTypeFixture
// seed loop on bundle_reallife.json, the pathShares schedule) with both path
// observers installed, and clears the observers when the pass ends.
func liveMap(t *testing.T) liveMapTally {
	t.Helper()
	liveMapOnce.Do(func() {
		b := loadPathShareBundle(t, "bundle_reallife.json")
		tally := liveMapTally{
			picks: map[outcomeCode]int{},
			ends:  map[outcomeCode]map[pathEnd]int{},
			sites: map[result.FoulSite]int{},
		}
		pathObserver = func(code outcomeCode, _ bool) { tally.picks[code]++ }
		pathEndObserver = func(code outcomeCode, end pathEnd) {
			if tally.ends[code] == nil {
				tally.ends[code] = map[pathEnd]int{}
			}
			tally.ends[code][end]++
		}
		defer func() {
			pathObserver = nil
			pathEndObserver = nil
		}()
		ids := make([]int, 0, len(b.Teams))
		for _, tm := range b.Teams {
			ids = append(ids, tm.TeamID)
		}
		sort.Ints(ids)
		tmpl := b.Schedule[0]
		n := len(ids)
		for g := 0; g < pathShareGames; g++ {
			game := tmpl
			game.HomeTeamID = ids[g%n]
			game.VisitorTeamID = ids[(g+1)%n]
			b.Schedule = []bundle.Game{game}
			for _, gm := range Simulate(b, foulRateSeed+uint64(g)).Games {
				for _, e := range gm.Events {
					if e.Kind == result.EventFoul {
						tally.sites[e.FoulSite]++
					}
				}
				for _, pb := range gm.PlayerBoxes {
					tally.boxSTL += pb.GameSTL
				}
			}
		}
		for _, c := range tally.picks {
			tally.pickSize += c
		}
		liveMapResult = tally
	})
	if liveMapResult.pickSize == 0 {
		t.Fatal("live-map tally saw no picks")
	}
	return liveMapResult
}

func endsUnder(m liveMapTally, code outcomeCode) (total int) {
	for _, c := range m.ends[code] {
		total += c
	}
	return total
}

// Every code-3 pick ends in the foul handler, exactly once.
func TestLiveMap_Code3EndsInFoul(t *testing.T) {
	m := liveMap(t)
	foul := m.ends[outcomeFoul][pathEndFoul]
	if all := endsUnder(m, outcomeFoul); all != foul {
		t.Errorf("code 3 has %d ends, %d of them foul; want every code-3 end to be a foul", all, foul)
	}
	if foul != m.picks[outcomeFoul] {
		t.Errorf("code 3: %d foul ends for %d picks, want equal", foul, m.picks[outcomeFoul])
	}
	t.Logf("LIVE_MAP code3 share=%.4f%% (trace 610/6507 = 9.37%%)", 100*float64(m.picks[outcomeFoul])/float64(m.pickSize))
}

// Every code-4 pick ends as an offensive foul, a steal, or a plain turnover.
func TestLiveMap_Code4EndsInTurnoverPath(t *testing.T) {
	m := liveMap(t)
	e := m.ends[outcomeTurnover]
	sum := e[pathEndOffensiveFoul] + e[pathEndSteal] + e[pathEndTurnover]
	if sum != m.picks[outcomeTurnover] {
		t.Errorf("code 4: %d picks, %d offensive-foul + steal + turnover ends; want equal", m.picks[outcomeTurnover], sum)
	}
	if e[pathEndFoul] != 0 {
		t.Errorf("code 4 reached the foul handler %d times, want 0", e[pathEndFoul])
	}
	t.Logf("LIVE_MAP code4 share=%.4f%% (trace 717/6507 = 11.02%%) steal-split=%.4f%% (trace 84.2%% observed, 74.5%% predicted)",
		100*float64(m.picks[outcomeTurnover])/float64(m.pickSize),
		100*float64(e[pathEndSteal])/float64(e[pathEndSteal]+e[pathEndTurnover]))
}

// The picker never returns a code outside 1-4.
func TestLiveMap_NoCodeFive(t *testing.T) {
	m := liveMap(t)
	for code, c := range m.picks {
		if code < outcome2pt || code > outcomeTurnover {
			t.Errorf("picker returned code %d (%d times), want only codes 1-4", code, c)
		}
	}
}

// About 10% of code-4 turnovers become offensive fouls (0x4da7a0), and each one
// carries the FoulSiteOffensiveTurnover tag.
func TestLiveMap_OffensiveFoulShare(t *testing.T) {
	m := liveMap(t)
	off := m.ends[outcomeTurnover][pathEndOffensiveFoul]
	share := float64(off) / float64(m.picks[outcomeTurnover])
	if share < 0.07 || share > 0.13 {
		t.Errorf("offensive-foul share of code 4 = %.4f (%d / %d), want in [0.07, 0.13]", share, off, m.picks[outcomeTurnover])
	}
	if tags := m.sites[result.FoulSiteOffensiveTurnover]; tags != off {
		t.Errorf("%d fouls tagged %s, %d offensive-foul ends; want equal", tags, result.FoulSiteOffensiveTurnover, off)
	}
	t.Logf("LIVE_MAP offensive-foul share=%.4f%% (trace 0.1 constant, 82/926 observed)", 100*share)
}

// A steal comes only from a code-4 turnover, and every box-score steal is one.
func TestLiveMap_StealsOnlyAfterTurnover(t *testing.T) {
	m := liveMap(t)
	steals := m.ends[outcomeTurnover][pathEndSteal]
	if m.boxSTL != steals {
		t.Errorf("box-score STL = %d, code-4 steal ends = %d; want equal", m.boxSTL, steals)
	}
	for code, ends := range m.ends {
		if code != outcomeTurnover && ends[pathEndSteal] != 0 {
			t.Errorf("code %d has %d steal ends, want 0", code, ends[pathEndSteal])
		}
	}
}

// And-ones come only from the code-1 made-2 roll and the code-2 made-3 roll;
// codes 3 and 4 make none.
func TestLiveMap_AndOneOnlyFromCodeOne(t *testing.T) {
	m := liveMap(t)
	if m.ends[outcome2pt][pathEndAndOne] == 0 {
		t.Error("code 1 produced no and-one")
	}
	for _, code := range []outcomeCode{outcomeFoul, outcomeTurnover} {
		if c := m.ends[code][pathEndAndOne]; c != 0 {
			t.Errorf("code %d produced %d and-ones, want 0", code, c)
		}
	}
	var total int
	for _, ends := range m.ends {
		total += ends[pathEndAndOne]
	}
	tags := m.sites[result.FoulSiteAndOne] + m.sites[result.FoulSiteAndOneMadeTwo]
	if total != tags {
		t.Errorf("%d and-one ends, %d and-one site tags; want equal", total, tags)
	}
	t.Logf("LIVE_MAP code2 and-ones=%d", m.ends[outcome3pt][pathEndAndOne])
}
