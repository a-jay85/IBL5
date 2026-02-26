<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\TrnFileWriterInterface;

/**
 * Generates JSB .trn (Transactions) binary files from structured data.
 *
 * Produces a fixed 64,000-byte file with 128-byte records. Record 0's header
 * area (first 17 bytes) stores the record count. All numeric fields are
 * right-justified and space-padded, matching the format parsed by TrnFileParser.
 *
 * Builder methods promoted from TrnFileParserTest to production code.
 */
class TrnFileWriter implements TrnFileWriterInterface
{
    /**
     * @see TrnFileWriterInterface::generate()
     */
    public static function generate(array $records): string
    {
        $recordCount = count($records);

        // Start with 64,000 bytes of spaces
        $data = str_repeat(' ', TrnFileParser::FILE_SIZE);

        // Insert records at their sequential positions
        foreach ($records as $index => $record) {
            if (strlen($record) !== TrnFileParser::RECORD_SIZE) {
                throw new \RuntimeException(
                    'Record at index ' . $index . ' is ' . strlen($record)
                    . ' bytes, expected ' . TrnFileParser::RECORD_SIZE
                );
            }
            $offset = $index * TrnFileParser::RECORD_SIZE;
            $data = substr_replace($data, $record, $offset, TrnFileParser::RECORD_SIZE);
        }

        // Write record count in header area AFTER records (so it's not overwritten by record 0)
        $countStr = str_pad((string) $recordCount, TrnFileParser::HEADER_AREA_SIZE, ' ', STR_PAD_LEFT);
        $data = substr_replace($data, $countStr, 0, TrnFileParser::HEADER_AREA_SIZE);

        return $data;
    }

    /**
     * @see TrnFileWriterInterface::buildInjuryRecord()
     */
    public static function buildInjuryRecord(
        int $month,
        int $day,
        int $year,
        int $pid,
        int $teamId,
        int $gamesMissed,
        string $injuryDesc,
    ): string {
        $record = str_repeat(' ', TrnFileParser::RECORD_SIZE);

        // Common header at offsets 17-26
        $record = substr_replace($record, str_pad((string) $month, 2, ' ', STR_PAD_LEFT), 17, 2);
        $record = substr_replace($record, str_pad((string) $day, 2, ' ', STR_PAD_LEFT), 19, 2);
        $record = substr_replace($record, str_pad((string) $year, 4, ' ', STR_PAD_LEFT), 21, 4);
        $record = substr_replace($record, (string) TrnFileParser::TYPE_INJURY, 26, 1);

        // Injury-specific fields
        $record = substr_replace($record, str_pad((string) $pid, 4, ' ', STR_PAD_LEFT), 29, 4);
        $record = substr_replace($record, str_pad((string) $teamId, 2, ' ', STR_PAD_LEFT), 33, 2);
        $record = substr_replace($record, str_pad((string) $gamesMissed, 4, ' ', STR_PAD_LEFT), 35, 4);
        $record = substr_replace($record, str_pad($injuryDesc, 57), 39, 57);

        return $record;
    }

    /**
     * @see TrnFileWriterInterface::buildTradeRecord()
     */
    public static function buildTradeRecord(int $month, int $day, int $year, array $items): string
    {
        $record = str_repeat(' ', TrnFileParser::RECORD_SIZE);

        // Common header
        $record = substr_replace($record, str_pad((string) $month, 2, ' ', STR_PAD_LEFT), 17, 2);
        $record = substr_replace($record, str_pad((string) $day, 2, ' ', STR_PAD_LEFT), 19, 2);
        $record = substr_replace($record, str_pad((string) $year, 4, ' ', STR_PAD_LEFT), 21, 4);
        $record = substr_replace($record, (string) TrnFileParser::TYPE_TRADE, 26, 1);

        // Trade items starting at offset 27
        $tradeOffset = 27;
        foreach ($items as $item) {
            $marker = $item['marker'];
            $itemStr = (string) $marker;

            if ($marker === TrnFileParser::TRADE_MARKER_PLAYER) {
                // Player move: marker(1) + from_team(6) + to_team(6) + player_id(6)
                $itemStr .= str_pad((string) $item['from_team'], 6, ' ', STR_PAD_LEFT);
                $itemStr .= str_pad((string) $item['to_team'], 6, ' ', STR_PAD_LEFT);
                $itemStr .= str_pad((string) ($item['player_id'] ?? 0), 6, ' ', STR_PAD_LEFT);
            } elseif ($marker === TrnFileParser::TRADE_MARKER_DRAFT_PICK) {
                // Draft pick: marker(1) + draft_year(6) + from_team(6) + to_team(6)
                $itemStr .= str_pad((string) ($item['draft_year'] ?? 0), 6, ' ', STR_PAD_LEFT);
                $itemStr .= str_pad((string) $item['from_team'], 6, ' ', STR_PAD_LEFT);
                $itemStr .= str_pad((string) $item['to_team'], 6, ' ', STR_PAD_LEFT);
            }

            $record = substr_replace($record, $itemStr, $tradeOffset, TrnFileParser::TRADE_ITEM_SIZE);
            $tradeOffset += TrnFileParser::TRADE_ITEM_SIZE;
        }

        return $record;
    }

    /**
     * @see TrnFileWriterInterface::buildWaiverRecord()
     */
    public static function buildWaiverRecord(
        int $month,
        int $day,
        int $year,
        int $type,
        int $teamId,
        int $pid,
    ): string {
        $record = str_repeat(' ', TrnFileParser::RECORD_SIZE);

        // Common header
        $record = substr_replace($record, str_pad((string) $month, 2, ' ', STR_PAD_LEFT), 17, 2);
        $record = substr_replace($record, str_pad((string) $day, 2, ' ', STR_PAD_LEFT), 19, 2);
        $record = substr_replace($record, str_pad((string) $year, 4, ' ', STR_PAD_LEFT), 21, 4);
        $record = substr_replace($record, (string) $type, 26, 1);

        // Waiver fields: team_id at 27-28, pid at 31-34
        $record = substr_replace($record, str_pad((string) $teamId, 2, ' ', STR_PAD_LEFT), 27, 2);
        $record = substr_replace($record, str_pad((string) $pid, 4, ' ', STR_PAD_LEFT), 31, 4);

        return $record;
    }
}
