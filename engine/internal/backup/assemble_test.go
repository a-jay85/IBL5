package backup

import (
	"encoding/json"
	"errors"
	"fmt"
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
