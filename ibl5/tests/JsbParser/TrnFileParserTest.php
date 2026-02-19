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
    private string $trnFilePath;

    protected function setUp(): void
    {
        $this->trnFilePath = dirname(__DIR__, 2) . '/IBL5.trn';
    }

    public function testParseFileReturnsRecordCount(): void
    {
        if (!file_exists($this->trnFilePath)) {
            $this->markTestSkipped('.trn file not available');
        }

        $result = TrnFileParser::parseFile($this->trnFilePath);

        $this->assertArrayHasKey('record_count', $result);
        $this->assertIsInt($result['record_count']);
        $this->assertGreaterThan(0, $result['record_count']);
    }

    public function testParseFileReturnsTransactions(): void
    {
        if (!file_exists($this->trnFilePath)) {
            $this->markTestSkipped('.trn file not available');
        }

        $result = TrnFileParser::parseFile($this->trnFilePath);

        $this->assertArrayHasKey('transactions', $result);
        $this->assertNotEmpty($result['transactions']);
    }

    public function testTransactionHasExpectedStructure(): void
    {
        if (!file_exists($this->trnFilePath)) {
            $this->markTestSkipped('.trn file not available');
        }

        $result = TrnFileParser::parseFile($this->trnFilePath);
        $transaction = $result['transactions'][0];

        $this->assertArrayHasKey('index', $transaction);
        $this->assertArrayHasKey('month', $transaction);
        $this->assertArrayHasKey('day', $transaction);
        $this->assertArrayHasKey('year', $transaction);
        $this->assertArrayHasKey('type', $transaction);
    }

    public function testParsesInjuryRecords(): void
    {
        if (!file_exists($this->trnFilePath)) {
            $this->markTestSkipped('.trn file not available');
        }

        $result = TrnFileParser::parseFile($this->trnFilePath);

        // Find first injury record
        $injuries = array_filter(
            $result['transactions'],
            static fn (array $t): bool => $t['type'] === TrnFileParser::TYPE_INJURY
        );

        $this->assertNotEmpty($injuries, 'Should find at least one injury record');

        $injury = reset($injuries);
        $this->assertIsArray($injury);
        $this->assertIsInt($injury['pid']);
        $this->assertIsInt($injury['team_id']);
        $this->assertIsInt($injury['games_missed']);
        $this->assertIsString($injury['injury_description']);
        $this->assertNotSame('', $injury['injury_description']);
    }

    public function testParsesTradeRecords(): void
    {
        if (!file_exists($this->trnFilePath)) {
            $this->markTestSkipped('.trn file not available');
        }

        $result = TrnFileParser::parseFile($this->trnFilePath);

        // Find trade records with items
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

        $item = $trade['trade_items'][0];
        $this->assertArrayHasKey('marker', $item);
        $this->assertArrayHasKey('from_team', $item);
        $this->assertArrayHasKey('to_team', $item);
    }

    public function testParsesWaiverRecords(): void
    {
        if (!file_exists($this->trnFilePath)) {
            $this->markTestSkipped('.trn file not available');
        }

        $result = TrnFileParser::parseFile($this->trnFilePath);

        $waivers = array_filter(
            $result['transactions'],
            static fn (array $t): bool => $t['type'] === TrnFileParser::TYPE_WAIVER_CLAIM
                || $t['type'] === TrnFileParser::TYPE_WAIVER_RELEASE
        );

        // Waivers may not exist in all files, but structure should be valid if present
        if (count($waivers) > 0) {
            $waiver = reset($waivers);
            $this->assertIsArray($waiver);
            $this->assertIsInt($waiver['team_id']);
            $this->assertIsInt($waiver['pid']);
        } else {
            // Just verify parsing didn't error
            $this->assertIsArray($result['transactions']);
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
        if (!file_exists($this->trnFilePath)) {
            $this->markTestSkipped('.trn file not available');
        }

        $result = TrnFileParser::parseFile($this->trnFilePath);
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
    }

    public function testInjuryGamesMissedAreReasonable(): void
    {
        if (!file_exists($this->trnFilePath)) {
            $this->markTestSkipped('.trn file not available');
        }

        $result = TrnFileParser::parseFile($this->trnFilePath);

        $injuries = array_filter(
            $result['transactions'],
            static fn (array $t): bool => $t['type'] === TrnFileParser::TYPE_INJURY
        );

        foreach ($injuries as $injury) {
            if ($injury['games_missed'] !== null) {
                $this->assertGreaterThan(0, $injury['games_missed']);
                // Max would be a season-ending injury (~240 games for an 82-game season)
                $this->assertLessThan(300, $injury['games_missed']);
            }
        }
    }
}
