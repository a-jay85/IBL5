<?php

declare(strict_types=1);

namespace Tests\SeasonArchive;

use PHPUnit\Framework\TestCase;
use SeasonArchive\SeasonAwardExtractor;

/**
 * @covers \SeasonArchive\SeasonAwardExtractor
 */
class SeasonAwardExtractorTest extends TestCase
{
    private SeasonAwardExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new SeasonAwardExtractor();
    }

    // ─── extractAward ────────────────────────────────────────────────────────

    public function testExtractAwardReturnsWinnerOnExactMatch(): void
    {
        $awards = [
            ['year' => 2000, 'award' => 'Most Valuable Player (1st)', 'name' => 'Jordan', 'table_id' => 1],
        ];

        $this->assertSame('Jordan', $this->extractor->extractAward($awards, 'Most Valuable Player (1st)'));
    }

    public function testExtractAwardReturnsEmptyStringWhenAwardAbsent(): void
    {
        $awards = [
            ['year' => 2000, 'award' => 'Defensive Player of the Year (1st)', 'name' => 'Rodman', 'table_id' => 2],
        ];

        $this->assertSame('', $this->extractor->extractAward($awards, 'Most Valuable Player (1st)'));
    }

    public function testExtractAwardTrimsAwardAndNameWhitespace(): void
    {
        $awards = [
            ['year' => 2000, 'award' => 'Most Valuable Player (1st)  ', 'name' => '  Jordan  ', 'table_id' => 1],
        ];

        $this->assertSame('Jordan', $this->extractor->extractAward($awards, 'Most Valuable Player (1st)'));
    }

    public function testExtractAwardCollectsNameIntoAccumulator(): void
    {
        $awards = [
            ['year' => 2000, 'award' => 'Most Valuable Player (1st)', 'name' => 'Jordan', 'table_id' => 1],
        ];
        $collected = [];

        $this->extractor->extractAward($awards, 'Most Valuable Player (1st)', $collected);

        $this->assertSame(['Jordan' => true], $collected);
    }

    public function testExtractAwardDoesNotCollectEmptyName(): void
    {
        $awards = [
            ['year' => 2000, 'award' => 'Most Valuable Player (1st)', 'name' => '   ', 'table_id' => 1],
        ];
        $collected = [];

        $result = $this->extractor->extractAward($awards, 'Most Valuable Player (1st)', $collected);

        $this->assertSame('', $result);
        $this->assertSame([], $collected);
    }

    // ─── extractAwardList ────────────────────────────────────────────────────

    public function testExtractAwardListReturnsAllMatchingNames(): void
    {
        $awards = [
            ['year' => 2000, 'award' => 'All-League First Team', 'name' => 'Bird', 'table_id' => 1],
            ['year' => 2000, 'award' => 'All-League First Team', 'name' => 'Magic', 'table_id' => 2],
            ['year' => 2000, 'award' => 'All-League Second Team', 'name' => 'Other', 'table_id' => 3],
        ];

        $this->assertSame(['Bird', 'Magic'], $this->extractor->extractAwardList($awards, 'All-League First Team'));
    }

    public function testExtractAwardListReturnsEmptyArrayWhenNoMatch(): void
    {
        $awards = [
            ['year' => 2000, 'award' => 'All-League First Team', 'name' => 'Bird', 'table_id' => 1],
        ];

        $this->assertSame([], $this->extractor->extractAwardList($awards, 'All-League Second Team'));
    }

    public function testExtractAwardListCollectsOnlyNonEmptyNames(): void
    {
        $awards = [
            ['year' => 2000, 'award' => 'All-League First Team', 'name' => 'Bird', 'table_id' => 1],
            ['year' => 2000, 'award' => 'All-League First Team', 'name' => '', 'table_id' => 2],
        ];
        $collected = [];

        $result = $this->extractor->extractAwardList($awards, 'All-League First Team', $collected);

        $this->assertSame(['Bird', ''], $result);
        $this->assertSame(['Bird' => true], $collected);
    }

    public function testExtractAwardListWorksWithoutAccumulatorArgument(): void
    {
        $awards = [
            ['year' => 2000, 'award' => 'All-League First Team', 'name' => 'Bird', 'table_id' => 1],
            ['year' => 2000, 'award' => 'All-League First Team', 'name' => 'Magic', 'table_id' => 2],
        ];

        $result = $this->extractor->extractAwardList($awards, 'All-League First Team');

        $this->assertSame(['Bird', 'Magic'], $result);
    }

    // ─── getGmOfTheYear ──────────────────────────────────────────────────────

    public function testGmOfTheYearReturnsNameAndTeam(): void
    {
        $gmAwards = [
            ['year' => 2000, 'award' => 'GM of the Year', 'gm_display_name' => 'Riley', 'team_name' => 'Miami', 'table_id' => 1],
        ];

        $this->assertSame(['name' => 'Riley', 'team' => 'Miami'], $this->extractor->getGmOfTheYear($gmAwards, 2000));
    }

    public function testGmOfTheYearReturnsEmptyStructWhenNotFound(): void
    {
        $gmAwards = [
            ['year' => 2000, 'award' => 'GM of the Year', 'gm_display_name' => 'Riley', 'team_name' => 'Miami', 'table_id' => 1],
        ];

        $this->assertSame(['name' => '', 'team' => ''], $this->extractor->getGmOfTheYear($gmAwards, 1999));
    }

    public function testGmOfTheYearIgnoresSameAwardInDifferentYear(): void
    {
        $gmAwards = [
            ['year' => 1999, 'award' => 'GM of the Year', 'gm_display_name' => 'Jackson', 'team_name' => 'Chicago', 'table_id' => 1],
            ['year' => 2000, 'award' => 'Coach of the Year', 'gm_display_name' => 'Riley', 'team_name' => 'Miami', 'table_id' => 2],
        ];

        $this->assertSame(['name' => '', 'team' => ''], $this->extractor->getGmOfTheYear($gmAwards, 2000));
    }

    // ─── getAllStarCoaches ────────────────────────────────────────────────────

    public function testAllStarCoachesRoutedByConference(): void
    {
        $gmAwards = [
            ['year' => 2000, 'award' => 'ASG Head Coach', 'gm_display_name' => 'EastCoach', 'team_name' => 'Boston', 'table_id' => 1],
            ['year' => 2000, 'award' => 'ASG Head Coach', 'gm_display_name' => 'WestCoach', 'team_name' => 'Lakers', 'table_id' => 2],
        ];
        $conferences = ['Boston' => 'Eastern', 'Lakers' => 'Western'];

        $result = $this->extractor->getAllStarCoaches($gmAwards, 2000, $conferences);

        $this->assertSame(['east' => ['EastCoach'], 'west' => ['WestCoach']], $result);
    }

    public function testAllStarCoachesIncludesCoHeadCoach(): void
    {
        $gmAwards = [
            ['year' => 2000, 'award' => 'ASG Head Coach', 'gm_display_name' => 'Main', 'team_name' => 'Boston', 'table_id' => 1],
            ['year' => 2000, 'award' => 'ASG Co-Head Coach', 'gm_display_name' => 'Coach', 'team_name' => 'Celtics', 'table_id' => 2],
        ];
        $conferences = ['Boston' => 'Eastern', 'Celtics' => 'Eastern'];

        $result = $this->extractor->getAllStarCoaches($gmAwards, 2000, $conferences);

        $this->assertSame(['east' => ['Main', 'Coach'], 'west' => []], $result);
    }

    public function testAllStarCoachesIgnoresNonCoachAward(): void
    {
        $gmAwards = [
            ['year' => 2000, 'award' => 'GM of the Year', 'gm_display_name' => 'Riley', 'team_name' => 'Miami', 'table_id' => 1],
        ];
        $conferences = ['Miami' => 'Eastern'];

        $result = $this->extractor->getAllStarCoaches($gmAwards, 2000, $conferences);

        $this->assertSame(['east' => [], 'west' => []], $result);
    }

    public function testAllStarCoachesIgnoresOtherYears(): void
    {
        $gmAwards = [
            ['year' => 2001, 'award' => 'ASG Head Coach', 'gm_display_name' => 'Coach', 'team_name' => 'Boston', 'table_id' => 1],
        ];
        $conferences = ['Boston' => 'Eastern'];

        $result = $this->extractor->getAllStarCoaches($gmAwards, 2000, $conferences);

        $this->assertSame(['east' => [], 'west' => []], $result);
    }

    public function testAllStarCoachesReturnsEmptyArraysWhenNoConferenceMatch(): void
    {
        $gmAwards = [
            ['year' => 2000, 'award' => 'ASG Head Coach', 'gm_display_name' => 'Coach', 'team_name' => 'Unknown', 'table_id' => 1],
        ];
        $conferences = ['Boston' => 'Eastern'];

        $result = $this->extractor->getAllStarCoaches($gmAwards, 2000, $conferences);

        $this->assertSame(['east' => [], 'west' => []], $result);
    }

    // ─── getIblChampionCoach ─────────────────────────────────────────────────

    public function testIblChampionCoachFoundWithOpenTenure(): void
    {
        $gmTenures = [
            ['gm_display_name' => 'Coach', 'start_season_year' => 1995, 'end_season_year' => null, 'team_name' => 'Chicago'],
        ];

        $this->assertSame('Coach', $this->extractor->getIblChampionCoach($gmTenures, 'Chicago', 2000));
    }

    public function testIblChampionCoachFoundWithinClosedTenure(): void
    {
        $gmTenures = [
            ['gm_display_name' => 'Coach', 'start_season_year' => 1995, 'end_season_year' => 2002, 'team_name' => 'Chicago'],
        ];

        $this->assertSame('Coach', $this->extractor->getIblChampionCoach($gmTenures, 'Chicago', 2000));
    }

    public function testIblChampionCoachEmptyWhenChampionTeamIsEmptyString(): void
    {
        $gmTenures = [
            ['gm_display_name' => 'Coach', 'start_season_year' => 1995, 'end_season_year' => null, 'team_name' => 'Chicago'],
        ];

        $this->assertSame('', $this->extractor->getIblChampionCoach($gmTenures, '', 2000));
    }

    public function testIblChampionCoachEmptyWhenYearBeforeTenureStart(): void
    {
        $gmTenures = [
            ['gm_display_name' => 'Coach', 'start_season_year' => 1995, 'end_season_year' => null, 'team_name' => 'Chicago'],
        ];

        $this->assertSame('', $this->extractor->getIblChampionCoach($gmTenures, 'Chicago', 1994));
        $this->assertSame('Coach', $this->extractor->getIblChampionCoach($gmTenures, 'Chicago', 1995));
    }

    public function testIblChampionCoachEmptyWhenYearAfterTenureEnd(): void
    {
        $gmTenures = [
            ['gm_display_name' => 'Coach', 'start_season_year' => 1995, 'end_season_year' => 2002, 'team_name' => 'Chicago'],
            ['gm_display_name' => 'OtherCoach', 'start_season_year' => 1995, 'end_season_year' => 2002, 'team_name' => 'Lakers'],
        ];

        $this->assertSame('', $this->extractor->getIblChampionCoach($gmTenures, 'Chicago', 2003));
        $this->assertSame('Coach', $this->extractor->getIblChampionCoach($gmTenures, 'Chicago', 2002));
    }

    // ─── parseTeamAwards ─────────────────────────────────────────────────────

    public function testParseTeamAwardsStripsHtmlTags(): void
    {
        $teamAwardRows = [
            ['year' => 2000, 'name' => 'Boston', 'award' => '<B>Atlantic Division Champions</b>', 'id' => 1],
        ];

        $result = $this->extractor->parseTeamAwards($teamAwardRows);

        $this->assertSame(['Atlantic Division Champions' => 'Boston'], $result);
    }

    public function testParseTeamAwardsSplitsMultilineAwards(): void
    {
        $teamAwardRows = [
            ['year' => 2000, 'name' => 'Chicago', 'award' => "Division Champions\nConference Champions", 'id' => 1],
        ];

        $result = $this->extractor->parseTeamAwards($teamAwardRows);

        $this->assertSame([
            'Division Champions' => 'Chicago',
            'Conference Champions' => 'Chicago',
        ], $result);
    }

    public function testParseTeamAwardsSkipsEmptyPartsAfterSplit(): void
    {
        $teamAwardRows = [
            ['year' => 2000, 'name' => 'Chicago', 'award' => "Division Champions\n", 'id' => 1],
        ];

        $result = $this->extractor->parseTeamAwards($teamAwardRows);

        $this->assertSame(['Division Champions' => 'Chicago'], $result);
        $this->assertArrayNotHasKey('', $result);
    }

    public function testParseTeamAwardsReturnsEmptyArrayForNoRows(): void
    {
        $this->assertSame([], $this->extractor->parseTeamAwards([]));
    }

    // ─── getHeatChampionFromTeamAwards ───────────────────────────────────────

    public function testHeatChampionFoundByAwardLabel(): void
    {
        $teamAwards = [
            ['year' => 1999, 'name' => 'Miami', 'award' => 'HEAT Champion', 'id' => 1],
        ];

        $this->assertSame('Miami', $this->extractor->getHeatChampionFromTeamAwards($teamAwards));
    }

    public function testHeatChampionMatchIsCaseInsensitive(): void
    {
        $teamAwards = [
            ['year' => 1999, 'name' => 'Miami', 'award' => 'heat champion', 'id' => 1],
        ];

        $this->assertSame('Miami', $this->extractor->getHeatChampionFromTeamAwards($teamAwards));
    }

    public function testHeatChampionStripsHtmlBeforeMatching(): void
    {
        $teamAwards = [
            ['year' => 1999, 'name' => 'Miami', 'award' => '<B>HEAT Champion</b>', 'id' => 1],
        ];

        $this->assertSame('Miami', $this->extractor->getHeatChampionFromTeamAwards($teamAwards));
    }

    public function testHeatChampionReturnsEmptyStringWhenAbsent(): void
    {
        $teamAwards = [
            ['year' => 1999, 'name' => 'Chicago', 'award' => 'Atlantic Division Champions', 'id' => 1],
        ];

        $this->assertSame('', $this->extractor->getHeatChampionFromTeamAwards($teamAwards));
    }
}
