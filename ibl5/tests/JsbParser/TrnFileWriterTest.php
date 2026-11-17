<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\TrnFileParser;
use JsbParser\TrnFileWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\TrnFileWriter
 */
class TrnFileWriterTest extends TestCase
{
    public function testGenerateProducesCorrectSize(): void
    {
        $data = TrnFileWriter::generate([]);
        $this->assertSame(TrnFileParser::FILE_SIZE, strlen($data));
    }

    public function testGenerateStoresRecordCount(): void
    {
        $record = TrnFileWriter::buildInjuryRecord(10, 15, 2006, 1234, 5, 12, 'Sprained ankle');
        $data = TrnFileWriter::generate([$record]);

        $countStr = trim(substr($data, 0, TrnFileParser::HEADER_AREA_SIZE));
        $this->assertSame(1, (int) $countStr);
    }

    public function testGenerateThrowsOnInvalidRecordSize(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('expected ' . TrnFileParser::RECORD_SIZE);

        TrnFileWriter::generate(['too short']);
    }

    public function testBuildInjuryRecordSize(): void
    {
        $record = TrnFileWriter::buildInjuryRecord(11, 3, 2006, 5678, 12, 8, 'Torn ACL');
        $this->assertSame(TrnFileParser::RECORD_SIZE, strlen($record));
    }

    public function testBuildTradeRecordSize(): void
    {
        $record = TrnFileWriter::buildTradeRecord(12, 1, 2006, [
            ['marker' => TrnFileParser::TRADE_MARKER_PLAYER, 'from_team' => 5, 'to_team' => 10, 'player_id' => 2345],
        ]);
        $this->assertSame(TrnFileParser::RECORD_SIZE, strlen($record));
    }

    public function testBuildWaiverRecordSize(): void
    {
        $record = TrnFileWriter::buildWaiverRecord(1, 20, 2007, TrnFileParser::TYPE_WAIVER_CLAIM, 8, 9999);
        $this->assertSame(TrnFileParser::RECORD_SIZE, strlen($record));
    }

    public function testInjuryRoundTrip(): void
    {
        $record = TrnFileWriter::buildInjuryRecord(11, 3, 2006, 5678, 12, 8, 'Torn ACL');
        $data = TrnFileWriter::generate([$record]);

        $tmpFile = $this->writeTmpFile($data);
        try {
            $result = TrnFileParser::parseFile($tmpFile);

            $injuries = array_values(array_filter(
                $result['transactions'],
                static fn (array $t): bool => $t['type'] === TrnFileParser::TYPE_INJURY
            ));

            $this->assertCount(1, $injuries);
            $this->assertSame(5678, $injuries[0]['pid']);
            $this->assertSame(12, $injuries[0]['team_id']);
            $this->assertSame(8, $injuries[0]['games_missed']);
            $this->assertSame('Torn ACL', $injuries[0]['injury_description']);
            $this->assertSame(11, $injuries[0]['month']);
            $this->assertSame(3, $injuries[0]['day']);
            $this->assertSame(2007, $injuries[0]['year']); // Writer stores 2006 (beginning year), parser returns 2006+1=2007 (ending year)
        } finally {
            unlink($tmpFile);
        }
    }

    public function testTradeWithPlayerMovesRoundTrip(): void
    {
        $record = TrnFileWriter::buildTradeRecord(12, 1, 2006, [
            ['marker' => TrnFileParser::TRADE_MARKER_PLAYER, 'from_team' => 5, 'to_team' => 10, 'player_id' => 2345],
            ['marker' => TrnFileParser::TRADE_MARKER_PLAYER, 'from_team' => 10, 'to_team' => 5, 'player_id' => 6789],
        ]);
        $data = TrnFileWriter::generate([$record]);

        $tmpFile = $this->writeTmpFile($data);
        try {
            $result = TrnFileParser::parseFile($tmpFile);

            $trades = array_values(array_filter(
                $result['transactions'],
                static fn (array $t): bool => $t['type'] === TrnFileParser::TYPE_TRADE
                    && is_array($t['trade_items'])
                    && $t['trade_items'] !== []
            ));

            $this->assertCount(1, $trades);
            $this->assertIsArray($trades[0]['trade_items']);
            $this->assertCount(2, $trades[0]['trade_items']);

            $this->assertSame(TrnFileParser::TRADE_MARKER_PLAYER, $trades[0]['trade_items'][0]['marker']);
            $this->assertSame(5, $trades[0]['trade_items'][0]['from_team']);
            $this->assertSame(10, $trades[0]['trade_items'][0]['to_team']);
            $this->assertSame(2345, $trades[0]['trade_items'][0]['player_id']);

            $this->assertSame(6789, $trades[0]['trade_items'][1]['player_id']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testTradeWithDraftPickRoundTrip(): void
    {
        $record = TrnFileWriter::buildTradeRecord(12, 1, 2006, [
            ['marker' => TrnFileParser::TRADE_MARKER_DRAFT_PICK, 'draft_year' => 2008, 'round' => 2, 'original_team' => 10],
        ]);
        // JSB's pick item: year, round, original team (sim09 record 80: `1  2009     1    20`).
        $this->assertSame('1  2008     2    10', substr($record, 27, TrnFileParser::TRADE_ITEM_SIZE));
        $data = TrnFileWriter::generate([$record]);

        $tmpFile = $this->writeTmpFile($data);
        try {
            $result = TrnFileParser::parseFile($tmpFile);

            $trades = array_values(array_filter(
                $result['transactions'],
                static fn (array $t): bool => $t['type'] === TrnFileParser::TYPE_TRADE
                    && is_array($t['trade_items'])
                    && $t['trade_items'] !== []
            ));

            $this->assertCount(1, $trades);
            $this->assertIsArray($trades[0]['trade_items']);
            $this->assertCount(1, $trades[0]['trade_items']);
            $this->assertSame(TrnFileParser::TRADE_MARKER_DRAFT_PICK, $trades[0]['trade_items'][0]['marker']);
            $this->assertSame(2008, $trades[0]['trade_items'][0]['draft_year']);
            // TrnFileParser still labels the round and original-team fields from_team / to_team.
            $this->assertSame(2, $trades[0]['trade_items'][0]['from_team']);
            $this->assertSame(10, $trades[0]['trade_items'][0]['to_team']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testAppendRecordsKeepsExistingBytesAndPlacesRecordsAtJsbOffsets(): void
    {
        $existing = str_pad(str_pad('2', 16) . str_pad(' 11 22008 3 7  2993', 128) . str_pad(' 11 32008 3 7  2994', 127) . 'Q', TrnFileParser::FILE_SIZE);
        $record = TrnFileWriter::buildWaiverRecord(1, 5, 2008, TrnFileParser::TYPE_WAIVER_CLAIM, 9, 1234);

        $output = TrnFileWriter::appendRecords($existing, [$record]);

        $this->assertSame(TrnFileParser::FILE_SIZE, strlen($output));
        $this->assertSame(str_pad('3', 16), substr($output, 0, 16));
        $this->assertSame(substr($existing, 16, 256), substr($output, 16, 256));
        $this->assertSame(substr($record, 16) . str_repeat(' ', 16), substr($output, 16 + 256, 128));
        $this->assertSame(substr($existing, 16 + 384), substr($output, 16 + 384));
    }

    public function testAppendRecordsRejectsAFullFile(): void
    {
        $existing = str_pad('499', TrnFileParser::FILE_SIZE);
        $record = TrnFileWriter::buildWaiverRecord(1, 5, 2008, TrnFileParser::TYPE_WAIVER_CLAIM, 9, 1234);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/^The \.trn file is full at 499 records$/');
        TrnFileWriter::appendRecords($existing, [$record]);
    }

    public function testAppendRecordsAcceptsTheLastSlot(): void
    {
        $existing = str_pad('498', TrnFileParser::FILE_SIZE);
        $record = TrnFileWriter::buildWaiverRecord(1, 5, 2008, TrnFileParser::TYPE_WAIVER_CLAIM, 9, 1234);

        $output = TrnFileWriter::appendRecords($existing, [$record]);

        $this->assertSame(str_pad('499', 16), substr($output, 0, 16));
        $this->assertSame(substr($record, 16) . str_repeat(' ', 16), substr($output, 16 + 498 * 128, 128));
    }

    public function testReadJsbRecordCountRejectsJunkHeader(): void
    {
        $this->assertSame(0, TrnFileWriter::readJsbRecordCount(str_repeat(' ', 16)));
        $this->assertSame(77, TrnFileWriter::readJsbRecordCount(str_pad('77', 16)));
        $this->expectException(\RuntimeException::class);
        TrnFileWriter::readJsbRecordCount(str_repeat('Z', 16));
    }

    #[DataProvider('partlyNumericHeaderProvider')]
    public function testReadJsbRecordCountRejectsAHeaderThatIsOnlyPartlyDigits(string $header): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/^The \.trn header does not hold a record count$/');
        TrnFileWriter::readJsbRecordCount(str_pad($header, 16));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function partlyNumericHeaderProvider(): array
    {
        return [
            'digits then junk' => ['12x'],
            'junk then digits' => ['x12'],
        ];
    }

    public function testTradeItemWithoutTeamOrPickFieldsWritesZeros(): void
    {
        $record = TrnFileWriter::buildTradeRecord(1, 5, 2008, [
            ['marker' => TrnFileParser::TRADE_MARKER_PLAYER, 'player_id' => 77],
            ['marker' => TrnFileParser::TRADE_MARKER_DRAFT_PICK, 'draft_year' => 2009],
        ]);

        $this->assertSame('0     0     0    77', substr($record, 27, TrnFileParser::TRADE_ITEM_SIZE));
        $this->assertSame('1  2009     0     0', substr($record, 27 + TrnFileParser::TRADE_ITEM_SIZE, TrnFileParser::TRADE_ITEM_SIZE));
    }

    public function testWaiverClaimRoundTrip(): void
    {
        $record = TrnFileWriter::buildWaiverRecord(1, 20, 2007, TrnFileParser::TYPE_WAIVER_CLAIM, 8, 9999);
        $data = TrnFileWriter::generate([$record]);

        $tmpFile = $this->writeTmpFile($data);
        try {
            $result = TrnFileParser::parseFile($tmpFile);

            $waivers = array_values(array_filter(
                $result['transactions'],
                static fn (array $t): bool => $t['type'] === TrnFileParser::TYPE_WAIVER_CLAIM
            ));

            $this->assertCount(1, $waivers);
            $this->assertSame(8, $waivers[0]['team_id']);
            $this->assertSame(9999, $waivers[0]['pid']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testWaiverReleaseRoundTrip(): void
    {
        $record = TrnFileWriter::buildWaiverRecord(2, 14, 2007, TrnFileParser::TYPE_WAIVER_RELEASE, 3, 7777);
        $data = TrnFileWriter::generate([$record]);

        $tmpFile = $this->writeTmpFile($data);
        try {
            $result = TrnFileParser::parseFile($tmpFile);

            $waivers = array_values(array_filter(
                $result['transactions'],
                static fn (array $t): bool => $t['type'] === TrnFileParser::TYPE_WAIVER_RELEASE
            ));

            $this->assertCount(1, $waivers);
            $this->assertSame(3, $waivers[0]['team_id']);
            $this->assertSame(7777, $waivers[0]['pid']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testMultipleRecordTypes(): void
    {
        $injury = TrnFileWriter::buildInjuryRecord(10, 15, 2006, 1234, 5, 12, 'Sprained ankle');
        $trade = TrnFileWriter::buildTradeRecord(11, 1, 2006, [
            ['marker' => TrnFileParser::TRADE_MARKER_PLAYER, 'from_team' => 5, 'to_team' => 10, 'player_id' => 2345],
        ]);
        $waiver = TrnFileWriter::buildWaiverRecord(12, 5, 2006, TrnFileParser::TYPE_WAIVER_RELEASE, 3, 7777);

        $data = TrnFileWriter::generate([$injury, $trade, $waiver]);

        $tmpFile = $this->writeTmpFile($data);
        try {
            $result = TrnFileParser::parseFile($tmpFile);

            $this->assertSame(3, $result['record_count']);
            $this->assertGreaterThanOrEqual(3, count($result['transactions']));
        } finally {
            unlink($tmpFile);
        }
    }

    public function testPlayoffMonthRoundTrip(): void
    {
        // Write with IBL playoff month (6), which Writer converts to JSB month (10) in binary
        $record = TrnFileWriter::buildInjuryRecord(6, 15, 2006, 1234, 5, 3, 'Playoff injury');
        $data = TrnFileWriter::generate([$record]);

        $tmpFile = $this->writeTmpFile($data);
        try {
            $result = TrnFileParser::parseFile($tmpFile);

            $injuries = array_values(array_filter(
                $result['transactions'],
                static fn (array $t): bool => $t['type'] === TrnFileParser::TYPE_INJURY
            ));

            $this->assertCount(1, $injuries);
            // Writer converts 6→10, Parser converts 10→6: round-trip preserves the value
            $this->assertSame(\Season\Season::IBL_PLAYOFF_MONTH, $injuries[0]['month']);
            $this->assertSame(15, $injuries[0]['day']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testEmptyFileGeneration(): void
    {
        $data = TrnFileWriter::generate([]);
        $this->assertSame(TrnFileParser::FILE_SIZE, strlen($data));

        $tmpFile = $this->writeTmpFile($data);
        try {
            $result = TrnFileParser::parseFile($tmpFile);
            $this->assertSame(0, $result['record_count']);
            $this->assertSame([], $result['transactions']);
        } finally {
            unlink($tmpFile);
        }
    }

    private function writeTmpFile(string $data): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'trn_writer_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $data);
        return $tmpFile;
    }
}
