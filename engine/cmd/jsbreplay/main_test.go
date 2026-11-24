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

// startPassValues are the 60 leading values every recorded game opens with: the
// draws of JSB's start-of-game record pass.
func startPassValues() []int {
	vals := make([]int, sim.GameStartPassDraws)
	for i := range vals {
		vals[i] = i * 541 % 32768
	}
	return vals
}

// withStartPass puts the start-of-game pass values in front of a game's own values.
func withStartPass(vals []int) []int {
	return append(startPassValues(), vals...)
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
	values := withStartPass(rawValues(11, 200000))
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
		if i >= len(startPassValues()) && d.Fn != "" {
			mapped++
		}
		if d.V != values[i] {
			t.Fatalf("line %d: v %d, want input %d", i, d.V, values[i])
		}
	}
	if mapped == 0 {
		t.Error("no game draw line carried a non-empty fn; the fn lookup is not wired")
	}
}

func TestReplayGameExhaustionIsExtraDraw(t *testing.T) {
	b := loadBundle(t)
	values := withStartPass(rawValues(11, 10))
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
	if len(lines) != 71 {
		t.Fatalf("%d lines, want 70 draws + 1 exhausted marker", len(lines))
	}
	var last exhaustedLine
	if err := json.Unmarshal([]byte(lines[len(lines)-1]), &last); err != nil {
		t.Fatal(err)
	}
	if last.I != 70 || !last.Exhausted || last.Game != 4 {
		t.Errorf("last line = %+v, want game 4, i 70, exhausted", last)
	}
	if last.Caller == "sim.dayStartScaledPass" {
		t.Errorf("exhaustion landed inside the start pass, not in the game: %+v", last)
	}
	if _, ok := sim.DrawSites[last.Caller]; !ok {
		t.Errorf("caller %q is not a DrawSites key", last.Caller)
	}
	if last.Fn != sim.DrawSites[last.Caller] {
		t.Errorf("fn %q, want %q", last.Fn, sim.DrawSites[last.Caller])
	}
}

func TestReplayGameStartsWithGameStartPass(t *testing.T) {
	b := loadBundle(t)
	lead := startPassValues()
	values := withStartPass(rawValues(11, 200000))
	out, err := replayGame(b, b.Schedule[0], values)
	if err != nil {
		t.Fatalf("replayGame: %v", err)
	}
	var buf bytes.Buffer
	if err := writeDrawLines(&buf, 0, values, out); err != nil {
		t.Fatalf("writeDrawLines: %v", err)
	}
	lines := strings.Split(strings.TrimSpace(buf.String()), "\n")
	if len(lines) <= len(lead) {
		t.Fatalf("%d lines, want more than the %d start-pass draws", len(lines), len(lead))
	}
	for i, l := range lines[:len(lead)+1] {
		var d drawLine
		if err := json.Unmarshal([]byte(l), &d); err != nil {
			t.Fatalf("line %d: %v", i, err)
		}
		if d.I != i {
			t.Fatalf("line %d: i = %d", i, d.I)
		}
		if i < len(lead) {
			if d.Caller != "sim.dayStartScaledPass" || d.Fn != "FUN_005c6100" || d.API != "raw" || d.V != lead[i] {
				t.Fatalf("line %d = %+v, want a raw start-pass draw FUN_005c6100 with value %d", i, d, lead[i])
			}
			continue
		}
		if d.Caller == "sim.dayStartScaledPass" {
			t.Fatalf("line %d: the game's own draws still carry the start-pass caller", i)
		}
	}
}

func TestReplayGameExhaustsInsideGameStartPass(t *testing.T) {
	b := loadBundle(t)
	values := startPassValues()[:sim.GameStartPassDraws-1]
	out, err := replayGame(b, b.Schedule[0], values)
	if err != nil {
		t.Fatalf("replayGame: %v", err)
	}
	if out.Exhausted == nil {
		t.Fatalf("want an exhausted game, got %+v", out)
	}
	if out.Exhausted.Caller != "sim.dayStartScaledPass" {
		t.Errorf("exhaustion caller = %q, want sim.dayStartScaledPass", out.Exhausted.Caller)
	}
	if len(out.Draws) != 59 {
		t.Errorf("%d draws, want 59", len(out.Draws))
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

func TestWriteDayWritesAllOutputs(t *testing.T) {
	b := loadBundle(t)
	g0 := b.Schedule[0]
	day := []backup.ScoGame{
		{VisitorTeamID: g0.VisitorTeamID, HomeTeamID: g0.HomeTeamID, VisitorScore: 90, HomeScore: 88,
			Boxes: []backup.ScoBox{{TeamID: g0.VisitorTeamID, PlayerID: 7, Min: 30}}},
		{VisitorTeamID: g0.VisitorTeamID, HomeTeamID: g0.HomeTeamID},
	}
	games := [][]int{withStartPass(rawValues(11, 200000)), withStartPass(rawValues(11, 10))}
	// The second game reuses the same pairing, so it needs a second schedule
	// entry; matchSchedule must not hand game 0's entry out twice.
	b.Schedule = append(b.Schedule[:1:1], g0)
	out := filepath.Join(t.TempDir(), "out")
	if err := writeDay(b, day, games, out); err != nil {
		t.Fatalf("writeDay: %v", err)
	}

	draws, err := os.ReadFile(filepath.Join(out, "engine_draws.jsonl"))
	if err != nil {
		t.Fatal(err)
	}
	if !strings.Contains(string(draws), `"game":1,"i":70,"exhausted":true`) {
		t.Errorf("draw log has no exhausted marker for game 1")
	}
	if !strings.Contains(string(draws), `"game":0,"i":59,"api":"raw"`) ||
		!strings.Contains(string(draws), `"fn":"FUN_005c6100"`) {
		t.Errorf("draw log does not open game 0 with the 60 start-pass draws")
	}

	var jsb []gameBox
	data, err := os.ReadFile(filepath.Join(out, "jsb_box.json"))
	if err != nil {
		t.Fatal(err)
	}
	if err := json.Unmarshal(data, &jsb); err != nil {
		t.Fatal(err)
	}
	if len(jsb) != 2 || jsb[0].VisitorScore != 90 || len(jsb[0].Players) != 1 {
		t.Errorf("jsb_box = %+v", jsb)
	}

	var eng []*gameBox
	data, err = os.ReadFile(filepath.Join(out, "engine_box.json"))
	if err != nil {
		t.Fatal(err)
	}
	if err := json.Unmarshal(data, &eng); err != nil {
		t.Fatal(err)
	}
	if len(eng) != 2 || eng[0] == nil || eng[1] != nil {
		t.Fatalf("engine_box = %+v, want one played game then null", eng)
	}
	if eng[0].VisitorScore+eng[0].HomeScore == 0 || len(eng[0].Players) == 0 {
		t.Errorf("engine game 0 has no score or players: %+v", eng[0])
	}
}

func TestWriteDayRejectsUnscheduledGame(t *testing.T) {
	b := loadBundle(t)
	day := []backup.ScoGame{{VisitorTeamID: -1, HomeTeamID: -2}}
	err := writeDay(b, day, [][]int{{1}}, t.TempDir())
	if err == nil || !strings.Contains(err.Error(), "game 0 not in schedule") {
		t.Errorf("err = %v, want game 0 not in schedule", err)
	}
}

func TestWriteDayFailsOnUnwritableOutDir(t *testing.T) {
	b := loadBundle(t)
	g0 := b.Schedule[0]
	day := []backup.ScoGame{{VisitorTeamID: g0.VisitorTeamID, HomeTeamID: g0.HomeTeamID}}
	file := filepath.Join(t.TempDir(), "file")
	if err := os.WriteFile(file, nil, 0o644); err != nil {
		t.Fatal(err)
	}
	if err := writeDay(b, day, [][]int{rawValues(11, 10)}, filepath.Join(file, "out")); err == nil {
		t.Error("writeDay into a path under a file succeeded")
	}
}

func TestWriteJSONReportsWriteError(t *testing.T) {
	err := writeJSON(filepath.Join(t.TempDir(), "missing", "x.json"), []int{1})
	if err == nil || !strings.Contains(err.Error(), "write") {
		t.Errorf("err = %v, want a write error", err)
	}
	if err := writeJSON(filepath.Join(t.TempDir(), "x.json"), func() {}); err == nil || !strings.Contains(err.Error(), "marshal") {
		t.Errorf("err = %v, want a marshal error", err)
	}
}

func TestSortLinesOrdersByTeamThenPID(t *testing.T) {
	got := sortLines([]boxLine{{Team: 2, PID: 1}, {Team: 1, PID: 9}, {Team: 1, PID: 3}})
	want := []boxLine{{Team: 1, PID: 3}, {Team: 1, PID: 9}, {Team: 2, PID: 1}}
	if !reflect.DeepEqual(got, want) {
		t.Errorf("got %v, want %v", got, want)
	}
}

func TestReplayDayInputErrors(t *testing.T) {
	dir := t.TempDir()
	sco := filepath.Join(dir, "empty.sco")
	if err := os.WriteFile(sco, make([]byte, 1_000_000), 0o644); err != nil {
		t.Fatal(err)
	}
	write := func(name, body string) string {
		p := filepath.Join(dir, name)
		if err := os.WriteFile(p, []byte(body), 0o644); err != nil {
			t.Fatal(err)
		}
		return p
	}
	empty := write("empty.json", `{"format":"jsb-replay/1","games":[]}`)
	one := write("one.json", `{"format":"jsb-replay/1","games":[[1]]}`)
	bad := write("bad.json", `{`)
	cases := []struct {
		name, replay, scoPost, want string
	}{
		{"missing replay", filepath.Join(dir, "nope.json"), sco, "open"},
		{"unparsable replay", bad, sco, "parse"},
		{"missing sco", empty, filepath.Join(dir, "nope.sco"), "open"},
		{"game count mismatch", one, sco, "replay has 1 games, sco day has 0"},
		{"missing plr", empty, sco, "open"},
	}
	for _, c := range cases {
		t.Run(c.name, func(t *testing.T) {
			err := replayDay(filepath.Join(dir, "nope.plr"), "", sco, c.scoPost, c.replay, dir)
			if err == nil || !strings.Contains(err.Error(), c.want) {
				t.Errorf("err = %v, want %q", err, c.want)
			}
		})
	}
}
