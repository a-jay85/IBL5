package main

import (
	"bytes"
	"encoding/json"
	"os"
	"path/filepath"
	"reflect"
	"strings"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/backup"
	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
	"github.com/a-jay85/IBL5/engine/internal/sim"
)

func loadBundle(t *testing.T) bundle.Bundle {
	t.Helper()
	in, err := os.ReadFile(filepath.Join("..", "..", "internal", "sim", "testdata", "bundle.json"))
	if err != nil {
		t.Fatalf("read fixture: %v", err)
	}
	b, err := bundle.Decode(in)
	if err != nil {
		t.Fatalf("decode fixture: %v", err)
	}
	return b
}

func rawValues(seed uint64, n int) []int {
	src := rng.New(seed)
	vals := make([]int, n)
	for i := range vals {
		vals[i] = src.Raw()
	}
	return vals
}

func TestDayGamesReturnsAppendedRecords(t *testing.T) {
	g1 := backup.ScoGame{VisitorTeamID: 1, HomeTeamID: 2, VisitorScore: 90}
	g2 := backup.ScoGame{VisitorTeamID: 3, HomeTeamID: 4, VisitorScore: 80}
	g3 := backup.ScoGame{VisitorTeamID: 5, HomeTeamID: 6, VisitorScore: 70}

	got, err := dayGames([]backup.ScoGame{g1}, []backup.ScoGame{g1, g2, g3})
	if err != nil {
		t.Fatalf("dayGames: %v", err)
	}
	if want := []backup.ScoGame{g2, g3}; !reflect.DeepEqual(got, want) {
		t.Errorf("got %v, want %v", got, want)
	}

	t.Run("post shorter", func(t *testing.T) {
		_, err := dayGames([]backup.ScoGame{g1, g2}, []backup.ScoGame{g1})
		if err == nil || !strings.Contains(err.Error(), "sco not append-only") {
			t.Errorf("err = %v, want sco not append-only", err)
		}
	})
	t.Run("same length one record differs", func(t *testing.T) {
		changed := g2
		changed.VisitorScore++
		_, err := dayGames([]backup.ScoGame{g1, g2}, []backup.ScoGame{g1, changed})
		if err == nil || !strings.Contains(err.Error(), "sco not append-only") {
			t.Errorf("err = %v, want sco not append-only", err)
		}
	})
}

func TestReplayGameWritesDrawLog(t *testing.T) {
	b := loadBundle(t)
	values := rawValues(11, 200000)
	out, err := replayGame(b, b.Schedule[0], values)
	if err != nil {
		t.Fatalf("replayGame: %v", err)
	}
	if out.Exhausted != nil || out.Result == nil {
		t.Fatalf("game exhausted with %d values", len(values))
	}
	var buf bytes.Buffer
	if err := writeDrawLines(&buf, 0, values, out); err != nil {
		t.Fatalf("writeDrawLines: %v", err)
	}
	lines := strings.Split(strings.TrimSpace(buf.String()), "\n")
	if len(lines) == 0 || len(lines) != len(out.Draws) {
		t.Fatalf("%d lines for %d draws", len(lines), len(out.Draws))
	}
	mapped := 0
	for i, l := range lines {
		var d drawLine
		if err := json.Unmarshal([]byte(l), &d); err != nil {
			t.Fatalf("line %d: %v", i, err)
		}
		if d.Caller == "" {
			t.Fatalf("line %d: empty caller", i)
		}
		if d.Fn != sim.DrawSites[d.Caller] {
			t.Fatalf("line %d: fn %q, want DrawSites[%q] = %q", i, d.Fn, d.Caller, sim.DrawSites[d.Caller])
		}
		if d.Fn != "" {
			mapped++
		}
		if d.V != values[i] {
			t.Fatalf("line %d: v %d, want input %d", i, d.V, values[i])
		}
	}
	if mapped == 0 {
		t.Error("no draw line carried a non-empty fn; the fn lookup is not wired")
	}
}

func TestReplayGameExhaustionIsExtraDraw(t *testing.T) {
	b := loadBundle(t)
	values := rawValues(11, 10)
	out, err := replayGame(b, b.Schedule[0], values)
	if err != nil {
		t.Fatalf("replayGame: %v", err)
	}
	if out.Exhausted == nil || out.Result != nil {
		t.Fatalf("want an exhausted game, got %+v", out)
	}
	var buf bytes.Buffer
	if err := writeDrawLines(&buf, 4, values, out); err != nil {
		t.Fatalf("writeDrawLines: %v", err)
	}
	lines := strings.Split(strings.TrimSpace(buf.String()), "\n")
	if len(lines) != 11 {
		t.Fatalf("%d lines, want 10 draws + 1 exhausted marker", len(lines))
	}
	var last exhaustedLine
	if err := json.Unmarshal([]byte(lines[len(lines)-1]), &last); err != nil {
		t.Fatal(err)
	}
	if last.I != 10 || !last.Exhausted || last.Game != 4 {
		t.Errorf("last line = %+v, want game 4, i 10, exhausted", last)
	}
	if _, ok := sim.DrawSites[last.Caller]; !ok {
		t.Errorf("caller %q is not a DrawSites key", last.Caller)
	}
	if last.Fn != sim.DrawSites[last.Caller] {
		t.Errorf("fn %q, want %q", last.Fn, sim.DrawSites[last.Caller])
	}
}

func TestBoxLineMapping(t *testing.T) {
	sg := backup.ScoGame{
		VisitorTeamID: 1, HomeTeamID: 2, VisitorScore: 50, HomeScore: 60,
		Boxes: []backup.ScoBox{
			{TeamID: 1, PlayerID: 20, Min: 30, TwoGM: 3, ThreeGM: 1},
			{TeamID: 1, PlayerID: 10, Min: 0, TwoGM: 9},
			{TeamID: 1, PlayerID: 0, Min: 240, TwoGM: 40},
		},
	}
	gr := result.GameResult{
		VisitorTeamID: 1, HomeTeamID: 2,
		PlayerBoxes: []result.PlayerBox{
			{PID: 20, GameMIN: 30, Game2GM: 3, Game3GM: 1},
			{PID: 10, GameMIN: 0, Game2GM: 9},
		},
		TeamBoxes: []result.TeamBox{
			{TeamID: 1, Q1: 10, Q2: 10, Q3: 10, Q4: 10, OT: []int{9}},
			{TeamID: 2, IsHome: true, Q1: 15, Q2: 15, Q3: 15, Q4: 15},
		},
	}
	jsb := fromSco(sg)
	eng := fromResult(gr, map[int]int{20: 1, 10: 1})

	jb, err := json.Marshal(jsb.Players)
	if err != nil {
		t.Fatal(err)
	}
	eb, err := json.Marshal(eng.Players)
	if err != nil {
		t.Fatal(err)
	}
	if string(jb) != string(eb) {
		t.Errorf("player lines differ:\n jsb %s\n eng %s", jb, eb)
	}
	if len(jsb.Players) != 1 || len(eng.Players) != 1 {
		t.Fatalf("DNP or team-total rows kept: jsb %d, eng %d", len(jsb.Players), len(eng.Players))
	}
	line := jsb.Players[0]
	if line.TwoM != 3 || line.Thr != 1 || line.Min != 30 {
		t.Errorf("line = %+v, want 2gm 3, 3gm 1, min 30", line)
	}
	if !strings.Contains(string(jb), `"2gm":3`) || !strings.Contains(string(jb), `"3gm":1`) {
		t.Errorf("json %s does not carry 2gm:3 and 3gm:1", jb)
	}
	if eng.VisitorScore != 49 || eng.HomeScore != 60 {
		t.Errorf("engine score %d-%d, want 49-60 (OT 9 included)", eng.VisitorScore, eng.HomeScore)
	}
}

func TestRunCountGamesOnEmptySco(t *testing.T) {
	dir := t.TempDir()
	sco := filepath.Join(dir, "empty.sco")
	if err := os.WriteFile(sco, make([]byte, 1_000_000), 0o644); err != nil {
		t.Fatal(err)
	}
	var out, errb bytes.Buffer
	if code := run([]string{"-count-games", "-sco-pre", sco, "-sco-post", sco}, &out, &errb); code != 0 {
		t.Fatalf("code = %d, stderr %q", code, errb.String())
	}
	if got := strings.TrimSpace(out.String()); got != `{"games":0}` {
		t.Errorf("stdout = %q, want {\"games\":0}", got)
	}
}

func TestRunRejectsUnknownFlag(t *testing.T) {
	var out, errb bytes.Buffer
	if code := run([]string{"-bogus"}, &out, &errb); code != 2 {
		t.Errorf("code = %d, want 2", code)
	}
}

func TestRunRequiresReplayFlag(t *testing.T) {
	var out, errb bytes.Buffer
	args := []string{"-plr", "a.plr", "-sco-pre", "a.sco", "-sco-post", "b.sco", "-out", "o"}
	if code := run(args, &out, &errb); code != 2 {
		t.Errorf("code = %d, want 2", code)
	}
	if !strings.Contains(errb.String(), "-replay") {
		t.Errorf("stderr %q does not name -replay", errb.String())
	}
}

func TestRunRejectsWrongReplayFormat(t *testing.T) {
	dir := t.TempDir()
	rp := filepath.Join(dir, "replay.json")
	if err := os.WriteFile(rp, []byte(`{"format":"jsb-replay/2","games":[]}`), 0o644); err != nil {
		t.Fatal(err)
	}
	var out, errb bytes.Buffer
	args := []string{"-plr", "a.plr", "-sco-pre", "a.sco", "-sco-post", "b.sco", "-replay", rp, "-out", dir}
	if code := run(args, &out, &errb); code != 3 {
		t.Errorf("code = %d, want 3 (stderr %q)", code, errb.String())
	}
	if !strings.Contains(errb.String(), "replay format") {
		t.Errorf("stderr %q does not name the format error", errb.String())
	}
}
