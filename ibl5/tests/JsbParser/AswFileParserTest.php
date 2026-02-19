<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\AswFileParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\AswFileParser
 */
class AswFileParserTest extends TestCase
{
    private string $aswFilePath;

    protected function setUp(): void
    {
        $this->aswFilePath = dirname(__DIR__, 2) . '/IBL5.asw';
    }

    public function testParseFileReturnsRosters(): void
    {
        if (!file_exists($this->aswFilePath)) {
            $this->markTestSkipped('.asw file not available');
        }

        $result = AswFileParser::parseFile($this->aswFilePath);

        $this->assertArrayHasKey('rosters', $result);

        $expectedKeys = ['allstar_1', 'allstar_2', 'rookie_1', 'rookie_2', 'three_point', 'dunk_contest'];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result['rosters'], "Missing roster key: {$key}");
        }
    }

    public function testParseFileReturnsScores(): void
    {
        if (!file_exists($this->aswFilePath)) {
            $this->markTestSkipped('.asw file not available');
        }

        $result = AswFileParser::parseFile($this->aswFilePath);

        $this->assertArrayHasKey('scores', $result);

        $expectedKeys = ['dunk_round1', 'dunk_finals', 'three_pt_round1', 'three_pt_semis', 'three_pt_finals'];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result['scores'], "Missing score key: {$key}");
        }
    }

    public function testAllStarRostersHavePlayers(): void
    {
        if (!file_exists($this->aswFilePath)) {
            $this->markTestSkipped('.asw file not available');
        }

        $result = AswFileParser::parseFile($this->aswFilePath);

        // All-Star team 1 should have players
        $this->assertNotEmpty($result['rosters']['allstar_1'], 'All-Star Team 1 should have players');
        $this->assertNotEmpty($result['rosters']['allstar_2'], 'All-Star Team 2 should have players');
    }

    public function testPlayerIdsArePositiveIntegers(): void
    {
        if (!file_exists($this->aswFilePath)) {
            $this->markTestSkipped('.asw file not available');
        }

        $result = AswFileParser::parseFile($this->aswFilePath);

        foreach ($result['rosters'] as $eventType => $playerIds) {
            foreach ($playerIds as $pid) {
                $this->assertIsInt($pid);
                $this->assertGreaterThan(0, $pid, "Player ID in {$eventType} should be positive");
            }
        }
    }

    public function testDunkScoresAreOnHundredPointScale(): void
    {
        if (!file_exists($this->aswFilePath)) {
            $this->markTestSkipped('.asw file not available');
        }

        $result = AswFileParser::parseFile($this->aswFilePath);

        // Dunk scores are stored as score * 10 (e.g., 932 = 93.2)
        foreach ($result['scores']['dunk_round1'] as $score) {
            $this->assertIsInt($score);
            $this->assertGreaterThanOrEqual(500, $score, 'Dunk score should be >= 50.0 (500)');
            $this->assertLessThanOrEqual(1000, $score, 'Dunk score should be <= 100.0 (1000)');
        }

        foreach ($result['scores']['dunk_finals'] as $score) {
            $this->assertIsInt($score);
            $this->assertGreaterThanOrEqual(500, $score);
            $this->assertLessThanOrEqual(1000, $score);
        }
    }

    public function testThreePointScoresAreRawCounts(): void
    {
        if (!file_exists($this->aswFilePath)) {
            $this->markTestSkipped('.asw file not available');
        }

        $result = AswFileParser::parseFile($this->aswFilePath);

        // 3-pt scores are raw count of makes (typically 16-25 out of 25 attempts)
        foreach ($result['scores']['three_pt_round1'] as $score) {
            $this->assertIsInt($score);
            $this->assertGreaterThanOrEqual(0, $score);
            $this->assertLessThanOrEqual(30, $score, '3-pt score should be <= 30');
        }
    }

    public function testContestParticipantCounts(): void
    {
        if (!file_exists($this->aswFilePath)) {
            $this->markTestSkipped('.asw file not available');
        }

        $result = AswFileParser::parseFile($this->aswFilePath);

        // Up to 8 participants per contest
        $this->assertLessThanOrEqual(10, count($result['rosters']['three_point']));
        $this->assertLessThanOrEqual(10, count($result['rosters']['dunk_contest']));

        // Round 1 should have up to 8 scores
        $this->assertLessThanOrEqual(8, count($result['scores']['dunk_round1']));
        $this->assertLessThanOrEqual(8, count($result['scores']['three_pt_round1']));
    }

    public function testParseFileThrowsForMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ASW file not found');

        AswFileParser::parseFile('/nonexistent/file.asw');
    }
}
