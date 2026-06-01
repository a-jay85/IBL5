package main

import (
	"bytes"
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/validate"
)

const cmdRuns = 5

// --- compact fixed-width encoders (inverse of the PR9a backup readers) ------
//
// Duplicated in miniature from internal/validate/fixtures_test.go because test
// helpers are not exported across packages. They write just enough of a
// .plr/.sch/.sco triple to drive the engine through the CLI.

func putRJ(buf []byte, off, w, v int) { copy(buf[off:off+w], fmt.Sprintf("%*d", w, v)) }
func putStr(buf []byte, off, w int, s string) {
	if len(s) > w {
		s = s[:w]
	}
	copy(buf[off:off+w], s)
}

func plrRecord(ordinal, pid, teamID, slot, fgp int, name string) []byte {
	buf := make([]byte, 607)
	for i := range buf {
		buf[i] = ' '
	}
	putRJ(buf, 0, 4, ordinal)
	putStr(buf, 4, 32, name)
	putRJ(buf, 36, 2, 27)
	putRJ(buf, 38, 6, pid)
	putRJ(buf, 44, 2, teamID)
	putRJ(buf, 46, 4, 27)
	putStr(buf, 50, 2, "PG")
	putRJ(buf, 128, 2, 5)
	putRJ(buf, 130, 2, 5)
	putRJ(buf, []int{132, 133, 134, 135, 136}[slot], 1, 1)
	putRJ(buf, 137, 1, 1)
	putRJ(buf, 268, 2, 50)
	putRJ(buf, 270, 2, 50)
	putRJ(buf, 272, 2, 50)
	putRJ(buf, 555, 3, 60)
	putRJ(buf, 558, 3, fgp)
	putRJ(buf, 561, 3, 20)
	putRJ(buf, 564, 3, 75)
	putRJ(buf, 567, 3, 25)
	putRJ(buf, 570, 3, 33)
	putRJ(buf, 573, 3, 20)
	putRJ(buf, 576, 3, 35)
	putRJ(buf, 579, 3, 30)
	putRJ(buf, 582, 3, 30)
	putRJ(buf, 585, 3, 40)
	putRJ(buf, 588, 3, 20)
	putRJ(buf, 591, 2, 6)
	putRJ(buf, 593, 2, 5)
	putRJ(buf, 595, 2, 5)
	putRJ(buf, 597, 2, 7)
	putRJ(buf, 599, 2, 5)
	putRJ(buf, 601, 2, 5)
	putRJ(buf, 603, 2, 5)
	putRJ(buf, 605, 2, 5)
	return buf
}

func writePlr(t *testing.T, path string) {
	t.Helper()
	var recs []string
	ord, pid := 0, 100
	for _, tm := range []struct{ id, fgp int }{{7, 46}, {3, 50}} {
		for slot := 0; slot < 5; slot++ {
			ord++
			pid++
			recs = append(recs, string(plrRecord(ord, pid, tm.id, slot, tm.fgp, fmt.Sprintf("P%d", pid))))
		}
	}
	if err := os.WriteFile(path, []byte(strings.Join(recs, "\r\n")), 0o644); err != nil {
		t.Fatal(err)
	}
}

func writeSch(t *testing.T, path string, vis, home, vScore, hScore int) {
	t.Helper()
	buf := make([]byte, 80000)
	for i := 0; i < 8000; i++ {
		copy(buf[i*10:], "0   0     ")
	}
	teams := fmt.Sprintf("%d%02d", vis, home)
	for len(teams) < 4 {
		teams += " "
	}
	scores := fmt.Sprintf("%d%03d", vScore, hScore)
	for len(scores) < 6 {
		scores += " "
	}
	copy(buf[0:10], teams[:4]+scores[:6])
	if err := os.WriteFile(path, buf, 0o644); err != nil {
		t.Fatal(err)
	}
}

type box struct {
	pid                                                int
	twoGM, twoGA, ftm, fta, threeGM, threeGA, orb, drb int
	ast, stl, tov, blk, pf                             int
}

func putSlot(rec []byte, idx int, b box) {
	base := 58 + idx*53
	putStr(rec, base, 16, fmt.Sprintf("PL%d", b.pid))
	putStr(rec, base+16, 2, "PG")
	putRJ(rec, base+18, 6, b.pid)
	putRJ(rec, base+24, 2, 30)
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

func writeSco(t *testing.T, path string, vis, home, vScore, hScore int, visBox, homeBox box) {
	t.Helper()
	rec := make([]byte, 2000)
	for i := range rec {
		rec[i] = ' '
	}
	putRJ(rec, 6, 2, vis-1)
	putRJ(rec, 8, 2, home-1)
	putRJ(rec, 28, 3, vScore)
	putRJ(rec, 43, 3, hScore)
	putSlot(rec, 0, visBox)
	putSlot(rec, 15, homeBox)
	data := append([]byte(strings.Repeat(" ", 1_000_000)), rec...)
	if err := os.WriteFile(path, data, 0o644); err != nil {
		t.Fatal(err)
	}
}

func round(f float64) int {
	if f < 0 {
		return int(f - 0.5)
	}
	return int(f + 0.5)
}

// boxFromMeans builds a .sco player row whose summed stats reproduce the rounded
// per-stat means for one team.
func boxFromMeans(pid int, m map[string]float64) box {
	tgm, tga := round(m["tgm"]), round(m["tga"])
	return box{
		pid:     pid,
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

// buildCorpus writes a one-game synthetic triple. It first runs the harness on a
// placeholder .sco (scores 1-1, matching the placeholder .sch) to read the
// engine's observed means from the exported Report, then rewrites the .sch/.sco
// with real scores and either means-engineered boxes (inBand → PASS) or zeroed
// boxes (out of band → FAIL).
func buildCorpus(t *testing.T, dir string, inBand bool, runs int, seed uint64) {
	t.Helper()
	plr := filepath.Join(dir, "synth.plr")
	sch := filepath.Join(dir, "synth.sch")
	sco := filepath.Join(dir, "synth.sco")
	writePlr(t, plr)
	writeSch(t, sch, 7, 3, 1, 1)
	writeSco(t, sco, 7, 3, 1, 1, box{pid: 1}, box{pid: 2})

	rep, err := validate.ValidateCorpus(dir, runs, seed, bundle.GameTypeRegular)
	if err != nil {
		t.Fatalf("placeholder ValidateCorpus: %v", err)
	}
	if len(rep.Games) != 1 {
		t.Fatalf("placeholder report games = %d, want 1", len(rep.Games))
	}
	means := map[int]map[string]float64{7: {}, 3: {}}
	for _, r := range rep.Games[0].Rows {
		means[r.TeamID][r.Stat] = r.EngineMean
	}
	visPts, homePts := round(means[7]["points"]), round(means[3]["points"])

	writeSch(t, sch, 7, 3, visPts, homePts)
	if inBand {
		writeSco(t, sco, 7, 3, visPts, homePts, boxFromMeans(1, means[7]), boxFromMeans(2, means[3]))
	} else {
		writeSco(t, sco, 7, 3, visPts, homePts, box{pid: 1}, box{pid: 2})
	}
}

// Row #10: the CLI prints a PASS report and exits 0 on an in-band corpus, and
// prints FAIL rows and exits nonzero on an out-of-band corpus.
func TestRun_PassAndFail(t *testing.T) {
	passDir := t.TempDir()
	buildCorpus(t, passDir, true, cmdRuns, 1000)
	var out, errBuf bytes.Buffer
	code := run([]string{"--corpus", passDir, "--runs", fmt.Sprint(cmdRuns), "--seed", "1000"}, &out, &errBuf)
	if code != 0 {
		t.Fatalf("in-band CLI exit = %d, want 0\nstdout:\n%s\nstderr:\n%s", code, out.String(), errBuf.String())
	}
	if !strings.Contains(out.String(), "RESULT: PASS") {
		t.Errorf("expected a PASS result line, got:\n%s", out.String())
	}

	failDir := t.TempDir()
	buildCorpus(t, failDir, false, cmdRuns, 1000)
	out.Reset()
	errBuf.Reset()
	code = run([]string{"--corpus", failDir, "--runs", fmt.Sprint(cmdRuns), "--seed", "1000"}, &out, &errBuf)
	if code == 0 {
		t.Fatalf("out-of-band CLI exit = 0, want nonzero\nstdout:\n%s", out.String())
	}
	if !strings.Contains(out.String(), "RESULT: FAIL") {
		t.Errorf("expected a FAIL result line, got:\n%s", out.String())
	}
}

// Row #11: the CLI is deterministic — two invocations on the same corpus with
// the same --seed/--runs produce byte-identical reports.
func TestRun_Deterministic(t *testing.T) {
	dir := t.TempDir()
	buildCorpus(t, dir, true, cmdRuns, 2024)
	args := []string{"--corpus", dir, "--runs", fmt.Sprint(cmdRuns), "--seed", "2024"}

	var a, b bytes.Buffer
	if code := run(args, &a, &bytes.Buffer{}); code != 0 {
		t.Fatalf("run 1 exit = %d", code)
	}
	if code := run(args, &b, &bytes.Buffer{}); code != 0 {
		t.Fatalf("run 2 exit = %d", code)
	}
	if a.String() != b.String() {
		t.Errorf("CLI output not byte-identical across runs:\n--- run1 ---\n%s\n--- run2 ---\n%s", a.String(), b.String())
	}
}

// Negative: a missing --corpus is a usage error (exit 2), not a panic.
func TestRun_MissingCorpus(t *testing.T) {
	var out, errBuf bytes.Buffer
	if code := run(nil, &out, &errBuf); code != 2 {
		t.Fatalf("missing --corpus exit = %d, want 2", code)
	}
	if !strings.Contains(errBuf.String(), "--corpus") {
		t.Errorf("expected a --corpus usage message, got: %q", errBuf.String())
	}
}

// Negative: an invalid --game-type is rejected with a usage error (exit 2).
func TestRun_InvalidGameType(t *testing.T) {
	dir := t.TempDir()
	buildCorpus(t, dir, true, cmdRuns, 1)
	var out, errBuf bytes.Buffer
	code := run([]string{"--corpus", dir, "--game-type", "9"}, &out, &errBuf)
	if code != 2 {
		t.Fatalf("invalid --game-type exit = %d, want 2", code)
	}
	if !strings.Contains(errBuf.String(), "invalid --game-type") {
		t.Errorf("expected an invalid game-type message, got: %q", errBuf.String())
	}
}
