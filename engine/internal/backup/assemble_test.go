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

// TestToBundlePlayer_RealLifeSTLTVR_Mapping: RealLifeSTL/TVR map through assembler correctly;
// rating fields are not cross-wired into the real-life fields.
func TestToBundlePlayer_RealLifeSTLTVR_Mapping(t *testing.T) {
	roster := teamRoster(1)
	roster[0].RealLifeSTL = 110
	roster[0].RealLifeTVR = 150
	// Distinct rating values so we can prove no cross-wiring.
	roster[0].RatingSTL = 45
	roster[0].RatingTVR = 55
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
	if got.RealLifeSTL != 110 || got.RealLifeTVR != 150 {
		t.Errorf("RealLifeSTL/TVR = %d/%d, want 110/150", got.RealLifeSTL, got.RealLifeTVR)
	}
	// Isolation: real-life sums must not equal the rating (they are different sources).
	if got.RealLifeSTL == got.STL {
		t.Errorf("RealLifeSTL (%d) must not equal STL rating (%d) — cross-wiring!", got.RealLifeSTL, got.STL)
	}
	if got.RealLifeTVR == got.TVR {
		t.Errorf("RealLifeTVR (%d) must not equal TVR rating (%d) — cross-wiring!", got.RealLifeTVR, got.TVR)
	}
}

// TestToBundlePlayer_RealLifeSTLTVR_EndToEnd: ReadPlr → toBundlePlayer carries
// STL(96)/TVR(100) columns all the way to bundle.Player fields.
func TestToBundlePlayer_RealLifeSTLTVR_EndToEnd(t *testing.T) {
	buf := []byte(newPlrRecord(1, 100, 1, 25, "Defender", "SG", 70, 5, 5))
	plrField(buf, 56, itoaPad(2000, 4)) // RealLifeMIN (> 0 so guard passes)
	plrField(buf, 96, itoaPad(110, 4))  // RealLifeSTL
	plrField(buf, 100, itoaPad(150, 4)) // RealLifeTVR
	plrField(buf, 137, "1")             // CanPlayInGame

	parsed, err := ReadPlr(strings.NewReader(string(buf) + "\r\n"))
	if err != nil {
		t.Fatalf("ReadPlr: %v", err)
	}
	bp := toBundlePlayer(parsed[0], nil, 0, nonMatchedLeagueParams{})
	if bp.RealLifeSTL != 110 || bp.RealLifeTVR != 150 {
		t.Errorf("end-to-end RealLifeSTL/TVR = %d/%d, want 110/150", bp.RealLifeSTL, bp.RealLifeTVR)
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

// Row 9: a bundle.Bundle JSON without the "league_blk48" field decodes cleanly
// and yields LeagueBlk48==0 (zero-value default).
func TestBundle_MissingLeagueBlk48_DecodesZero(t *testing.T) {
	raw := []byte(`{"league_id":1,"seed":1,"teams":[],"players":[],"schedule":[]}`)
	var b bundle.Bundle
	if err := json.Unmarshal(raw, &b); err != nil {
		t.Fatalf("json.Unmarshal: %v", err)
	}
	if b.LeagueBlk48 != 0 {
		t.Errorf("LeagueBlk48 = %v on missing field, want 0", b.LeagueBlk48)
	}
}

// Row 11: D-field zero-guards — DE8=0 when MIN=0; D80=0 when 3GA=0; D60=D64=0 when 2PA=0.
func TestToBundlePlayer_DFieldZeroGuards(t *testing.T) {
	sched := []SchGame{{VisitorTeamID: 1, HomeTeamID: 2, Month: 11, Day: 2, Played: true}}

	assemble := func(p0 PlrPlayer) bundle.Player {
		t.Helper()
		players := append([]PlrPlayer{p0}, teamRoster(2)...)
		b, err := ToBundle(players, sched, AssembleOptions{})
		if err != nil {
			t.Fatalf("ToBundle: %v", err)
		}
		for _, p := range b.Players {
			if p.PID == p0.PID {
				return p
			}
		}
		t.Fatal("assembled player not found")
		return bundle.Player{}
	}

	// DE8=0 when MIN=0: BLK/MIN is undefined; guard must yield 0, not Inf.
	p0 := teamRoster(1)[0]
	p0.RealLifeMIN = 0
	p0.RealLifeBLK = 50 // would produce Inf without the MIN guard
	if got := assemble(p0).DE8; got != 0 {
		t.Errorf("MIN=0: DE8=%v, want 0", got)
	}

	// D80=0 when 3GA=0: no 3pt attempts means no 3pt make rate.
	p1 := teamRoster(1)[0]
	p1.PID += 100
	p1.RealLife3GA = 0
	p1.RealLife3GM = 0
	if got := assemble(p1).D80; got != 0 {
		t.Errorf("3GA=0: D80=%v, want 0", got)
	}

	// D60=0 when 2PA=0 (FGA==3GA: all attempts are 3-pointers).
	p2 := teamRoster(1)[0]
	p2.PID += 200
	p2.RealLifeFGA = 400
	p2.RealLife3GA = 400 // 2PA = FGA-3GA = 0
	p2.RealLifeFGM = 180
	p2.RealLife3GM = 180
	got2 := assemble(p2)
	if got2.D60 != 0 {
		t.Errorf("2PA=0: D60=%v, want 0", got2.D60)
	}
}

// Row 12: computeLeagueBlk48 returns 0 on an empty qualifying population.
func TestComputeLeagueBlk48_EmptyReturnsZero(t *testing.T) {
	// All players have RecordIndex > 959 → none qualify.
	players := []PlrPlayer{
		{RecordIndex: 960, Name: "A", RealLifeGP: 10, RealLifeMIN: 30, RealLifeBLK: 5},
		{RecordIndex: 961, Name: "B", RealLifeGP: 10, RealLifeMIN: 30, RealLifeBLK: 5},
	}
	if got := computeLeagueBlk48(players); got != 0 {
		t.Errorf("empty qualifying pop: got %v, want 0", got)
	}
	// All players have empty Name → none qualify.
	players2 := []PlrPlayer{
		{RecordIndex: 1, Name: "", RealLifeGP: 10, RealLifeMIN: 30, RealLifeBLK: 5},
	}
	if got := computeLeagueBlk48(players2); got != 0 {
		t.Errorf("empty-name pop: got %v, want 0", got)
	}
}

// --- J24 matchupQuality Phase 3/4: NonMatchedTerm deferral + DefAST48 +
// computeLeagueAST48ByPos --------------------------------------------------

// TestNonMatchedTerm_MinGate_Zero: the synthetic roster carries no real-life
// stats (RealLifeMIN=0), so every assembled player's NonMatchedTerm is 0 via
// the FUN_00561c00 MIN gate — the faithful no-data degrade, replacing the
// pre-J25 deferred-zero contract.
func TestNonMatchedTerm_MinGate_Zero(t *testing.T) {
	players := append(teamRoster(1), teamRoster(2)...)
	sched := []SchGame{{VisitorTeamID: 1, HomeTeamID: 2, Month: 11, Day: 2, Played: true}}
	b, err := ToBundle(players, sched, AssembleOptions{})
	if err != nil {
		t.Fatalf("ToBundle: %v", err)
	}
	for _, p := range b.Players {
		if p.NonMatchedTerm != 0 {
			t.Errorf("player %d NonMatchedTerm = %v, want 0 (MIN gate)", p.PID, p.NonMatchedTerm)
		}
	}
}

// TestNonMatchedTerm_Computed: hand-derived +0x350 value for a single-player
// league (the player IS the league, so the ten params come from its own
// totals). Worked by hand from the J25 formula — NOT by re-running the code:
// M=1000 → p2=31.2 p3=9.6 p4=7.2 p6=4.8 p7=9.6 p8=12 p9=3.84 p10=5.76
// p11=1.92; Prod=612.5 → p5=29.4; A_num=575 → A48=27.6; B=14.8; C=16.0;
// term = (27.6−29.4) − 14.8 + 16.0 = −0.6. Locks the +C sign (a −C
// misread yields −32.6) and the full A/B/C shape.
func TestNonMatchedTerm_Computed(t *testing.T) {
	pl := PlrPlayer{
		RecordIndex: 1, Name: "NM Fixture",
		RealLifeGP: 50, RealLifeMIN: 1000,
		RealLifeFGM: 400, RealLifeFGA: 800,
		RealLifeFTM: 150, RealLifeFTA: 200,
		RealLife3GM: 50, RealLife3GA: 150,
		RealLifeORB: 100, RealLifeREB: 300,
		RealLifeAST: 250, RealLifeSTL: 80,
		RealLifeTVR: 120, RealLifeBLK: 40,
	}
	lp := computeLeagueNonMatchedParams([]PlrPlayer{pl})
	if math.Abs(lp.Prod48-29.4) > 1e-9 || math.Abs(lp.TwoPA48-31.2) > 1e-9 {
		t.Fatalf("league params: Prod48 = %v (want 29.4), TwoPA48 = %v (want 31.2)", lp.Prod48, lp.TwoPA48)
	}
	if got, want := computeNonMatchedTerm(pl, lp), -0.6; math.Abs(got-want) > 1e-9 {
		t.Errorf("NonMatchedTerm = %v, want %v", got, want)
	}

	// Records beyond the 1-960 scan never feed the param bank.
	out := pl
	out.RecordIndex = leagueShotBaselineMaxRecordIndex + 1
	if lp := computeLeagueNonMatchedParams([]PlrPlayer{out}); lp != (nonMatchedLeagueParams{}) {
		t.Errorf("out-of-scan record leaked into params: %+v", lp)
	}
}

// TestDefAST48_KnownStats (P3a): DefAST48 = RealLifeAST/RealLifeMIN×48 for a
// player with known real-life AST/MIN.
func TestDefAST48_KnownStats(t *testing.T) {
	roster := teamRoster(1)
	roster[0].RealLifeMIN = 2000
	roster[0].RealLifeAST = 300 // 300/2000×48 = 7.2
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
	want := 300.0 / 2000.0 * 48.0
	if math.Abs(got.DefAST48-want) > 1e-9 {
		t.Errorf("DefAST48 = %v, want %v", got.DefAST48, want)
	}
}

// TestDefAST48_StandIn_NoRealLife (P3b): a player with RealLifeMIN==0 (no
// real-life AST data) falls back to the rating stand-in — floor1AST(rating)/50
// × leagueAST48Standin — and yields a positive, finite value (no divide-by-
// zero / NaN). A second roster player carries qualifying real-life AST/MIN so
// leagueAST48Standin (the population mean of computeLeagueAST48ByPos) is
// itself non-zero, proving the stand-in path is non-degenerate.
func TestDefAST48_StandIn_NoRealLife(t *testing.T) {
	roster := teamRoster(1)
	// roster[0] (PG, primary slot 1): no real-life AST/MIN -> stand-in path.
	roster[0].RatingAST = 70
	// roster[1] (SG, primary slot 2): qualifying real-life AST/MIN so the league
	// mean (leagueAST48Standin) is non-zero.
	roster[1].RealLifeGP = 10
	roster[1].RealLifeMIN = 500 // > 2×10
	roster[1].RealLifeAST = 100 // 100/500×48 = 9.6
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
	if got.DefAST48 <= 0 {
		t.Errorf("DefAST48 stand-in = %v, want > 0 (no MIN/AST -> rating stand-in path)", got.DefAST48)
	}
	if math.IsNaN(got.DefAST48) || math.IsInf(got.DefAST48, 0) {
		t.Errorf("DefAST48 stand-in = %v, want finite", got.DefAST48)
	}
	// Exact: floor1AST(70)/50 × leagueAST48Standin(=9.6, the sole qualifying bucket).
	want := 70.0 / 50.0 * 9.6
	if math.Abs(got.DefAST48-want) > 1e-9 {
		t.Errorf("DefAST48 stand-in = %v, want %v", got.DefAST48, want)
	}
}

// TestComputeLeagueAST48ByPos (P3c): per-bucket mean AST48 over the qualifying
// population, and an all-zero result for buckets/populations with no
// qualifying players.
func TestComputeLeagueAST48ByPos(t *testing.T) {
	players := []PlrPlayer{
		// PG bucket (index 1): two qualifying players -> mean (9.6+24.0)/2 = 16.8.
		{RecordIndex: 1, Name: "A", PGDepth: 1, RealLifeGP: 10, RealLifeMIN: 500, RealLifeAST: 100},
		{RecordIndex: 2, Name: "B", PGDepth: 1, RealLifeGP: 10, RealLifeMIN: 1000, RealLifeAST: 500},
		// SG bucket (index 2): one qualifying player -> mean 12.0.
		{RecordIndex: 3, Name: "C", SGDepth: 1, RealLifeGP: 5, RealLifeMIN: 200, RealLifeAST: 50},
		// Non-qualifying: MIN not > 2×GP (boundary, strict >) — excluded.
		{RecordIndex: 4, Name: "D", PGDepth: 1, RealLifeGP: 100, RealLifeMIN: 200, RealLifeAST: 999},
		// Empty name: excluded despite otherwise-qualifying stats.
		{RecordIndex: 5, Name: "", PGDepth: 1, RealLifeGP: 10, RealLifeMIN: 500, RealLifeAST: 999},
		// RecordIndex beyond the scan boundary: excluded.
		{RecordIndex: 960, Name: "F", PGDepth: 1, RealLifeGP: 10, RealLifeMIN: 500, RealLifeAST: 999},
	}
	got := computeLeagueAST48ByPos(players)
	wantPG := (100.0/500.0*48.0 + 500.0/1000.0*48.0) / 2.0
	wantSG := 50.0 / 200.0 * 48.0
	if math.Abs(got[1]-wantPG) > 1e-9 {
		t.Errorf("PG bucket = %v, want %v", got[1], wantPG)
	}
	if math.Abs(got[2]-wantSG) > 1e-9 {
		t.Errorf("SG bucket = %v, want %v", got[2], wantSG)
	}
	// SF/PF/C buckets have no qualifying players -> 0.
	if got[3] != 0 || got[4] != 0 || got[5] != 0 {
		t.Errorf("empty buckets = %+v, want all zero", got)
	}

	// Fully-empty population -> [6]float64{} (all zero) — the graceful degrade.
	if empty := computeLeagueAST48ByPos(nil); empty != ([6]float64{}) {
		t.Errorf("empty population = %+v, want all-zero", empty)
	}
}
