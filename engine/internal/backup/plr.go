// Package backup reads the JSB 5.60 backup file triple — .plr (roster), .sch
// (schedule), and .sco (box-score corpus), all fixed-width ASCII — into
// in-memory structs, and assembles a .plr+.sch pair into a bundle.Bundle so a historical
// backup can drive the native engine through the same stdin→stdout contract the
// DB-built bundle uses. This is the input/ground-truth side of the offline
// fidelity harness (PR9); the distributional comparison itself is PR9b.
//
// These files are fixed-width ASCII text, NOT binary: there is no magic number,
// no version header, and no encoding/binary. Every field is sliced by character
// offset, mirroring the authoritative PHP parsers exactly (see the per-file
// offset tables below). The canonical spec is ibl5/docs/JSB_FILE_FORMATS.md.
package backup

import (
	"errors"
	"fmt"
	"io"
	"strconv"
	"strings"
	"unicode"
)

// trimPad strips leading/trailing field padding. Real backup files pad fixed-
// width fields with spaces in most records but with NUL bytes (0x00) in the
// trailing padding records of a .sco corpus, so trimming whitespace alone leaves
// "\x00\x00" in a numeric/name field and turns a padding record into a parse
// error. Treating NUL as padding (alongside any Unicode space) lets those
// records collapse to "" and be skipped, matching how space-padded records are
// already handled.
func trimPad(s string) string {
	return strings.TrimFunc(s, func(r rune) bool {
		return r == 0 || unicode.IsSpace(r)
	})
}

// .plr 607-byte record offsets, transcribed verbatim from
// ibl5/classes/PlrParser/PlrLineParser.php (the authoritative reader). Only the
// fields that feed bundle.Player (plus identity) are mapped; the ~200 other
// substr offsets in PlrLineParser are season/career/contract aggregates the
// engine input does not consume, so transcribing them here would be dead code.
//
// Offsets verified identical across JSB 5.60 (ibl5/IBL5.plr) and 5.99
// (default_*.plr): the ASCII column layout is version-stable, so no version
// gate is needed (a record from either version parses correctly).
const (
	plrRecordSize = 607 // a full record; CRLF-separated in the file

	// Identity.
	offOrdinal = 0  // width 4 — roster slot 1..1440; >1440 = team-summary/padding row
	offName    = 4  // width 32 — CP1252, space-padded (right-justified in practice; trimmed)
	offAge     = 36 // width 2
	offPID     = 38 // width 6 — ibl_plr primary key; 0 = team-summary row
	offTeamID  = 44 // width 2 — tid
	offPeak    = 46 // width 4
	offPos     = 50 // width 2 — PG/SG/SF/PF/" C"

	// Real-life / previous-season counting-stat sums (width 4 each), the engine's
	// per-48-MINUTE shot-volume rate inputs (D88/DB8/D70 in sim/bucketweights.go).
	// Offsets transcribed from PlrLineParser.php 42-55. This is the STATIC reference
	// block JSB reads for "real-life tendencies" (ibl5/docs/JSB_FILE_FORMATS.md, the
	// 52-111 block), distinct from the in-game season totals (144-207).
	//
	// The rate is (stat / divisor) × 48 — the per-48 convention (_DAT_00669ed0 = 48.0).
	// The divisor is season MINUTES (offRealLifeMIN), established by elimination, not by
	// a clean decompile trace: a games-magnitude divisor (GP +0x144 or GS +0x148, both
	// ≈ 75-82) drives the 2pt bucket to O(100s) and collapses the foul/FTA play-outcome
	// mix to ~0, while a minutes-magnitude divisor (≈ 2500) reproduces jumpshot 5.60's
	// FTA/PF against the .sco corpus (5.60's own output) — and MIN (+0x14C) is the only
	// minutes-magnitude field in the stat block. COMPOSITE_DOUBLES_TRACE.md §1 labels
	// the divisor "ΣGP" and its §2 stat-offset map is flagged PARTIAL; the divisor
	// temporary is reused across blocks, so the exact accumulator identity is NOT
	// cleanly pinned — but games-scale is empirically excluded, leaving minutes.
	//
	// offRealLifeFGA is TOTAL field-goal attempts (incl. 3PA, standard FG naming;
	// verified FGA>=3GA across all 657 records of a real .plr) — it IS the decompile's
	// FGA_total, so D88 reads it directly. The four real-life fields feed the per-player
	// 2pt-bucket composite. The Branch-B usage-shrink's team DRB/AST rates come from a
	// DIFFERENT block — the per-player IN-SEASON box-score totals (offSeasonGP/DRB/AST
	// below), summed per team — NOT a separate team-summary record. (The earlier note
	// here citing "offsets 88/92" was wrong: 88/92 are not the DRB/AST source; the
	// faithful JSB team rate is (Σ_player season_DRB / Σ_player season_GP)×48 — see
	// offSeasonGP and assemble.go.)
	//
	// offRealLifeGP and offRealLife3GA feed the league 2PA/48 shot baseline
	// (assemble.go computeLeagueShotBaseline, the FUN_004385f0 league-table
	// port, over raw RECORDS — RecordIndex ≤ 959, not the bundle player list):
	// GP is the MIN > 2×GP inclusion gate, and 3GA isolates pure 2-point attempts
	// (2PA = FGA_total − 3GA, since offRealLifeFGA is the combined total).
	offRealLifeGP  = 52 // width 4 — games played (league-baseline inclusion gate)
	offRealLifeMIN = 56 // width 4 — minutes played (the per-48 rate divisor)
	offRealLifeFGM = 60 // width 4 — field goals made (feeds D60/D64 per-player make-rate)
	offRealLifeFGA = 64 // width 4 — total FG attempts (D88)
	offRealLifeFTM = 68 // width 4 — FT made (record +0x154; NonMatchedTerm pctFT + FT-draw terms)
	offRealLifeFTA = 72 // width 4 — FT attempts (D70 per-player part)
	offRealLife3GM = 76 // width 4 — 3-point field goals made (feeds D80 per-player 3pt make-rate)
	offRealLife3GA = 80 // width 4 — 3pt attempts (subtracted from FGA for the 2PA/48 baseline)
	offRealLifeORB = 84 // width 4 — offensive rebounds (DB8)
	// offRealLifeREB is TOTAL rebounds, not DRB: the binary's record map has REB at
	// +0x168 (FUN_0043c680 reads it as local_cd4 and forms DRB = REB − ORB for both
	// the production composite and the league DRB/48 bucket totals). The earlier
	// comment here calling offset 88 "DRB" was an unverified label.
	offRealLifeREB = 88  // width 4 — total rebounds (record +0x168; DRB = REB − ORB)
	offRealLifeAST = 92  // width 4 — career assists (real-life), feeds DefAST48 = AST/MIN×48 + LeagueAST48ByPos
	offRealLifeSTL = 96  // width 4 — career steals (real-life), PlrLineParser.php:53 substr(line,96,4)
	offRealLifeTVR = 100 // width 4 — career turnovers (real-life), PlrLineParser.php:54 substr(line,100,4)
	offRealLifeBLK = 104 // width 4 — blocks (feeds DE8 per-player block-rate; also LeagueBlk48)

	// Per-player IN-SEASON box-score totals (record-relative, width 4 each), the
	// Branch-B team-rate inputs. JSB's per-half setup (FUN_004cfa50, COMPOSITE_DOUBLES_
	// TRACE.md §1/§2) accumulates these over a team's players and forms the team DRB/AST
	// rates team[+0xDC0]=(Σ season_DRB/Σ season_GP)×48 and team[+0xDD0]=(Σ season_AST/Σ
	// season_GP)×44 — the (DRB-rate + AST-rate) factor of the usage-shrink target. These
	// are distinct from the static real-life block (56-84): they are the cumulative
	// season tallies (the 144-207 region). VALIDATED against a real .plr (IBL5.plr,
	// 2026-06-07): summing offSeasonAST over a team's player rows EXACTLY equals the
	// team-summary row's AST field; the team-summary row's own GP (= team-games, not
	// Σ player-GP) is the WRONG divisor and yields a degenerate over-shrink, which is
	// why the per-player accumulation — not the team-summary row — is the faithful source.
	offSeasonGP  = 148 // width 4 — season games played (Σ over a team = the rate divisor)
	offSeasonDRB = 184 // width 4 — season defensive rebounds
	offSeasonAST = 188 // width 4 — season assists

	// Clutch / consistency / depth chart.
	offClutch        = 128 // width 2
	offConsistency   = 130 // width 2
	offPGDepth       = 132 // width 1
	offSGDepth       = 133 // width 1
	offSFDepth       = 134 // width 1
	offPFDepth       = 135 // width 1
	offCDepth        = 136 // width 1
	offCanPlayInGame = 137 // width 1

	// Attributes.
	offTalent      = 268 // width 2
	offSkill       = 270 // width 2
	offIntangibles = 272 // width 2

	// Height/weight/ratings block.
	offRating2GA = 555 // width 3 — r_fga
	offRating2GP = 558 // width 3 — r_fgp
	offRatingFTA = 561 // width 3 — r_fta
	offRatingFTP = 564 // width 3 — r_ftp
	offRating3GA = 567 // width 3 — r_3ga
	offRating3GP = 570 // width 3 — r_3gp
	offRatingORB = 573 // width 3 — r_orb
	offRatingDRB = 576 // width 3 — r_drb
	offRatingAST = 579 // width 3 — r_ast
	offRatingSTL = 582 // width 3 — r_stl
	offRatingTVR = 585 // width 3 — r_tvr
	offRatingBLK = 588 // width 3 — r_blk

	// ODPT ratings (2 wide each). Offense: OO/DO/PO/TO; defense: OD/DD/PD/TD.
	offRatingOO = 591 // outside offense
	offRatingDO = 593 // drive offense   -> bundle r_drive_off
	offRatingPO = 595 // post offense
	offRatingTO = 597 // transition off  -> bundle r_trans_off
	offRatingOD = 599 // outside defense
	offRatingDD = 601 // drive defense
	offRatingPD = 603 // post defense
	offRatingTD = 605 // transition defense

	// A record shorter than this cannot carry the identity/ratings fields, so it
	// is blank/short padding and skipped. PlrLineParser::parse has no explicit
	// length guard (it relies on the pid==0 / ordinal>1440 checks below); this is
	// an added defense so a truncated line can never reach a field slice.
	plrMinRecordLen = 200
	plrMaxOrdinal   = 1440
)

// ErrBadField reports a non-numeric value where a numeric field was required.
// It names the offset and record index so a malformed corpus is diagnosable.
var ErrBadField = errors.New("backup: non-numeric field in .plr record")

// PlrPlayer is one parsed .plr player record, carrying exactly the fields that
// map onto bundle.Player (see ToBundle). Fields the engine needs but the .plr
// format does not store (r_foul, stamina, dc_minutes) are intentionally absent
// here and zeroed during assembly — see the unmapped-field note in assemble.go.
type PlrPlayer struct {
	Ordinal int
	PID     int
	Name    string
	TeamID  int
	Pos     string
	Age     int
	Peak    int

	// RecordIndex is the 1-based position of this player's line in the raw .plr
	// file (every CRLF-separated line counts, including short/padding rows and
	// pid==0 team-summary rows that never become a PlrPlayer — the parse is
	// sequential, so this is simply the loop position, NOT the roster-slot
	// Ordinal above). It is the FUN_004385f0 league-table scan boundary: JSB
	// 5.60 computes the league 2PA/48 baseline over records 1-959 ONLY (records
	// 960+ hold hundreds more named players outside that scan). Ordinal
	// happens to coincide with RecordIndex on some files but is a DIFFERENT
	// concept (a roster slot the .plr text itself carries) and must not be
	// used as the gate — see jsb-native/re-artifacts/jsb-J9-baseline-pin-
	// 20260712.md.
	RecordIndex int

	Clutch        int
	Consistency   int
	Talent        int
	Skill         int
	Intangibles   int
	CanPlayInGame int

	PGDepth int
	SGDepth int
	SFDepth int
	PFDepth int
	CDepth  int

	// Real-life / previous-season counting-stat sums — the per-48-MINUTE rate inputs
	// the 2pt-bucket composite reads ((stat/MIN)×48; RealLifeFGA is total FG attempts
	// incl. 3PA). Zero MINUTES means no prior-season reference (e.g. a rookie); the
	// engine falls back to the rating stand-in (sim/bucketweights.go twoPtBucketWeight).
	// RealLifeGP (the MIN > 2×GP inclusion gate) and RealLife3GA (2PA = FGA − 3GA)
	// feed the league 2PA/48 shot baseline (assemble.go computeLeagueShotBaseline),
	// gated jointly with RecordIndex ≤ 959 and a non-empty Name.
	RealLifeGP  int
	RealLifeMIN int
	RealLifeFGM int
	RealLifeFGA int
	RealLifeFTM int
	RealLifeFTA int
	RealLife3GM int
	RealLife3GA int
	RealLifeORB int
	RealLifeREB int // TOTAL rebounds (record +0x168); DRB = REB − ORB
	RealLifeAST int
	RealLifeSTL int
	RealLifeTVR int
	RealLifeBLK int

	// Per-player IN-SEASON box-score totals (offSeasonGP/DRB/AST), the Branch-B
	// team-rate inputs. Summed per team in assemble.go into bundle.Team.DRBRate/
	// ASTRate = (Σ season_DRB / Σ season_GP)×48 / (Σ season_AST / Σ season_GP)×44.
	// Distinct from the static RealLife* block: these are the cumulative season
	// tallies (the 144-207 region), the divisor being season GP, not minutes.
	SeasonGP  int
	SeasonDRB int
	SeasonAST int

	// Main ratings (0-99).
	RatingFGA int
	RatingFGP int
	RatingFTA int
	RatingFTP int
	Rating3GA int
	Rating3GP int
	RatingORB int
	RatingDRB int
	RatingAST int
	RatingSTL int
	RatingTVR int
	RatingBLK int

	// ODPT ratings.
	RatingOO int
	RatingOD int
	RatingDO int
	RatingDD int
	RatingPO int
	RatingPD int
	RatingTO int
	RatingTD int
}

// ReadPlr reads a backup .plr roster from r and returns the active player
// records. Records are CRLF-separated fixed-width lines; blank/short padding
// lines (< 200 bytes) and team-summary/padding rows (pid==0 or ordinal>1440)
// are skipped, mirroring PlrLineParser::parse. A non-numeric value in a numeric
// field yields ErrBadField naming the offset and record — never a panic.
func ReadPlr(r io.Reader) ([]PlrPlayer, error) {
	data, err := io.ReadAll(r)
	if err != nil {
		return nil, fmt.Errorf("backup: read .plr: %w", err)
	}
	// Mirror the PHP explode("\r\n", $data): split on CRLF, tolerate a bare
	// trailing newline or final partial record by skipping short lines below.
	lines := strings.Split(string(data), "\r\n")

	players := make([]PlrPlayer, 0, len(lines))
	for i, line := range lines {
		if len(line) < plrMinRecordLen {
			continue // blank / short padding row
		}
		ordinal, err := plrInt(line, offOrdinal, 4, i)
		if err != nil {
			return nil, err
		}
		pid, err := plrInt(line, offPID, 6, i)
		if err != nil {
			return nil, err
		}
		if pid == 0 || ordinal > plrMaxOrdinal {
			continue // team-summary row (pid==0) or padding slot
		}

		// RecordIndex is the 1-based position of THIS line in the raw file (i is
		// the index into every CRLF-separated line, including skipped short/
		// padding/team-summary rows before it) — the FUN_004385f0 scan boundary,
		// deliberately independent of Ordinal (see the PlrPlayer doc comment).
		p := PlrPlayer{Ordinal: ordinal, PID: pid, RecordIndex: i + 1, Name: decodeCP1252(plrSlice(line, offName, 32))}
		p.Pos = strings.TrimSpace(plrSlice(line, offPos, 2))

		// Parse every numeric field; the first non-numeric one short-circuits.
		fields := []struct {
			dst *int
			off int
			w   int
		}{
			{&p.TeamID, offTeamID, 2}, {&p.Age, offAge, 2}, {&p.Peak, offPeak, 4},
			{&p.Clutch, offClutch, 2}, {&p.Consistency, offConsistency, 2},
			{&p.Talent, offTalent, 2}, {&p.Skill, offSkill, 2}, {&p.Intangibles, offIntangibles, 2},
			{&p.CanPlayInGame, offCanPlayInGame, 1},
			{&p.PGDepth, offPGDepth, 1}, {&p.SGDepth, offSGDepth, 1}, {&p.SFDepth, offSFDepth, 1},
			{&p.PFDepth, offPFDepth, 1}, {&p.CDepth, offCDepth, 1},
			{&p.RealLifeGP, offRealLifeGP, 4},
			{&p.RealLifeMIN, offRealLifeMIN, 4},
			{&p.RealLifeFGM, offRealLifeFGM, 4}, {&p.RealLifeFGA, offRealLifeFGA, 4},
			{&p.RealLifeFTM, offRealLifeFTM, 4}, {&p.RealLifeFTA, offRealLifeFTA, 4},
			{&p.RealLife3GM, offRealLife3GM, 4}, {&p.RealLife3GA, offRealLife3GA, 4},
			{&p.RealLifeORB, offRealLifeORB, 4}, {&p.RealLifeREB, offRealLifeREB, 4},
			{&p.RealLifeAST, offRealLifeAST, 4},
			{&p.RealLifeSTL, offRealLifeSTL, 4},
			{&p.RealLifeTVR, offRealLifeTVR, 4},
			{&p.RealLifeBLK, offRealLifeBLK, 4},
			{&p.SeasonGP, offSeasonGP, 4}, {&p.SeasonDRB, offSeasonDRB, 4},
			{&p.SeasonAST, offSeasonAST, 4},
			{&p.RatingFGA, offRating2GA, 3}, {&p.RatingFGP, offRating2GP, 3},
			{&p.RatingFTA, offRatingFTA, 3}, {&p.RatingFTP, offRatingFTP, 3},
			{&p.Rating3GA, offRating3GA, 3}, {&p.Rating3GP, offRating3GP, 3},
			{&p.RatingORB, offRatingORB, 3}, {&p.RatingDRB, offRatingDRB, 3},
			{&p.RatingAST, offRatingAST, 3}, {&p.RatingSTL, offRatingSTL, 3},
			{&p.RatingTVR, offRatingTVR, 3}, {&p.RatingBLK, offRatingBLK, 3},
			{&p.RatingOO, offRatingOO, 2}, {&p.RatingDO, offRatingDO, 2},
			{&p.RatingPO, offRatingPO, 2}, {&p.RatingTO, offRatingTO, 2},
			{&p.RatingOD, offRatingOD, 2}, {&p.RatingDD, offRatingDD, 2},
			{&p.RatingPD, offRatingPD, 2}, {&p.RatingTD, offRatingTD, 2},
		}
		for _, f := range fields {
			v, err := plrInt(line, f.off, f.w, i)
			if err != nil {
				return nil, err
			}
			*f.dst = v
		}
		players = append(players, p)
	}
	return players, nil
}

// plrSlice returns line[off:off+width], clamped to the line length so a short
// (but >= 200) record yields "" for an out-of-range field rather than panicking
// — mirroring PHP substr, which returns "" past the end.
func plrSlice(line string, off, width int) string {
	if off >= len(line) {
		return ""
	}
	end := off + width
	if end > len(line) {
		end = len(line)
	}
	return line[off:end]
}

// plrInt slices a numeric field, trims padding, and parses it. An empty/blank
// field is 0 (matching PHP's (int)"   "); a non-numeric value is ErrBadField.
func plrInt(line string, off, width, rec int) (int, error) {
	s := strings.TrimSpace(plrSlice(line, off, width))
	if s == "" {
		return 0, nil
	}
	v, err := strconv.Atoi(s)
	if err != nil {
		return 0, fmt.Errorf("%w: record %d offset %d width %d = %q", ErrBadField, rec, off, width, s)
	}
	return v, nil
}

// decodeCP1252 converts a Windows-1252 byte string to a trimmed UTF-8 string,
// matching PlrLineParser's iconv('CP1252','UTF-8'). Pure stdlib: 0x00-0x7F and
// 0xA0-0xFF are code-point-identical to Unicode (Latin-1); only 0x80-0x9F need
// the CP1252 table below.
func decodeCP1252(s string) string {
	var b strings.Builder
	b.Grow(len(s))
	for i := 0; i < len(s); i++ {
		c := s[i]
		switch {
		case c < 0x80, c >= 0xA0:
			b.WriteRune(rune(c))
		default:
			if r := cp1252High[c-0x80]; r != 0 {
				b.WriteRune(r)
			} // 0 = undefined CP1252 code point; drop it (iconv //IGNORE)
		}
	}
	return trimPad(b.String())
}

// cp1252High maps the 0x80-0x9F range where CP1252 diverges from Latin-1. A 0
// entry is an undefined CP1252 byte (dropped, matching iconv //IGNORE).
var cp1252High = [32]rune{
	0x20AC, 0, 0x201A, 0x0192, 0x201E, 0x2026, 0x2020, 0x2021,
	0x02C6, 0x2030, 0x0160, 0x2039, 0x0152, 0, 0x017D, 0,
	0, 0x2018, 0x2019, 0x201C, 0x201D, 0x2022, 0x2013, 0x2014,
	0x02DC, 0x2122, 0x0161, 0x203A, 0x0153, 0, 0x017E, 0x0178,
}
