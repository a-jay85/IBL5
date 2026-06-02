package validate

import (
	"errors"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"sort"
	"strings"

	"github.com/a-jay85/IBL5/engine/internal/backup"
	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/sim"
)

// ErrNoCorpus reports a corpus directory that contains no complete
// .plr/.sch/.sco triple — an empty dir, or files that do not share a stem.
var ErrNoCorpus = errors.New("validate: no .plr/.sch/.sco triple found in corpus dir")

// UnmatchedGame is a .sco ground-truth game that could not be paired with a
// schedule entry, so the engine could not be pointed at the same matchup. It is
// reported (never silently dropped) and forces the Report to FAIL — an
// unvalidatable ground-truth game is a corpus-integrity signal, not a pass.
type UnmatchedGame struct {
	Stem          string
	VisitorTeamID int
	HomeTeamID    int
	VisitorScore  int
	HomeScore     int
	Reason        string
}

// ExcludedGame is an unmatched .sco game that ValidateUnscheduled could not
// simulate because a participating team has no .plr roster in the bundle, so the
// engine cannot build a lineup. It is reported (never silently dropped) so a
// malformed/edge .sco surfaces; unlike an UnmatchedGame in ValidateCorpus it
// does NOT force the Report to FAIL — it is an expected defensive skip, and the
// Phase-4 archive scan (not this report's Pass) asserts zero exclusions.
type ExcludedGame struct {
	Stem          string
	VisitorTeamID int
	HomeTeamID    int
	Date          string
	Reason        string
}

// Report is the harness's full result over a corpus directory. Pass is true
// only when every game's every stat passed AND no .sco game was left unmatched.
// Same (dir, runs, baseSeed, gameType) always yields an identical Report.
type Report struct {
	Runs      int
	BaseSeed  uint64
	GameType  bundle.GameType
	Pass      bool
	Games     []GameReport
	Unmatched []UnmatchedGame
	Excluded  []ExcludedGame
}

// triple names one backup file set sharing a stem within the corpus dir.
type triple struct {
	stem string
	plr  string
	sch  string
	sco  string
}

// findTriples groups the .plr/.sch/.sco files in dir by shared stem and returns
// only the complete triples, sorted by stem for deterministic processing order.
func findTriples(dir string) ([]triple, error) {
	entries, err := os.ReadDir(dir)
	if err != nil {
		return nil, fmt.Errorf("validate: read corpus dir: %w", err)
	}
	byStem := map[string]*triple{}
	get := func(stem string) *triple {
		if t, ok := byStem[stem]; ok {
			return t
		}
		t := &triple{stem: stem}
		byStem[stem] = t
		return t
	}
	for _, e := range entries {
		if e.IsDir() {
			continue
		}
		name := e.Name()
		ext := strings.ToLower(filepath.Ext(name))
		stem := strings.TrimSuffix(name, filepath.Ext(name))
		full := filepath.Join(dir, name)
		switch ext {
		case ".plr":
			get(stem).plr = full
		case ".sch":
			get(stem).sch = full
		case ".sco":
			get(stem).sco = full
		}
	}
	stems := make([]string, 0, len(byStem))
	for stem := range byStem {
		stems = append(stems, stem)
	}
	sort.Strings(stems)
	triples := make([]triple, 0, len(stems))
	for _, stem := range stems {
		t := byStem[stem]
		if t.plr != "" && t.sch != "" && t.sco != "" {
			triples = append(triples, *t)
		}
	}
	if len(triples) == 0 {
		return nil, ErrNoCorpus
	}
	return triples, nil
}

// readTriple reads and assembles one triple into a bundle plus the parsed
// schedule (kept for .sco↔.sch matching, which needs the historical scores the
// assembled bundle.Game discards) and the .sco ground-truth games.
func readTriple(t triple, gameType bundle.GameType) (bundle.Bundle, []backup.SchGame, []backup.ScoGame, error) {
	players, err := readFile(t.plr, backup.ReadPlr)
	if err != nil {
		return bundle.Bundle{}, nil, nil, err
	}
	sched, err := readFile(t.sch, backup.ReadSch)
	if err != nil {
		return bundle.Bundle{}, nil, nil, err
	}
	scoGames, err := readFile(t.sco, backup.ReadSco)
	if err != nil {
		return bundle.Bundle{}, nil, nil, err
	}
	b, err := backup.ToBundle(players, sched, backup.AssembleOptions{GameType: gameType})
	if err != nil {
		return bundle.Bundle{}, nil, nil, fmt.Errorf("validate: assemble %q: %w", t.stem, err)
	}
	return b, sched, scoGames, nil
}

// readFile opens path and applies a backup reader, wrapping any error with the
// path so a malformed corpus file is diagnosable.
func readFile[T any](path string, read func(io.Reader) ([]T, error)) ([]T, error) {
	f, err := os.Open(path)
	if err != nil {
		return nil, fmt.Errorf("validate: open %q: %w", path, err)
	}
	defer func() { _ = f.Close() }()
	out, err := read(f)
	if err != nil {
		return nil, fmt.Errorf("validate: parse %q: %w", path, err)
	}
	return out, nil
}

// ValidateCorpus runs the full harness over every complete triple in dir. For
// each .sco game it locates the matching schedule entry (by the historical
// (visitor, home, visitorScore, homeScore) tuple both files carry — bundle.Game
// has no scores and (visitor, home) alone is not unique across a season), runs
// the engine `runs` times on that single matchup (seed =
// baseSeed + gameIndex*runs + runIndex), aggregates the per-team distributions,
// and compares them to the .sco ground truth within the tolerance bands.
//
// gameType stamps every assembled game (neither .sch nor .sco carries one). The
// CLI exposes this; pass bundle.GameTypeRegular for a regular-season corpus.
// NOTE: a mixed corpus (e.g. playoff or all-star backups) compared under a
// single game type is a systematic basis mismatch — calibrate per game type.
//
// Determinism: same (dir, runs, baseSeed, gameType) yields a byte-identical
// Report. No map-iteration order leaks into the output (triples sorted by stem;
// .sco games kept in file order; stat rows emitted in statNames order).
func ValidateCorpus(dir string, runs int, baseSeed uint64, gameType bundle.GameType) (Report, error) {
	if runs <= 0 {
		return Report{}, fmt.Errorf("validate: runs must be >= 1, got %d", runs)
	}
	triples, err := findTriples(dir)
	if err != nil {
		return Report{}, err
	}
	rep := Report{Runs: runs, BaseSeed: baseSeed, GameType: gameType, Pass: true}
	gameIndex := 0
	for _, t := range triples {
		b, sched, scoGames, err := readTriple(t, gameType)
		if err != nil {
			return Report{}, err
		}
		consumed := make([]bool, len(sched))
		for _, sg := range scoGames {
			schIdx := matchSchedule(sched, consumed, sg)
			if schIdx < 0 {
				rep.Unmatched = append(rep.Unmatched, UnmatchedGame{
					Stem:          t.stem,
					VisitorTeamID: sg.VisitorTeamID,
					HomeTeamID:    sg.HomeTeamID,
					VisitorScore:  sg.VisitorScore,
					HomeScore:     sg.HomeScore,
					Reason:        "no schedule entry with matching (visitor, home, scores)",
				})
				rep.Pass = false
				gameIndex++
				continue
			}
			consumed[schIdx] = true
			gr := validateGame(b, b.Schedule[schIdx], sg, runs, baseSeed+uint64(gameIndex*runs), gameType)
			rep.Games = append(rep.Games, gr)
			if !gr.Pass {
				rep.Pass = false
			}
			gameIndex++
		}
	}
	return rep, nil
}

// ValidateUnscheduled is the exact complement of ValidateCorpus: it simulates
// the .sco games that have NO .sch match — playoff games, which are never in the
// binary .sch (the production playoff schedule is parsed from Schedule.htm). A
// .sco game carries its own VisitorTeamID/HomeTeamID/Date, and the engine
// derives lineups from the .plr rosters, so an unmatched game's matchup is
// synthesized directly (no schedule needed) and simulated under gameType.
//
// matchSchedule decides "unmatched" with the SAME predicate ValidateCorpus uses
// to decide "matched" (consuming each .sch entry once), so the two paths are
// exact complements over a corpus's .sco games.
//
// Sim-validity guard: a synthesized game whose visitor or home team has no .plr
// roster cannot be given a lineup, so it is skipped and recorded in
// Report.Excluded (never silently dropped). On a real finals snapshot this set
// is empty — the All-Star/Rising-Stars games live in the 1,000,000-byte .sco
// header that backup.ReadSco skips wholesale, so they never reach the harness.
//
// Determinism: same (dir, runs, baseSeed, gameType) yields a byte-identical
// Report. .sco games are kept in file order; the per-game seed advances by a
// monotonic index over INCLUDED (simulated) games only.
func ValidateUnscheduled(dir string, runs int, baseSeed uint64, gameType bundle.GameType) (Report, error) {
	if runs <= 0 {
		return Report{}, fmt.Errorf("validate: runs must be >= 1, got %d", runs)
	}
	triples, err := findTriples(dir)
	if err != nil {
		return Report{}, err
	}
	rep := Report{Runs: runs, BaseSeed: baseSeed, GameType: gameType, Pass: true}
	gameIndex := 0
	for _, t := range triples {
		b, sched, scoGames, err := readTriple(t, gameType)
		if err != nil {
			return Report{}, err
		}
		hasLineup := lineupTeams(b)
		consumed := make([]bool, len(sched))
		for _, sg := range scoGames {
			if schIdx := matchSchedule(sched, consumed, sg); schIdx >= 0 {
				consumed[schIdx] = true
				continue // scheduled game — ValidateCorpus's domain, not ours
			}
			if !hasLineup[sg.VisitorTeamID] || !hasLineup[sg.HomeTeamID] {
				rep.Excluded = append(rep.Excluded, ExcludedGame{
					Stem:          t.stem,
					VisitorTeamID: sg.VisitorTeamID,
					HomeTeamID:    sg.HomeTeamID,
					Date:          sg.Date,
					Reason:        "no .plr roster for a participating team — cannot build a lineup",
				})
				continue
			}
			g := bundle.Game{
				VisitorTeamID: sg.VisitorTeamID,
				HomeTeamID:    sg.HomeTeamID,
				Date:          sg.Date,
				GameType:      gameType,
			}
			gr := validateGame(b, g, sg, runs, baseSeed+uint64(gameIndex*runs), gameType)
			rep.Games = append(rep.Games, gr)
			if !gr.Pass {
				rep.Pass = false
			}
			gameIndex++
		}
	}
	return rep, nil
}

// lineupTeams returns the set of team IDs with at least one player in the
// bundle, i.e. the teams the engine can build a lineup for.
func lineupTeams(b bundle.Bundle) map[int]bool {
	set := make(map[int]bool)
	for _, p := range b.Players {
		set[p.TeamID] = true
	}
	return set
}

// matchSchedule returns the index of the first unconsumed schedule game whose
// (visitor, home, visitorScore, homeScore) equals the .sco game's, or -1.
func matchSchedule(sched []backup.SchGame, consumed []bool, sg backup.ScoGame) int {
	for i, s := range sched {
		if consumed[i] {
			continue
		}
		if s.VisitorTeamID == sg.VisitorTeamID && s.HomeTeamID == sg.HomeTeamID &&
			s.VisitorScore == sg.VisitorScore && s.HomeScore == sg.HomeScore {
			return i
		}
	}
	return -1
}

// validateGame simulates one matchup `runs` times and compares the aggregated
// per-team engine means against the .sco ground truth, using gameType's bands.
func validateGame(b bundle.Bundle, g bundle.Game, sg backup.ScoGame, runs int, baseSeed uint64, gameType bundle.GameType) GameReport {
	visMean, homeMean := simulateGameMeans(b, g, runs, baseSeed)
	visSco := teamStatFromSco(sg, g.VisitorTeamID)
	homeSco := teamStatFromSco(sg, g.HomeTeamID)
	return compareGame(gameType, g.VisitorTeamID, g.HomeTeamID, sg.Date, visSco, homeSco, visMean, homeMean)
}

// simulateGameMeans runs the engine on the single matchup g for `runs` seeds
// (baseSeed+0 .. baseSeed+runs-1) and returns the per-team mean TeamStat keyed
// by statNames. Each run is an independent single-game sub-bundle so one game's
// distribution is isolated from the rest of the schedule.
func simulateGameMeans(b bundle.Bundle, g bundle.Game, runs int, baseSeed uint64) (visMean, homeMean map[string]float64) {
	sub := bundle.Bundle{
		LeagueID: b.LeagueID,
		Teams:    b.Teams,
		Players:  b.Players,
		Schedule: []bundle.Game{g},
	}
	visSamples := make([]TeamStat, 0, runs)
	homeSamples := make([]TeamStat, 0, runs)
	for run := 0; run < runs; run++ {
		res := sim.Simulate(sub, baseSeed+uint64(run))
		gr := res.Games[0]
		for _, tb := range gr.TeamBoxes {
			ts := teamStatFromBox(tb)
			switch tb.TeamID {
			case g.VisitorTeamID:
				visSamples = append(visSamples, ts)
			case g.HomeTeamID:
				homeSamples = append(homeSamples, ts)
			}
		}
	}
	return mean(visSamples), mean(homeSamples)
}
