<?php

declare(strict_types=1);

namespace Tests\RecordHolders;

use PHPUnit\Framework\TestCase;
use RecordHolders\RecordFormatter;

final class RecordFormatterTest extends TestCase
{
    private RecordFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new RecordFormatter();
    }

    // -------------------------------------------------------------------------
    // formatPlayerRecords
    // -------------------------------------------------------------------------

    public function testFormatPlayerRecordsMapsFields(): void
    {
        $row = $this->makePlayerRow(teamid: 1, oppTid: 2, value: 42);
        $result = $this->formatter->formatPlayerRecords([$row], 'regularSeason');

        $this->assertCount(1, $result);
        $this->assertSame('bos', $result[0]['teamAbbr']);
        $this->assertSame('mia', $result[0]['oppAbbr']);
        $this->assertSame('42', $result[0]['amount']);
        $this->assertSame('1996', $result[0]['teamYr']);
    }

    public function testFormatPlayerRecordsEmptyInput(): void
    {
        $this->assertSame([], $this->formatter->formatPlayerRecords([], 'regularSeason'));
    }

    public function testFormatPlayerRecordsUnknownTeamIdReturnsEmptyAbbr(): void
    {
        $row = $this->makePlayerRow(teamid: 999, oppTid: 998);
        $result = $this->formatter->formatPlayerRecords([$row], 'regularSeason');

        $this->assertSame('', $result[0]['teamAbbr']);
        $this->assertSame('', $result[0]['oppAbbr']);
    }

    // -------------------------------------------------------------------------
    // formatQuadrupleDoubles
    // -------------------------------------------------------------------------

    public function testFormatQuadrupleDoublesBuildsMultiLineAmount(): void
    {
        $row = $this->makeQDRow(points: 30, rebounds: 12, assists: 10, steals: 10, blocks: 10);
        $result = $this->formatter->formatQuadrupleDoubles([$row]);

        $this->assertCount(1, $result);
        $this->assertStringContainsString("30pts", $result[0]['amount']);
        $this->assertStringContainsString("12rbs", $result[0]['amount']);
        $this->assertStringContainsString("10ast", $result[0]['amount']);
        $this->assertStringContainsString("10stl", $result[0]['amount']);
        $this->assertStringContainsString("10blk", $result[0]['amount']);
    }

    public function testFormatQuadrupleDoublesOmitsBlkLineWhenBlocksUnderTen(): void
    {
        $row = $this->makeQDRow(blocks: 9);
        $result = $this->formatter->formatQuadrupleDoubles([$row]);

        $this->assertStringNotContainsString("blk", $result[0]['amount']);
    }

    // -------------------------------------------------------------------------
    // formatAllStarRecord
    // -------------------------------------------------------------------------

    public function testFormatAllStarRecordReturnsTopRecord(): void
    {
        $records = [['name' => 'LeBron James', 'pid' => 42, 'appearances' => 15]];
        $result = $this->formatter->formatAllStarRecord($records);

        $this->assertSame('LeBron James', $result['name']);
        $this->assertSame(42, $result['pid']);
        $this->assertSame(15, $result['amount']);
    }

    public function testFormatAllStarRecordEmptyInputReturnsEmptyShape(): void
    {
        $result = $this->formatter->formatAllStarRecord([]);

        $this->assertSame('', $result['name']);
        $this->assertNull($result['pid']);
        $this->assertSame(0, $result['amount']);
        $this->assertSame('', $result['years']);
    }

    // -------------------------------------------------------------------------
    // formatPlayerSeasonRecords
    // -------------------------------------------------------------------------

    public function testFormatPlayerSeasonRecordsBuildsSeasonString(): void
    {
        $row = ['pid' => 1, 'name' => 'Player', 'teamid' => 1, 'team' => 'Celtics', 'year' => 1994, 'value' => 25.6];
        $result = $this->formatter->formatPlayerSeasonRecords([$row]);

        $this->assertCount(1, $result);
        $this->assertSame('1993-94', $result[0]['season']);
        $this->assertSame('bos', $result[0]['teamAbbr']);
    }

    public function testFormatPlayerSeasonRecordsFormatsAmountOneDecimal(): void
    {
        $row = ['pid' => 1, 'name' => 'Player', 'teamid' => 1, 'team' => 'Celtics', 'year' => 1994, 'value' => 25.0];
        $result = $this->formatter->formatPlayerSeasonRecords([$row]);

        $this->assertSame('25.0', $result[0]['amount']);
    }

    public function testFormatPlayerSeasonRecordsEmptyInput(): void
    {
        $this->assertSame([], $this->formatter->formatPlayerSeasonRecords([]));
    }

    // -------------------------------------------------------------------------
    // formatTeamGameRecords
    // -------------------------------------------------------------------------

    public function testFormatTeamGameRecordsMapsFields(): void
    {
        $row = $this->makeTeamGameRow(teamid: 1, oppTid: 2, value: 150);
        $result = $this->formatter->formatTeamGameRecords([$row]);

        $this->assertCount(1, $result);
        $this->assertSame('bos', $result[0]['teamAbbr']);
        $this->assertSame('mia', $result[0]['oppAbbr']);
        $this->assertSame('150', $result[0]['amount']);
    }

    public function testFormatTeamGameRecordsEmptyInput(): void
    {
        $this->assertSame([], $this->formatter->formatTeamGameRecords([]));
    }

    // -------------------------------------------------------------------------
    // formatMarginRecords
    // -------------------------------------------------------------------------

    public function testFormatMarginRecordsMapsWinnerAndLoser(): void
    {
        $row = [
            'winner_tid' => 1, 'winner_name' => 'Celtics',
            'loser_tid' => 2, 'loser_name' => 'Heat',
            'date' => '1996-01-16', 'box_id' => 0, 'game_of_that_day' => 1, 'margin' => 35,
        ];
        $result = $this->formatter->formatMarginRecords([$row]);

        $this->assertCount(1, $result);
        $this->assertSame('bos', $result[0]['teamAbbr']);
        $this->assertSame('mia', $result[0]['oppAbbr']);
        $this->assertSame('35', $result[0]['amount']);
    }

    public function testFormatMarginRecordsEmptyInput(): void
    {
        $this->assertSame([], $this->formatter->formatMarginRecords([]));
    }

    // -------------------------------------------------------------------------
    // formatSeasonWinLossRecords
    // -------------------------------------------------------------------------

    public function testFormatSeasonWinLossRecordsBuildsAmount(): void
    {
        $row = ['team_name' => 'Celtics', 'year' => 1996, 'wins' => 55, 'losses' => 27];
        $result = $this->formatter->formatSeasonWinLossRecords([$row]);

        $this->assertCount(1, $result);
        $this->assertSame('55-27', $result[0]['amount']);
        $this->assertSame('bos', $result[0]['teamAbbr']);
        $this->assertSame('1995-96', $result[0]['season']);
    }

    public function testFormatSeasonWinLossRecordsTrimsToTopTied(): void
    {
        $rows = [
            ['team_name' => 'Celtics', 'year' => 1996, 'wins' => 55, 'losses' => 27],
            ['team_name' => 'Heat', 'year' => 1997, 'wins' => 55, 'losses' => 27],
            ['team_name' => 'Knicks', 'year' => 1995, 'wins' => 50, 'losses' => 32],
        ];
        $result = $this->formatter->formatSeasonWinLossRecords($rows);

        $this->assertCount(2, $result);
        $this->assertSame('bos', $result[0]['teamAbbr']);
        $this->assertSame('mia', $result[1]['teamAbbr']);
    }

    // -------------------------------------------------------------------------
    // formatSeasonStartRecords
    // -------------------------------------------------------------------------

    public function testFormatSeasonStartRecordsBuildsAmount(): void
    {
        $row = ['team_name' => 'Heat', 'year' => 1997, 'wins' => 10, 'losses' => 0];
        $result = $this->formatter->formatSeasonStartRecords([$row], 'best');

        $this->assertCount(1, $result);
        $this->assertSame('10-0', $result[0]['amount']);
        $this->assertSame('mia', $result[0]['teamAbbr']);
    }

    public function testFormatSeasonStartRecordsSingleRowNoTieTrimming(): void
    {
        $row = ['team_name' => 'Celtics', 'year' => 1996, 'wins' => 8, 'losses' => 2];
        $result = $this->formatter->formatSeasonStartRecords([$row], 'best');

        $this->assertCount(1, $result);
    }

    // -------------------------------------------------------------------------
    // formatStreakRecords
    // -------------------------------------------------------------------------

    public function testFormatStreakRecordsSingleSeasonStreak(): void
    {
        $row = [
            'team_name' => 'Bulls', 'streak' => 15,
            'start_date' => '1995-01-01', 'end_date' => '1995-02-10',
            'start_year' => 1995, 'end_year' => 1995,
        ];
        $result = $this->formatter->formatStreakRecords([$row]);

        $this->assertCount(1, $result);
        $this->assertSame('chi', $result[0]['teamAbbr']);
        $this->assertSame('15', $result[0]['amount']);
        $this->assertSame('1994-95', $result[0]['season']);
    }

    public function testFormatStreakRecordsCrossYearSeasonJoin(): void
    {
        $row = [
            'team_name' => 'Bulls', 'streak' => 20,
            'start_date' => '1995-12-01', 'end_date' => '1996-01-20',
            'start_year' => 1995, 'end_year' => 1996,
        ];
        $result = $this->formatter->formatStreakRecords([$row]);

        $this->assertSame('1994-95, 1995-96', $result[0]['season']);
    }

    // -------------------------------------------------------------------------
    // formatFranchiseRecords
    // -------------------------------------------------------------------------

    public function testFormatFranchiseRecordsMapsFields(): void
    {
        $row = ['team_name' => 'Celtics', 'count' => 8, 'years' => '1990, 1992, 1994'];
        $result = $this->formatter->formatFranchiseRecords([$row]);

        $this->assertCount(1, $result);
        $this->assertSame('bos', $result[0]['teamAbbr']);
        $this->assertSame('8', $result[0]['amount']);
        $this->assertSame('1990, 1992, 1994', $result[0]['years']);
    }

    public function testFormatFranchiseRecordsStripsTagsFromYears(): void
    {
        $row = ['team_name' => 'Celtics', 'count' => 3, 'years' => '<b>1990</b>, <i>1992</i>'];
        $result = $this->formatter->formatFranchiseRecords([$row]);

        $this->assertSame('1990, 1992', $result[0]['years']);
    }

    // -------------------------------------------------------------------------
    // detectTies
    // -------------------------------------------------------------------------

    public function testDetectTiesKeepsAllMatchingTopAmount(): void
    {
        $records = [
            ['amount' => '30', 'name' => 'A'],
            ['amount' => '30', 'name' => 'B'],
            ['amount' => '28', 'name' => 'C'],
        ];
        $result = $this->formatter->detectTies($records);

        $this->assertCount(2, $result);
        $this->assertSame('A', $result[0]['name']);
        $this->assertSame('B', $result[1]['name']);
    }

    public function testDetectTiesSingleRecordReturnedUnchanged(): void
    {
        $records = [['amount' => '30', 'name' => 'A']];
        $result = $this->formatter->detectTies($records);

        $this->assertSame($records, $result);
    }

    public function testDetectTiesEmptyInputReturnedUnchanged(): void
    {
        $this->assertSame([], $this->formatter->detectTies([]));
    }

    public function testDetectTiesNoTieReturnsOnlyFirst(): void
    {
        $records = [
            ['amount' => '30', 'name' => 'A'],
            ['amount' => '28', 'name' => 'B'],
        ];
        $result = $this->formatter->detectTies($records);

        $this->assertCount(1, $result);
        $this->assertSame('A', $result[0]['name']);
    }

    // -------------------------------------------------------------------------
    // addTieLabel
    // -------------------------------------------------------------------------

    public function testAddTieLabelAppendsSuffixForMultipleRecords(): void
    {
        $records = [['amount' => '30'], ['amount' => '30']];
        $result = $this->formatter->addTieLabel('Most Points', $records);

        $this->assertSame('Most Points [tie]', $result);
    }

    public function testAddTieLabelNoSuffixForSingleRecord(): void
    {
        $records = [['amount' => '30']];
        $result = $this->formatter->addTieLabel('Most Points', $records);

        $this->assertSame('Most Points', $result);
    }

    public function testAddTieLabelNoSuffixForEmptyRecords(): void
    {
        $result = $this->formatter->addTieLabel('Most Points', []);

        $this->assertSame('Most Points', $result);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return array{pid: int, name: string, teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, value: int}
     */
    private function makePlayerRow(int $teamid = 1, int $oppTid = 2, int $value = 30): array
    {
        return [
            'pid' => 1,
            'name' => 'Test Player',
            'teamid' => $teamid,
            'team_name' => 'Celtics',
            'date' => '1996-01-16',
            'box_id' => 0,
            'game_of_that_day' => 1,
            'oppTid' => $oppTid,
            'opp_team_name' => 'Heat',
            'value' => $value,
        ];
    }

    /**
     * @return array{pid: int, name: string, teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, points: int, rebounds: int, assists: int, steals: int, blocks: int}
     */
    private function makeQDRow(int $points = 20, int $rebounds = 12, int $assists = 11, int $steals = 10, int $blocks = 5): array
    {
        return [
            'pid' => 1,
            'name' => 'Test Player',
            'teamid' => 1,
            'team_name' => 'Celtics',
            'date' => '1996-01-16',
            'box_id' => 0,
            'game_of_that_day' => 1,
            'oppTid' => 2,
            'opp_team_name' => 'Heat',
            'points' => $points,
            'rebounds' => $rebounds,
            'assists' => $assists,
            'steals' => $steals,
            'blocks' => $blocks,
        ];
    }

    /**
     * @return array{teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, value: int}
     */
    private function makeTeamGameRow(int $teamid = 1, int $oppTid = 2, int $value = 130): array
    {
        return [
            'teamid' => $teamid,
            'team_name' => 'Celtics',
            'date' => '1996-01-16',
            'box_id' => 0,
            'game_of_that_day' => 1,
            'oppTid' => $oppTid,
            'opp_team_name' => 'Heat',
            'value' => $value,
        ];
    }
}
