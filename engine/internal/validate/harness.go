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
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/sim"
)

// ErrNoCorpus reports a corpus directory that contains no complete
// .plr/.sch/.sco triple — an empty dir, or files that do not share a stem. The
// .plb depth-chart file is an OPTIONAL fourth member (supplies per-player
// dc_minutes); its absence is reported via Report.MissingPlb, never required.
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
// Excluded games (ValidateUnscheduled's rosterless sim-validity skips) are a
// third outcome that does NOT affect Pass — they are reported for visibility.
// Same (dir, runs, baseSeed, gameType) always yields an identical Report.
type Report struct {
	Runs      int
	BaseSeed  uint64
	GameType  bundle.GameType
	Pass      bool
	Games     []GameReport
	Unmatched []UnmatchedGame
	Excluded  []ExcludedGame
	// MissingPlb lists the stems whose snapshot had no .plb depth chart, so every
	// player's dc_minutes defaulted to 0. Reported for visibility (never silently
	// zeroed); it does NOT affect Pass — a snapshot is still validatable on team
	// stats without the per-player minutes signal.
	MissingPlb []string
	// Label is an optional human identifier for the snapshot/season this report
	// covers (e.g. "04-05" or "04-05 (playoffs)"), stamped by the calibrate
	// collectors so the season-aggregate output is attributable. Default "";
	// additive and NOT printed by WriteReport (the text report stays byte-stable).
	Label string
}

// triple names one backup file set sharing a stem within the corpus dir. plr/
// sch/sco are required; plb is the optional depth-chart member ("" if absent).
type triple struct {
	stem string
	plr  string
	sch  string
	sco  string
	plb  string
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
		case ".plb":
			get(stem).plb = full
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
	// The .plb is optional; a missing one yields a nil map -> dc_minutes 0.
	var minutes map[int]int
	if t.plb != "" {
		minutes, err = readPlb(t.plb)
		if err != nil {
			return bundle.Bundle{}, nil, nil, err
		}
	}
	b, err := backup.ToBundle(players, sched, backup.AssembleOptions{GameType: gameType, Minutes: minutes})
	if err != nil {
		return bundle.Bundle{}, nil, nil, fmt.Errorf("validate: assemble %q: %w", t.stem, err)
	}
	return b, sched, scoGames, nil
}

// readPlb opens a .plb depth-chart file and parses it into an ordinal->minutes
// map. It cannot use the generic readFile helper (which returns []T); the
// error-wrapping mirrors readFile so a malformed .plb is diagnosable by path.
func readPlb(path string) (map[int]int, error) {
	f, err := os.Open(path)
	if err != nil {
		return nil, fmt.Errorf("validate: open %q: %w", path, err)
	}
	defer func() { _ = f.Close() }()
	m, err := backup.ReadPlb(f)
	if err != nil {
		return nil, fmt.Errorf("validate: parse %q: %w", path, err)
	}
	return m, nil
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
	return ValidateCorpusWith(dir, runs, baseSeed, gameType, sim.Options{})
}

// ValidateCorpusWith is ValidateCorpus plus a sim.Options passthrough, threaded into
// every engine run. A zero sim.Options{} is byte-identical to ValidateCorpus — the
// calibration A/B harnesses are the only callers that set fields. The bundled Options
// carries the measurement seams that used to be positional bools: opts.Freeze.BranchB
// (the usage-shrink toggle), opts.Freeze.MakePutback/MakePutbackHalf + opts.Freeze.Means
// (the ADR-0053 decoupling arms), opts.BranchBAccum (the engagement instrument), and
// opts.Accum (a FreezeMeans harvest pass). Pointer fields, when non-nil, are shared
// across every game so the harness reads the aggregate after the corpus pass.
func ValidateCorpusWith(dir string, runs int, baseSeed uint64, gameType bundle.GameType, opts sim.Options) (Report, error) {
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
		if t.plb == "" {
			rep.MissingPlb = append(rep.MissingPlb, t.stem)
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
			gr := validateGame(b, b.Schedule[schIdx], sg, runs, baseSeed+uint64(gameIndex*runs), gameType, opts)
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
	return ValidateUnscheduledWith(dir, runs, baseSeed, gameType, sim.Options{})
}

// ValidateUnscheduledWith is ValidateUnscheduled plus a sim.Options passthrough, the
// unscheduled (playoff) complement of ValidateCorpusWith. A zero sim.Options{} is
// byte-identical to ValidateUnscheduled.
func ValidateUnscheduledWith(dir string, runs int, baseSeed uint64, gameType bundle.GameType, opts sim.Options) (Report, error) {
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
		if t.plb == "" {
			rep.MissingPlb = append(rep.MissingPlb, t.stem)
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
			gr := validateGame(b, g, sg, runs, baseSeed+uint64(gameIndex*runs), gameType, opts)
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
func validateGame(b bundle.Bundle, g bundle.Game, sg backup.ScoGame, runs int, baseSeed uint64, gameType bundle.GameType, opts sim.Options) GameReport {
	visMean, homeMean, homeWinFrac, originFGA, possProxyPerG, possCountPerG, orbPerG, contDepthPerG, gateContPerG := simulateGameMeans(b, g, runs, baseSeed, opts)
	visSco := teamStatFromSco(sg, g.VisitorTeamID)
	homeSco := teamStatFromSco(sg, g.HomeTeamID)
	gr := compareGame(gameType, g.VisitorTeamID, g.HomeTeamID, sg.Date, visSco, homeSco, visMean, homeMean)
	gr.EngineHomeWinFraction = homeWinFrac
	gr.EngineOriginFGA = originFGA
	// Split inputs: the SAME Dean-Oliver box proxy on both sides (apples-to-apples —
	// mixing a true count against an FGA-derived proxy biases which factor absorbs the
	// coupling). The authoritative count rides alongside as an engine-only diagnostic.
	gr.EnginePossPerG = possProxyPerG
	gr.EnginePossCountPerG = possCountPerG
	gr.ScoPossPerG = map[int]float64{
		g.VisitorTeamID: possProxy(visSco),
		g.HomeTeamID:    possProxy(homeSco),
	}
	// ORB-intensity channel: ORB/g from the SAME TeamStat.ORB on both sides (the .sco
	// box exposes ORB directly). Engine-only continuation-depth tallies ride alongside.
	gr.EngineORBPerG = orbPerG
	gr.ScoORBPerG = map[int]float64{
		g.VisitorTeamID: float64(visSco.ORB),
		g.HomeTeamID:    float64(homeSco.ORB),
	}
	gr.EngineContinuationDepth = contDepthPerG
	gr.EngineGateCont = gateContPerG
	return gr
}

// simulateGameMeans runs the engine on the single matchup g for `runs` seeds
// (baseSeed+0 .. baseSeed+runs-1) and returns the per-team mean TeamStat keyed
// by statNames, plus the home team's win fraction over those runs (the
// runs-stable P(home win) estimate the season-aggregate layer needs). Each run
// is an independent single-game sub-bundle so one game's distribution is
// isolated from the rest of the schedule.
func simulateGameMeans(b bundle.Bundle, g bundle.Game, runs int, baseSeed uint64, opts sim.Options) (visMean, homeMean map[string]float64, homeWinFrac float64, originFGA map[int]OriginFGA, possProxyPerG, possCountPerG, orbPerG map[int]float64, contDepthPerG map[int]ContinuationDepthRaw, gateContPerG map[int]GateContRaw) {
	sub := bundle.Bundle{
		LeagueID: b.LeagueID,
		Teams:    b.Teams,
		Players:  b.Players,
		Schedule: []bundle.Game{g},
	}
	// L1 gate-1 counterfactual instrument (ADR-0057/0058): one accumulator per game,
	// pooled across the game's runs. Attaching it issues no rng draw and does not alter
	// any outcome (read-only at the sim rebound site), so the per-team stat means below
	// are unchanged. opts.GateBaseline (the sweep seam) rides through from the caller.
	gateAcc := sim.NewGateContAccum()
	opts.GateCont = gateAcc
	// opts is threaded verbatim from the caller: a zero sim.Options{} ⇒ SimulateWith ==
	// Simulate (byte-identical OFF calibration), and any pointer field (opts.BranchBAccum
	// engagement instrument, opts.Accum FreezeMeans harvest) is shared across every game
	// so its aggregate is read after the corpus pass.
	visSamples := make([]TeamStat, 0, runs)
	homeSamples := make([]TeamStat, 0, runs)
	originTotals := map[int]*OriginFGA{} // Σ by-origin FGA across runs, per team
	possCountTotals := map[int]int{}     // Σ EventPossessionStart across runs, per team
	possProxyTotals := map[int]float64{} // Σ box-proxy possessions across runs, per team
	orbTotals := map[int]float64{}       // Σ offensive rebounds across runs, per team (ORB-intensity numerator)
	depthTotals := map[int]*depthAcc{}   // Σ per-possession continuation-depth tallies across runs, per team
	for run := 0; run < runs; run++ {
		res, _ := sim.SimulateWith(sub, baseSeed+uint64(run), opts)
		gr := res.Games[0]
		accumulateOriginFGA(originTotals, gr.Events)
		accumulatePossessions(possCountTotals, gr.Events)
		accumulateContinuationDepth(depthTotals, gr.Events)
		for _, tb := range gr.TeamBoxes {
			ts := teamStatFromBox(tb)
			possProxyTotals[tb.TeamID] += possProxy(ts) // same Dean-Oliver proxy as the .sco side
			orbTotals[tb.TeamID] += float64(ts.ORB)     // same TeamStat.ORB feeding possProxy
			switch tb.TeamID {
			case g.VisitorTeamID:
				visSamples = append(visSamples, ts)
			case g.HomeTeamID:
				homeSamples = append(homeSamples, ts)
			}
		}
	}
	originFGA = make(map[int]OriginFGA, len(originTotals))
	rf := float64(runs)
	for id, o := range originTotals {
		originFGA[id] = OriginFGA{
			Initial: o.Initial / rf, Oreb: o.Oreb / rf, Transition: o.Transition / rf,
			InitialMade: o.InitialMade / rf, OrebMade: o.OrebMade / rf, TransitionMade: o.TransitionMade / rf,
		}
	}
	possProxyPerG = make(map[int]float64, len(possProxyTotals))
	for id, s := range possProxyTotals {
		possProxyPerG[id] = s / rf
	}
	possCountPerG = make(map[int]float64, len(possCountTotals))
	for id, n := range possCountTotals {
		possCountPerG[id] = float64(n) / rf
	}
	orbPerG = make(map[int]float64, len(orbTotals))
	for id, s := range orbTotals {
		orbPerG[id] = s / rf
	}
	contDepthPerG = make(map[int]ContinuationDepthRaw, len(depthTotals))
	for id, d := range depthTotals {
		contDepthPerG[id] = ContinuationDepthRaw{
			N:      float64(d.n) / rf,
			SumK:   d.sumK / rf,
			SumK2:  d.sumK2 / rf,
			B0:     float64(d.b0) / rf,
			B1:     float64(d.b1) / rf,
			B2:     float64(d.b2) / rf,
			B3Plus: float64(d.b3plus) / rf,
		}
	}
	// Gate-1 counterfactual per-team means: N is resolutions/game (n÷runs); the gate and
	// strength fields are per-RESOLUTION means (sum÷n, run-pooled). A team with no
	// offensive-rebound resolution contributes no entry (n==0 skipped — no spurious key).
	gateContPerG = make(map[int]GateContRaw)
	for _, id := range gateAcc.TeamIDs() {
		n, sumG1, sumG2, sumProd, sumOffStr, sumDefStr := gateAcc.Team(id)
		if n == 0 {
			continue
		}
		fn := float64(n)
		gateContPerG[id] = GateContRaw{
			N:          fn / rf,
			MeanG1:     sumG1 / fn,
			MeanG2:     sumG2 / fn,
			MeanProd:   sumProd / fn,
			MeanOffStr: sumOffStr / fn,
			MeanDefStr: sumDefStr / fn,
		}
	}
	return mean(visSamples), mean(homeSamples), homeWinFraction(homeSamples, visSamples), originFGA, possProxyPerG, possCountPerG, orbPerG, contDepthPerG, gateContPerG
}

// accumulateOriginFGA folds one game's shot events into per-team by-origin counters:
// EventShotAttempt → FGA by origin; EventShotMake → MADE field goals by the same
// origin (both carry exactly one origin; see result.ShotOrigin). The made tally
// rides EventShotMake — which creditMadeFieldGoal also emits for the and-one basket
// — so made/attempts is the per-origin shooting efficiency (ADR-0053 instrument).
// Read-only: counting events Simulate already emits changes no engine behavior.
func accumulateOriginFGA(into map[int]*OriginFGA, events []result.Event) {
	get := func(team int) *OriginFGA {
		o := into[team]
		if o == nil {
			o = &OriginFGA{}
			into[team] = o
		}
		return o
	}
	for _, e := range events {
		switch e.Kind {
		case result.EventShotAttempt:
			o := get(e.TeamID)
			switch e.Origin {
			case result.OriginInitial:
				o.Initial++
			case result.OriginOffReb:
				o.Oreb++
			case result.OriginTransition:
				o.Transition++
			}
		case result.EventShotMake:
			o := get(e.TeamID)
			switch e.Origin {
			case result.OriginInitial:
				o.InitialMade++
			case result.OriginOffReb:
				o.OrebMade++
			case result.OriginTransition:
				o.TransitionMade++
			}
		}
	}
}

// possProxy is the Dean-Oliver true-possession estimate for one team's box (engine
// OR .sco): FGA + 0.44·FTA + TOV − ORB. The −ORB term is essential, not cosmetic: an
// offensive rebound EXTENDS a possession (an extra shot in the same trip), so it
// belongs in the shots-per-possession factor, not the possession count; without it
// ORB-continuations would be misattributed into the count channel (ADR-0049). The
// SAME formula runs on both sides so the cross-side Cov split is apples-to-apples
// (the engine's authoritative EventPossessionStart count is kept as a separate
// diagnostic — mixing it into one side of the split would bias the allocation,
// since the FGA-derived proxy correlates with FGA by construction). Matches
// calibrate/possession_archive_test.go's convention exactly.
func possProxy(ts TeamStat) float64 {
	return float64(ts.FGA) + 0.44*float64(ts.FTA) + float64(ts.TOV) - float64(ts.ORB)
}

// accumulatePossessions folds one game's events into a per-team possession count,
// tallying exactly the EventPossessionStart events (one per offensive trip; see
// sim/possession.go). It is the read-only possession-count instrument for the
// season-aggregate POSS decomposition (ADR-0049): it observes the event stream
// Simulate already produces, so no engine behavior changes and the golden fixture
// stays byte-identical. Events of any other kind are ignored — only possession
// starts contribute, so a slice with no EventPossessionStart leaves `into`
// untouched (no spurious team keys).
func accumulatePossessions(into map[int]int, events []result.Event) {
	for _, e := range events {
		if e.Kind == result.EventPossessionStart {
			into[e.TeamID]++
		}
	}
}

// depthAcc accumulates one team's per-possession continuation-depth tallies while
// accumulateContinuationDepth walks a game's event stream. n/sumK/sumK2 are the
// exact moment sums (mean = sumK/n, Var = sumK2/n − mean² downstream); b0..b3plus
// are the capped histogram buckets (k = 0 / 1 / 2 / ≥3) used for SHAPE only.
type depthAcc struct {
	n                  int
	sumK               float64
	sumK2              float64
	b0, b1, b2, b3plus int
}

// accumulateContinuationDepth segments one game's event stream into possessions and
// folds each possession's offensive-rebound continuation depth k into its OWNING
// team's accumulator (Part B continuation-chain instrument). A possession opens at
// an EventPossessionStart — whose TeamID owns the whole trip — and closes at the
// NEXT EventPossessionStart or at slice end; k is the count of
// EventRebound{OffensiveRebound:true} seen within it. The just-closed possession is
// ALWAYS attributed to the team that OPENED it (curTeam), never the team that opens
// the next one — the easy misattribution to avoid. Only EventRebound{offensive}
// increments depth; EventShotAttempt{Origin:OriginOffReb} does NOT (the putback
// shot, not the rebound, would double-count). mean/Var are derived downstream from
// n/sumK/sumK2 — NEVER from the capped buckets (b3plus collapses the tail). The
// slice-end fold is done per call so the last possession of every run is counted
// and no possession bleeds across runs. Read-only: it observes events Simulate
// already emits, so no engine behavior changes.
func accumulateContinuationDepth(into map[int]*depthAcc, events []result.Event) {
	get := func(team int) *depthAcc {
		a := into[team]
		if a == nil {
			a = &depthAcc{}
			into[team] = a
		}
		return a
	}
	curTeam := 0
	cur := 0
	open := false
	fold := func() {
		if !open {
			return
		}
		a := get(curTeam)
		a.n++
		a.sumK += float64(cur)
		a.sumK2 += float64(cur * cur)
		switch cur {
		case 0:
			a.b0++
		case 1:
			a.b1++
		case 2:
			a.b2++
		default:
			a.b3plus++
		}
	}
	for _, e := range events {
		switch e.Kind {
		case result.EventPossessionStart:
			fold() // close the previous possession (if any)
			curTeam = e.TeamID
			cur = 0
			open = true
		case result.EventRebound:
			if open && e.OffensiveRebound {
				cur++
			}
		}
	}
	fold() // close the final possession at slice end
}
