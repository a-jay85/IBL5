package validate

import (
	"fmt"
	"math"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/backup"
	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/sim"
)

// This file builds SYNTHETIC .plr/.sch/.sco corpora on disk for the harness
// tests. The encoders are the byte-level inverse of the PR9a readers
// (backup.ReadPlr/ReadSch/ReadSco), reproducing only the fields the readers and
// the engine actually consume — enough to exercise the full read→assemble→
// simulate→compare path without any large developer-local corpus file.
//
// The corpora are deliberately small (2 teams × 5 starters, one game). The
// in-band .sco is engineered from the engine's OWN observed means so the happy
// path passes deterministically; the out-of-band .sco zeroes the box stats so
// the FAIL path is exercised too.

func putRJ(buf []byte, off, w, v int) { copy(buf[off:off+w], fmt.Sprintf("%*d", w, v)) }

func putStr(buf []byte, off, w int, s string) {
	if len(s) > w {
		s = s[:w]
	}
	copy(buf[off:off+w], s)
}

func round(f float64) int { return int(math.Round(f)) }

// --- .plr roster encoder ----------------------------------------------------

// plrSpec carries just the .plr fields the engine reads. Slot is 0..4
// (PG..C); the player is stamped depth 1 at that slot and dc_can_play_in_game=1.
type plrSpec struct {
	ordinal, pid, teamID, slot, fgp int
	name                            string
}

// plr offsets — mirror backup/plr.go exactly.
func encodePlrRecord(p plrSpec) []byte {
	buf := make([]byte, 607)
	for i := range buf {
		buf[i] = ' '
	}
	putRJ(buf, 0, 4, p.ordinal) // ordinal
	putStr(buf, 4, 32, p.name)  // name
	putRJ(buf, 36, 2, 27)       // age
	putRJ(buf, 38, 6, p.pid)    // pid
	putRJ(buf, 44, 2, p.teamID) // teamid
	putRJ(buf, 46, 4, 27)       // peak
	putStr(buf, 50, 2, "PG")    // pos (label only; engine uses depth slots)
	putRJ(buf, 128, 2, 5)       // clutch
	putRJ(buf, 130, 2, 5)       // consistency
	depthOff := []int{132, 133, 134, 135, 136}
	putRJ(buf, depthOff[p.slot], 1, 1) // depth 1 at the chosen slot
	putRJ(buf, 137, 1, 1)              // can_play_in_game
	putRJ(buf, 268, 2, 50)             // talent
	putRJ(buf, 270, 2, 50)             // skill
	putRJ(buf, 272, 2, 50)             // intangibles
	putRJ(buf, 555, 3, 60)             // FGA
	putRJ(buf, 558, 3, p.fgp)          // FGP
	putRJ(buf, 561, 3, 20)             // FTA
	putRJ(buf, 564, 3, 75)             // FTP
	putRJ(buf, 567, 3, 25)             // 3GA
	putRJ(buf, 570, 3, 33)             // 3GP
	putRJ(buf, 573, 3, 20)             // ORB
	putRJ(buf, 576, 3, 35)             // DRB
	putRJ(buf, 579, 3, 30)             // AST
	putRJ(buf, 582, 3, 30)             // STL
	putRJ(buf, 585, 3, 40)             // TVR
	putRJ(buf, 588, 3, 20)             // BLK
	putRJ(buf, 591, 2, 6)              // OO
	putRJ(buf, 593, 2, 5)              // DO
	putRJ(buf, 595, 2, 5)              // PO
	putRJ(buf, 597, 2, 7)              // TO
	putRJ(buf, 599, 2, 5)              // OD
	putRJ(buf, 601, 2, 5)              // DD
	putRJ(buf, 603, 2, 5)              // PD
	putRJ(buf, 605, 2, 5)              // TD
	return buf
}

func writePlr(t *testing.T, path string, specs []plrSpec) {
	t.Helper()
	recs := make([]string, 0, len(specs))
	for _, s := range specs {
		recs = append(recs, string(encodePlrRecord(s)))
	}
	if err := os.WriteFile(path, []byte(strings.Join(recs, "\r\n")), 0o644); err != nil {
		t.Fatalf("write .plr: %v", err)
	}
}

// starterSpecs is the canonical synthetic roster: 5 starters each for teams 7
// (visitor) and 3 (home), the home side shooting better so games resolve.
func starterSpecs() []plrSpec {
	specs := make([]plrSpec, 0, 10)
	ord, pid := 0, 100
	for _, tm := range []struct{ id, fgp int }{{7, 46}, {3, 50}} {
		for slot := 0; slot < 5; slot++ {
			ord++
			pid++
			specs = append(specs, plrSpec{ordinal: ord, pid: pid, teamID: tm.id, slot: slot, fgp: tm.fgp, name: fmt.Sprintf("P%d", pid)})
		}
	}
	return specs
}

// --- .sch schedule encoder --------------------------------------------------

type schSpec struct {
	vis, home, vScore, hScore, dateSlot, slotInDate int
}

func encodeSch(games []schSpec) []byte {
	buf := make([]byte, 80000)
	empty := "0   0     "
	for i := 0; i < 8000; i++ {
		copy(buf[i*10:], empty)
	}
	for _, g := range games {
		off := (g.dateSlot*16 + g.slotInDate) * 10
		teams := fmt.Sprintf("%d%02d", g.vis, g.home)
		for len(teams) < 4 {
			teams += " "
		}
		scores := fmt.Sprintf("%d%03d", g.vScore, g.hScore)
		for len(scores) < 6 {
			scores += " "
		}
		copy(buf[off:off+10], teams[:4]+scores[:6])
	}
	return buf
}

func writeSch(t *testing.T, path string, games []schSpec) {
	t.Helper()
	if err := os.WriteFile(path, encodeSch(games), 0o644); err != nil {
		t.Fatalf("write .sch: %v", err)
	}
}

// --- .sco box-score encoder -------------------------------------------------

type scoBoxSpec struct {
	pid                                                int
	name                                               string
	twoGM, twoGA, ftm, fta, threeGM, threeGA, orb, drb int
	ast, stl, tov, blk, pf                             int
}

type scoGameSpec struct {
	visTID, homeTID     int // 1-based; encoder writes raw = id-1
	visScore, homeScore int
	visBoxes, homeBoxes []scoBoxSpec
}

func putSlot(rec []byte, slotIdx int, b scoBoxSpec) {
	base := 58 + slotIdx*53
	putStr(rec, base+0, 16, b.name)
	putStr(rec, base+16, 2, "PG")
	putRJ(rec, base+18, 6, b.pid)
	putRJ(rec, base+24, 2, 30) // minutes (unused by comparison)
	putRJ(rec, base+26, 2, b.twoGM)
	putRJ(rec, base+28, 3, b.twoGA)
	putRJ(rec, base+31, 2, b.ftm)
	putRJ(rec, base+33, 2, b.fta)
	putRJ(rec, base+35, 2, b.threeGM)
	putRJ(rec, base+37, 2, b.threeGA)
	putRJ(rec, base+39, 2, b.orb)
	putRJ(rec, base+41, 2, b.drb)
	putRJ(rec, base+43, 2, b.ast)
	putRJ(rec, base+45, 2, b.stl)
	putRJ(rec, base+47, 2, b.tov)
	putRJ(rec, base+49, 2, b.blk)
	putRJ(rec, base+51, 2, b.pf)
}

func encodeScoRecord(g scoGameSpec) []byte {
	rec := make([]byte, 2000)
	for i := range rec {
		rec[i] = ' '
	}
	putRJ(rec, 0, 2, 0)           // month raw (decode +10 = Oct)
	putRJ(rec, 2, 2, 0)           // day raw (decode +1)
	putRJ(rec, 6, 2, g.visTID-1)  // visitor team raw
	putRJ(rec, 8, 2, g.homeTID-1) // home team raw
	putRJ(rec, 28, 3, g.visScore) // visitor Q1 (rest blank -> 0)
	putRJ(rec, 43, 3, g.homeScore)
	for i, b := range g.visBoxes {
		putSlot(rec, i, b)
	}
	for i, b := range g.homeBoxes {
		putSlot(rec, 15+i, b)
	}
	return rec
}

func scoHeader() string { return strings.Repeat(" ", 1_000_000) }

func writeSco(t *testing.T, path string, games []scoGameSpec) {
	t.Helper()
	var sb strings.Builder
	sb.WriteString(scoHeader())
	for _, g := range games {
		sb.Write(encodeScoRecord(g))
	}
	if err := os.WriteFile(path, []byte(sb.String()), 0o644); err != nil {
		t.Fatalf("write .sco: %v", err)
	}
}

// boxFromMeans turns a per-stat mean map into one .sco player row whose summed
// stats reproduce the rounded means (one row per team, so teamStatFromSco's
// PID!=0 sum equals it). 2GM = round(fgm) - round(tgm); ORB carries all rebounds.
func boxFromMeans(pid int, name string, m map[string]float64) scoBoxSpec {
	tgm := round(m["tgm"])
	tga := round(m["tga"])
	return scoBoxSpec{
		pid: pid, name: name,
		twoGM:   round(m["fgm"]) - tgm,
		twoGA:   round(m["fga"]) - tga,
		ftm:     round(m["ftm"]),
		fta:     round(m["fta"]),
		threeGM: tgm,
		threeGA: tga,
		orb:     round(m["reb"]),
		ast:     round(m["ast"]),
		stl:     round(m["stl"]),
		tov:     round(m["tov"]),
		blk:     round(m["blk"]),
		pf:      round(m["pf"]),
	}
}

// corpusPaths returns the triple file paths for a stem inside dir.
func corpusPaths(dir, stem string) (plr, sch, sco string) {
	return filepath.Join(dir, stem+".plr"),
		filepath.Join(dir, stem+".sch"),
		filepath.Join(dir, stem+".sco")
}

// assembleSynthBundle reads back the written .plr/.sch to produce exactly the
// bundle ValidateCorpus will assemble, so engineered .sco values match the
// engine means the harness computes.
func assembleSynthBundle(t *testing.T, plrPath, schPath string) bundle.Bundle {
	t.Helper()
	pf, err := os.Open(plrPath)
	if err != nil {
		t.Fatalf("open .plr: %v", err)
	}
	defer func() { _ = pf.Close() }()
	players, err := backup.ReadPlr(pf)
	if err != nil {
		t.Fatalf("read .plr: %v", err)
	}
	sf, err := os.Open(schPath)
	if err != nil {
		t.Fatalf("open .sch: %v", err)
	}
	defer func() { _ = sf.Close() }()
	sched, err := backup.ReadSch(sf)
	if err != nil {
		t.Fatalf("read .sch: %v", err)
	}
	b, err := backup.ToBundle(players, sched, backup.AssembleOptions{GameType: bundle.GameTypeRegular})
	if err != nil {
		t.Fatalf("assemble: %v", err)
	}
	return b
}

// buildCorpus writes a one-game synthetic triple under dir. When inBand, the
// .sco is engineered from the engine's observed means (rounded) so every stat
// passes; otherwise the box stats are zeroed so the shooting/rebound/etc. stats
// fall far out of band (FAIL). runs/baseSeed must match the ValidateCorpus call
// the test makes. The .sch and .sco carry identical scores so the .sco↔.sch
// match succeeds.
func buildCorpus(t *testing.T, dir string, inBand bool, runs int, baseSeed uint64, extraUnmatched ...scoGameSpec) {
	t.Helper()
	plr, sch, sco := corpusPaths(dir, "synth")
	writePlr(t, plr, starterSpecs())
	// Placeholder schedule; scores rewritten below once means are known.
	writeSch(t, sch, []schSpec{{vis: 7, home: 3, vScore: 1, hScore: 1}})

	b := assembleSynthBundle(t, plr, sch)
	visMean, homeMean, _, _, _, _, _, _ := simulateGameMeans(b, b.Schedule[0], runs, baseSeed, sim.Options{})
	visPts, homePts := round(visMean["points"]), round(homeMean["points"])

	// Rewrite .sch with the real scores so the .sco↔.sch tuple match succeeds.
	writeSch(t, sch, []schSpec{{vis: 7, home: 3, vScore: visPts, hScore: homePts}})

	var visBox, homeBox scoBoxSpec
	if inBand {
		visBox = boxFromMeans(1, "VIS", visMean)
		homeBox = boxFromMeans(2, "HOME", homeMean)
	} else {
		visBox = scoBoxSpec{pid: 1, name: "VIS"} // all-zero box -> far out of band
		homeBox = scoBoxSpec{pid: 2, name: "HOME"}
	}
	games := []scoGameSpec{{
		visTID: 7, homeTID: 3, visScore: visPts, homeScore: homePts,
		visBoxes: []scoBoxSpec{visBox}, homeBoxes: []scoBoxSpec{homeBox},
	}}
	games = append(games, extraUnmatched...)
	writeSco(t, sco, games)
}
