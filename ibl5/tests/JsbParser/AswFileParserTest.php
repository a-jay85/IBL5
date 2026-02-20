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
    /**
     * Build a synthetic .asw file with known roster and score data.
     *
     * The .asw format has 112 data lines. Sections:
     * - Lines 0-15: All-Star Team 1 roster (PIDs)
     * - Lines 16-30: All-Star Team 2 roster
     * - Lines 31-45: Rookie Team 1 roster
     * - Lines 46-60: Rookie Team 2 roster
     * - Lines 61-70: 3-Point Shootout participants
     * - Lines 71-80: Dunk Contest participants
     * - Lines 81-96: Dunk scores (81=header, 82-89=round1, 90-92=finals)
     * - Lines 97-111: 3-pt scores (97-104=round1, 105-108=semis, 109-110=finals)
     *
     * @param list<int> $allstar1 PIDs for All-Star Team 1 (up to 16)
     * @param list<int> $allstar2 PIDs for All-Star Team 2 (up to 15)
     * @param list<int> $threePointPids 3-pt contest participant PIDs
     * @param list<int> $dunkPids Dunk contest participant PIDs
     * @param list<int> $dunkRound1Scores Dunk round 1 scores (stored as score*10)
     * @param list<int> $dunkFinalsScores Dunk finals scores
     * @param list<int> $threePtRound1Scores 3-pt round 1 scores (raw count)
     * @param list<int> $threePtSemisScores 3-pt semis scores
     * @param list<int> $threePtFinalsScores 3-pt finals scores
     */
    private function buildAswFile(
        array $allstar1 = [],
        array $allstar2 = [],
        array $threePointPids = [],
        array $dunkPids = [],
        array $dunkRound1Scores = [],
        array $dunkFinalsScores = [],
        array $threePtRound1Scores = [],
        array $threePtSemisScores = [],
        array $threePtFinalsScores = [],
    ): string {
        // Initialize 112 lines with "0"
        $lines = array_fill(0, AswFileParser::TOTAL_DATA_LINES, '0');

        // Fill roster sections
        $this->fillLines($lines, AswFileParser::ALLSTAR_1_START, $allstar1);
        $this->fillLines($lines, AswFileParser::ALLSTAR_2_START, $allstar2);
        // Rookie rosters left as 0
        $this->fillLines($lines, AswFileParser::THREE_POINT_START, $threePointPids);
        $this->fillLines($lines, AswFileParser::DUNK_CONTEST_START, $dunkPids);

        // Fill dunk scores: 82-89 = round1, 90-92 = finals
        $this->fillLines($lines, 82, $dunkRound1Scores);
        $this->fillLines($lines, 90, $dunkFinalsScores);

        // Fill 3-pt scores: 97-104 = round1, 105-108 = semis, 109-110 = finals
        $this->fillLines($lines, 97, $threePtRound1Scores);
        $this->fillLines($lines, 105, $threePtSemisScores);
        $this->fillLines($lines, 109, $threePtFinalsScores);

        $content = implode("\r\n", $lines) . "\r\n";

        // Pad to exactly 10,000 bytes
        if (strlen($content) < AswFileParser::FILE_SIZE) {
            $content = str_pad($content, AswFileParser::FILE_SIZE);
        }

        return $content;
    }

    /**
     * Fill lines array starting at offset with values.
     *
     * @param list<string> $lines Lines array (modified in place)
     * @param int $startLine Starting line index
     * @param list<int> $values Values to fill
     */
    private function fillLines(array &$lines, int $startLine, array $values): void
    {
        foreach ($values as $i => $value) {
            $lines[$startLine + $i] = (string) $value;
        }
    }

    /**
     * Write to temp file and return path.
     */
    private function writeTmpAswFile(string $data): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'asw_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $data);
        return $tmpFile;
    }

    public function testParseFileReturnsRosters(): void
    {
        $aswData = $this->buildAswFile(
            allstar1: [1001, 1002, 1003, 1004, 1005],
            allstar2: [2001, 2002, 2003, 2004, 2005],
            threePointPids: [3001, 3002, 3003, 3004, 3005, 3006, 3007, 3008],
            dunkPids: [4001, 4002, 4003, 4004],
        );
        $tmpFile = $this->writeTmpAswFile($aswData);

        try {
            $result = AswFileParser::parseFile($tmpFile);

            $this->assertArrayHasKey('rosters', $result);

            $expectedKeys = ['allstar_1', 'allstar_2', 'rookie_1', 'rookie_2', 'three_point', 'dunk_contest'];
            foreach ($expectedKeys as $key) {
                $this->assertArrayHasKey($key, $result['rosters'], "Missing roster key: {$key}");
            }
        } finally {
            unlink($tmpFile);
        }
    }

    public function testParseFileReturnsScores(): void
    {
        $aswData = $this->buildAswFile(
            dunkRound1Scores: [920, 880, 950, 870],
            dunkFinalsScores: [940, 960],
            threePtRound1Scores: [18, 22, 15, 20, 19, 21, 16, 23],
            threePtSemisScores: [22, 20, 21, 19],
            threePtFinalsScores: [23, 21],
        );
        $tmpFile = $this->writeTmpAswFile($aswData);

        try {
            $result = AswFileParser::parseFile($tmpFile);

            $this->assertArrayHasKey('scores', $result);

            $expectedKeys = ['dunk_round1', 'dunk_finals', 'three_pt_round1', 'three_pt_semis', 'three_pt_finals'];
            foreach ($expectedKeys as $key) {
                $this->assertArrayHasKey($key, $result['scores'], "Missing score key: {$key}");
            }
        } finally {
            unlink($tmpFile);
        }
    }

    public function testAllStarRostersHavePlayers(): void
    {
        $aswData = $this->buildAswFile(
            allstar1: [1001, 1002, 1003, 1004, 1005, 1006, 1007, 1008, 1009, 1010, 1011, 1012],
            allstar2: [2001, 2002, 2003, 2004, 2005, 2006, 2007, 2008, 2009, 2010, 2011, 2012],
        );
        $tmpFile = $this->writeTmpAswFile($aswData);

        try {
            $result = AswFileParser::parseFile($tmpFile);

            $this->assertNotEmpty($result['rosters']['allstar_1'], 'All-Star Team 1 should have players');
            $this->assertNotEmpty($result['rosters']['allstar_2'], 'All-Star Team 2 should have players');
            $this->assertCount(12, $result['rosters']['allstar_1']);
            $this->assertCount(12, $result['rosters']['allstar_2']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testPlayerIdsArePositiveIntegers(): void
    {
        $aswData = $this->buildAswFile(
            allstar1: [1001, 1002, 1003],
            allstar2: [2001, 2002],
            threePointPids: [3001, 3002, 3003],
            dunkPids: [4001, 4002],
        );
        $tmpFile = $this->writeTmpAswFile($aswData);

        try {
            $result = AswFileParser::parseFile($tmpFile);

            foreach ($result['rosters'] as $eventType => $playerIds) {
                foreach ($playerIds as $pid) {
                    $this->assertIsInt($pid);
                    $this->assertGreaterThan(0, $pid, "Player ID in {$eventType} should be positive");
                }
            }
        } finally {
            unlink($tmpFile);
        }
    }

    public function testDunkScoresAreOnHundredPointScale(): void
    {
        $aswData = $this->buildAswFile(
            dunkRound1Scores: [920, 880, 950, 870, 900, 850, 930, 860],
            dunkFinalsScores: [940, 960, 910],
        );
        $tmpFile = $this->writeTmpAswFile($aswData);

        try {
            $result = AswFileParser::parseFile($tmpFile);

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
        } finally {
            unlink($tmpFile);
        }
    }

    public function testThreePointScoresAreRawCounts(): void
    {
        $aswData = $this->buildAswFile(
            threePtRound1Scores: [18, 22, 15, 20, 19, 21, 16, 23],
        );
        $tmpFile = $this->writeTmpAswFile($aswData);

        try {
            $result = AswFileParser::parseFile($tmpFile);

            foreach ($result['scores']['three_pt_round1'] as $score) {
                $this->assertIsInt($score);
                $this->assertGreaterThanOrEqual(0, $score);
                $this->assertLessThanOrEqual(30, $score, '3-pt score should be <= 30');
            }
        } finally {
            unlink($tmpFile);
        }
    }

    public function testContestParticipantCounts(): void
    {
        $aswData = $this->buildAswFile(
            threePointPids: [3001, 3002, 3003, 3004, 3005, 3006, 3007, 3008],
            dunkPids: [4001, 4002, 4003, 4004],
            dunkRound1Scores: [920, 880, 950, 870],
            threePtRound1Scores: [18, 22, 15, 20, 19, 21, 16, 23],
        );
        $tmpFile = $this->writeTmpAswFile($aswData);

        try {
            $result = AswFileParser::parseFile($tmpFile);

            $this->assertLessThanOrEqual(10, count($result['rosters']['three_point']));
            $this->assertLessThanOrEqual(10, count($result['rosters']['dunk_contest']));
            $this->assertLessThanOrEqual(8, count($result['scores']['dunk_round1']));
            $this->assertLessThanOrEqual(8, count($result['scores']['three_pt_round1']));
        } finally {
            unlink($tmpFile);
        }
    }

    public function testParseFileThrowsForMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ASW file not found');

        AswFileParser::parseFile('/nonexistent/file.asw');
    }

    public function testParseFileReturnsCorrectRosterValues(): void
    {
        $aswData = $this->buildAswFile(
            allstar1: [1001, 1002, 1003],
            allstar2: [2001, 2002],
        );
        $tmpFile = $this->writeTmpAswFile($aswData);

        try {
            $result = AswFileParser::parseFile($tmpFile);

            $this->assertSame([1001, 1002, 1003], $result['rosters']['allstar_1']);
            $this->assertSame([2001, 2002], $result['rosters']['allstar_2']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testParseFileReturnsCorrectScoreValues(): void
    {
        $aswData = $this->buildAswFile(
            dunkRound1Scores: [920, 880],
            threePtRound1Scores: [18, 22],
            threePtFinalsScores: [23],
        );
        $tmpFile = $this->writeTmpAswFile($aswData);

        try {
            $result = AswFileParser::parseFile($tmpFile);

            $this->assertSame([920, 880], $result['scores']['dunk_round1']);
            $this->assertSame([18, 22], $result['scores']['three_pt_round1']);
            $this->assertSame([23], $result['scores']['three_pt_finals']);
        } finally {
            unlink($tmpFile);
        }
    }
}
