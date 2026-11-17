package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// twoTeams builds offense/defense states from richBundle (team 7 visitor, team 3
// home), each with five fully-rated starters.
func twoTeams() (*teamState, *teamState) {
	b := richBundle()
	return newTeamState(b.Players, 7, false), newTeamState(b.Players, 3, true)
}

// stealLineupPlayers builds a 5-starter player slice for team `teamID`, overriding
// every player's TVR (turnover rate stand-in) and STL (steal rate stand-in) so the
// steal split is controllable.
func stealLineupPlayers(teamID, tvr, stl int) []bundle.Player {
	var players []bundle.Player
	pid := teamID * 100
	for slot := slotPG; slot <= slotC; slot++ {
		pid++
		p := mkPlayer(pid, teamID, slot, 48)
		p.TVR = tvr
		p.STL = stl
		players = append(players, p)
	}
	return players
}

// stealLineups builds offense (team 7) and defense (team 3) 5-starter teams with a
// given offense TVR and defender STL. Returned via newTeamState so the live
// maps (injured, box) are initialized.
func stealLineups(offenseTVR, defenderSTL int) (*teamState, *teamState) {
	players := append(stealLineupPlayers(7, offenseTVR, 30), stealLineupPlayers(3, 40, defenderSTL)...)
	return newTeamState(players, 7, false), newTeamState(players, 3, true)
}

// turnoverPathTally runs `seeds` independent code-4 turnover paths (one fresh RNG
// per seed) and counts the steals and offensive fouls among them.
func turnoverPathTally(offenseTVR, defenderSTL int, seeds uint64) (steals, offFouls int) {
	for s := uint64(1); s <= seeds; s++ {
		off, def := stealLineups(offenseTVR, defenderSTL)
		gs := &gameState{rng: rng.New(s), period: 1, clock: 500}
		if gs.resolveTurnoverPath(off, def, off.players[0], outcomeTurnover) {
			steals++
		}
		if off.teamFouls > 0 {
			offFouls++
		}
	}
	return steals, offFouls
}

// The steal split is the defense's steal pool against the offense's turnover
// pool (FUN_004e2fc0): a higher-STL defense steals more of the turnovers, and a
// more turnover-prone offense (a bigger roll range) loses fewer of them to steals.
func TestResolveTurnoverPath_StealSplitOrientation(t *testing.T) {
	hiSTL, _ := turnoverPathTally(40, 60, 4000)
	loSTL, _ := turnoverPathTally(40, 10, 4000)
	if hiSTL <= loSTL {
		t.Errorf("STL=60 defense steals %d, STL=10 steals %d; want more steals from the higher-STL defense", hiSTL, loSTL)
	}
	loTVR, _ := turnoverPathTally(20, 30, 4000)
	hiTVR, _ := turnoverPathTally(80, 30, 4000)
	if hiTVR >= loTVR {
		t.Errorf("TVR=80 offense lost %d steals, TVR=20 lost %d; want fewer steals against the bigger turnover pool", hiTVR, loTVR)
	}
}

// stealPool caps at defQualityCapMultiplier·defQualityCapTeamMult·leagueSTL48, the
// same ceiling defQuality uses.
func TestStealPool_CappedAtDefQualityCeiling(t *testing.T) {
	_, def := stealLineups(40, 99)
	for i := range def.players {
		def.players[i].RealLifeMIN = 1
		def.players[i].RealLifeSTL = 100 // 4400 per 44: far past the cap
		def.players[i].fatigue = 1
	}
	want := defQualityCapMultiplier * defQualityCapTeamMult * leagueSTL48
	if got := stealPool(def); got != want {
		t.Errorf("stealPool = %v, want the cap %v", got, want)
	}
}

// turnoverPool weights each offensive player's tovRate by (2.0 − fatigue).
func TestTurnoverPool_FatigueWeighting(t *testing.T) {
	off, _ := stealLineups(40, 30)
	var want float64
	for i := range off.players {
		off.players[i].fatigue = 0.5
		want += 1.5 * tovRate(off.players[i])
	}
	if got := turnoverPool(off); got != want {
		t.Errorf("turnoverPool = %v, want %v", got, want)
	}
}

// --- on a steal, the defender is credited STL and the ball handler keeps TOV ---

// On a steal resolveTurnoverPath emits EventTurnover (ball handler, → GameTOV)
// AND EventSteal (DefenderID = stealer, → the stealer's GameSTL). aggregateBoxes
// derives the box counters from those events; the helper writes no box rows.
func TestStealTurnover_CreditsDefenderVictimKeepsTOV(t *testing.T) {
	for seed := uint64(1); seed < 200; seed++ {
		offense, defense := stealLineups(10, 45)
		victim := offense.players[0]
		gs := &gameState{rng: rng.New(seed), period: 1, clock: 500}
		if !gs.resolveTurnoverPath(offense, defense, victim, outcomeTurnover) {
			continue
		}

		var tos, steals, fouls []result.Event
		for _, e := range gs.events {
			switch e.Kind {
			case result.EventTurnover:
				tos = append(tos, e)
			case result.EventSteal:
				steals = append(steals, e)
			case result.EventFoul:
				fouls = append(fouls, e)
			}
		}
		if len(tos) != 1 || len(steals) != 1 || len(fouls) != 0 {
			t.Fatalf("expected 1 turnover + 1 steal + 0 fouls, got %d / %d / %d", len(tos), len(steals), len(fouls))
		}
		if tos[0].PlayerID != victim.PID || tos[0].TeamID != offense.teamID {
			t.Errorf("turnover PlayerID/TeamID = %d/%d, want victim %d / offense %d",
				tos[0].PlayerID, tos[0].TeamID, victim.PID, offense.teamID)
		}
		st := steals[0]
		if st.PlayerID != victim.PID {
			t.Errorf("steal PlayerID = %d, want victim %d", st.PlayerID, victim.PID)
		}
		if defense.box(st.DefenderID) == nil {
			t.Fatalf("DefenderID %d is not on the defending team", st.DefenderID)
		}
		if defense.box(st.DefenderID).GameSTL != 0 {
			t.Error("resolveTurnoverPath must not write GameSTL (box is event-derived)")
		}
		return
	}
	t.Fatal("no seed in range produced a steal")
}

// An offensive foul (10% of code-4 turnovers, 0x4da7a0) charges the ball handler
// a turnover and a personal foul tagged FoulSiteOffensiveTurnover, adds one to the
// offense's team fouls and the handler's foul tally, shoots no free throws, and
// never credits a steal.
func TestResolveTurnoverPath_OffensiveFoul(t *testing.T) {
	for seed := uint64(1); seed < 400; seed++ {
		offense, defense := stealLineups(40, 30)
		bh := offense.players[0]
		gs := &gameState{rng: rng.New(seed), period: 1, clock: 500}
		steal := gs.resolveTurnoverPath(offense, defense, bh, outcomeTurnover)
		if offense.teamFouls == 0 {
			continue
		}
		if steal {
			t.Fatal("an offensive foul must not end in a steal")
		}
		if offense.teamFouls != 1 || offense.fouls[bh.PID] != 1 || defense.teamFouls != 0 {
			t.Errorf("team fouls off/def = %d/%d, handler fouls = %d; want 1/0/1",
				offense.teamFouls, defense.teamFouls, offense.fouls[bh.PID])
		}
		var tos, fouls, fts, steals int
		for _, e := range gs.events {
			switch e.Kind {
			case result.EventTurnover:
				tos++
			case result.EventFoul:
				fouls++
				if e.PlayerID != bh.PID || e.TeamID != offense.teamID || e.FoulSite != result.FoulSiteOffensiveTurnover {
					t.Errorf("foul event = player %d team %d site %v; want handler %d, offense %d, offensive-turnover",
						e.PlayerID, e.TeamID, e.FoulSite, bh.PID, offense.teamID)
				}
			case result.EventFreeThrow:
				fts++
			case result.EventSteal:
				steals++
			}
		}
		if tos != 1 || fouls != 1 || fts != 0 || steals != 0 {
			t.Errorf("events: %d turnovers, %d fouls, %d FT, %d steals; want 1, 1, 0, 0", tos, fouls, fts, steals)
		}
		return
	}
	t.Fatal("no seed in range produced an offensive foul")
}

// --- matrix #4: all-zero STL weights → players[0] fallback, no divide-by-zero --

func TestSelectStealer_AllZeroWeightsFallsBack(t *testing.T) {
	b := richBundle()
	// Zero out every STL rating so the weighted sum is zero.
	for i := range b.Players {
		b.Players[i].STL = 0
	}
	defense := newTeamState(b.Players, 3, true)

	got, ok := selectStealer(defense, rng.New(7))
	if ok {
		t.Error("ok should be false on the all-zero fallback")
	}
	if got.PID != defense.players[0].PID {
		t.Errorf("fallback returned %d, want players[0] = %d", got.PID, defense.players[0].PID)
	}
}
