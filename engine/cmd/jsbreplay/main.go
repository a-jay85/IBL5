// Command jsbreplay replays one JSB day's recorded raw draws through the Go
// engine and writes the engine's draw log and box scores beside the JSB box
// scores for that day, so a finder can diff the two.
//
// Usage:
//
//	jsbreplay -plr <ibl5.plr> [-sch <ibl5.sch>] -sco-pre <before.sco> \
//	    -sco-post <after.sco> -replay <replay.json> -out <dir>
//	jsbreplay -count-games -sco-pre <before.sco> -sco-post <after.sco>
//
// The .sco is cumulative, so a day's games are the records the post file
// appends to the pre file. The replay file holds values only:
// {"format":"jsb-replay/1","games":[[v,...],...]}, one list per day game in the
// order JSB played them. IBL5 commits none of these inputs; the jsb-native
// recorder produces them at run time.
//
// Exit codes: 0 ok, 2 usage, 3 input error.
package main

import (
	"bytes"
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"reflect"
	"sort"
	"strings"

	"github.com/a-jay85/IBL5/engine/internal/backup"
	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
	"github.com/a-jay85/IBL5/engine/internal/sim"
)

const (
	exitOK    = 0
	exitUsage = 2
	exitInput = 3

	replayFormat = "jsb-replay/1"
)

func main() {
	os.Exit(run(os.Args[1:], os.Stdout, os.Stderr))
}

// replayFile is the on-disk replay input.
type replayFile struct {
	Format string  `json:"format"`
	Games  [][]int `json:"games"`
}

// gameOut is the outcome of replaying one game.
type gameOut struct {
	Draws     []rng.Draw
	Exhausted *rng.ExhaustedError // non-nil when the replay ran out of values
	Result    *result.GameResult  // nil when exhausted
}

// boxLine is one player's line, shared by the JSB and engine sides.
type boxLine struct {
	Team int `json:"team"`
	PID  int `json:"pid"`
	Min  int `json:"min"`
	TwoM int `json:"2gm"`
	TwoA int `json:"2ga"`
	FTM  int `json:"ftm"`
	FTA  int `json:"fta"`
	Thr  int `json:"3gm"`
	ThrA int `json:"3ga"`
	ORB  int `json:"orb"`
	DRB  int `json:"drb"`
	AST  int `json:"ast"`
	STL  int `json:"stl"`
	TOV  int `json:"tov"`
	BLK  int `json:"blk"`
	PF   int `json:"pf"`
}

// gameBox is one game's score and player lines, shared by both sides.
type gameBox struct {
	Visitor      int       `json:"visitor"`
	Home         int       `json:"home"`
	VisitorScore int       `json:"visitor_score"`
	HomeScore    int       `json:"home_score"`
	Players      []boxLine `json:"players"`
}

type drawLine struct {
	Game   int    `json:"game"`
	I      int    `json:"i"`
	API    string `json:"api"`
	N      int    `json:"n"`
	V      int    `json:"v"`
	Caller string `json:"caller"`
	Fn     string `json:"fn"`
}

type exhaustedLine struct {
	Game      int    `json:"game"`
	I         int    `json:"i"`
	Exhausted bool   `json:"exhausted"`
	API       string `json:"api"`
	Caller    string `json:"caller"`
	Fn        string `json:"fn"`
}

func run(args []string, stdout, stderr io.Writer) int {
	fs := flag.NewFlagSet("jsbreplay", flag.ContinueOnError)
	fs.SetOutput(io.Discard)
	plrPath := fs.String("plr", "", "JSB .plr roster file")
	schPath := fs.String("sch", "", "JSB .sch schedule (default: the .sch beside -plr)")
	scoPre := fs.String("sco-pre", "", ".sco before the day")
	scoPost := fs.String("sco-post", "", ".sco after the day")
	replayPath := fs.String("replay", "", "jsb-replay/1 draw file")
	outDir := fs.String("out", "", "output directory")
	countGames := fs.Bool("count-games", false, "print the day's game count as JSON and exit")
	if err := fs.Parse(args); err != nil {
		fmt.Fprintln(stderr, "jsbreplay:", err)
		return exitUsage
	}

	required := []struct{ name, val string }{{"sco-pre", *scoPre}, {"sco-post", *scoPost}}
	if !*countGames {
		required = []struct{ name, val string }{
			{"plr", *plrPath}, {"sco-pre", *scoPre}, {"sco-post", *scoPost},
			{"replay", *replayPath}, {"out", *outDir},
		}
	}
	for _, r := range required {
		if r.val == "" {
			fmt.Fprintf(stderr, "jsbreplay: missing -%s\n", r.name)
			return exitUsage
		}
	}

	if *countGames {
		day, err := readDay(*scoPre, *scoPost)
		if err != nil {
			fmt.Fprintln(stderr, "jsbreplay:", err)
			return exitInput
		}
		if _, err := fmt.Fprintf(stdout, "{\"games\":%d}\n", len(day)); err != nil {
			fmt.Fprintln(stderr, "jsbreplay:", err)
			return exitInput
		}
		return exitOK
	}

	if err := replayDay(*plrPath, *schPath, *scoPre, *scoPost, *replayPath, *outDir); err != nil {
		fmt.Fprintln(stderr, "jsbreplay:", err)
		return exitInput
	}
	return exitOK
}

func replayDay(plrPath, schPath, scoPre, scoPost, replayPath, outDir string) error {
	rf, err := readReplay(replayPath)
	if err != nil {
		return err
	}
	day, err := readDay(scoPre, scoPost)
	if err != nil {
		return err
	}
	if len(rf.Games) != len(day) {
		return fmt.Errorf("replay has %d games, sco day has %d", len(rf.Games), len(day))
	}

	if schPath == "" {
		schPath = strings.TrimSuffix(plrPath, filepath.Ext(plrPath)) + ".sch"
	}
	players, err := readFile(plrPath, backup.ReadPlr)
	if err != nil {
		return err
	}
	sched, err := readFile(schPath, backup.ReadSch)
	if err != nil {
		return err
	}
	b, err := backup.ToBundle(players, sched, backup.AssembleOptions{})
	if err != nil {
		return err
	}
	idx, err := matchSchedule(b, day)
	if err != nil {
		return err
	}
	teamOf := make(map[int]int, len(b.Players))
	for _, p := range b.Players {
		teamOf[p.PID] = p.TeamID
	}

	var draws bytes.Buffer
	jsbBoxes := make([]gameBox, 0, len(day))
	engBoxes := make([]*gameBox, 0, len(day))
	for k, sg := range day {
		out, err := replayGame(b, b.Schedule[idx[k]], rf.Games[k])
		if err != nil {
			return fmt.Errorf("game %d: %w", k, err)
		}
		if err := writeDrawLines(&draws, k, rf.Games[k], out); err != nil {
			return err
		}
		jsbBoxes = append(jsbBoxes, fromSco(sg))
		if out.Result == nil {
			engBoxes = append(engBoxes, nil)
		} else {
			eb := fromResult(*out.Result, teamOf)
			engBoxes = append(engBoxes, &eb)
		}
	}

	if err := os.MkdirAll(outDir, 0o755); err != nil {
		return fmt.Errorf("create %q: %w", outDir, err)
	}
	if err := os.WriteFile(filepath.Join(outDir, "engine_draws.jsonl"), draws.Bytes(), 0o644); err != nil {
		return err
	}
	if err := writeJSON(filepath.Join(outDir, "jsb_box.json"), jsbBoxes); err != nil {
		return err
	}
	return writeJSON(filepath.Join(outDir, "engine_box.json"), engBoxes)
}

// readDay reads both .sco files and returns the day's appended games.
func readDay(prePath, postPath string) ([]backup.ScoGame, error) {
	pre, err := readFile(prePath, backup.ReadSco)
	if err != nil {
		return nil, err
	}
	post, err := readFile(postPath, backup.ReadSco)
	if err != nil {
		return nil, err
	}
	return dayGames(pre, post)
}

// dayGames returns the records post appends to pre. The .sco is cumulative, so
// the day's games are the appended records, in the order JSB played them.
func dayGames(pre, post []backup.ScoGame) ([]backup.ScoGame, error) {
	if len(post) < len(pre) {
		return nil, errors.New("sco not append-only")
	}
	for i := range pre {
		if !reflect.DeepEqual(pre[i], post[i]) {
			return nil, errors.New("sco not append-only")
		}
	}
	return post[len(pre):], nil
}

func readReplay(path string) (replayFile, error) {
	data, err := os.ReadFile(path)
	if err != nil {
		return replayFile{}, fmt.Errorf("open %q: %w", path, err)
	}
	var rf replayFile
	if err := json.Unmarshal(data, &rf); err != nil {
		return replayFile{}, fmt.Errorf("parse %q: %w", path, err)
	}
	if rf.Format != replayFormat {
		return replayFile{}, fmt.Errorf("replay format %q, want %q", rf.Format, replayFormat)
	}
	return rf, nil
}

// matchSchedule maps each day game to the earliest unused schedule entry with
// the same visitor and home team ids.
func matchSchedule(b bundle.Bundle, day []backup.ScoGame) ([]int, error) {
	used := make([]bool, len(b.Schedule))
	idx := make([]int, len(day))
	for k, sg := range day {
		found := -1
		for i, g := range b.Schedule {
			if !used[i] && g.VisitorTeamID == sg.VisitorTeamID && g.HomeTeamID == sg.HomeTeamID {
				found = i
				break
			}
		}
		if found < 0 {
			return nil, fmt.Errorf("game %d not in schedule", k)
		}
		used[found] = true
		idx[k] = found
	}
	return idx, nil
}

// replayGame plays one game from the recorded values on a one-game copy of the
// bundle. Running out of values marks the game exhausted; any other panic
// propagates.
func replayGame(b bundle.Bundle, g bundle.Game, values []int) (out gameOut, err error) {
	rep, err := rng.NewReplay(values)
	if err != nil {
		return gameOut{}, err
	}
	b1 := b
	b1.Schedule = []bundle.Game{g}
	defer func() {
		if r := recover(); r != nil {
			ex, ok := r.(*rng.ExhaustedError)
			if !ok {
				panic(r)
			}
			out = gameOut{Draws: rep.Draws(), Exhausted: ex}
			err = nil
		}
	}()
	res, err := sim.SimulateWith(b1, 0, sim.Options{RNG: rep})
	if err != nil {
		return gameOut{}, err
	}
	gr := res.Games[0]
	return gameOut{Draws: rep.Draws(), Result: &gr}, nil
}

// writeDrawLines appends game k's draw log as JSON lines. An exhausted game
// ends with a line marking the draw the engine wanted past the recorded values.
func writeDrawLines(w io.Writer, k int, values []int, out gameOut) error {
	enc := json.NewEncoder(w)
	for i, d := range out.Draws {
		line := drawLine{Game: k, I: i, API: d.API, N: d.N, V: d.Value, Caller: d.Caller, Fn: sim.DrawSites[d.Caller]}
		if err := enc.Encode(line); err != nil {
			return err
		}
	}
	if out.Exhausted != nil {
		e := out.Exhausted
		return enc.Encode(exhaustedLine{
			Game: k, I: len(values), Exhausted: true,
			API: e.API, Caller: e.Caller, Fn: sim.DrawSites[e.Caller],
		})
	}
	return nil
}

func sortLines(lines []boxLine) []boxLine {
	sort.Slice(lines, func(i, j int) bool {
		if lines[i].Team != lines[j].Team {
			return lines[i].Team < lines[j].Team
		}
		return lines[i].PID < lines[j].PID
	})
	return lines
}

// fromSco maps a .sco game to the shared box shape. The .sco FGM/FGA slots are
// 2-point only, which is what TwoGM/TwoGA hold. Team-total rows (pid 0) and
// DNP rows (min 0) are dropped.
func fromSco(g backup.ScoGame) gameBox {
	lines := make([]boxLine, 0, len(g.Boxes))
	for _, x := range g.Boxes {
		if x.PlayerID == 0 || x.Min == 0 {
			continue
		}
		lines = append(lines, boxLine{
			Team: x.TeamID, PID: x.PlayerID, Min: x.Min,
			TwoM: x.TwoGM, TwoA: x.TwoGA, FTM: x.FTM, FTA: x.FTA,
			Thr: x.ThreeGM, ThrA: x.ThreeGA, ORB: x.ORB, DRB: x.DRB,
			AST: x.AST, STL: x.STL, TOV: x.TOV, BLK: x.BLK, PF: x.PF,
		})
	}
	return gameBox{
		Visitor: g.VisitorTeamID, Home: g.HomeTeamID,
		VisitorScore: g.VisitorScore, HomeScore: g.HomeScore,
		Players: sortLines(lines),
	}
}

// fromResult maps an engine game to the shared box shape. PlayerBox carries no
// team id, so teamOf (pid to team id, from the bundle) supplies it. Each score
// is Q1+Q2+Q3+Q4 plus every OT period of that side's TeamBox.
func fromResult(gr result.GameResult, teamOf map[int]int) gameBox {
	lines := make([]boxLine, 0, len(gr.PlayerBoxes))
	for _, p := range gr.PlayerBoxes {
		if p.GameMIN == 0 {
			continue
		}
		lines = append(lines, boxLine{
			Team: teamOf[p.PID], PID: p.PID, Min: p.GameMIN,
			TwoM: p.Game2GM, TwoA: p.Game2GA, FTM: p.GameFTM, FTA: p.GameFTA,
			Thr: p.Game3GM, ThrA: p.Game3GA, ORB: p.GameORB, DRB: p.GameDRB,
			AST: p.GameAST, STL: p.GameSTL, TOV: p.GameTOV, BLK: p.GameBLK, PF: p.GamePF,
		})
	}
	gb := gameBox{Visitor: gr.VisitorTeamID, Home: gr.HomeTeamID, Players: sortLines(lines)}
	for _, tb := range gr.TeamBoxes {
		score := tb.Q1 + tb.Q2 + tb.Q3 + tb.Q4
		for _, ot := range tb.OT {
			score += ot
		}
		if tb.IsHome {
			gb.HomeScore = score
		} else {
			gb.VisitorScore = score
		}
	}
	return gb
}

func writeJSON(path string, v any) error {
	data, err := json.Marshal(v)
	if err != nil {
		return fmt.Errorf("marshal %q: %w", path, err)
	}
	data = append(data, '\n')
	if err := os.WriteFile(path, data, 0o644); err != nil {
		return fmt.Errorf("write %q: %w", path, err)
	}
	return nil
}

// readFile opens path and applies a backup reader, wrapping any error with
// the path.
func readFile[T any](path string, read func(io.Reader) ([]T, error)) ([]T, error) {
	f, err := os.Open(path)
	if err != nil {
		return nil, fmt.Errorf("open %q: %w", path, err)
	}
	defer func() { _ = f.Close() }()
	out, err := read(f)
	if err != nil {
		return nil, fmt.Errorf("parse %q: %w", path, err)
	}
	return out, nil
}
