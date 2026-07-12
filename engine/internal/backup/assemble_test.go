package backup

import (
	"encoding/json"
	"errors"
	"fmt"
	"math"
	"reflect"
	"strings"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/sim"
)

// makePlayer builds a PlrPlayer eligible for the lineup (CanPlayInGame=1) with
// one position depth set and modest ratings so a smoke sim produces real play.
func makePlayer(pid, tid, pgd, sgd, sfd, pfd, cd int) PlrPlayer {
	return PlrPlayer{
		Ordinal: pid, PID: pid, TeamID: tid, Name: fmt.Sprintf("P%d", pid), Pos: "PG",
		CanPlayInGame: 1, PGDepth: pgd, SGDepth: sgd, SFDepth: sfd, PFDepth: pfd, CDepth: cd,
		RatingFGA: 60, RatingFGP: 50, RatingFTA: 40, RatingFTP: 75, Rating3GA: 20, Rating3GP: 35,
		RatingORB: 40, RatingDRB: 50, RatingAST: 50, RatingSTL: 40, RatingTVR: 30, RatingBLK: 30,
		RatingOO: 5, RatingOD: 5, RatingDO: 5, RatingDD: 5, RatingPO: 5, RatingPD: 5, RatingTO: 5, RatingTD: 5,
	}
}

// teamRoster returns 5 players for a team, one per position slot.
func teamRoster(tid int) []PlrPlayer {
	base := tid * 100
	return []PlrPlayer{
		makePlayer(base+1, tid, 1, 0, 0, 0, 0),
		makePlayer(base+2, tid, 0, 1, 0, 0, 0),
		makePlayer(base+3, tid, 0, 0, 1, 0, 0),
		makePlayer(base+4, tid, 0, 0, 0, 1, 0),
		makePlayer(base+5, tid, 0, 0, 0, 0, 1),
	}
}

// Row #10: ToBundle populates Players/Schedule/Teams, builds date from the
// .sch-derived Month/Day, and stamps the caller-supplied (default regular=2)
// game type in bundle.GameType wire form.
func TestToBundle_Assembles(t *testing.T) {
	players := append(teamRoster(1), teamRoster(2)...)
	sched := []SchGame{{VisitorTeamID: 1, HomeTeamID: 2, Month: 11, Day: 2, Played: true}}

	b, err := ToBundle(players, sched, AssembleOptions{LeagueID: 5, Seed: 42})
	if err != nil {
		t.Fatalf("ToBundle: %v", err)
	}
	if len(b.Players) != 10 {
		t.Errorf("players = %d, want 10", len(b.Players))
	}
	if len(b.Schedule) != 1 {
		t.Fatalf("schedule = %d, want 1", len(b.Schedule))
	}
	if len(b.Teams) != 2 || b.Teams[0].TeamID != 1 || b.Teams[1].TeamID != 2 {
		t.Errorf("teams = %+v, want sorted [1,2]", b.Teams)
	}
	if b.LeagueID != 5 || b.Seed != 42 {
		t.Errorf("league/seed = %d/%d, want 5/42", b.LeagueID, b.Seed)
	}
	g := b.Schedule[0]
	if g.Date != "11-02" {
		t.Errorf("date = %q, want 11-02", g.Date)
	}
	if g.GameType != bundle.GameTypeRegular {
		t.Errorf("game_type = %d, want %d (default regular)", g.GameType, bundle.GameTypeRegular)
	}
	// Mapping spot-check: r_drive_off <- ratingDO, r_trans_off <- ratingTO.
	if b.Players[0].DriveOff != 5 || b.Players[0].TransOff != 5 || b.Players[0].FTP != 75 {
		t.Errorf("player0 mapped ratings = %+v", b.Players[0])
	}

	// Explicit non-default game type is honored.
	bp, err := ToBundle(players, sched, AssembleOptions{GameType: bundle.GameTypePlayoff})
	if err != nil {
		t.Fatalf("ToBundle playoff: %v", err)
	}
	if bp.Schedule[0].GameType != bundle.GameTypePlayoff {
		t.Errorf("playoff game_type = %d, want %d", bp.Schedule[0].GameType, bundle.GameTypePlayoff)
	}
}

// Row #11: the assembled bundle round-trips through json.Marshal -> bundle.Decode
// identically AND drives sim.Simulate to a structurally-valid result.
func TestToBundle_RoundTripAndSimulate(t *testing.T) {
	players := append(teamRoster(1), teamRoster(2)...)
	sched := []SchGame{{VisitorTeamID: 1, HomeTeamID: 2, Month: 11, Day: 2, Played: true}}
	b, err := ToBundle(players, sched, AssembleOptions{LeagueID: 1, Seed: 7})
	if err != nil {
		t.Fatalf("ToBundle: %v", err)
	}

	raw, err := json.Marshal(b)
	if err != nil {
		t.Fatalf("Marshal: %v", err)
	}
	decoded, err := bundle.Decode(raw)
	if err != nil {
		t.Fatalf("Decode: %v", err)
	}
	if !reflect.DeepEqual(b, decoded) {
		t.Errorf("round-trip mismatch:\n got %+v\nwant %+v", decoded, b)
	}

	res := sim.Simulate(b, 7)
	if len(res.Games) != 1 {
		t.Fatalf("sim games = %d, want 1", len(res.Games))
	}
	// Structurally valid: the game produced player box scores for both teams.
	if len(res.Games[0].PlayerBoxes) == 0 {
		t.Errorf("sim produced no player boxes")
	}
}

// Row 5 (characterization): a PlrPlayer with no real-life sums assembles to bundle
// RealLife*==0 — locks the zero mapping across the struct change (the no-reference
// case the engine falls back to the rating stand-in for).
func TestToBundle_NoRealLife_Zero(t *testing.T) {
	players := append(teamRoster(1), teamRoster(2)...) // makePlayer sets no real-life sums
	sched := []SchGame{{VisitorTeamID: 1, HomeTeamID: 2, Month: 11, Day: 2, Played: true}}

	b, err := ToBundle(players, sched, AssembleOptions{})
	if err != nil {
		t.Fatalf("ToBundle: %v", err)
	}
	for _, p := range b.Players {
		if p.RealLifeMIN != 0 || p.RealLifeFGA != 0 || p.RealLifeFTA != 0 || p.RealLifeORB != 0 {
			t.Fatalf("player %d real-life not zero: %+v", p.PID, p)
		}
	}
}

// Row 6: populated real-life sums on the PlrPlayer map straight through to
// bundle.Player.RealLife* by field.
func TestToBundle_RealLifeWired(t *testing.T) {
	roster := teamRoster(1)
	roster[0].RealLifeMIN = 2520
	roster[0].RealLifeFGA = 1400
	roster[0].RealLifeFTA = 360
	roster[0].RealLifeORB = 120
	players := append(roster, teamRoster(2)...)
	sched := []SchGame{{VisitorTeamID: 1, HomeTeamID: 2, Month: 11, Day: 2, Played: true}}

	b, err := ToBundle(players, sched, AssembleOptions{})
	if err != nil {
		t.Fatalf("ToBundle: %v", err)
	}
	var got bundle.Player
	for _, p := range b.Players {
		if p.PID == roster[0].PID {
			got = p
		}
	}
	if got.RealLifeMIN != 2520 || got.RealLifeFGA != 1400 || got.RealLifeFTA != 360 || got.RealLifeORB != 120 {
		t.Errorf("wired real-life = MIN%d FGA%d FTA%d ORB%d, want 2520/1400/360/120",
			got.RealLifeMIN, got.RealLifeFGA, got.RealLifeFTA, got.RealLifeORB)
	}
}

// Rows 6 & 7: ToBundle aggregates each team's per-player season DRB/AST over its
// Σ season GP into bundle.Team.DRBRate=(ΣDRB/ΣGP)×48 / ASTRate=(ΣAST/ΣGP)×44 (the
// faithful JSB accumulation — NOT a team-summary record). A team with ΣGP==0 gets
// rate 0 (no divide-by-zero / NaN).
func TestToBundle_TeamRates(t *testing.T) {
	// Team 1: give two players season stats; the rest 0.
	roster1 := teamRoster(1)
	roster1[0].SeasonGP, roster1[0].SeasonDRB, roster1[0].SeasonAST = 40, 200, 120
	roster1[1].SeasonGP, roster1[1].SeasonDRB, roster1[1].SeasonAST = 20, 100, 40
	// Team 2: all season stats 0 → ΣGP==0 → rates 0.
	roster2 := teamRoster(2)

	players := append(roster1, roster2...)
	sched := []SchGame{{VisitorTeamID: 1, HomeTeamID: 2, Month: 11, Day: 2, Played: true}}
	b, err := ToBundle(players, sched, AssembleOptions{})
	if err != nil {
		t.Fatalf("ToBundle: %v", err)
	}

	var t1, t2 bundle.Team
	for _, tm := range b.Teams {
		switch tm.TeamID {
		case 1:
			t1 = tm
		case 2:
			t2 = tm
		}
	}
	// ΣGP=60, ΣDRB=300, ΣAST=160 → DRBRate=(300/60)×48=240, ASTRate=(160/60)×44≈117.33.
	wantDRB := 300.0 / 60.0 * 48.0
	wantAST := 160.0 / 60.0 * 44.0
	if math.Abs(t1.DRBRate-wantDRB) > 1e-9 || math.Abs(t1.ASTRate-wantAST) > 1e-9 {
		t.Errorf("team1 rates = DRB%.4f AST%.4f, want %.4f/%.4f", t1.DRBRate, t1.ASTRate, wantDRB, wantAST)
	}
	if t2.DRBRate != 0 || t2.ASTRate != 0 {
		t.Errorf("team2 (ΣGP=0) rates = DRB%v AST%v, want 0/0", t2.DRBRate, t2.ASTRate)
	}
}

// --- J9: faithful league 2PA/48 baseline (FUN_004385f0 port), over RAW .plr
// records (RecordIndex-gated), NOT the assembled bundle player list ----------

// mkBaselinePlr builds a minimal PlrPlayer carrying only the fields
// computeLeagueShotBaseline reads: the raw-record scan gate (RecordIndex,
// Name) and the real-life rate inputs (RealLifeGP/MIN/FGA/3GA).
func mkBaselinePlr(recordIndex int, name string, gp, min, fga, tga int) PlrPlayer {
	return PlrPlayer{
		RecordIndex: recordIndex, Name: name,
		RealLifeGP: gp, RealLifeMIN: min, RealLifeFGA: fga, RealLife3GA: tga,
	}
}

// TestComputeLeagueShotBaseline pins the faithful computation on a synthetic
// fixture: the RecordIndex ≤ 959 scan boundary (959 included, 960 excluded),
// the non-empty-name gate, the MIN > 2×GP inclusion gate (strict >, so
// MIN == 2×GP is excluded), the ratio-of-accumulated-sums arithmetic
// (explicitly NOT the mean of per-player rates — the decompile write loop,
// jsb560_decompiled.c:27124-27175, divides pre-accumulated sums), the
// 2PA = FGA − 3GA subtraction, and the empty-population zero result (the
// engine-side constant fallback is sim/shotdecision.go's concern, not this
// function's — see TestShotBaselineOrFallback in the sim package).
func TestComputeLeagueShotBaseline(t *testing.T) {
	// Two qualifying players with DIFFERENT per-minute rates plus one gated-out
	// player whose huge rate would poison the result if the gate leaked.
	//   A: GP 70, MIN 2400 (> 140) — 2PA = 1000 − 200 = 800  → rate 16.0/48min
	//   B: GP 80, MIN 1200 (> 160) — 2PA = 700 − 100  = 600  → rate 24.0/48min
	//   C: GP 60, MIN 120 (NOT > 120 — boundary MIN == 2×GP fails a strict >),
	//      2PA = 119 (would shift the sums if included)
	base := []PlrPlayer{
		mkBaselinePlr(1, "A", 70, 2400, 1000, 200),
		mkBaselinePlr(2, "B", 80, 1200, 700, 100),
		mkBaselinePlr(3, "C", 60, 120, 119, 0),
	}

	// Ratio of sums: (800+600)/(2400+1200) × 48 = 1400/3600 × 48 = 18.666…
	want := 1400.0 / 3600.0 * 48.0
	got := computeLeagueShotBaseline(base)
	if math.Abs(got-want) > 1e-12 {
		t.Errorf("computeLeagueShotBaseline = %v, want %v (ratio of sums)", got, want)
	}
	// Mean of per-player rates would be (16+24)/2 = 20.0 — must NOT match.
	meanOfRates := (800.0/2400.0*48.0 + 600.0/1200.0*48.0) / 2.0
	if math.Abs(got-meanOfRates) < 1e-9 {
		t.Errorf("computeLeagueShotBaseline = %v equals the mean of per-player rates %v — must be the ratio of accumulated sums", got, meanOfRates)
	}

	// MIN==2×GP boundary gate: flipping C to qualifying (MIN 121 > 120) must
	// change the result — proves the strict-> gate, not an off-by-one artifact.
	incl := make([]PlrPlayer, len(base))
	copy(incl, base)
	incl[2] = mkBaselinePlr(3, "C", 60, 121, 119, 0)
	if inc := computeLeagueShotBaseline(incl); math.Abs(inc-got) < 1e-9 {
		t.Errorf("gate fixture too weak: including C (MIN 121 > 120) left the baseline at %v", inc)
	}

	// RecordIndex scan boundary: a record at 959 is IN the scan; the identical
	// record at 960 is OUT — the FUN_004385f0 league-select loop bound.
	at959 := append(append([]PlrPlayer{}, base...), mkBaselinePlr(959, "D", 70, 2400, 1000, 200))
	at960 := append(append([]PlrPlayer{}, base...), mkBaselinePlr(960, "D", 70, 2400, 1000, 200))
	got959 := computeLeagueShotBaseline(at959)
	got960 := computeLeagueShotBaseline(at960)
	if math.Abs(got959-got960) < 1e-9 {
		t.Errorf("RecordIndex 959 vs 960 gave the same baseline (%v == %v) — scan boundary not enforced", got959, got960)
	}
	if math.Abs(got960-got) > 1e-9 {
		t.Errorf("RecordIndex 960 leaked into the sum: got %v, want unchanged base %v", got960, got)
	}
	// (959 should differ from base since D is a qualifying additional record.)
	if math.Abs(got959-got) < 1e-9 {
		t.Error("RecordIndex 959 fixture too weak: record D did not affect the baseline")
	}

	// Empty-name gate: a record within the scan with no name is excluded even
	// though its stats would otherwise qualify.
	noName := append(append([]PlrPlayer{}, base...), mkBaselinePlr(4, "", 70, 2400, 1000, 200))
	if got4 := computeLeagueShotBaseline(noName); math.Abs(got4-got) > 1e-9 {
		t.Errorf("empty-name record leaked into the sum: got %v, want unchanged base %v", got4, got)
	}

	// Empty population → 0 (never leagueBaselineFallback — that constant is the
	// sim package's concern, applied by shotBaselineOrFallback on a zero field).
	if got := computeLeagueShotBaseline(nil); got != 0 {
		t.Errorf("nil population baseline = %v, want 0", got)
	}
	allZero := []PlrPlayer{mkBaselinePlr(1, "E", 0, 0, 0, 0), mkBaselinePlr(2, "F", 0, 0, 0, 0)}
	if got := computeLeagueShotBaseline(allZero); got != 0 {
		t.Errorf("all-zero population baseline = %v, want 0", got)
	}
	// Qualifying minutes but zero attempts (degenerate Σ2PA) → 0, never a zero
	// divisor downstream in shotValue2pt.
	noShots := []PlrPlayer{mkBaselinePlr(1, "G", 10, 500, 0, 0)}
	if got := computeLeagueShotBaseline(noShots); got != 0 {
		t.Errorf("zero-attempt population baseline = %v, want 0", got)
	}
}

// TestToBundle_LeagueShotBaseline confirms ToBundle wires computeLeagueShotBaseline's
// result onto the assembled bundle's LeagueShotBaseline field — the end-to-end
// path gameloop.go's gs.shotBaseline = b.LeagueShotBaseline depends on. teamRoster's
// makePlayer sets no real-life sums, so an unwired roster assembles to the
// empty-population zero (the sim-side fallback then applies via
// shotBaselineOrFallback, not exercised here).
func TestToBundle_LeagueShotBaseline(t *testing.T) {
	players := append(teamRoster(1), teamRoster(2)...)
	sched := []SchGame{{VisitorTeamID: 1, HomeTeamID: 2, Month: 11, Day: 2, Played: true}}

	b, err := ToBundle(players, sched, AssembleOptions{})
	if err != nil {
		t.Fatalf("ToBundle: %v", err)
	}
	if b.LeagueShotBaseline != 0 {
		t.Errorf("LeagueShotBaseline = %v, want 0 (no roster player has real-life sums wired)", b.LeagueShotBaseline)
	}

	roster := teamRoster(1)
	roster[0].RecordIndex = 1
	roster[0].RealLifeGP, roster[0].RealLifeMIN, roster[0].RealLifeFGA, roster[0].RealLife3GA = 70, 2400, 1000, 200
	players2 := append(roster, teamRoster(2)...)
	b2, err := ToBundle(players2, sched, AssembleOptions{})
	if err != nil {
		t.Fatalf("ToBundle: %v", err)
	}
	want := 800.0 / 2400.0 * 48.0
	if math.Abs(b2.LeagueShotBaseline-want) > 1e-9 {
		t.Errorf("LeagueShotBaseline = %v, want %v", b2.LeagueShotBaseline, want)
	}
}

// Row #12: an unknown team and empty inputs are typed errors, not silent empty
// bundles.
func TestToBundle_Errors(t *testing.T) {
	players := teamRoster(1)

	// Schedule references team 99 which has no roster.
	_, err := ToBundle(players, []SchGame{{VisitorTeamID: 1, HomeTeamID: 99, Month: 11, Day: 2}}, AssembleOptions{})
	if !errors.Is(err, ErrUnknownTeam) {
		t.Errorf("unknown team: err = %v, want ErrUnknownTeam", err)
	}

	_, err = ToBundle(nil, []SchGame{{VisitorTeamID: 1, HomeTeamID: 2}}, AssembleOptions{})
	if !errors.Is(err, bundle.ErrEmptyRoster) {
		t.Errorf("empty roster: err = %v, want ErrEmptyRoster", err)
	}

	_, err = ToBundle(players, nil, AssembleOptions{})
	if !errors.Is(err, bundle.ErrNoSchedule) {
		t.Errorf("empty schedule: err = %v, want ErrNoSchedule", err)
	}
}

// Row 6 (characterization): with a nil Minutes map (no .plb), every assembled
// player's DCMinutes falls back to 0 — locks the historical behavior across the
// toBundlePlayer signature change.
func TestToBundle_NoPlb_ZeroMinutes(t *testing.T) {
	players := append(teamRoster(1), teamRoster(2)...)
	sched := []SchGame{{VisitorTeamID: 1, HomeTeamID: 2, Month: 11, Day: 2}}
	b, err := ToBundle(players, sched, AssembleOptions{}) // nil Minutes
	if err != nil {
		t.Fatalf("ToBundle: %v", err)
	}
	for _, p := range b.Players {
		if p.DCMinutes != 0 {
			t.Errorf("player pid=%d DCMinutes = %d, want 0 (no .plb)", p.PID, p.DCMinutes)
		}
	}
}

// Row 7: a populated Minutes map sets Player.DCMinutes by Ordinal (missing
// ordinal -> 0), AND every assembled player's Stamina is the uniform energy
// ceiling 100 (previously unasserted — the zeroed default never had a test).
func TestToBundle_StaminaAndMinutes(t *testing.T) {
	players := append(teamRoster(1), teamRoster(2)...)
	sched := []SchGame{{VisitorTeamID: 1, HomeTeamID: 2, Month: 11, Day: 2}}
	// teamRoster's makePlayer sets Ordinal == PID; team1 -> 101..105, team2 -> 201..205.
	minutes := map[int]int{101: 40, 102: 16, 201: 38}
	b, err := ToBundle(players, sched, AssembleOptions{Minutes: minutes})
	if err != nil {
		t.Fatalf("ToBundle: %v", err)
	}
	byPID := map[int]bundle.Player{}
	for _, p := range b.Players {
		byPID[p.PID] = p
	}
	for pid, want := range map[int]int{101: 40, 102: 16, 201: 38, 105: 0 /* no map entry */} {
		if got := byPID[pid].DCMinutes; got != want {
			t.Errorf("pid %d DCMinutes = %d, want %d", pid, got, want)
		}
	}
	for _, p := range b.Players {
		if p.Stamina != 100 {
			t.Errorf("player pid=%d Stamina = %d, want 100 (uniform energy ceiling)", p.PID, p.Stamina)
		}
	}
}

// Row 5: a Minutes map carrying an ordinal that matches no player is harmless —
// the orphan key is ignored and real players still map correctly.
func TestToBundle_PlbOrdinalNoMatch(t *testing.T) {
	players := append(teamRoster(1), teamRoster(2)...)
	sched := []SchGame{{VisitorTeamID: 1, HomeTeamID: 2, Month: 11, Day: 2}}
	minutes := map[int]int{9999: 30, 101: 22} // ordinal 9999 matches no player
	b, err := ToBundle(players, sched, AssembleOptions{Minutes: minutes})
	if err != nil {
		t.Fatalf("ToBundle with orphan ordinal: %v", err)
	}
	var found bool
	for _, p := range b.Players {
		if p.PID == 101 {
			found = true
			if p.DCMinutes != 22 {
				t.Errorf("pid 101 DCMinutes = %d, want 22", p.DCMinutes)
			}
		}
		if p.DCMinutes == 30 {
			t.Errorf("orphan ordinal 9999 leaked onto pid=%d", p.PID)
		}
	}
	if !found {
		t.Fatal("pid 101 missing from assembled bundle")
	}
}

// Row #13: ReadPlr/ReadSch/ToBundle are pure — identical bytes produce
// byte-identical structs and bundles across runs (no map-order or time leak).
func TestToBundle_Deterministic(t *testing.T) {
	// Build a .plr with players on teams 1 and 2, and a .sch referencing both.
	var plr strings.Builder
	ord := 1
	for tid := 1; tid <= 2; tid++ {
		for i := 0; i < 3; i++ {
			plr.WriteString(newPlrRecord(ord, tid*100+i, tid, 25, fmt.Sprintf("P%d", ord), "PG", 70, 5, 5))
			plr.WriteString("\r\n")
			ord++
		}
	}
	plrData := plr.String()

	sch := []byte(strings.Repeat(" ", schFileSize))
	putSlot(sch, 0, "0102099101") // visitor 1, home 02, 99-101
	schData := string(sch)

	assemble := func() bundle.Bundle {
		players, err := ReadPlr(strings.NewReader(plrData))
		if err != nil {
			t.Fatalf("ReadPlr: %v", err)
		}
		games, err := ReadSch(strings.NewReader(schData))
		if err != nil {
			t.Fatalf("ReadSch: %v", err)
		}
		b, err := ToBundle(players, games, AssembleOptions{LeagueID: 1, Seed: 99})
		if err != nil {
			t.Fatalf("ToBundle: %v", err)
		}
		return b
	}

	b1, b2 := assemble(), assemble()
	if !reflect.DeepEqual(b1, b2) {
		t.Errorf("non-deterministic assembly:\n b1=%+v\n b2=%+v", b1, b2)
	}
	// Teams must be in sorted id order regardless of roster/schedule order.
	if len(b1.Teams) != 2 || b1.Teams[0].TeamID != 1 || b1.Teams[1].TeamID != 2 {
		t.Errorf("teams = %+v, want sorted [1,2]", b1.Teams)
	}
}
