package sim

import "github.com/a-jay85/IBL5/engine/internal/result"

// playerMeta is the per-player roster metadata the aggregator needs but cannot
// reconstruct from the event stream: identity (PID), position (Pos), and minutes
// (GameMIN, the PR4b possession-time accumulator). A DNP carries GameMIN == 0 and
// emits no events, so it can only be surfaced from this metadata.
type playerMeta struct {
	PID     int
	Pos     string
	GameMIN int
}

// rosterMeta is one team's identity plus its ordered player roster (bundle
// order). teamID/isHome are not derivable from events — a steal/block event
// carries the offense's TeamID, and DNPs emit nothing — so they are supplied
// here to label the TeamBox and to route each event's counter to the right team.
type rosterMeta struct {
	teamID  int
	isHome  bool
	players []playerMeta
}

// aggregateBoxes derives every output PlayerBox stat counter and TeamBox total +
// quarter split purely by folding over the event stream, joined with the two
// teams' roster metadata. It is the single source of truth for the box score:
// the sim emits events and feeds roster metadata; nothing else writes box stats.
//
// It emits one PlayerBox per rostered player, visitor team first then home (in
// bundle order). Players with no events yield an all-zero stat line carrying
// their Pos and GameMIN (DNP == GameMIN 0). GameAST is always 0 (commentary-only,
// master-reference L1098).
func aggregateBoxes(events []result.Event, visitor, home rosterMeta) ([]result.PlayerBox, []result.TeamBox) {
	boxes := make([]result.PlayerBox, 0, len(visitor.players)+len(home.players))
	for _, m := range visitor.players {
		boxes = append(boxes, result.PlayerBox{PID: m.PID, Pos: m.Pos, GameMIN: m.GameMIN})
	}
	for _, m := range home.players {
		boxes = append(boxes, result.PlayerBox{PID: m.PID, Pos: m.Pos, GameMIN: m.GameMIN})
	}

	// Index the output rows for O(1) folding, and map every PID to its team so
	// steal/block credit and team rollups route correctly.
	byPID := make(map[int]*result.PlayerBox, len(boxes))
	for i := range boxes {
		byPID[boxes[i].PID] = &boxes[i]
	}
	pidTeam := make(map[int]int, len(boxes))
	for _, m := range visitor.players {
		pidTeam[m.PID] = visitor.teamID
	}
	for _, m := range home.players {
		pidTeam[m.PID] = home.teamID
	}

	for _, e := range events {
		switch e.Kind {
		case result.EventShotAttempt:
			if b := byPID[e.PlayerID]; b != nil {
				if e.ShotType == result.ShotThree {
					b.Game3GA++
				} else {
					b.Game2GA++
				}
			}
		case result.EventShotMake:
			if b := byPID[e.PlayerID]; b != nil {
				if e.ShotType == result.ShotThree {
					b.Game3GM++
				} else {
					b.Game2GM++
				}
			}
		case result.EventRebound:
			if b := byPID[e.PlayerID]; b != nil {
				if e.OffensiveRebound {
					b.GameORB++
				} else {
					b.GameDRB++
				}
			}
		case result.EventTurnover:
			if b := byPID[e.PlayerID]; b != nil {
				b.GameTOV++
			}
		case result.EventFoul:
			if b := byPID[e.PlayerID]; b != nil {
				b.GamePF++
			}
		case result.EventSteal:
			if b := byPID[e.DefenderID]; b != nil { // the stealer, on the defending team
				b.GameSTL++
			}
		case result.EventBlock:
			if b := byPID[e.DefenderID]; b != nil { // the blocker, on the defending team
				b.GameBLK++
			}
		case result.EventFreeThrow:
			if b := byPID[e.PlayerID]; b != nil {
				b.GameFTA += e.FTAttempts
				b.GameFTM += e.FTMade
			}
		}
	}

	teams := []result.TeamBox{
		rollupTeam(visitor, byPID, pidTeam, events),
		rollupTeam(home, byPID, pidTeam, events),
	}
	return boxes, teams
}

// rollupTeam sums the team's player rows into one TeamBox and lays its scoring
// out by period (Q1–Q4, then OT in order). Points bucket from EventShotMake (2
// or 3 by ShotType) and EventFreeThrow (FTMade). A period is "reached" by any
// EventShotMake OR EventFreeThrow for the team — mirroring the live path, which
// calls addPeriodPoints on every made FG and every FT trip (even a 0-made trip),
// so a 0-made free throw still extends the quarter slice.
func rollupTeam(meta rosterMeta, byPID map[int]*result.PlayerBox, pidTeam map[int]int, events []result.Event) result.TeamBox {
	tb := result.TeamBox{TeamID: meta.teamID, IsHome: meta.isHome, OT: []int{}}
	for _, m := range meta.players {
		b := byPID[m.PID]
		tb.Game2GM += b.Game2GM
		tb.Game2GA += b.Game2GA
		tb.GameFTM += b.GameFTM
		tb.GameFTA += b.GameFTA
		tb.Game3GM += b.Game3GM
		tb.Game3GA += b.Game3GA
		tb.GameORB += b.GameORB
		tb.GameDRB += b.GameDRB
		tb.GameAST += b.GameAST
		tb.GameSTL += b.GameSTL
		tb.GameTOV += b.GameTOV
		tb.GameBLK += b.GameBLK
		tb.GamePF += b.GamePF
	}

	periodPts := map[int]int{}
	maxPeriod := 0
	for _, e := range events {
		var pts int
		switch e.Kind {
		case result.EventShotMake:
			if e.ShotType == result.ShotThree {
				pts = 3
			} else {
				pts = 2
			}
		case result.EventFreeThrow:
			pts = e.FTMade // may be 0; still extends the quarter slice
		default:
			continue
		}
		if pidTeam[e.PlayerID] != meta.teamID {
			continue
		}
		periodPts[e.Period] += pts
		if e.Period > maxPeriod {
			maxPeriod = e.Period
		}
	}
	for p := 1; p <= maxPeriod; p++ {
		switch p {
		case 1:
			tb.Q1 = periodPts[p]
		case 2:
			tb.Q2 = periodPts[p]
		case 3:
			tb.Q3 = periodPts[p]
		case 4:
			tb.Q4 = periodPts[p]
		default:
			tb.OT = append(tb.OT, periodPts[p])
		}
	}
	return tb
}
