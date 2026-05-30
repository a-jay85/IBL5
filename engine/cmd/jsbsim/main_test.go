package main

import (
	"bytes"
	"encoding/json"
	"strings"
	"testing"
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

func decodeSeed(t *testing.T, data []byte) uint64 {
	t.Helper()
	var res struct {
		Seed uint64 `json:"seed"`
	}
	if err := json.Unmarshal(data, &res); err != nil {
		t.Fatalf("decoding output: %v", err)
	}
	return res.Seed
}
