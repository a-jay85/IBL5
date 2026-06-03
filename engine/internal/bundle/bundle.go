// Package bundle defines the engine's input contract: the simulation "bundle"
// the PHP side builds from the database (or, for validation, from a historical
// backup .plr) and hands to the engine as JSON on stdin.
//
// JSON tags deliberately match the source-of-truth ibl_plr column names so the
// PHP bundle-builder (a later PR) maps 1:1 with no translation layer.
package bundle

import (
	"encoding/json"
	"errors"
	"fmt"
)

// GameType is the JSB game-type flag (CEngine+0x63b4 in the decompile). Only the
// values IBL actually runs are valid: 2/3 regular season, 4 playoff, 5/6
// all-star game. Exhibition (1) is intentionally rejected — IBL never runs it.
type GameType int

const (
	GameTypeRegular    GameType = 2
	GameTypeRegularAlt GameType = 3
	GameTypePlayoff    GameType = 4
	GameTypeAllStarA   GameType = 5
	GameTypeAllStarB   GameType = 6
)

func (g GameType) valid() bool {
	switch g {
	case GameTypeRegular, GameTypeRegularAlt, GameTypePlayoff, GameTypeAllStarA, GameTypeAllStarB:
		return true
	}
	return false
}

// UnmarshalJSON decodes an integer game-type and rejects any value IBL does not
// use, so a bad game_type fails fast at the contract boundary rather than deep
// in the sim.
func (g *GameType) UnmarshalJSON(data []byte) error {
	var v int
	if err := json.Unmarshal(data, &v); err != nil {
		return fmt.Errorf("game_type: %w", err)
	}
	gt := GameType(v)
	if !gt.valid() {
		return fmt.Errorf("game_type: unknown value %d (valid: 2,3 regular; 4 playoff; 5,6 all-star)", v)
	}
	*g = gt
	return nil
}

// Team identifies one franchise in the bundle.
type Team struct {
	TeamID int    `json:"teamid"`
	Name   string `json:"name"`
}

// Player carries the source-of-truth ratings from ibl_plr plus depth-chart
// settings. JSON tags equal the ibl_plr column names exactly.
type Player struct {
	PID    int    `json:"pid"`
	Name   string `json:"name"`
	TeamID int    `json:"teamid"`

	// ODPT ratings, 1-9 scale (paired order: offense/defense per play type).
	OO       int `json:"oo"`          // outside offense
	OD       int `json:"od"`          // outside defense
	DriveOff int `json:"r_drive_off"` // drive offense
	DD       int `json:"dd"`          // drive defense
	PO       int `json:"po"`          // post offense
	PD       int `json:"pd"`          // post defense
	TransOff int `json:"r_trans_off"` // transition offense
	TD       int `json:"td"`          // transition defense

	// Main ratings, 0-99 scale.
	FGA  int `json:"r_fga"`
	FGP  int `json:"r_fgp"`
	FTA  int `json:"r_fta"`
	FTP  int `json:"r_ftp"`
	TGA  int `json:"r_3ga"`
	TGP  int `json:"r_3gp"`
	ORB  int `json:"r_orb"`
	DRB  int `json:"r_drb"`
	AST  int `json:"r_ast"`
	STL  int `json:"r_stl"`
	TVR  int `json:"r_tvr"`
	BLK  int `json:"r_blk"`
	Foul int `json:"r_foul"`

	// Real-life / previous-season counting-stat sums — the engine's per-48-MINUTE
	// shot-volume rate inputs (D88/DB8/D70 = (stat/MIN)×48) for the 2pt-bucket
	// composite. JSON tags match the PHP PlrParserService rl_* derived columns so the
	// production bundle-builder (a follow-on PR) maps 1:1. The Go backup assembler
	// populates them from the static real-life .plr block (offsets 52-111). Absent/
	// zero MINUTES — a player with no prior-season reference, or a production bundle
	// not yet wired — falls back to the rating stand-in (sim/bucketweights.go).
	// RealLifeFGA is total FG attempts (incl. 3PA).
	RealLifeMIN int `json:"rl_min"`
	RealLifeFGA int `json:"rl_fga"`
	RealLifeFTA int `json:"rl_fta"`
	RealLifeORB int `json:"rl_orb"`

	// Attributes.
	Age         int `json:"age"`
	Stamina     int `json:"stamina"`
	Clutch      int `json:"clutch"`
	Consistency int `json:"consistency"`
	Talent      int `json:"talent"`
	Skill       int `json:"skill"`
	Intangibles int `json:"intangibles"`
	Peak        int `json:"peak"`

	// Depth-chart settings.
	DCMinutes       int `json:"dc_minutes"`
	DCPGDepth       int `json:"dc_pg_depth"`
	DCSGDepth       int `json:"dc_sg_depth"`
	DCSFDepth       int `json:"dc_sf_depth"`
	DCPFDepth       int `json:"dc_pf_depth"`
	DCCDepth        int `json:"dc_c_depth"`
	DCCanPlayInGame int `json:"dc_can_play_in_game"`
	DCOf            int `json:"dc_of"`
	DCDf            int `json:"dc_df"`
	DCOi            int `json:"dc_oi"`
	DCDi            int `json:"dc_di"`
	DCBh            int `json:"dc_bh"`
}

// Game is one scheduled matchup to simulate.
type Game struct {
	HomeTeamID    int      `json:"home_team_id"`
	VisitorTeamID int      `json:"visitor_team_id"`
	Date          string   `json:"date"`
	GameType      GameType `json:"game_type"`
}

// Bundle is the complete engine input for one sim run.
type Bundle struct {
	LeagueID int      `json:"league_id"`
	Seed     uint64   `json:"seed"`
	Teams    []Team   `json:"teams"`
	Players  []Player `json:"players"`
	Schedule []Game   `json:"schedule"`
}

// Validation errors surfaced at the contract boundary.
var (
	ErrEmptyRoster = errors.New("bundle: players is empty")
	ErrNoSchedule  = errors.New("bundle: schedule is empty")
)

// Decode reads and validates a bundle from JSON. Structurally-invalid input
// (bad JSON, unknown game_type, empty roster) is rejected here so it surfaces
// as a clean error rather than a panic inside the sim.
func Decode(data []byte) (Bundle, error) {
	var b Bundle
	if err := json.Unmarshal(data, &b); err != nil {
		return Bundle{}, err
	}
	if err := b.Validate(); err != nil {
		return Bundle{}, err
	}
	return b, nil
}

// Validate checks invariants that must hold for any bundle, whether decoded
// from JSON or constructed in code. (game_type is already validated during
// unmarshal; the loop here defends bundles built directly in Go.)
func (b Bundle) Validate() error {
	if len(b.Players) == 0 {
		return ErrEmptyRoster
	}
	if len(b.Schedule) == 0 {
		return ErrNoSchedule
	}
	for i, g := range b.Schedule {
		if !g.GameType.valid() {
			return fmt.Errorf("bundle: schedule[%d]: invalid game_type %d", i, int(g.GameType))
		}
	}
	return nil
}
