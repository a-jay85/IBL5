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

    public function testTeamGameRowEscapesDateOpponentAndAmount(): void
    {
        $datePayload = '<script>date()</script>';
        $oppPayload = '<script>opp()</script>';
        $amountPayload = '<script>amount()</script>';
        $records = $this->minimalRecords();
        $records['teamGameRecords'] = [
            'Most Points' => [[
                'teamAbbr' => 'tst',
                'teamTid' => 1,
                'teamYr' => '2026',
                'boxScoreUrl' => '',
                'dateDisplay' => $datePayload,
                'oppAbbr' => $oppPayload,
                'oppTid' => 2,
                'oppYr' => '2026',
                'amount' => $amountPayload,
            ]],
        ];

        $html = $this->renderer->renderTeamRecords($records);

        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>date()</script>', $html);
        self::assertStringNotContainsString('<script>opp()</script>', $html);
        self::assertStringNotContainsString('<script>amount()</script>', $html);
    }

    public function testTeamGameRowLinksBoxScoreOnlyWhenUrlPresent(): void
    {
        $url = 'box-score.php?game=42';
        $date = 'April 5, 2026';

        $records = $this->minimalRecords();
        $records['teamGameRecords'] = [
            'Most Points' => [[
                'teamAbbr' => 'tst',
                'teamTid' => 1,
                'teamYr' => '2026',
                'boxScoreUrl' => $url,
                'dateDisplay' => $date,
                'oppAbbr' => 'opp',
                'oppTid' => 2,
                'oppYr' => '2026',
                'amount' => '120',
            ]],
        ];

        $html = $this->renderer->renderTeamRecords($records);
        self::assertStringContainsString('<a href="' . $url . '">', $html);
        self::assertStringContainsString($date, $html);

        $recordsNoUrl = $this->minimalRecords();
        $recordsNoUrl['teamGameRecords'] = [
            'Most Points' => [[
                'teamAbbr' => 'tst',
                'teamTid' => 1,
                'teamYr' => '2026',
                'boxScoreUrl' => '',
                'dateDisplay' => $date,
                'oppAbbr' => 'opp',
                'oppTid' => 2,
                'oppYr' => '2026',
                'amount' => '120',
            ]],
        ];

        $htmlNoUrl = $this->renderer->renderTeamRecords($recordsNoUrl);
        self::assertStringNotContainsString('>' . $date . '</a>', $htmlNoUrl);
        self::assertStringContainsString($date, $htmlNoUrl);
    }

    public function testTeamSeasonRowEscapesSeasonAndAmount(): void
    {
        $seasonPayload = '<script>season()</script>';
        $amountPayload = '<script>amount()</script>';
        $records = $this->minimalRecords();
        $records['teamSeasonRecords'] = [
            'Most Points' => [[
                'teamAbbr' => 'tst',
                'teamTid' => 1,
                'teamYr' => '2026',
                'season' => $seasonPayload,
                'amount' => $amountPayload,
            ]],
        ];

        $html = $this->renderer->renderTeamRecords($records);

        self::assertStringNotContainsString('<script>season()</script>', $html);
        self::assertStringNotContainsString('<script>amount()</script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testEmptyRecordsRenderNoCategoryBlocks(): void
    {
        $records = $this->minimalRecords();

        $html = $this->renderer->renderTeamRecords($records);

        self::assertStringNotContainsString('record-category', $html);
        self::assertStringNotContainsString('Game Records', $html);
        self::assertStringNotContainsString('Season Records', $html);
        self::assertStringNotContainsString('Franchise Records', $html);
    }
}
