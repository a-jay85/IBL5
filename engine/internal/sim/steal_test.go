package sim

import (
	"math"
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

// stealPool caps at defQualityCapMultiplier·defQualityCapTeamMult·stealCapLeagueSTL48.
func TestStealPool_CappedAtDefQualityCeiling(t *testing.T) {
	_, def := stealLineups(40, 99)
	for i := range def.players {
		def.players[i].RealLifeMIN = 1
		def.players[i].RealLifeSTL = 100 // 4400 per 44: far past the cap
		def.players[i].fatigue = 1
	}
	want := defQualityCapMultiplier * defQualityCapTeamMult * stealCapLeagueSTL48
	if got := stealPool(def); got != want {
		t.Errorf("stealPool = %v, want the cap %v", got, want)
	}
}

// Over the cap, stealPool returns the cap built from the live league STL/48,
// 2.043. The literal pins it so a revert to leagueSTL48 (1.834) fails.
func TestStealPool_CapUsesLiveLeagueSTL48(t *testing.T) {
	def := realSTLDefense(5000, 5000, 5000, 5000, 5000)
	want := defQualityCapMultiplier * defQualityCapTeamMult * 2.043
	if got := stealPool(def); math.Abs(got-want) > 1e-12 {
		t.Errorf("stealPool = %v, want the 2.043 cap %v", got, want)
	}
}

// Below the cap, stealPool returns the raw summed terms.
func TestStealPool_BelowCapReturnsSum(t *testing.T) {
	def := realSTLDefense(10, 20, 30)
	want := stealTerm(def.players[0]) + stealTerm(def.players[1]) + stealTerm(def.players[2])
	if got := stealPool(def); math.Abs(got-want) > 1e-12 {
		t.Errorf("stealPool = %v, want the raw sum %v", got, want)
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

// --- the stealer pick reuses the steal roll (FUN_004e2fc0) ---

// realSTLDefense builds a defense whose players carry real career steals, so each
// stlRate is exact (steals per minute × 44) and a zero gives a zero-width bin.
func realSTLDefense(stls ...int) *teamState {
	_, def := stealLineups(40, 30)
	def.players = def.players[:len(stls)]
	for i, stl := range stls {
		def.players[i].RealLifeMIN = 1000
		def.players[i].RealLifeSTL = stl
		def.players[i].fatigue = 1
	}
	return def
}

// stealTerm is one defender's bin width, stlRate × fatigue.
func stealTerm(d onCourt) float64 { return stlRate(d) * d.fatigue }

// A roll exactly on a bin's upper edge credits that slot; a roll just above it
// credits the next slot.
func TestPickStealerFromRoll_BinsBySlot(t *testing.T) {
	def := realSTLDefense(10, 20, 30)
	if stealTermSum(def) > stealPool(def) {
		t.Fatalf("fixture terms %v pass the cap %v; want an uncapped defense", stealTermSum(def), stealPool(def))
	}
	e1 := stealTerm(def.players[0])
	e2 := e1 + stealTerm(def.players[1])
	e3 := e2 + stealTerm(def.players[2])
	cases := []struct {
		roll float64
		want int
	}{
		{e1, 0},
		{e1 + 1e-9, 1},
		{(e2 + e3) / 2, 2},
	}
	for _, c := range cases {
		got, ok := pickStealerFromRoll(def, c.roll)
		if !ok || got.PID != def.players[c.want].PID {
			t.Errorf("roll %v: got PID %d ok=%v, want slot %d PID %d ok=true",
				c.roll, got.PID, ok, c.want, def.players[c.want].PID)
		}
	}
}

// A zero-width first bin is never credited: roll 0 matches nothing, and a tiny
// positive roll lands in slot 1.
func TestPickStealerFromRoll_ZeroTermNeverCredited(t *testing.T) {
	def := realSTLDefense(0, 20)
	if got, ok := pickStealerFromRoll(def, 0); ok {
		t.Errorf("roll 0 credited PID %d; want no bin to match", got.PID)
	}
	got, ok := pickStealerFromRoll(def, 1e-9)
	if !ok || got.PID != def.players[1].PID {
		t.Errorf("roll 1e-9: got PID %d ok=%v, want slot 1 PID %d ok=true", got.PID, ok, def.players[1].PID)
	}
}

// A roll beyond every bin falls back to the last defender with false.
func TestPickStealerFromRoll_BeyondPoolFallsBackToLastSlot(t *testing.T) {
	def := realSTLDefense(10, 20)
	got, ok := pickStealerFromRoll(def, stealTermSum(def)+1)
	if ok {
		t.Error("ok should be false when no bin holds the roll")
	}
	if got.PID != def.players[1].PID {
		t.Errorf("fallback returned PID %d, want the last defender %d", got.PID, def.players[1].PID)
	}
}

// An empty defense returns the zero onCourt with false and never indexes a slot.
func TestPickStealerFromRoll_EmptyDefense(t *testing.T) {
	got, ok := pickStealerFromRoll(&teamState{}, 0.5)
	if ok || got.PID != 0 {
		t.Errorf("empty defense: got PID %d ok=%v, want the zero onCourt and false", got.PID, ok)
	}
}

// Over the cap, JSB scales every bin edge by pool/total, so the bins split the
// capped pool: a roll just under the pool still credits the last defender.
func TestPickStealerFromRoll_ScalesBinsToCap(t *testing.T) {
	def := realSTLDefense(5000, 5000, 5000)
	total, pool := stealTermSum(def), stealPool(def)
	if total <= pool {
		t.Fatalf("fixture total %v sits under the cap %v; want a capped defense", total, pool)
	}
	got, ok := pickStealerFromRoll(def, pool*(1-1e-9))
	if !ok || got.PID != def.players[2].PID {
		t.Errorf("roll just under the pool: got PID %d ok=%v, want slot 2 PID %d ok=true", got.PID, ok, def.players[2].PID)
	}
	got, ok = pickStealerFromRoll(def, pool/3+1e-9)
	if !ok || got.PID != def.players[1].PID {
		t.Errorf("roll just past the first scaled edge: got PID %d ok=%v, want slot 1 PID %d ok=true", got.PID, ok, def.players[1].PID)
	}
}

// A steal possession and a no-steal possession from the same seed leave the RNG
// at the same position. Both draw the offensive-foul roll and the steal roll
// only, so the stealer pick consumes nothing.
func TestResolveTurnoverPath_StealDrawsOneRoll(t *testing.T) {
	build := func(stl int) (*teamState, *teamState) {
		off, _ := stealLineups(40, 30)
		for i := range off.players {
			off.players[i].RealLifeMIN = 1000
			off.players[i].RealLifeTVR = 1 // tiny turnover pool, under the steal cap
			off.players[i].fatigue = 1
		}
		return off, realSTLDefense(stl, stl, stl, stl, stl)
	}
	var seed uint64
	for s := uint64(1); s < 1000; s++ {
		if rng.New(s).Float64() >= offensiveFoulOnTurnover {
			seed = s
			break
		}
	}
	if seed == 0 {
		t.Fatal("no seed in range skips the offensive foul")
	}
	offA, defA := build(0)
	gsA := &gameState{rng: rng.New(seed), period: 1, clock: 500}
	if gsA.resolveTurnoverPath(offA, defA, offA.players[0], outcomeTurnover) {
		t.Fatal("a zero steal pool must not steal")
	}
	offB, defB := build(5000)
	if stealPool(defB) <= turnoverPool(offB) {
		t.Fatalf("steal pool %v must exceed the turnover pool %v", stealPool(defB), turnoverPool(offB))
	}
	gsB := &gameState{rng: rng.New(seed), period: 1, clock: 500}
	if !gsB.resolveTurnoverPath(offB, defB, offB.players[0], outcomeTurnover) {
		t.Fatal("a steal pool above the turnover pool must steal")
	}
	if a, b := gsA.rng.Float64(), gsB.rng.Float64(); a != b {
		t.Errorf("next draw after no-steal %v != after steal %v; the stealer pick drew from the RNG", a, b)
	}
}
