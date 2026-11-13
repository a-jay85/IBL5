<?php

declare(strict_types=1);

namespace Tests\JsbParser\Importers;

use JsbParser\AswFileParser;

/**
 * Synthetic .asw file builders shared by the unit and integration tests.
 */
trait BuildsAswFixtures
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
     * @param list<int> $rookie1 PIDs for Rookie Team 1 (up to 15)
     * @param list<int> $rookie2 PIDs for Rookie Team 2 (up to 15)
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
        array $rookie1 = [],
        array $rookie2 = [],
    ): string {
        // Initialize 112 lines with "0". array_map with a string-typed callback yields
        // list<string> (not list<'0'>), matching fillLines()'s by-ref param type.
        $lines = array_map(
            static fn (int $i): string => '0',
            range(0, AswFileParser::TOTAL_DATA_LINES - 1)
        );

        // Fill roster sections
        $this->fillLines($lines, AswFileParser::ALLSTAR_1_START, $allstar1);
        $this->fillLines($lines, AswFileParser::ALLSTAR_2_START, $allstar2);
        $this->fillLines($lines, AswFileParser::ROOKIE_1_START, $rookie1);
        $this->fillLines($lines, AswFileParser::ROOKIE_2_START, $rookie2);
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
     * @param array<int, string> $lines Lines array (modified in place)
     * @param int $startLine Starting line index
     * @param list<int> $values Values to fill
     */
    private function fillLines(array &$lines, int $startLine, array $values): void
    {
        foreach ($values as $i => $value) {
            $lines[$startLine + $i] = (string) $value;
        }
    }

    private function buildFixture(): string
    {
        return $this->buildAswFile(
            allstar1: [101, 102],
            threePointPids: [301, 302],
            dunkPids: [401, 402],
            dunkRound1Scores: [455, 480, 470],
            threePtRound1Scores: [18, 21],
        );
    }
}
