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
}

// ToBundle assembles a parsed .plr roster and .sch schedule into a
// bundle.Bundle the native engine accepts. Players map onto bundle.Player by
// the shared ibl_plr field names; each schedule slot becomes a bundle.Game
// stamped with opts.GameType (the .sch carries none). Teams are the distinct
// IDs referenced by the schedule, in sorted order so the output is
// deterministic. An empty roster or schedule, or a schedule referencing a team
// with no roster players, is a typed error rather than a silent empty bundle.
//
// Fields the engine reads that the .plr format does not store are zeroed here:
//   - r_foul   — no foul rating in the .plr ratings block
//   - stamina  — an ibl_plr column, but not present in the .plr file layout
//   - dc_minutes — sourced from the DB depth chart, not the .plr
//
// The dc_of/df/oi/di/bh depth fields are also zero, which is correct (not a
// gap): they are dead on IBL5 data and the engine deliberately never reads them
// (see internal/sim/lineup.go). This means a backup-driven sim runs with no
// per-player minutes/stamina signal; PR9b accounts for that when choosing
// tolerance bands. PR9a only proves the bundle is structurally engine-valid.
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
		bundlePlayers = append(bundlePlayers, toBundlePlayer(p))
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
// TO=transition offense -> r_trans_off.
func toBundlePlayer(p PlrPlayer) bundle.Player {
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

		Age:         p.Age,
		Clutch:      p.Clutch,
		Consistency: p.Consistency,
		Talent:      p.Talent,
		Skill:       p.Skill,
		Intangibles: p.Intangibles,
		Peak:        p.Peak,
		// Stamina: no .plr source -> 0.

		DCPGDepth:       p.PGDepth,
		DCSGDepth:       p.SGDepth,
		DCSFDepth:       p.SFDepth,
		DCPFDepth:       p.PFDepth,
		DCCDepth:        p.CDepth,
		DCCanPlayInGame: p.CanPlayInGame,
		// DCMinutes and DCOf/Df/Oi/Di/Bh: no .plr source -> 0 (see ToBundle doc).
	}
}
