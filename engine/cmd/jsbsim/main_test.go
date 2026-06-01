package main

import (
	"bytes"
	"encoding/json"
	"strings"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/result"
)

const oneGameBundle = `{
  "seed": 42,
  "teams": [{"teamid": 3, "name": "Heat"}, {"teamid": 7, "name": "Lakers"}],
  "players": [
    {"pid": 101, "teamid": 3, "dc_minutes": 34, "dc_pg_depth": 1, "dc_can_play_in_game": 1},
    {"pid": 201, "teamid": 7, "dc_minutes": 30, "dc_sg_depth": 1, "dc_can_play_in_game": 1}
  ],
  "schedule": [{"home_team_id": 3, "visitor_team_id": 7, "date": "1988-11-04", "game_type": 2}]
}`

// #2 (negative) — malformed bundle JSON makes run return an error (the process
// would exit nonzero) and writes nothing usable to stdout.
func TestRun_MalformedJSONErrors(t *testing.T) {
	var out bytes.Buffer
	err := run(nil, strings.NewReader("{not valid json"), &out)
	if err == nil {
		t.Fatal("expected error for malformed JSON, got nil")
	}
}

// #2 (negative) — a structurally-invalid bundle (empty roster) also errors.
func TestRun_EmptyRosterErrors(t *testing.T) {
	var out bytes.Buffer
	j := `{"seed":1,"players":[],"schedule":[{"home_team_id":3,"visitor_team_id":7,"date":"d","game_type":2}]}`
	if err := run(nil, strings.NewReader(j), &out); err == nil {
		t.Fatal("expected error for empty roster, got nil")
	}
}

// #9 — the --seed flag overrides the bundle seed, and the seed actually used is
// echoed in the output.
func TestRun_SeedOverrideEchoed(t *testing.T) {
	// Without override: output echoes the bundle seed (42).
	var out bytes.Buffer
	if err := run(nil, strings.NewReader(oneGameBundle), &out); err != nil {
		t.Fatalf("run: %v", err)
	}
	if seed := decodeSeed(t, out.Bytes()); seed != 42 {
		t.Errorf("no override: seed = %d, want 42", seed)
	}

	// With override: output echoes the overriding seed (777).
	out.Reset()
	if err := run([]string{"--seed", "777"}, strings.NewReader(oneGameBundle), &out); err != nil {
		t.Fatalf("run --seed 777: %v", err)
	}
	if seed := decodeSeed(t, out.Bytes()); seed != 777 {
		t.Errorf("override: seed = %d, want 777", seed)
	}
}

// decodeSeed reads only the NDJSON header line (the first line) and unmarshals
// its {seed} field. The game lines that follow are not part of the header.
func decodeSeed(t *testing.T, data []byte) uint64 {
	t.Helper()
	nl := bytes.IndexByte(data, '\n')
	if nl < 0 {
		t.Fatalf("no header line (no newline) in output: %q", data)
	}
	var res struct {
		Seed uint64 `json:"seed"`
	}
	if err := json.Unmarshal(data[:nl], &res); err != nil {
		t.Fatalf("decoding header line: %v", err)
	}
	return res.Seed
}

// gameLines returns the non-empty lines after the header line.
func gameLines(t *testing.T, data []byte) [][]byte {
	t.Helper()
	lines := bytes.Split(data, []byte("\n"))
	if len(lines) == 0 {
		t.Fatal("empty output")
	}
	var games [][]byte
	for _, line := range lines[1:] {
		if len(bytes.TrimSpace(line)) == 0 {
			continue
		}
		games = append(games, line)
	}
	return games
}

// #2 — NDJSON shape: header line {seed:42} + exactly one compact game line per
// scheduled game, each single-line (no embedded newline, no two-space indent),
// each decoding to a GameResult with the expected date/team ids.
func TestRun_NDJSONShape(t *testing.T) {
	var out bytes.Buffer
	if err := run(nil, strings.NewReader(oneGameBundle), &out); err != nil {
		t.Fatalf("run: %v", err)
	}

	if seed := decodeSeed(t, out.Bytes()); seed != 42 {
		t.Errorf("header seed = %d, want 42", seed)
	}

	games := gameLines(t, out.Bytes())
	if len(games) != 1 {
		t.Fatalf("game lines = %d, want 1", len(games))
	}

	line := games[0]
	if bytes.Contains(line, []byte("  ")) {
		t.Errorf("game line is indented (not compact): %q", line)
	}
	var g result.GameResult
	if err := json.Unmarshal(line, &g); err != nil {
		t.Fatalf("decoding game line: %v", err)
	}
	if g.Date != "1988-11-04" {
		t.Errorf("game date = %q, want 1988-11-04", g.Date)
	}
	if g.HomeTeamID != 3 || g.VisitorTeamID != 7 {
		t.Errorf("teams = home %d / visitor %d, want home 3 / visitor 7", g.HomeTeamID, g.VisitorTeamID)
	}
}

// #3 (boundary) — the emitted game-line count equals the schedule length
// exactly: one compact line per scheduled game, no trailing garbage and no
// phantom line. (The bundle decoder rejects an empty schedule, so a literal
// zero-games stream is unreachable through run(); the consumer-side header-only
// case is covered by EngineRunnerTest::emptyGamesHeaderOnlyReturnsZero.)
func TestRun_GameLineCountMatchesSchedule(t *testing.T) {
	twoGameBundle := `{
	  "seed": 42,
	  "teams": [{"teamid": 3, "name": "Heat"}, {"teamid": 7, "name": "Lakers"}],
	  "players": [
	    {"pid": 101, "teamid": 3, "dc_minutes": 34, "dc_pg_depth": 1, "dc_can_play_in_game": 1},
	    {"pid": 201, "teamid": 7, "dc_minutes": 30, "dc_sg_depth": 1, "dc_can_play_in_game": 1}
	  ],
	  "schedule": [
	    {"home_team_id": 3, "visitor_team_id": 7, "date": "1988-11-04", "game_type": 2},
	    {"home_team_id": 7, "visitor_team_id": 3, "date": "1988-11-06", "game_type": 2}
	  ]
	}`

	var out bytes.Buffer
	if err := run(nil, strings.NewReader(twoGameBundle), &out); err != nil {
		t.Fatalf("run: %v", err)
	}

	if seed := decodeSeed(t, out.Bytes()); seed != 42 {
		t.Errorf("header seed = %d, want 42", seed)
	}
	games := gameLines(t, out.Bytes())
	if len(games) != 2 {
		t.Fatalf("game lines = %d, want 2 (one per scheduled game, no trailing garbage)", len(games))
	}
	for i, line := range games {
		var g result.GameResult
		if err := json.Unmarshal(line, &g); err != nil {
			t.Fatalf("decoding game line %d: %v", i, err)
		}
	}
}
