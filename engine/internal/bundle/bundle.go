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
//
// DRBRate/ASTRate are the per-48 team defensive-rebound and assist rates that feed
// the JSB Branch-B usage-shrink (sim/bucketweights.go): the usage target is
// TransOff × (DRBRate + ASTRate) × 0.2 × 0.04. They are (Σ_player season_DRB /
// Σ_player season_GP)×48 and (Σ_player season_AST / Σ_player season_GP)×44 — the
// faithful JSB accumulation (COMPOSITE_DOUBLES_TRACE.md §1; team[+0xDC0]/[+0xDD0]).
// The backup-driven calibration path populates them (backup.ToBundle); the DB-built
// bundle leaves them 0 (Branch-B inert there) until a future PR wires DB rates.
// JSON tags drb_rate/ast_rate match the prospective PHP bundle columns.
type Team struct {
	TeamID  int     `json:"teamid"`
	Name    string  `json:"name"`
	DRBRate float64 `json:"drb_rate"`
	ASTRate float64 `json:"ast_rate"`
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
	// rate inputs. JSON tags match PHP PlrParserService rl_* columns; the Go backup
	// assembler populates them from the static real-life .plr block (offsets 52-111).
	// Absent/zero MINUTES — rookie or un-wired bundle — falls back to rating stand-ins.
	//
	// Shot-volume rate inputs (D88/DB8/D70 = (stat/MIN)×48) for the 2pt-bucket
	// composite: RealLifeMIN/FGA/FTA/3GA/ORB. RealLifeFGA is total FG attempts (incl. 3PA).
	// RealLifeGP and RealLife3GA also feed the league 2PA/48 shot baseline
	// (sim/shotdecision.go leagueShotBaseline).
	//
	// Quality composite inputs for defQ/offQ (sim/teamquality.go):
	// RealLifeSTL (STL/MIN×44) and RealLifeTVR (TOV/MIN×48) — the faithful per-player
	// J22 wiring. PHP PlrParserService already emits rl_stl/rl_tvr; these JSON tags are
	// the matching Go half. Zero MINUTES → rating stand-in fallback (RealLifeMIN==0 guard).
	RealLifeGP  int `json:"rl_gp"`
	RealLifeMIN int `json:"rl_min"`
	RealLifeFGA int `json:"rl_fga"`
	RealLifeFTA int `json:"rl_fta"`
	RealLife3GA int `json:"rl_3ga"`
	RealLifeORB int `json:"rl_orb"`
	RealLifeSTL int `json:"rl_stl"`
	RealLifeTVR int `json:"rl_tvr"`
	RealLifeFGM int `json:"rl_fgm"`
	RealLife3GM int `json:"rl_3gm"`
	RealLifeBLK int `json:"rl_blk"`

	// Per-player derived make-rate composites computed at bundle-build time
	// (backup/assemble.go toBundlePlayer). D80/D60/D64 are per-mille values;
	// DE8 is BLK/MIN×48. Zero when the player has no real-life counting-stat data
	// (rookies, DB-built bundles) — shotdecision.go falls back to fgpToPermille
	// for D64==0 and D60==0 (mirror of shotBaselineOrFallback).
	//
	// D80 = round(3GM/3GA×1000), 0 if 3GA≤0       [3pt make %‰]
	// D60 = round((FGM-3GM)/(FGA-3GA)×1000), 0 if (FGA-3GA)≤0  [2pt make %‰]
	// D64 = round(D60×(4×D90−D88)/(3×D90)), 0 if D90≤0 [putback-adjusted 2P‰;
	//        D90=3GA/MIN×48, D88=(FGA-3GA)/MIN×48 — same derivation as bucketweights.go:260-265]
	// DE8 = BLK/MIN×48, 0 if MIN≤0                [blocks per 48 min]
	D80 int     `json:"d80"`
	D60 int     `json:"d60"`
	D64 int     `json:"d64"`
	DE8 float64 `json:"de8"`

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
//
// LeagueShotBaseline is the league 2PA-per-48-player-minutes shot baseline
// (CEngine+0x6638, the FUN_004385f0 league-table port — sim/shotdecision.go
// shotValue2pt/shotValue3pt). It is assembled ONCE per snapshot from the raw
// .plr records (backup.ToBundle's computeLeagueShotBaseline), NOT from the
// bundle's player list: the qualifying population is .plr file records 1-959
// with a non-empty name and RealLifeMIN > 2×RealLifeGP, which is narrower than
// (and record-position-gated relative to) the assembled Players slice. A
// bundle that has not wired this field (e.g. a hand-built test bundle) leaves
// it 0, and the sim degrades to the documented constant fallback
// (sim/state.go shotBaselineOrFallback) rather than dividing by zero.
type Bundle struct {
	LeagueID           int      `json:"league_id"`
	Seed               uint64   `json:"seed"`
	Teams              []Team   `json:"teams"`
	Players            []Player `json:"players"`
	Schedule           []Game   `json:"schedule"`
	LeagueShotBaseline float64  `json:"league_shot_baseline"`
	// LeagueBlk48 is the league BLK-per-48-player-minutes rate (analogous to
	// LeagueShotBaseline), assembled once per snapshot by backup.ToBundle's
	// computeLeagueBlk48 over raw .plr records ≤959 with MIN>2×GP and non-empty
	// Name. Expected ~1.7484 on the game-install IBL5.plr. A zero value (unwired
	// bundle) leaves blockMod returning 0 — a graceful no-op, since the cap
	// defBlkSum ≤ 1.5×5×leagueBlk48 forces defBlkSum to 0 when leagueBlk48=0.
	LeagueBlk48 float64 `json:"league_blk48"`
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
