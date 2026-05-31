package result

import (
	"encoding/json"
	"reflect"
	"strings"
	"testing"
)

// jsonTagNames returns the JSON field names (tag before any comma) declared on
// a struct type.
func jsonTagNames(t reflect.Type) []string {
	var names []string
	for i := 0; i < t.NumField(); i++ {
		tag := t.Field(i).Tag.Get("json")
		if tag == "" || tag == "-" {
			continue
		}
		names = append(names, strings.Split(tag, ",")[0])
	}
	return names
}

// #5 — the box-score structs map to RAW ibl_box_scores columns only and never
// emit the MySQL-generated columns.
func TestBoxStructs_OmitGeneratedColumns(t *testing.T) {
	generated := map[string]bool{
		"calc_points": true, "calc_rebounds": true, "calc_fg_made": true,
		"game_type": true, "season_year": true,
	}
	for _, typ := range []reflect.Type{reflect.TypeOf(PlayerBox{}), reflect.TypeOf(TeamBox{})} {
		for _, name := range jsonTagNames(typ) {
			if generated[name] {
				t.Errorf("%s emits generated column %q — engine must not produce it", typ.Name(), name)
			}
		}
	}
}

// #6 — team box rows carry quarter-by-quarter points including overtime plus
// the full set of team stat totals.
func TestTeamBox_HasQuartersOvertimeAndTotals(t *testing.T) {
	tb := TeamBox{
		TeamID: 3, IsHome: true,
		Q1: 25, Q2: 22, Q3: 28, Q4: 20, OT: []int{8, 6},
		Game2GM: 40, Game2GA: 80, GameFTM: 15, GameFTA: 20,
		Game3GM: 9, Game3GA: 25, GameORB: 12, GameDRB: 33,
		GameAST: 24, GameSTL: 8, GameTOV: 13, GameBLK: 5, GamePF: 19,
	}
	data, err := json.Marshal(tb)
	if err != nil {
		t.Fatalf("Marshal: %v", err)
	}
	var back TeamBox
	if err := json.Unmarshal(data, &back); err != nil {
		t.Fatalf("Unmarshal: %v", err)
	}
	if back.Q1 != 25 || back.Q4 != 20 {
		t.Errorf("quarters lost: Q1=%d Q4=%d", back.Q1, back.Q4)
	}
	if len(back.OT) != 2 || back.OT[0] != 8 || back.OT[1] != 6 {
		t.Errorf("overtime periods lost: %v", back.OT)
	}
	if back.Game3GM != 9 || back.GameBLK != 5 || back.GamePF != 19 {
		t.Errorf("stat totals lost: 3GM=%d BLK=%d PF=%d", back.Game3GM, back.GameBLK, back.GamePF)
	}
}

// #7 — each modeled event kind round-trips carrying its required fields.
func TestEvent_KindsCarryRequiredFields(t *testing.T) {
	events := []Event{
		{Kind: EventPossessionStart, Period: 1, Clock: 720, TeamID: 3},
		{Kind: EventShotAttempt, Period: 1, Clock: 700, TeamID: 3, PlayerID: 101, ShotType: ShotThree},
		{Kind: EventShotMake, Period: 1, Clock: 700, TeamID: 3, PlayerID: 101, ShotType: ShotThree},
		{Kind: EventShotMiss, Period: 2, Clock: 300, TeamID: 7, PlayerID: 202, ShotType: ShotTwoPoint},
		{Kind: EventRebound, Period: 2, Clock: 299, TeamID: 3, PlayerID: 101, OffensiveRebound: true},
		{Kind: EventTurnover, Period: 3, Clock: 120, TeamID: 7, PlayerID: 202},
		{Kind: EventSteal, Period: 3, Clock: 120, TeamID: 3, PlayerID: 101, DefenderID: 202},
		{Kind: EventBlock, Period: 3, Clock: 90, TeamID: 3, PlayerID: 101, DefenderID: 202},
		{Kind: EventFoul, Period: 4, Clock: 60, TeamID: 7, PlayerID: 202},
		{Kind: EventSubstitution, Period: 4, Clock: 60, TeamID: 3, PlayerID: 103},
		{Kind: EventFreeThrow, Period: 4, Clock: 60, TeamID: 7, PlayerID: 202, ShotType: ShotFreeThrow},
		{Kind: EventPeriodBoundary, Period: 4, Clock: 0},
	}
	data, err := json.Marshal(events)
	if err != nil {
		t.Fatalf("Marshal: %v", err)
	}
	var back []Event
	if err := json.Unmarshal(data, &back); err != nil {
		t.Fatalf("Unmarshal: %v", err)
	}
	if len(back) != len(events) {
		t.Fatalf("event count: got %d want %d", len(back), len(events))
	}
	// Shot events must retain player id + shot type.
	shot := back[1]
	if shot.Kind != EventShotAttempt || shot.PlayerID != 101 || shot.ShotType != ShotThree {
		t.Errorf("shot_attempt lost fields: %+v", shot)
	}
	// Rebound must retain its offensive flag.
	reb := back[4]
	if reb.Kind != EventRebound || !reb.OffensiveRebound {
		t.Errorf("rebound lost offensive flag: %+v", reb)
	}
	// Steal/block must retain the defender.
	steal := back[6]
	if steal.Kind != EventSteal || steal.DefenderID != 202 {
		t.Errorf("steal lost defender: %+v", steal)
	}
}

// #3 — EventFreeThrow carries ft_attempts/ft_made for box reconstruction; the
// fields are omitempty, so non-FT events never emit them, and a 0-made trip
// round-trips ft_made == 0.
func TestEvent_FreeThrowCounts(t *testing.T) {
	// A made 1-of-2 trip emits both counts.
	ft := Event{Kind: EventFreeThrow, Period: 4, Clock: 60, TeamID: 7, PlayerID: 202,
		ShotType: ShotFreeThrow, FTAttempts: 2, FTMade: 1}
	data, err := json.Marshal(ft)
	if err != nil {
		t.Fatalf("Marshal: %v", err)
	}
	if !strings.Contains(string(data), `"ft_attempts":2`) || !strings.Contains(string(data), `"ft_made":1`) {
		t.Errorf("free throw missing counts in JSON: %s", data)
	}
	var back Event
	if err := json.Unmarshal(data, &back); err != nil {
		t.Fatalf("Unmarshal: %v", err)
	}
	if back.FTAttempts != 2 || back.FTMade != 1 {
		t.Errorf("counts lost: FTAttempts=%d FTMade=%d", back.FTAttempts, back.FTMade)
	}

	// A 0-of-2 trip omits ft_made (omitempty) but still round-trips to 0.
	zeroMade, _ := json.Marshal(Event{Kind: EventFreeThrow, Period: 1, Clock: 700,
		TeamID: 7, PlayerID: 202, ShotType: ShotFreeThrow, FTAttempts: 2, FTMade: 0})
	if strings.Contains(string(zeroMade), "ft_made") {
		t.Errorf("ft_made should be omitted when 0: %s", zeroMade)
	}
	var backZero Event
	if err := json.Unmarshal(zeroMade, &backZero); err != nil {
		t.Fatalf("Unmarshal: %v", err)
	}
	if backZero.FTMade != 0 || backZero.FTAttempts != 2 {
		t.Errorf("0-made trip lost counts: FTAttempts=%d FTMade=%d", backZero.FTAttempts, backZero.FTMade)
	}

	// A non-FT event omits both fields entirely.
	shot, _ := json.Marshal(Event{Kind: EventShotMake, Period: 1, Clock: 700,
		TeamID: 3, PlayerID: 101, ShotType: ShotTwoPoint})
	if strings.Contains(string(shot), "ft_attempts") || strings.Contains(string(shot), "ft_made") {
		t.Errorf("non-FT event must omit FT counts: %s", shot)
	}
}
