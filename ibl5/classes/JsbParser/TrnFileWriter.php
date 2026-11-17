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
     * @see TrnFileWriterInterface::appendRecords()
     */
    public static function appendRecords(string $existing, array $records): string
    {
        if (strlen($existing) !== TrnFileParser::FILE_SIZE) {
            throw new \RuntimeException(
                'Invalid .trn file size: expected ' . TrnFileParser::FILE_SIZE . ' bytes, got ' . strlen($existing)
            );
        }

        $count = self::readJsbRecordCount($existing);
        $headerSize = TrnFileParser::JSB_HEADER_SIZE;
        $data = $existing;

        foreach ($records as $index => $record) {
            if (strlen($record) !== TrnFileParser::RECORD_SIZE) {
                throw new \RuntimeException(
                    'Record at index ' . $index . ' is ' . strlen($record)
                    . ' bytes, expected ' . TrnFileParser::RECORD_SIZE
                );
            }
            $offset = $headerSize + $count * TrnFileParser::RECORD_SIZE;
            /** @infection-ignore-all Equivalent: 64,000 - 16 is not a multiple of 128, so offset + 128 never equals the file size and > and >= reject the same counts. */
            if ($offset + TrnFileParser::RECORD_SIZE > TrnFileParser::FILE_SIZE) {
                throw new \RuntimeException('The .trn file is full at ' . $count . ' records');
            }
            // Builder records put the JSB record body at offset 16. JSB stores that body
            // at 16 + n * 128 and pads its last 16 bytes with spaces.
            $jsbRecord = substr($record, $headerSize) . str_repeat(' ', $headerSize);
            $data = substr_replace($data, $jsbRecord, $offset, TrnFileParser::RECORD_SIZE);
            $count++;
        }

        // JSB writes the count left-justified in the 16-byte header.
        return substr_replace($data, str_pad((string) $count, $headerSize), 0, $headerSize);
    }

    /**
     * @see TrnFileWriterInterface::readJsbRecordCount()
     */
    public static function readJsbRecordCount(string $data): int
    {
        $header = trim(substr($data, 0, TrnFileParser::JSB_HEADER_SIZE));
        if ($header === '') {
            return 0;
        }
        if (preg_match('/^\d+$/', $header) !== 1) {
            throw new \RuntimeException('The .trn header does not hold a record count');
        }
        return (int) $header;
    }

    /**
     * Convert IBL calendar month to JSB binary format month.
     * Reverses the parse-time remap of JSB_PLAYOFF_MONTH → IBL_PLAYOFF_MONTH.
     */
    private static function toJsbMonth(int $month): int
    {
        if ($month === \Season\Season::IBL_PLAYOFF_MONTH) {
            return TrnFileParser::JSB_PLAYOFF_MONTH;
        }
        return $month;
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
        $record = substr_replace($record, str_pad((string) self::toJsbMonth($month), 2, ' ', STR_PAD_LEFT), 17, 2);
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
        $record = substr_replace($record, str_pad((string) self::toJsbMonth($month), 2, ' ', STR_PAD_LEFT), 17, 2);
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
                $itemStr .= str_pad((string) ($item['from_team'] ?? 0), 6, ' ', STR_PAD_LEFT);
                $itemStr .= str_pad((string) ($item['to_team'] ?? 0), 6, ' ', STR_PAD_LEFT);
                $itemStr .= str_pad((string) ($item['player_id'] ?? 0), 6, ' ', STR_PAD_LEFT);
            } elseif ($marker === TrnFileParser::TRADE_MARKER_DRAFT_PICK) {
                // Draft pick: marker(1) + draft_year(6) + round(6) + original_team(6).
                // JSB writes no from/to here. The pick travels with the record's giving team.
                $itemStr .= str_pad((string) ($item['draft_year'] ?? 0), 6, ' ', STR_PAD_LEFT);
                $itemStr .= str_pad((string) ($item['round'] ?? 0), 6, ' ', STR_PAD_LEFT);
                $itemStr .= str_pad((string) ($item['original_team'] ?? 0), 6, ' ', STR_PAD_LEFT);
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
        $record = substr_replace($record, str_pad((string) self::toJsbMonth($month), 2, ' ', STR_PAD_LEFT), 17, 2);
        $record = substr_replace($record, str_pad((string) $day, 2, ' ', STR_PAD_LEFT), 19, 2);
        $record = substr_replace($record, str_pad((string) $year, 4, ' ', STR_PAD_LEFT), 21, 4);
        $record = substr_replace($record, (string) $type, 26, 1);

        // Waiver fields: team_id at 27-28, pid at 31-34
        $record = substr_replace($record, str_pad((string) $teamId, 2, ' ', STR_PAD_LEFT), 27, 2);
        $record = substr_replace($record, str_pad((string) $pid, 4, ' ', STR_PAD_LEFT), 31, 4);

        return $record;
    }
}
