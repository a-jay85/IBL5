//go:build archive

// Possession-accounting diagnostic over the REAL ~53 GB JSB backup archive.
// Answers the ADR-0044 follow-up branch (advisor 2026-06-03): is the engine's
// ~-25 FGA/team level deficit a PACE bug (too few possessions — the
// defense-composite base_time placeholder runs slow, tempo.go) or a
// FGA-per-possession bug (it reaches ~real possessions but trips die in
// turnovers / FT-only / no shot)? Different fixes (base_time RE vs
// within-possession RE), so measure before scoping.
//
// Possessions are box-derived (FGA + 0.44*FTA + TOV - ORB) identically on both
// sides — the raw engine TeamBox AND the raw .sco ScoBox both carry ORB (only
// the COMPARED StatRow set collapses to total REB). Possession count is
// runs-stable, so 1 seed/game suffices.
//
// Invoke:
//
//	cd engine && JSB_ARCHIVE_DIR=/Users/ajaynicolas/GitHub/IBL5/ibl5/backups \
//	  go test -tags archive ./internal/calibrate -run PossessionAccounting -v -timeout 30m
package calibrate

import (
	"os"
	"path/filepath"
	"strconv"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/backup"
	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/sim"
)

func possEnvInt(key string, def int) int {
	if v := os.Getenv(key); v != "" {
		if n, err := strconv.Atoi(v); err == nil {
			return n
		}
	}
	return def
}

// loadTripleWithSco mirrors loadSeasonBundle but also returns the parsed .sco
// games and a cleanup func (the caller reads the .sco AFTER load, so the temp
// dir must outlive the call). Regular-season assembly.
func loadTripleWithSco(zipPath string) (bundle.Bundle, []backup.ScoGame, func(), *Skip) {
	noop := func() {}
	tmp, err := os.MkdirTemp("", "jsbposs-*")
	if err != nil {
		return bundle.Bundle{}, nil, noop, &Skip{zipPath, "mkdir temp: " + err.Error()}
	}
	cleanup := func() { _ = os.RemoveAll(tmp) }

	found, err := extractTriple(zipPath, tmp)
	if err != nil {
		cleanup()
		return bundle.Bundle{}, nil, noop, &Skip{zipPath, "extract: " + err.Error()}
	}
	if !found {
		cleanup()
		return bundle.Bundle{}, nil, noop, &Skip{zipPath, "missing one of IBL5.{plr,sch,sco}"}
	}

	players, err := readBackup(filepath.Join(tmp, "IBL5.plr"), backup.ReadPlr)
	if err != nil {
		cleanup()
		return bundle.Bundle{}, nil, noop, &Skip{zipPath, err.Error()}
	}
	sched, err := readBackup(filepath.Join(tmp, "IBL5.sch"), backup.ReadSch)
	if err != nil {
		cleanup()
		return bundle.Bundle{}, nil, noop, &Skip{zipPath, err.Error()}
	}
	scoGames, err := readBackup(filepath.Join(tmp, "IBL5.sco"), backup.ReadSco)
	if err != nil {
		cleanup()
		return bundle.Bundle{}, nil, noop, &Skip{zipPath, err.Error()}
	}
	var minutes map[int]int
	if plb := filepath.Join(tmp, "IBL5.plb"); fileExists(plb) {
		if f, err := os.Open(plb); err == nil {
			minutes, _ = backup.ReadPlb(f)
			_ = f.Close()
		}
	}
	b, err := backup.ToBundle(players, sched, backup.AssembleOptions{GameType: bundle.GameTypeRegular, Minutes: minutes})
	if err != nil {
		cleanup()
		return bundle.Bundle{}, nil, noop, &Skip{zipPath, "assemble: " + err.Error()}
	}
	return b, scoGames, cleanup, nil
}

func TestRealArchive_PossessionAccounting(t *testing.T) {
	dir := os.Getenv("JSB_ARCHIVE_DIR")
	if dir == "" {
		dir = "/Users/ajaynicolas/GitHub/IBL5/ibl5/backups"
	}
	if _, err := os.Stat(dir); err != nil {
		t.Skipf("archive dir %q not available: %v", dir, err)
	}

	zips, _, err := listArchiveZips(dir)
	if err != nil {
		t.Fatalf("listArchiveZips: %v", err)
	}
	if len(zips) == 0 {
		t.Fatal("no zips in archive")
	}

	zipLimit := possEnvInt("JSB_POSS_ZIPS", 12)
	gameCap := possEnvInt("JSB_POSS_GAMES", 60)
	stride := len(zips) / zipLimit
	if stride < 1 {
		stride = 1
	}

	var en, eFGA, eFTA, eTOV, eORB, eDRB, ePTS float64
	var e2GM, e2GA, e3GM, e3GA float64
	var sn, sFGA, sFTA, sTOV, sORB, sDRB, sPTS float64
	var s2GM, s2GA, s3GM, s3GA float64
	used := 0

	for zi := 0; zi < len(zips); zi += stride {
		zp := zips[zi]
		if isOlympicsPath(zp) {
			continue
		}
		b, scoGames, cleanup, skip := loadTripleWithSco(zp)
		if skip != nil {
			t.Logf("skip %s: %s", filepath.Base(zp), skip.Reason)
			continue
		}
		used++

		// Engine: sim a capped sample of real scheduled matchups, one game per seed.
		for gi, g := range b.Schedule {
			if gi >= gameCap {
				break
			}
			sub := bundle.Bundle{LeagueID: b.LeagueID, Teams: b.Teams, Players: b.Players, Schedule: []bundle.Game{g}}
			// seed MUST vary per game — see threept_attemptrouting_archive_test.go:~119 and
			// threept_undershoot_archive_test.go:~340. A constant seed over single-game
			// bundles re-draws overlapping prefixes of one PCG stream, amplifying that
			// prefix's bias instead of averaging it away.
			res := sim.Simulate(sub, 20240601+uint64(gi))
			for _, tb := range res.Games[0].TeamBoxes {
				pts := tb.Q1 + tb.Q2 + tb.Q3 + tb.Q4
				for _, o := range tb.OT {
					pts += o
				}
				eFGA += float64(tb.Game2GA + tb.Game3GA)
				e2GM += float64(tb.Game2GM)
				e2GA += float64(tb.Game2GA)
				e3GM += float64(tb.Game3GM)
				e3GA += float64(tb.Game3GA)
				eFTA += float64(tb.GameFTA)
				eTOV += float64(tb.GameTOV)
				eORB += float64(tb.GameORB)
				eDRB += float64(tb.GameDRB)
				ePTS += float64(pts)
				en++
			}
		}

		// Sco real: aggregate per-team from player rows (skip team-total row PID 0).
		for gi, sg := range scoGames {
			if gi >= gameCap {
				break
			}
			type agg struct{ fga, fta, tov, orb, drb, twogm, twoga, threegm, threega float64 }
			byTeam := map[int]*agg{sg.VisitorTeamID: {}, sg.HomeTeamID: {}}
			for _, bx := range sg.Boxes {
				if bx.PlayerID == 0 {
					continue
				}
				a := byTeam[bx.TeamID]
				if a == nil {
					continue
				}
				a.fga += float64(bx.TwoGA + bx.ThreeGA)
				a.twogm += float64(bx.TwoGM)
				a.twoga += float64(bx.TwoGA)
				a.threegm += float64(bx.ThreeGM)
				a.threega += float64(bx.ThreeGA)
				a.fta += float64(bx.FTA)
				a.tov += float64(bx.TOV)
				a.orb += float64(bx.ORB)
				a.drb += float64(bx.DRB)
			}
			pts := map[int]float64{sg.VisitorTeamID: float64(sg.VisitorScore), sg.HomeTeamID: float64(sg.HomeScore)}
			for id, a := range byTeam {
				sFGA += a.fga
				s2GM += a.twogm
				s2GA += a.twoga
				s3GM += a.threegm
				s3GA += a.threega
				sFTA += a.fta
				sTOV += a.tov
				sORB += a.orb
				sDRB += a.drb
				sPTS += pts[id]
				sn++
			}
		}
		cleanup()
	}

	if en == 0 || sn == 0 {
		t.Fatalf("no team-games aggregated (engine=%.0f sco=%.0f, zips_used=%d)", en, sn, used)
	}

	mE := func(s float64) float64 { return s / en }
	mS := func(s float64) float64 { return s / sn }
	ePoss := mE(eFGA) + 0.44*mE(eFTA) + mE(eTOV) - mE(eORB)
	sPoss := mS(sFGA) + 0.44*mS(sFTA) + mS(sTOV) - mS(sORB)

	t.Logf("zips_used=%d engine_team_games=%.0f sco_team_games=%.0f", used, en, sn)
	t.Logf("ENGINE/team: PTS=%.1f FGA=%.1f FTA=%.1f TOV=%.1f ORB=%.1f DRB=%.1f  POSS=%.1f  FGA/POSS=%.3f",
		mE(ePTS), mE(eFGA), mE(eFTA), mE(eTOV), mE(eORB), mE(eDRB), ePoss, mE(eFGA)/ePoss)
	t.Logf("SCO   /team: PTS=%.1f FGA=%.1f FTA=%.1f TOV=%.1f ORB=%.1f DRB=%.1f  POSS=%.1f  FGA/POSS=%.3f",
		mS(sPTS), mS(sFGA), mS(sFTA), mS(sTOV), mS(sORB), mS(sDRB), sPoss, mS(sFGA)/sPoss)
	t.Logf("GAP   /team: dPTS=%.1f dFGA=%.1f dFTA=%.1f dTOV=%.1f dORB=%.1f dPOSS=%.1f",
		mE(ePTS)-mS(sPTS), mE(eFGA)-mS(sFGA), mE(eFTA)-mS(sFTA), mE(eTOV)-mS(sTOV), mE(eORB)-mS(sORB), ePoss-sPoss)
	t.Logf("VERDICT: dPOSS=%.1f, dFGA/POSS=%.3f. If |dPOSS| small but FGA/POSS low → within-possession bug (#2). If dPOSS large negative → base_time pace bug (#4).",
		ePoss-sPoss, mE(eFGA)/ePoss-mS(sFGA)/sPoss)

	// Shooting %s — the leagueBaseline=233 fidelity check (advisor): JSB 3pt make =
	// baseline×1.5 with no other input, so sco 3pt% pins baseline = 3pt% × 666.7.
	pct := func(m, a float64) float64 {
		if a == 0 {
			return 0
		}
		return m / a * 100
	}
	e3pct, s3pct := pct(e3GM, e3GA), pct(s3GM, s3GA)
	t.Logf("3PT%%: engine %.1f%% sco %.1f%%  | 2PT%%: engine %.1f%% sco %.1f%%  | FG%%: engine %.1f%% sco %.1f%%",
		e3pct, s3pct, pct(e2GM, e2GA), pct(s2GM, s2GA), pct(e2GM+e3GM, e2GA+e3GA), pct(s2GM+s3GM, s2GA+s3GA))
	t.Logf("leagueBaseline CHECK: engine uses 233.0; sco-implied baseline = sco_3pt%% × 666.7 = %.1f. "+
		"If ≈233 → net→make slope faithful (close #2 as faithful-weak); if far off → leagueBaseline mis-set, net slope suspect (#2 stays OPEN, candidate flat-offense root).",
		s3pct/100*666.7)
}
