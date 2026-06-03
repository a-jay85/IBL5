package backup

import (
	"errors"
	"fmt"
	"sort"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

// ErrUnknownTeam reports a schedule slot referencing a team that has no players
// in the parsed roster — the "schedule references an unknown team" boundary.
var ErrUnknownTeam = errors.New("backup: schedule references a team with no roster")

// AssembleOptions carries the bundle-level metadata the backup files do not
// themselves provide. The .sch has no game type, so the caller supplies one;
// the zero value defaults to a regular-season game.
type AssembleOptions struct {
	LeagueID int
	Seed     uint64
	GameType bundle.GameType // 0 -> GameTypeRegular (2)
	// Minutes maps a player ordinal -> dc_minutes, parsed from the snapshot's
	// .plb depth-chart file (see ReadPlb). A nil map means no .plb was present:
	// every player's DCMinutes falls back to 0, the historical behavior.
	Minutes map[int]int
}

// ToBundle assembles a parsed .plr roster and .sch schedule into a
// bundle.Bundle the native engine accepts. Players map onto bundle.Player by
// the shared ibl_plr field names; each schedule slot becomes a bundle.Game
// stamped with opts.GameType (the .sch carries none). Teams are the distinct
// IDs referenced by the schedule, in sorted order so the output is
// deterministic. An empty roster or schedule, or a schedule referencing a team
// with no roster players, is a typed error rather than a silent empty bundle.
//
// Two engine inputs are NOT per-player fields of the .plr text record and are
// supplied here instead of read from it:
//   - stamina — the energy ceiling. The .plr does not carry a per-player stamina
//     rating (verified: DB ibl_plr.stamina matches no .plr offset, and JSB zeroes
//     the ceiling on .plr load, then sets it from the conditioning roll whose
//     .plr source is a constant 100 across all players). So the faithful ceiling
//     is the uniform constant 100, which we assign directly rather than reading
//     the variable-width .plr tail block. See ibl5/docs/JSB_FILE_FORMATS.md.
//   - dc_minutes — the GM depth-chart minutes, sourced from the snapshot's .plb
//     file via opts.Minutes (keyed by player Ordinal). A nil map -> 0 (no .plb).
//
// The real-life / previous-season counting-stat sums (RealLifeMIN/FGA/FTA/ORB) ARE
// per-player .plr fields (static block at offsets 52-111, parsed by ReadPlr) and
// are mapped straight through; they feed the engine's per-48-minute shot-volume
// rate composite (sim/bucketweights.go). On the PHP production path the same bundle
// fields come from PlrParserService's rl_* columns (a follow-on PR).
//
// Still zeroed (correct, not a gap):
//   - r_foul — no foul rating in the .plr ratings block.
//   - dc_of/df/oi/di/bh — dead on IBL5 data; the engine deliberately never reads
//     them (see internal/sim/lineup.go), and the .plb carries them but we do not
//     wire them since populating dead fields only churns output.
func ToBundle(players []PlrPlayer, sched []SchGame, opts AssembleOptions) (bundle.Bundle, error) {
	if len(players) == 0 {
		return bundle.Bundle{}, fmt.Errorf("backup: assemble: %w", bundle.ErrEmptyRoster)
	}
	if len(sched) == 0 {
		return bundle.Bundle{}, fmt.Errorf("backup: assemble: %w", bundle.ErrNoSchedule)
	}

	gameType := opts.GameType
	if gameType == 0 {
		gameType = bundle.GameTypeRegular
	}

	rosterTeams := make(map[int]bool, 32)
	bundlePlayers := make([]bundle.Player, 0, len(players))
	for _, p := range players {
		rosterTeams[p.TeamID] = true
		bundlePlayers = append(bundlePlayers, toBundlePlayer(p, opts.Minutes))
	}

	scheduleTeams := make(map[int]bool, 32)
	games := make([]bundle.Game, 0, len(sched))
	for _, g := range sched {
		if !rosterTeams[g.VisitorTeamID] {
			return bundle.Bundle{}, fmt.Errorf("%w: visitor team %d", ErrUnknownTeam, g.VisitorTeamID)
		}
		if !rosterTeams[g.HomeTeamID] {
			return bundle.Bundle{}, fmt.Errorf("%w: home team %d", ErrUnknownTeam, g.HomeTeamID)
		}
		scheduleTeams[g.VisitorTeamID] = true
		scheduleTeams[g.HomeTeamID] = true
		games = append(games, bundle.Game{
			HomeTeamID:    g.HomeTeamID,
			VisitorTeamID: g.VisitorTeamID,
			Date:          fmt.Sprintf("%02d-%02d", g.Month, g.Day),
			GameType:      gameType,
		})
	}

	teamIDs := make([]int, 0, len(scheduleTeams))
	for id := range scheduleTeams {
		teamIDs = append(teamIDs, id)
	}
	sort.Ints(teamIDs) // deterministic ordering — never map-iteration order
	teams := make([]bundle.Team, 0, len(teamIDs))
	for _, id := range teamIDs {
		teams = append(teams, bundle.Team{TeamID: id, Name: fmt.Sprintf("team-%d", id)})
	}

	return bundle.Bundle{
		LeagueID: opts.LeagueID,
		Seed:     opts.Seed,
		Teams:    teams,
		Players:  bundlePlayers,
		Schedule: games,
	}, nil
}

// toBundlePlayer maps a .plr record onto bundle.Player. The ODPT pairing
// follows the .plr rating names: DO=drive offense -> r_drive_off,
// TO=transition offense -> r_trans_off. minutes maps player ordinal ->
// dc_minutes from the .plb (nil -> 0).
func toBundlePlayer(p PlrPlayer, minutes map[int]int) bundle.Player {
	return bundle.Player{
		PID:    p.PID,
		Name:   p.Name,
		TeamID: p.TeamID,

		OO:       p.RatingOO,
		OD:       p.RatingOD,
		DriveOff: p.RatingDO,
		DD:       p.RatingDD,
		PO:       p.RatingPO,
		PD:       p.RatingPD,
		TransOff: p.RatingTO,
		TD:       p.RatingTD,

		FGA: p.RatingFGA,
		FGP: p.RatingFGP,
		FTA: p.RatingFTA,
		FTP: p.RatingFTP,
		TGA: p.Rating3GA,
		TGP: p.Rating3GP,
		ORB: p.RatingORB,
		DRB: p.RatingDRB,
		AST: p.RatingAST,
		STL: p.RatingSTL,
		TVR: p.RatingTVR,
		BLK: p.RatingBLK,
		// Foul: no .plr source -> 0.

		// Real-life / previous-season sums -> the per-48-minute rate composite inputs.
		RealLifeMIN: p.RealLifeMIN,
		RealLifeFGA: p.RealLifeFGA,
		RealLifeFTA: p.RealLifeFTA,
		RealLifeORB: p.RealLifeORB,

		Age:         p.Age,
		Clutch:      p.Clutch,
		Consistency: p.Consistency,
		Talent:      p.Talent,
		Skill:       p.Skill,
		Intangibles: p.Intangibles,
		Peak:        p.Peak,
		// Stamina is the energy ceiling. The .plr carries no per-player stamina;
		// JSB's faithful ceiling is the uniform constant 100 (see ToBundle doc).
		Stamina: 100,

		DCPGDepth:       p.PGDepth,
		DCSGDepth:       p.SGDepth,
		DCSFDepth:       p.SFDepth,
		DCPFDepth:       p.PFDepth,
		DCCDepth:        p.CDepth,
		DCCanPlayInGame: p.CanPlayInGame,
		// DCMinutes from the .plb (keyed by Ordinal; missing/nil -> 0). DCOf/Df/
		// Oi/Di/Bh stay 0 — dead fields the engine never reads (see ToBundle doc).
		DCMinutes: minutes[p.Ordinal],
	}
}
