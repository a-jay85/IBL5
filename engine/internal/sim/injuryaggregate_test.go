package sim

import (
	"encoding/json"
	"strings"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/result"
)

// --- matrix #13: aggregateInjuries folds each EventInjury into an Injury ------

func TestAggregateInjuries_FoldsEvents(t *testing.T) {
	events := []result.Event{
		{Kind: result.EventTurnover, Period: 1, Clock: 700, TeamID: 7, PlayerID: 201},
		{Kind: result.EventInjury, Period: 1, Clock: 700, TeamID: 7, PlayerID: 201, Severity: 2, GamesMissed: 4},
		{Kind: result.EventShotMake, Period: 2, Clock: 400, TeamID: 3, PlayerID: 101},
		{Kind: result.EventInjury, Period: 4, Clock: 33, TeamID: 3, PlayerID: 103, Severity: 78, GamesMissed: 175},
	}
	got := aggregateInjuries(events)
	want := []result.Injury{
		{PID: 201, TeamID: 7, GamesMissed: 4, Severity: 2, Period: 1, Clock: 700},
		{PID: 103, TeamID: 3, GamesMissed: 175, Severity: 78, Period: 4, Clock: 33},
	}
	if len(got) != len(want) {
		t.Fatalf("aggregateInjuries returned %d injuries, want %d (%+v)", len(got), len(want), got)
	}
	for i := range want {
		if got[i] != want[i] {
			t.Errorf("injury[%d] = %+v, want %+v", i, got[i], want[i])
		}
	}
}

// --- matrix #14: zero EventInjury → empty slice AND omitted JSON key ----------

func TestAggregateInjuries_NoneOmitsKey(t *testing.T) {
	events := []result.Event{
		{Kind: result.EventTurnover, Period: 1, Clock: 700, TeamID: 7, PlayerID: 201},
		{Kind: result.EventShotMake, Period: 2, Clock: 400, TeamID: 3, PlayerID: 101},
	}
	got := aggregateInjuries(events)
	if len(got) != 0 {
		t.Errorf("aggregateInjuries with no injury events = %+v, want empty", got)
	}

	gr := result.GameResult{
		Date: "1988-11-04", HomeTeamID: 3, VisitorTeamID: 7,
		Injuries: aggregateInjuries(events),
	}
	data, err := json.Marshal(gr)
	if err != nil {
		t.Fatalf("Marshal: %v", err)
	}
	if strings.Contains(string(data), "injuries") {
		t.Errorf("injury-free GameResult must omit the injuries key: %s", data)
	}
}
