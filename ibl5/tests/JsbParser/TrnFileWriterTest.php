<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\TrnFileParser;
use JsbParser\TrnFileWriter;
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
        $this->expectExceptionMessage('expected ' . TrnFileParser::RECORD_SIZE);

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
            $this->assertSame(2006, $injuries[0]['year']);
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
            ['marker' => TrnFileParser::TRADE_MARKER_DRAFT_PICK, 'from_team' => 5, 'to_team' => 10, 'draft_year' => 2008],
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
            $this->assertCount(1, $trades[0]['trade_items']);
            $this->assertSame(TrnFileParser::TRADE_MARKER_DRAFT_PICK, $trades[0]['trade_items'][0]['marker']);
            $this->assertSame(2008, $trades[0]['trade_items'][0]['draft_year']);
            $this->assertSame(5, $trades[0]['trade_items'][0]['from_team']);
            $this->assertSame(10, $trades[0]['trade_items'][0]['to_team']);
        } finally {
            unlink($tmpFile);
        }
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
