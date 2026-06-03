package bundle

import (
	"encoding/json"
	"errors"
	"testing"
)

// validBundleJSON is a minimal but complete bundle covering one player and one
// regular-season game.
const validBundleJSON = `{
  "league_id": 1,
  "seed": 42,
  "teams": [{"teamid": 3, "name": "Heat"}, {"teamid": 7, "name": "Lakers"}],
  "players": [{
    "pid": 101, "name": "Test Player", "teamid": 3,
    "oo": 5, "od": 4, "r_drive_off": 6, "dd": 3, "po": 2, "pd": 5, "r_trans_off": 7, "td": 4,
    "r_fga": 70, "r_fgp": 48, "r_fta": 30, "r_ftp": 80, "r_3ga": 40, "r_3gp": 36,
    "r_orb": 20, "r_drb": 50, "r_ast": 45, "r_stl": 15, "r_tvr": 12, "r_blk": 8, "r_foul": 25,
    "age": 27, "stamina": 88, "clutch": 5, "consistency": 3,
    "talent": 80, "skill": 75, "intangibles": 60, "peak": 28,
    "dc_minutes": 34, "dc_pg_depth": 1, "dc_sg_depth": 0, "dc_sf_depth": 0,
    "dc_pf_depth": 0, "dc_c_depth": 0, "dc_can_play_in_game": 1,
    "dc_of": 3, "dc_df": 3, "dc_oi": 3, "dc_di": 3, "dc_bh": 3
  }],
  "schedule": [{"home_team_id": 3, "visitor_team_id": 7, "date": "1988-11-04", "game_type": 2}]
}`

// #1 — a valid bundle decodes with every rating, attribute, depth, and schedule
// field populated, and round-trips back to JSON.
func TestDecode_RoundTripPopulatesAllFields(t *testing.T) {
	b, err := Decode([]byte(validBundleJSON))
	if err != nil {
		t.Fatalf("Decode: %v", err)
	}
	if b.Seed != 42 || b.LeagueID != 1 {
		t.Fatalf("top-level: seed=%d league=%d", b.Seed, b.LeagueID)
	}
	if len(b.Players) != 1 || len(b.Schedule) != 1 || len(b.Teams) != 2 {
		t.Fatalf("counts: players=%d schedule=%d teams=%d", len(b.Players), len(b.Schedule), len(b.Teams))
	}
	p := b.Players[0]
	// Spot-check one field from each group to prove tags bound correctly.
	if p.DriveOff != 6 || p.TransOff != 7 {
		t.Errorf("ODPT renamed cols: r_drive_off=%d r_trans_off=%d", p.DriveOff, p.TransOff)
	}
	if p.TGA != 40 || p.TVR != 12 {
		t.Errorf("main ratings: r_3ga=%d r_tvr=%d", p.TGA, p.TVR)
	}
	if p.DCMinutes != 34 || p.DCCanPlayInGame != 1 || p.DCPGDepth != 1 {
		t.Errorf("depth: dc_minutes=%d dc_can_play_in_game=%d dc_pg_depth=%d", p.DCMinutes, p.DCCanPlayInGame, p.DCPGDepth)
	}
	if p.Stamina != 88 || p.Peak != 28 {
		t.Errorf("attributes: stamina=%d peak=%d", p.Stamina, p.Peak)
	}
	if b.Schedule[0].GameType != GameTypeRegular {
		t.Errorf("game_type = %d, want 2", int(b.Schedule[0].GameType))
	}

	// Round-trip back to JSON and decode again — must be stable.
	out, err := json.Marshal(b)
	if err != nil {
		t.Fatalf("Marshal: %v", err)
	}
	if _, err := Decode(out); err != nil {
		t.Fatalf("re-Decode of marshaled bundle: %v", err)
	}
}

// Row 4 (boundary) — a bundle whose player omits the rl_* keys decodes with
// RealLife*==0 (production-omission tolerated → the engine falls back to the
// rating stand-in); when the keys ARE present they bind by tag.
func TestDecode_RealLifeAbsentZero(t *testing.T) {
	// validBundleJSON carries no rl_* keys.
	b, err := Decode([]byte(validBundleJSON))
	if err != nil {
		t.Fatalf("Decode: %v", err)
	}
	if p := b.Players[0]; p.RealLifeMIN != 0 || p.RealLifeFGA != 0 || p.RealLifeFTA != 0 || p.RealLifeORB != 0 {
		t.Errorf("absent rl_* should default to 0, got MIN=%d FGA=%d FTA=%d ORB=%d",
			p.RealLifeMIN, p.RealLifeFGA, p.RealLifeFTA, p.RealLifeORB)
	}

	withRL := `{"seed":1,"players":[{"pid":1,"teamid":3,"rl_min":2520,"rl_fga":1400,"rl_fta":360,"rl_orb":120}],` +
		`"schedule":[{"home_team_id":3,"visitor_team_id":7,"date":"d","game_type":2}]}`
	b2, err := Decode([]byte(withRL))
	if err != nil {
		t.Fatalf("Decode withRL: %v", err)
	}
	if p := b2.Players[0]; p.RealLifeMIN != 2520 || p.RealLifeFGA != 1400 || p.RealLifeFTA != 360 || p.RealLifeORB != 120 {
		t.Errorf("rl_* decode = MIN%d FGA%d FTA%d ORB%d, want 2520/1400/360/120",
			p.RealLifeMIN, p.RealLifeFGA, p.RealLifeFTA, p.RealLifeORB)
	}
}

// #3 (boundary) — an unknown game_type value is rejected at decode time.
func TestDecode_RejectsUnknownGameType(t *testing.T) {
	for _, gt := range []int{0, 1, 7, 99} {
		j := `{"seed":1,"players":[{"pid":1,"teamid":3}],"schedule":[{"home_team_id":3,"visitor_team_id":7,"date":"d","game_type":` + itoa(gt) + `}]}`
		if _, err := Decode([]byte(j)); err == nil {
			t.Errorf("game_type %d: expected rejection, got nil error", gt)
		}
	}
}

// #4 (boundary) — an empty roster is handled with a typed error, not a panic.
func TestDecode_RejectsEmptyRoster(t *testing.T) {
	j := `{"seed":1,"players":[],"schedule":[{"home_team_id":3,"visitor_team_id":7,"date":"d","game_type":2}]}`
	_, err := Decode([]byte(j))
	if !errors.Is(err, ErrEmptyRoster) {
		t.Fatalf("empty roster: err = %v, want ErrEmptyRoster", err)
	}
}

// itoa avoids pulling in strconv for a one-liner in test data construction.
func itoa(n int) string {
	if n == 0 {
		return "0"
	}
	neg := n < 0
	if neg {
		n = -n
	}
	var buf [20]byte
	i := len(buf)
	for n > 0 {
		i--
		buf[i] = byte('0' + n%10)
		n /= 10
	}
	if neg {
		i--
		buf[i] = '-'
	}
	return string(buf[i:])
}
