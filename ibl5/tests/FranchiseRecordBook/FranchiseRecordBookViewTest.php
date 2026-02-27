<?php

declare(strict_types=1);

namespace Tests\FranchiseRecordBook;

use FranchiseRecordBook\FranchiseRecordBookView;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FranchiseRecordBook\FranchiseRecordBookView
 */
class FranchiseRecordBookViewTest extends TestCase
{
    private FranchiseRecordBookView $view;

    protected function setUp(): void
    {
        $this->view = new FranchiseRecordBookView();
    }

    /**
     * @return array{id: int, scope: string, team_id: int, record_type: string, stat_category: string, ranking: int, player_name: string, car_block_id: int|null, pid: int|null, stat_value: string, stat_raw: int, team_of_record: int|null, season_year: int|null, career_total: int|null}
     */
    private static function makeRecord(
        string $recordType = 'career',
        int $teamOfRecord = 0,
        ?int $careerTotal = 25000,
    ): array {
        return [
            'id' => 1,
            'scope' => 'league',
            'team_id' => 0,
            'record_type' => $recordType,
            'stat_category' => 'pts',
            'ranking' => 1,
            'player_name' => 'Test Player',
            'car_block_id' => 100,
            'pid' => 42,
            'stat_value' => '20.5',
            'stat_raw' => 2050,
            'team_of_record' => $teamOfRecord,
            'season_year' => null,
            'career_total' => $careerTotal,
        ];
    }

    /**
     * @return array{teamid: int, team_name: string, color1: string, color2: string}
     */
    private static function makeTeam(int $teamId = 1): array
    {
        return [
            'teamid' => $teamId,
            'team_name' => 'Test Team',
            'color1' => '008040',
            'color2' => 'FFFFFF',
        ];
    }

    public function testCareerRecordWithNoTeamShowsRetired(): void
    {
        $record = self::makeRecord('career', 0);

        $data = [
            'singleSeason' => ['ppg' => [], 'rpg' => [], 'apg' => [], 'spg' => [], 'bpg' => [], 'fg_pct' => [], 'ft_pct' => [], 'three_pct' => []],
            'career' => ['pts' => [$record], 'trb' => [], 'ast' => [], 'stl' => [], 'blk' => [], 'fg_pct' => [], 'ft_pct' => [], 'three_pct' => []],
            'team' => null,
            'teams' => [self::makeTeam()],
            'scope' => 'league',
        ];

        $html = $this->view->render($data);

        $this->assertStringContainsString('<td class="record-book-retired-cell">Retired</td>', $html);
    }

    public function testCareerRecordWithTeamShowsTeamCell(): void
    {
        $record = self::makeRecord('career', 1);

        $data = [
            'singleSeason' => ['ppg' => [], 'rpg' => [], 'apg' => [], 'spg' => [], 'bpg' => [], 'fg_pct' => [], 'ft_pct' => [], 'three_pct' => []],
            'career' => ['pts' => [$record], 'trb' => [], 'ast' => [], 'stl' => [], 'blk' => [], 'fg_pct' => [], 'ft_pct' => [], 'three_pct' => []],
            'team' => null,
            'teams' => [self::makeTeam(1)],
            'scope' => 'league',
        ];

        $html = $this->view->render($data);

        $this->assertStringNotContainsString('Retired</td>', $html);
        $this->assertStringContainsString('Test Team', $html);
    }

    public function testSingleSeasonRecordWithNoTeamShowsEmptyCell(): void
    {
        $record = self::makeRecord('single_season', 0, null);
        $record['stat_category'] = 'ppg';
        $record['season_year'] = 2005;

        $data = [
            'singleSeason' => ['ppg' => [$record], 'rpg' => [], 'apg' => [], 'spg' => [], 'bpg' => [], 'fg_pct' => [], 'ft_pct' => [], 'three_pct' => []],
            'career' => ['pts' => [], 'trb' => [], 'ast' => [], 'stl' => [], 'blk' => [], 'fg_pct' => [], 'ft_pct' => [], 'three_pct' => []],
            'team' => null,
            'teams' => [self::makeTeam()],
            'scope' => 'league',
        ];

        $html = $this->view->render($data);

        $this->assertStringNotContainsString('Retired</td>', $html);
        $this->assertStringContainsString('<td></td>', $html);
    }
}
