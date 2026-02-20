<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\TrnFileParserInterface;

/**
 * Parser for JSB .trn (Transactions) binary files.
 *
 * The .trn format uses fixed-width 128-byte records. Record 0 stores the used-count
 * in its header area. Transaction types: 1=injury, 2=trade, 3=waiver claim, 4=waiver release.
 *
 * Trade records (type 2) use a sub-record system with 19-byte items for player moves
 * and draft pick trades. A trade block begins with a subtype-blank separator record,
 * followed by item records.
 *
 * @see /docs/JSB_FILE_FORMATS.md
 */
class TrnFileParser implements TrnFileParserInterface
{
    public const FILE_SIZE = 64000;
    public const RECORD_SIZE = 128;
    public const MAX_RECORDS = 500;
    public const TRADE_ITEM_SIZE = 19;
    public const HEADER_AREA_SIZE = 17;

    // Transaction types
    public const TYPE_INJURY = 1;
    public const TYPE_TRADE = 2;
    public const TYPE_WAIVER_CLAIM = 3;
    public const TYPE_WAIVER_RELEASE = 4;

    // Trade item markers
    public const TRADE_MARKER_PLAYER = 0;
    public const TRADE_MARKER_DRAFT_PICK = 1;

    /**
     * @see TrnFileParserInterface::parseFile()
     */
    public static function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("TRN file not found: {$filePath}");
        }

        $data = file_get_contents($filePath);
        if ($data === false) {
            throw new \RuntimeException("Failed to read TRN file: {$filePath}");
        }

        $fileSize = strlen($data);
        if ($fileSize !== self::FILE_SIZE) {
            throw new \RuntimeException(
                'Invalid .trn file size: expected ' . self::FILE_SIZE . " bytes, got {$fileSize}"
            );
        }

        // Record 0 header: first 17 bytes contain record count (right-justified)
        $recordCountStr = trim(substr($data, 0, self::HEADER_AREA_SIZE));
        $recordCount = (int) $recordCountStr;

        $transactions = [];
        for ($i = 0; $i < self::MAX_RECORDS; $i++) {
            $recordData = substr($data, $i * self::RECORD_SIZE, self::RECORD_SIZE);
            $parsed = self::parseRecord($recordData, $i);
            if ($parsed !== null) {
                $transactions[] = $parsed;
            }
        }

        return [
            'record_count' => $recordCount,
            'transactions' => $transactions,
        ];
    }

    /**
     * @see TrnFileParserInterface::parseRecord()
     */
    public static function parseRecord(string $data, int $index): ?array
    {
        if (strlen($data) < self::RECORD_SIZE) {
            return null;
        }

        // Common fields: offsets 17-26
        $monthStr = trim(substr($data, 17, 2));
        $dayStr = trim(substr($data, 19, 2));
        $yearStr = trim(substr($data, 21, 4));
        $typeStr = trim(substr($data, 26, 1));

        // Skip empty records
        if ($monthStr === '' || $yearStr === '' || $typeStr === '') {
            return null;
        }

        $month = (int) $monthStr;
        $day = (int) $dayStr;
        $year = (int) $yearStr;
        $type = (int) $typeStr;

        if ($month === 0 || $year === 0 || $type === 0) {
            return null;
        }

        $result = [
            'index' => $index,
            'month' => $month,
            'day' => $day,
            'year' => $year,
            'type' => $type,
            'pid' => null,
            'team_id' => null,
            'games_missed' => null,
            'injury_description' => null,
            'trade_items' => null,
        ];

        // Type-specific parsing
        switch ($type) {
            case self::TYPE_INJURY:
                $result = self::parseInjuryData($data, $result);
                break;
            case self::TYPE_TRADE:
                $result = self::parseTradeData($data, $result);
                break;
            case self::TYPE_WAIVER_CLAIM:
            case self::TYPE_WAIVER_RELEASE:
                $result = self::parseWaiverData($data, $result);
                break;
        }

        return $result;
    }

    /**
     * Parse injury-specific fields from a type 1 record.
     *
     * Layout (offset from record start):
     * - 29-32: Player ID (4 chars, right-justified)
     * - 33-34: Team ID (2 chars, right-justified)
     * - 35-38: Games missed (4 chars, right-justified)
     * - 39-95: Injury description (right-justified text)
     *
     * @param string $data Full 128-byte record
     * @param array{index: int, month: int, day: int, year: int, type: int, pid: int|null, team_id: int|null, games_missed: int|null, injury_description: string|null, trade_items: list<array{marker: int, from_team: int, to_team: int, player_id: int|null, draft_year: int|null}>|null} $result
     * @return array{index: int, month: int, day: int, year: int, type: int, pid: int|null, team_id: int|null, games_missed: int|null, injury_description: string|null, trade_items: list<array{marker: int, from_team: int, to_team: int, player_id: int|null, draft_year: int|null}>|null}
     */
    private static function parseInjuryData(string $data, array $result): array
    {
        $pidStr = trim(substr($data, 29, 4));
        $teamIdStr = trim(substr($data, 33, 2));
        $gamesMissedStr = trim(substr($data, 35, 4));
        $injuryDesc = trim(substr($data, 39, 57));

        if ($pidStr !== '') {
            $result['pid'] = (int) $pidStr;
        }
        if ($teamIdStr !== '') {
            $result['team_id'] = (int) $teamIdStr;
        }
        if ($gamesMissedStr !== '') {
            $result['games_missed'] = (int) $gamesMissedStr;
        }
        if ($injuryDesc !== '') {
            $result['injury_description'] = $injuryDesc;
        }

        return $result;
    }

    /**
     * Parse trade-specific fields from a type 2 record.
     *
     * Trade records use 19-byte sub-items starting at offset 27.
     * A record may contain up to 5 items (5 * 19 = 95 bytes, fits in 101 remaining bytes).
     * Some records are separator/header records with no items.
     *
     * @param string $data Full 128-byte record
     * @param array{index: int, month: int, day: int, year: int, type: int, pid: int|null, team_id: int|null, games_missed: int|null, injury_description: string|null, trade_items: list<array{marker: int, from_team: int, to_team: int, player_id: int|null, draft_year: int|null}>|null} $result
     * @return array{index: int, month: int, day: int, year: int, type: int, pid: int|null, team_id: int|null, games_missed: int|null, injury_description: string|null, trade_items: list<array{marker: int, from_team: int, to_team: int, player_id: int|null, draft_year: int|null}>|null}
     */
    private static function parseTradeData(string $data, array $result): array
    {
        $items = [];
        $tradeAreaStart = 27;
        $maxItems = 5;

        for ($i = 0; $i < $maxItems; $i++) {
            $offset = $tradeAreaStart + $i * self::TRADE_ITEM_SIZE;
            if ($offset + self::TRADE_ITEM_SIZE > strlen($data)) {
                break;
            }

            $itemData = substr($data, $offset, self::TRADE_ITEM_SIZE);
            $item = self::parseTradeItem($itemData);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        $result['trade_items'] = $items;
        return $result;
    }

    /**
     * Parse a single 19-byte trade sub-item.
     *
     * Player move (marker 0): marker(1) + from_team(6) + to_team(6) + player_id(6)
     * Draft pick (marker 1): marker(1) + draft_year(6) + from_team(6) + to_team(6)
     *
     * @return array{marker: int, from_team: int, to_team: int, player_id: int|null, draft_year: int|null}|null
     */
    private static function parseTradeItem(string $data): ?array
    {
        if (strlen($data) < self::TRADE_ITEM_SIZE) {
            return null;
        }

        $markerStr = trim(substr($data, 0, 1));
        if ($markerStr === '') {
            return null;
        }

        $marker = (int) $markerStr;

        if ($marker === self::TRADE_MARKER_PLAYER) {
            // Player move: from_team(6) + to_team(6) + player_id(6)
            $fromTeam = (int) trim(substr($data, 1, 6));
            $toTeam = (int) trim(substr($data, 7, 6));
            $playerId = (int) trim(substr($data, 13, 6));

            if ($fromTeam === 0 && $toTeam === 0 && $playerId === 0) {
                return null;
            }

            return [
                'marker' => $marker,
                'from_team' => $fromTeam,
                'to_team' => $toTeam,
                'player_id' => $playerId,
                'draft_year' => null,
            ];
        }

        if ($marker === self::TRADE_MARKER_DRAFT_PICK) {
            // Draft pick: draft_year(6) + from_team(6) + to_team(6)
            $draftYear = (int) trim(substr($data, 1, 6));
            $fromTeam = (int) trim(substr($data, 7, 6));
            $toTeam = (int) trim(substr($data, 13, 6));

            if ($draftYear === 0 && $fromTeam === 0 && $toTeam === 0) {
                return null;
            }

            return [
                'marker' => $marker,
                'from_team' => $fromTeam,
                'to_team' => $toTeam,
                'player_id' => null,
                'draft_year' => $draftYear,
            ];
        }

        return null;
    }

    /**
     * Parse waiver-specific fields from type 3/4 records.
     *
     * Layout: offset 27-28 = team_id (2), 29-30 = padding, 31-34 = player_id (4)
     *
     * @param string $data Full 128-byte record
     * @param array{index: int, month: int, day: int, year: int, type: int, pid: int|null, team_id: int|null, games_missed: int|null, injury_description: string|null, trade_items: list<array{marker: int, from_team: int, to_team: int, player_id: int|null, draft_year: int|null}>|null} $result
     * @return array{index: int, month: int, day: int, year: int, type: int, pid: int|null, team_id: int|null, games_missed: int|null, injury_description: string|null, trade_items: list<array{marker: int, from_team: int, to_team: int, player_id: int|null, draft_year: int|null}>|null}
     */
    private static function parseWaiverData(string $data, array $result): array
    {
        $teamIdStr = trim(substr($data, 27, 2));
        $pidStr = trim(substr($data, 31, 4));

        if ($teamIdStr !== '') {
            $result['team_id'] = (int) $teamIdStr;
        }
        if ($pidStr !== '') {
            $result['pid'] = (int) $pidStr;
        }

        return $result;
    }
}
