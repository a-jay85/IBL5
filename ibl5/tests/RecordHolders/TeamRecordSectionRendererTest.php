<?php

declare(strict_types=1);

namespace Tests\RecordHolders;

use PHPUnit\Framework\TestCase;
use RecordHolders\RecordTableRenderer;
use RecordHolders\TeamRecordSectionRenderer;

/**
 * @covers \RecordHolders\TeamRecordSectionRenderer
 */
final class TeamRecordSectionRendererTest extends TestCase
{
    private TeamRecordSectionRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new TeamRecordSectionRenderer(new RecordTableRenderer());
    }

    /**
     * @return array{
     *     playerSingleGame: array{regularSeason: array<string, list<mixed>>, playoffs: array<string, list<mixed>>, heat: array<string, list<mixed>>},
     *     quadrupleDoubles: list<mixed>,
     *     allStarRecord: array{name: string, pid: int|null, teams: string, teamTids: string, amount: int, years: string},
     *     playerFullSeason: array<string, list<mixed>>,
     *     teamGameRecords: array<string, list<mixed>>,
     *     teamSeasonRecords: array<string, list<mixed>>,
     *     teamFranchise: array<string, list<mixed>>
     * }
     */
    private function minimalRecords(): array
    {
        return [
            'playerSingleGame' => [
                'regularSeason' => [],
                'playoffs' => [],
                'heat' => [],
            ],
            'quadrupleDoubles' => [],
            'allStarRecord' => ['name' => '', 'pid' => null, 'teams' => '', 'teamTids' => '', 'amount' => 0, 'years' => ''],
            'playerFullSeason' => [],
            'teamGameRecords' => [],
            'teamSeasonRecords' => [],
            'teamFranchise' => [],
        ];
    }

    public function testFranchiseRowEscapesYears(): void
    {
        $payload = '<script>alert(1)</script>';
        $records = $this->minimalRecords();
        $records['teamFranchise'] = [
            'Most Championships' => [[
                'teamAbbr' => 'tst',
                'teamTid' => 1,
                'amount' => '5',
                'years' => $payload,
            ]],
        ];

        $html = $this->renderer->renderTeamRecords($records);

        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function testGameRowEscapesDateAndAmount(): void
    {
        $payload = '<script>alert(1)</script>';
        $records = $this->minimalRecords();
        $records['teamGameRecords'] = [
            'Most Points' => [[
                'teamAbbr' => 'tst',
                'teamTid' => 1,
                'teamYr' => '2026',
                'boxScoreUrl' => '',
                'dateDisplay' => $payload,
                'oppAbbr' => 'opp',
                'oppTid' => 2,
                'oppYr' => '2026',
                'amount' => $payload,
            ]],
        ];

        $html = $this->renderer->renderTeamRecords($records);

        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function testSeasonRowEscapesTeamAndAmount(): void
    {
        $payload = '<script>alert(1)</script>';
        $records = $this->minimalRecords();
        $records['teamSeasonRecords'] = [
            'Most Points' => [[
                'teamAbbr' => $payload,
                'teamTid' => 1,
                'teamYr' => '2026',
                'season' => '2026',
                'amount' => $payload,
            ]],
        ];

        $html = $this->renderer->renderTeamRecords($records);

        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
    }
}
