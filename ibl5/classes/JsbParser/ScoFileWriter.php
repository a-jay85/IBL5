<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\ScoFileWriterInterface;

/**
 * Encodes JSB .sco (Box Score) binary records from structured PHP data.
 *
 * Produces fixed-width records that round-trip through ScoFileParser /
 * Boxscore::fillGameInfo / PlayerStats::fillBoxscoreStats with byte-exact equality.
 *
 * Background: the two ASG records (bytes 0..3999) in 06-07_36_finals.zip's
 * IBL5.sco are entirely blank. This class encodes the known-good reconstructed
 * stats (sourced from ibl5/scripts/reconstruct_2007_asg_boxscores.php) so a
 * future archive-only reimport reproduces the 48 DB rows natively.
 * The patch CLI that applies the block is ibl5/scripts/patch_2007_asg_sco.php.
 *
 * @see \JsbParser\ScoFileParser   Paired reader
 * @see \JsbParser\TrnFileWriter   Pattern reference
 * @see /docs/decisions/0051-reconstructed-2007-asg-boxscores-in-finals-sco.md
 */
class ScoFileWriter implements ScoFileWriterInterface
{
    // ── Game-info offsets and widths (mirrors Boxscore::fillGameInfo) ─────────
    public const OFF_MONTH         = 0;  public const W_MONTH         = 2;
    public const OFF_DAY           = 2;  public const W_DAY           = 2;
    public const OFF_GAME_OF_DAY   = 4;  public const W_GAME_OF_DAY   = 2;
    public const OFF_VISITOR_TID   = 6;  public const W_VISITOR_TID   = 2;
    public const OFF_HOME_TID      = 8;  public const W_HOME_TID      = 2;
    public const OFF_ATTENDANCE    = 10; public const W_ATTENDANCE    = 5;
    public const OFF_CAPACITY      = 15; public const W_CAPACITY      = 5;
    public const OFF_VISITOR_WINS  = 20; public const W_VISITOR_WINS  = 2;
    public const OFF_VISITOR_LOSSES = 22; public const W_VISITOR_LOSSES = 2;
    public const OFF_HOME_WINS     = 24; public const W_HOME_WINS     = 2;
    public const OFF_HOME_LOSSES   = 26; public const W_HOME_LOSSES   = 2;
    public const OFF_VISITOR_Q1    = 28; public const W_Q             = 3;
    public const OFF_VISITOR_Q2    = 31;
    public const OFF_VISITOR_Q3    = 34;
    public const OFF_VISITOR_Q4    = 37;
    public const OFF_VISITOR_OT    = 40;
    public const OFF_HOME_Q1       = 43;
    public const OFF_HOME_Q2       = 46;
    public const OFF_HOME_Q3       = 49;
    public const OFF_HOME_Q4       = 52;
    public const OFF_HOME_OT       = 55;

    // ── Player-slot offsets and widths (mirrors PlayerStats::fillBoxscoreStats) ─
    public const OFF_NAME   = 0;  public const W_NAME   = 16;
    public const OFF_POS    = 16; public const W_POS    = 2;
    public const OFF_PID    = 18; public const W_PID    = 6;
    public const OFF_MIN    = 24; public const W_MIN    = 2;
    public const OFF_2GM    = 26; public const W_2GM    = 2;
    public const OFF_2GA    = 28; public const W_2GA    = 3;
    public const OFF_FTM    = 31; public const W_FTM    = 2;
    public const OFF_FTA    = 33; public const W_FTA    = 2;
    public const OFF_3GM    = 35; public const W_3GM    = 2;
    public const OFF_3GA    = 37; public const W_3GA    = 2;
    public const OFF_ORB    = 39; public const W_ORB    = 2;
    public const OFF_DRB    = 41; public const W_DRB    = 2;
    public const OFF_AST    = 43; public const W_AST    = 2;
    public const OFF_STL    = 45; public const W_STL    = 2;
    public const OFF_TOV    = 47; public const W_TOV    = 2;
    public const OFF_BLK    = 49; public const W_BLK    = 2;
    public const OFF_PF     = 51; public const W_PF     = 2;

    public const SCO_FILE_SIZE = 12781648;

    /**
     * @see ScoFileWriterInterface::buildGameInfo()
     */
    public static function buildGameInfo(
        int $monthStored,
        int $dayStored,
        int $gameOfDayStored,
        int $visitorTidStored,
        int $homeTidStored,
        int $attendance,
        int $capacity,
        int $visitorWins,
        int $visitorLosses,
        int $homeWins,
        int $homeLosses,
        array $visitorQ,
        array $homeQ,
    ): string {
        $record = str_repeat(' ', ScoFileParser::GAME_INFO_SIZE);

        $record = self::writeInt($record, self::OFF_MONTH,          self::W_MONTH,          $monthStored);
        $record = self::writeInt($record, self::OFF_DAY,            self::W_DAY,            $dayStored);
        $record = self::writeInt($record, self::OFF_GAME_OF_DAY,    self::W_GAME_OF_DAY,    $gameOfDayStored);
        $record = self::writeInt($record, self::OFF_VISITOR_TID,    self::W_VISITOR_TID,    $visitorTidStored);
        $record = self::writeInt($record, self::OFF_HOME_TID,       self::W_HOME_TID,       $homeTidStored);
        $record = self::writeInt($record, self::OFF_ATTENDANCE,     self::W_ATTENDANCE,     $attendance);
        $record = self::writeInt($record, self::OFF_CAPACITY,       self::W_CAPACITY,       $capacity);
        $record = self::writeInt($record, self::OFF_VISITOR_WINS,   self::W_VISITOR_WINS,   $visitorWins);
        $record = self::writeInt($record, self::OFF_VISITOR_LOSSES, self::W_VISITOR_LOSSES, $visitorLosses);
        $record = self::writeInt($record, self::OFF_HOME_WINS,      self::W_HOME_WINS,      $homeWins);
        $record = self::writeInt($record, self::OFF_HOME_LOSSES,    self::W_HOME_LOSSES,    $homeLosses);

        $vOffsets = [self::OFF_VISITOR_Q1, self::OFF_VISITOR_Q2, self::OFF_VISITOR_Q3, self::OFF_VISITOR_Q4, self::OFF_VISITOR_OT];
        $hOffsets = [self::OFF_HOME_Q1,    self::OFF_HOME_Q2,    self::OFF_HOME_Q3,    self::OFF_HOME_Q4,    self::OFF_HOME_OT];

        for ($i = 0; $i < 5; $i++) {
            $record = self::writeInt($record, $vOffsets[$i], self::W_Q, $visitorQ[$i]);
            $record = self::writeInt($record, $hOffsets[$i], self::W_Q, $homeQ[$i]);
        }

        return $record;
    }

    /**
     * @see ScoFileWriterInterface::buildPlayerSlot()
     */
    public static function buildPlayerSlot(
        string $name,
        string $pos,
        int $playerID,
        int $min,
        int $twoGM,
        int $twoGA,
        int $ftm,
        int $fta,
        int $threeGM,
        int $threeGA,
        int $orb,
        int $drb,
        int $ast,
        int $stl,
        int $tov,
        int $blk,
        int $pf,
    ): string {
        $record = str_repeat(' ', ScoFileParser::PLAYER_SLOT_SIZE);

        $record = self::writeName($record, self::OFF_NAME, self::W_NAME, $name);
        $record = self::writeName($record, self::OFF_POS,  self::W_POS,  $pos);
        $record = self::writeInt($record, self::OFF_PID,   self::W_PID,   $playerID);
        $record = self::writeInt($record, self::OFF_MIN,   self::W_MIN,   $min);
        $record = self::writeInt($record, self::OFF_2GM,   self::W_2GM,   $twoGM);
        $record = self::writeInt($record, self::OFF_2GA,   self::W_2GA,   $twoGA);
        $record = self::writeInt($record, self::OFF_FTM,   self::W_FTM,   $ftm);
        $record = self::writeInt($record, self::OFF_FTA,   self::W_FTA,   $fta);
        $record = self::writeInt($record, self::OFF_3GM,   self::W_3GM,   $threeGM);
        $record = self::writeInt($record, self::OFF_3GA,   self::W_3GA,   $threeGA);
        $record = self::writeInt($record, self::OFF_ORB,   self::W_ORB,   $orb);
        $record = self::writeInt($record, self::OFF_DRB,   self::W_DRB,   $drb);
        $record = self::writeInt($record, self::OFF_AST,   self::W_AST,   $ast);
        $record = self::writeInt($record, self::OFF_STL,   self::W_STL,   $stl);
        $record = self::writeInt($record, self::OFF_TOV,   self::W_TOV,   $tov);
        $record = self::writeInt($record, self::OFF_BLK,   self::W_BLK,   $blk);
        $record = self::writeInt($record, self::OFF_PF,    self::W_PF,    $pf);

        return $record;
    }

    /**
     * @see ScoFileWriterInterface::buildTeamTotalSlot()
     */
    public static function buildTeamTotalSlot(
        string $name,
        int $twoGM,
        int $twoGA,
        int $ftm,
        int $fta,
        int $threeGM,
        int $threeGA,
        int $orb,
        int $drb,
        int $ast,
        int $stl,
        int $tov,
        int $blk,
        int $pf,
    ): string {
        return self::buildPlayerSlot($name, '', 0, 0, $twoGM, $twoGA, $ftm, $fta, $threeGM, $threeGA, $orb, $drb, $ast, $stl, $tov, $blk, $pf);
    }

    /**
     * @see ScoFileWriterInterface::buildRecord()
     */
    public static function buildRecord(string $gameInfo, array $playerSlots): string
    {
        if (strlen($gameInfo) !== ScoFileParser::GAME_INFO_SIZE) {
            throw new \RuntimeException('gameInfo must be ' . ScoFileParser::GAME_INFO_SIZE . ' bytes, got ' . strlen($gameInfo));
        }

        $record = str_repeat(' ', ScoFileParser::RECORD_SIZE);
        $record = substr_replace($record, $gameInfo, 0, ScoFileParser::GAME_INFO_SIZE);

        foreach ($playerSlots as $i => $slot) {
            if (strlen($slot) !== ScoFileParser::PLAYER_SLOT_SIZE) {
                throw new \RuntimeException('Slot ' . $i . ' must be ' . ScoFileParser::PLAYER_SLOT_SIZE . ' bytes, got ' . strlen($slot));
            }
            $offset = ScoFileParser::GAME_INFO_SIZE + ($i * ScoFileParser::PLAYER_SLOT_SIZE);
            $record = substr_replace($record, $slot, $offset, ScoFileParser::PLAYER_SLOT_SIZE);
        }

        if (strlen($record) !== ScoFileParser::RECORD_SIZE) {
            throw new \RuntimeException('Record must be ' . ScoFileParser::RECORD_SIZE . ' bytes');
        }

        return $record;
    }

    /**
     * @see ScoFileWriterInterface::buildAllStarHeaderBlock()
     */
    public static function buildAllStarHeaderBlock(array $risingStars, array $allStar): string
    {
        $block = self::buildGameRecord($risingStars) . self::buildGameRecord($allStar);

        if (strlen($block) !== ScoFileParser::RECORD_SIZE * 2) {
            throw new \RuntimeException('Block must be ' . (ScoFileParser::RECORD_SIZE * 2) . ' bytes');
        }

        return $block;
    }

    /**
     * @see ScoFileWriterInterface::spliceAllStarBlock()
     */
    public static function spliceAllStarBlock(string $sco, string $block): string
    {
        if (strlen($block) !== 4000) {
            throw new \RuntimeException('block must be exactly 4000 bytes, got ' . strlen($block));
        }

        if (strlen($sco) !== self::SCO_FILE_SIZE) {
            throw new \RuntimeException('sco must be exactly ' . self::SCO_FILE_SIZE . ' bytes, got ' . strlen($sco));
        }

        if (trim(substr($sco, 0, 4000)) !== '') {
            throw new \RuntimeException('sco bytes 0..3999 must be entirely spaces (blank precondition)');
        }

        $originalTailHash = hash('sha256', substr($sco, ScoFileParser::HEADER_OFFSET_BYTES));

        $patched = substr_replace($sco, $block, 0, 4000);

        if (strlen($patched) !== self::SCO_FILE_SIZE) {
            throw new \RuntimeException('splice changed file length: expected ' . self::SCO_FILE_SIZE . ', got ' . strlen($patched));
        }

        $patchedTailHash = hash('sha256', substr($patched, ScoFileParser::HEADER_OFFSET_BYTES));
        if ($patchedTailHash !== $originalTailHash) {
            throw new \RuntimeException('splice mutated tail (bytes >= 1,000,000)');
        }

        return $patched;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Build a 2000-byte record from one game's data array.
     *
     * @param array{
     *     visitor_name: string,
     *     home_name: string,
     *     visitor_q: list<int>,
     *     home_q: list<int>,
     *     visitor_teamid: int,
     *     home_teamid: int,
     *     attendance: int,
     *     capacity: int,
     *     visitor_team: array{twoGM: int, twoGA: int, ftm: int, fta: int, threeGM: int, threeGA: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int},
     *     home_team: array{twoGM: int, twoGA: int, ftm: int, fta: int, threeGM: int, threeGA: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int},
     *     visitor_players: list<array{name: string, pos: string, pid: int, min: int, twoGM: int, twoGA: int, ftm: int, fta: int, threeGM: int, threeGA: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int}>,
     *     home_players: list<array{name: string, pos: string, pid: int, min: int, twoGM: int, twoGA: int, ftm: int, fta: int, threeGM: int, threeGA: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int}>,
     * } $game
     */
    private static function buildGameRecord(array $game): string
    {
        $gameInfo = self::buildGameInfo(
            1, // monthStored — overridden at import by overrideGameContext
            1, // dayStored   — overridden at import
            0, // gameOfDayStored — overridden
            $game['visitor_teamid'] - 1,
            $game['home_teamid'] - 1,
            $game['attendance'],
            $game['capacity'],
            0, 0, 0, 0, // W/L unrecoverable
            $game['visitor_q'],
            $game['home_q'],
        );

        // Slot layout: visitor players (0..N-1), blanks (N..13), visitor total (14),
        //              home players (15..15+M-1), blanks (15+M..28), home total (29).
        $slots = [];

        // Visitor players
        foreach ($game['visitor_players'] as $p) {
            $slots[] = self::buildPlayerSlot(
                $p['name'], $p['pos'], $p['pid'], $p['min'],
                $p['twoGM'], $p['twoGA'], $p['ftm'], $p['fta'],
                $p['threeGM'], $p['threeGA'], $p['orb'], $p['drb'],
                $p['ast'], $p['stl'], $p['tov'], $p['blk'], $p['pf'],
            );
        }

        // Blank filler: fill up to slot 13 (index 13) with space-only slots
        while (count($slots) < 14) {
            $slots[] = str_repeat(' ', ScoFileParser::PLAYER_SLOT_SIZE);
        }

        // Visitor team total at slot 14
        $vt = $game['visitor_team'];
        $slots[] = self::buildTeamTotalSlot(
            $game['visitor_name'],
            $vt['twoGM'], $vt['twoGA'], $vt['ftm'], $vt['fta'],
            $vt['threeGM'], $vt['threeGA'], $vt['orb'], $vt['drb'],
            $vt['ast'], $vt['stl'], $vt['tov'], $vt['blk'], $vt['pf'],
        );

        // Home players (slots 15+)
        foreach ($game['home_players'] as $p) {
            $slots[] = self::buildPlayerSlot(
                $p['name'], $p['pos'], $p['pid'], $p['min'],
                $p['twoGM'], $p['twoGA'], $p['ftm'], $p['fta'],
                $p['threeGM'], $p['threeGA'], $p['orb'], $p['drb'],
                $p['ast'], $p['stl'], $p['tov'], $p['blk'], $p['pf'],
            );
        }

        // Blank filler: fill up to slot 28 (index 28) with space-only slots
        while (count($slots) < 29) {
            $slots[] = str_repeat(' ', ScoFileParser::PLAYER_SLOT_SIZE);
        }

        // Home team total at slot 29
        $ht = $game['home_team'];
        $slots[] = self::buildTeamTotalSlot(
            $game['home_name'],
            $ht['twoGM'], $ht['twoGA'], $ht['ftm'], $ht['fta'],
            $ht['threeGM'], $ht['threeGA'], $ht['orb'], $ht['drb'],
            $ht['ast'], $ht['stl'], $ht['tov'], $ht['blk'], $ht['pf'],
        );

        return self::buildRecord($gameInfo, $slots);
    }

    /**
     * Write a right-justified, space-padded integer into a fixed-width field.
     */
    private static function writeInt(string $record, int $offset, int $width, int $value): string
    {
        if ($value < 0) {
            throw new \RuntimeException("Negative value {$value} not allowed at offset {$offset}");
        }
        $str = (string) $value;
        if (strlen($str) > $width) {
            throw new \RuntimeException("Value {$value} overflows field width {$width} at offset {$offset}");
        }
        return substr_replace($record, str_pad($str, $width, ' ', STR_PAD_LEFT), $offset, $width);
    }

    /**
     * Write a left-justified, space-padded string into a fixed-width field.
     */
    private static function writeName(string $record, int $offset, int $width, string $value): string
    {
        if (strlen($value) > $width) {
            throw new \RuntimeException("Name '{$value}' exceeds field width {$width} at offset {$offset}");
        }
        return substr_replace($record, str_pad($value, $width), $offset, $width);
    }
}
