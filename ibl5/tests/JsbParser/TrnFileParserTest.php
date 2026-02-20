<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\TrnFileParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\TrnFileParser
 */
class TrnFileParserTest extends TestCase
{
    /**
     * Build a synthetic 64,000-byte .trn file with known records.
     *
     * @param int $recordCount Record count to store in header
     * @param list<string> $records Raw 128-byte records (injected starting at record 0)
     */
    private function buildTrnFile(int $recordCount, array $records = []): string
    {
        // Start with 64,000 bytes of spaces
        $data = str_repeat(' ', TrnFileParser::FILE_SIZE);

        // Insert records at their positions first
        foreach ($records as $index => $record) {
            $offset = $index * TrnFileParser::RECORD_SIZE;
            $data = substr_replace($data, $record, $offset, TrnFileParser::RECORD_SIZE);
        }

        // Write record count in header area AFTER records (so it's not overwritten by record 0)
        $countStr = str_pad((string) $recordCount, TrnFileParser::HEADER_AREA_SIZE, ' ', STR_PAD_LEFT);
        $data = substr_replace($data, $countStr, 0, TrnFileParser::HEADER_AREA_SIZE);

        return $data;
    }

    /**
     * Build an injury record (type 1) at a specific index position.
     *
     * @param int $month Transaction month
     * @param int $day Transaction day
     * @param int $year Transaction year
     * @param int $pid Player ID
     * @param int $teamId Team ID
     * @param int $gamesMissed Games missed
     * @param string $injuryDesc Injury description
     */
    private function buildInjuryRecord(
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
        // offset 25 is a spacer
        $record = substr_replace($record, (string) TrnFileParser::TYPE_INJURY, 26, 1);

        // Injury-specific fields
        $record = substr_replace($record, str_pad((string) $pid, 4, ' ', STR_PAD_LEFT), 29, 4);
        $record = substr_replace($record, str_pad((string) $teamId, 2, ' ', STR_PAD_LEFT), 33, 2);
        $record = substr_replace($record, str_pad((string) $gamesMissed, 4, ' ', STR_PAD_LEFT), 35, 4);
        $record = substr_replace($record, str_pad($injuryDesc, 57), 39, 57);

        return $record;
    }

    /**
     * Build a trade record (type 2) with player move items.
     *
     * @param int $month Transaction month
     * @param int $day Transaction day
     * @param int $year Transaction year
     * @param list<array{from_team: int, to_team: int, player_id: int}> $items Player trade items
     */
    private function buildTradeRecord(int $month, int $day, int $year, array $items): string
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
            // marker(1) + from_team(6) + to_team(6) + player_id(6) = 19 bytes
            $itemStr = '0'; // marker = player
            $itemStr .= str_pad((string) $item['from_team'], 6, ' ', STR_PAD_LEFT);
            $itemStr .= str_pad((string) $item['to_team'], 6, ' ', STR_PAD_LEFT);
            $itemStr .= str_pad((string) $item['player_id'], 6, ' ', STR_PAD_LEFT);
            $record = substr_replace($record, $itemStr, $tradeOffset, TrnFileParser::TRADE_ITEM_SIZE);
            $tradeOffset += TrnFileParser::TRADE_ITEM_SIZE;
        }

        return $record;
    }

    /**
     * Build a waiver record (type 3 or 4).
     */
    private function buildWaiverRecord(int $month, int $day, int $year, int $type, int $teamId, int $pid): string
    {
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

    /**
     * Write synthetic data to a temp file and return the path.
     */
    private function writeTmpTrnFile(string $data): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'trn_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $data);
        return $tmpFile;
    }

    public function testParseFileReturnsRecordCount(): void
    {
        $injuryRecord = $this->buildInjuryRecord(10, 15, 2006, 1234, 5, 12, 'Sprained ankle');
        $trnData = $this->buildTrnFile(1, [$injuryRecord]);
        $tmpFile = $this->writeTmpTrnFile($trnData);

        try {
            $result = TrnFileParser::parseFile($tmpFile);

            $this->assertArrayHasKey('record_count', $result);
            $this->assertIsInt($result['record_count']);
            $this->assertSame(1, $result['record_count']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testParseFileReturnsTransactions(): void
    {
        $injuryRecord = $this->buildInjuryRecord(10, 15, 2006, 1234, 5, 12, 'Sprained ankle');
        $trnData = $this->buildTrnFile(1, [$injuryRecord]);
        $tmpFile = $this->writeTmpTrnFile($trnData);

        try {
            $result = TrnFileParser::parseFile($tmpFile);

            $this->assertArrayHasKey('transactions', $result);
            $this->assertNotEmpty($result['transactions']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testTransactionHasExpectedStructure(): void
    {
        $injuryRecord = $this->buildInjuryRecord(10, 15, 2006, 1234, 5, 12, 'Sprained ankle');
        $trnData = $this->buildTrnFile(1, [$injuryRecord]);
        $tmpFile = $this->writeTmpTrnFile($trnData);

        try {
            $result = TrnFileParser::parseFile($tmpFile);
            $transaction = $result['transactions'][0];

            $this->assertArrayHasKey('index', $transaction);
            $this->assertArrayHasKey('month', $transaction);
            $this->assertArrayHasKey('day', $transaction);
            $this->assertArrayHasKey('year', $transaction);
            $this->assertArrayHasKey('type', $transaction);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testParsesInjuryRecords(): void
    {
        $injuryRecord = $this->buildInjuryRecord(11, 3, 2006, 5678, 12, 8, 'Torn ACL');
        $trnData = $this->buildTrnFile(1, [$injuryRecord]);
        $tmpFile = $this->writeTmpTrnFile($trnData);

        try {
            $result = TrnFileParser::parseFile($tmpFile);

            $injuries = array_filter(
                $result['transactions'],
                static fn (array $t): bool => $t['type'] === TrnFileParser::TYPE_INJURY
            );

            $this->assertNotEmpty($injuries, 'Should find at least one injury record');

            $injury = reset($injuries);
            $this->assertIsArray($injury);
            $this->assertSame(5678, $injury['pid']);
            $this->assertSame(12, $injury['team_id']);
            $this->assertSame(8, $injury['games_missed']);
            $this->assertSame('Torn ACL', $injury['injury_description']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testParsesTradeRecords(): void
    {
        $tradeRecord = $this->buildTradeRecord(12, 1, 2006, [
            ['from_team' => 5, 'to_team' => 10, 'player_id' => 2345],
            ['from_team' => 10, 'to_team' => 5, 'player_id' => 6789],
        ]);
        $trnData = $this->buildTrnFile(1, [$tradeRecord]);
        $tmpFile = $this->writeTmpTrnFile($trnData);

        try {
            $result = TrnFileParser::parseFile($tmpFile);

            $trades = array_filter(
                $result['transactions'],
                static fn (array $t): bool => $t['type'] === TrnFileParser::TYPE_TRADE
                    && is_array($t['trade_items'])
                    && $t['trade_items'] !== []
            );

            $this->assertNotEmpty($trades, 'Should find at least one trade record with items');

            $trade = reset($trades);
            $this->assertIsArray($trade);
            $this->assertIsArray($trade['trade_items']);
            $this->assertCount(2, $trade['trade_items']);

            $item = $trade['trade_items'][0];
            $this->assertArrayHasKey('marker', $item);
            $this->assertArrayHasKey('from_team', $item);
            $this->assertArrayHasKey('to_team', $item);
            $this->assertSame(0, $item['marker']);
            $this->assertSame(5, $item['from_team']);
            $this->assertSame(10, $item['to_team']);
            $this->assertSame(2345, $item['player_id']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testParsesWaiverRecords(): void
    {
        $waiverRecord = $this->buildWaiverRecord(1, 20, 2007, TrnFileParser::TYPE_WAIVER_CLAIM, 8, 9999);
        $trnData = $this->buildTrnFile(1, [$waiverRecord]);
        $tmpFile = $this->writeTmpTrnFile($trnData);

        try {
            $result = TrnFileParser::parseFile($tmpFile);

            $waivers = array_filter(
                $result['transactions'],
                static fn (array $t): bool => $t['type'] === TrnFileParser::TYPE_WAIVER_CLAIM
                    || $t['type'] === TrnFileParser::TYPE_WAIVER_RELEASE
            );

            $this->assertNotEmpty($waivers, 'Should find at least one waiver record');

            $waiver = reset($waivers);
            $this->assertIsArray($waiver);
            $this->assertSame(8, $waiver['team_id']);
            $this->assertSame(9999, $waiver['pid']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testParseRecordReturnsNullForEmptyRecord(): void
    {
        $emptyRecord = str_repeat(' ', TrnFileParser::RECORD_SIZE);
        $result = TrnFileParser::parseRecord($emptyRecord, 0);

        $this->assertNull($result);
    }

    public function testParseFileThrowsForMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('TRN file not found');

        TrnFileParser::parseFile('/nonexistent/file.trn');
    }

    public function testParseFileThrowsForWrongSize(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'trn_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, str_repeat(' ', 100));

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Invalid .trn file size');
            TrnFileParser::parseFile($tmpFile);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testTransactionTypesAreValid(): void
    {
        $injury = $this->buildInjuryRecord(10, 15, 2006, 1234, 5, 12, 'Sprained ankle');
        $trade = $this->buildTradeRecord(11, 1, 2006, [
            ['from_team' => 5, 'to_team' => 10, 'player_id' => 2345],
        ]);
        $waiver = $this->buildWaiverRecord(12, 5, 2006, TrnFileParser::TYPE_WAIVER_RELEASE, 3, 7777);

        $trnData = $this->buildTrnFile(3, [$injury, $trade, $waiver]);
        $tmpFile = $this->writeTmpTrnFile($trnData);

        try {
            $result = TrnFileParser::parseFile($tmpFile);
            $validTypes = [
                TrnFileParser::TYPE_INJURY,
                TrnFileParser::TYPE_TRADE,
                TrnFileParser::TYPE_WAIVER_CLAIM,
                TrnFileParser::TYPE_WAIVER_RELEASE,
            ];

            foreach ($result['transactions'] as $transaction) {
                $this->assertContains(
                    $transaction['type'],
                    $validTypes,
                    'Transaction type ' . $transaction['type'] . ' is not valid'
                );
            }
        } finally {
            unlink($tmpFile);
        }
    }

    public function testInjuryGamesMissedAreReasonable(): void
    {
        $injury1 = $this->buildInjuryRecord(10, 1, 2006, 1000, 5, 5, 'Minor bruise');
        $injury2 = $this->buildInjuryRecord(11, 15, 2006, 2000, 8, 82, 'Season ending');

        $trnData = $this->buildTrnFile(2, [$injury1, $injury2]);
        $tmpFile = $this->writeTmpTrnFile($trnData);

        try {
            $result = TrnFileParser::parseFile($tmpFile);

            $injuries = array_filter(
                $result['transactions'],
                static fn (array $t): bool => $t['type'] === TrnFileParser::TYPE_INJURY
            );

            foreach ($injuries as $injury) {
                if ($injury['games_missed'] !== null) {
                    $this->assertGreaterThan(0, $injury['games_missed']);
                    $this->assertLessThan(300, $injury['games_missed']);
                }
            }
        } finally {
            unlink($tmpFile);
        }
    }
}
