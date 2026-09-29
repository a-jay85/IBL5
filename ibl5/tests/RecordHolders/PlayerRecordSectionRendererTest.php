<?php

declare(strict_types=1);

namespace Tests\RecordHolders;

use PHPUnit\Framework\TestCase;
use RecordHolders\PlayerRecordSectionRenderer;
use RecordHolders\RecordTableRenderer;

/**
 * @covers \RecordHolders\PlayerRecordSectionRenderer
 */
final class PlayerRecordSectionRendererTest extends TestCase
{
    private PlayerRecordSectionRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new PlayerRecordSectionRenderer(new RecordTableRenderer());
    }

    /**
     * @return array{pid: int, name: string, teamAbbr: string, teamTid: int, teamYr: string, boxScoreUrl: string, dateDisplay: string, oppAbbr: string, oppTid: int, oppYr: string, amount: string}
     */
    private function playerRecord(string $dateDisplay = 'January 1, 2026', string $amount = '42'): array
    {
        return [
            'pid' => 1,
            'name' => 'Test Player',
            'teamAbbr' => 'tst',
            'teamTid' => 1,
            'teamYr' => '2026',
            'boxScoreUrl' => '',
            'dateDisplay' => $dateDisplay,
            'oppAbbr' => 'opp',
            'oppTid' => 2,
            'oppYr' => '2026',
            'amount' => $amount,
        ];
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

    public function testSingleGameRowEscapesDateAndAmount(): void
    {
        $payload = '<script>alert(1)</script>';
        $records = $this->minimalRecords();
        $records['playerSingleGame']['regularSeason'] = [
            'Most Points' => [$this->playerRecord($payload, $payload)],
        ];

        $html = $this->renderer->renderPlayerSingleGameRecords($records);

        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function testSingleGameRowEscapesBoxScoreUrlAttributeBreakout(): void
    {
        $payload = '"onload="alert(1)';
        $records = $this->minimalRecords();
        $records['playerSingleGame']['regularSeason'] = [
            'Most Points' => [$this->playerRecord('Jan 1', '42')],
        ];
        // Inject payload via boxScoreUrl - it appears in an href attribute
        $records['playerSingleGame']['regularSeason']['Most Points'][0]['boxScoreUrl'] = $payload;

        $html = $this->renderer->renderPlayerSingleGameRecords($records);

        self::assertStringNotContainsString('"onload="alert(1)', $html);
    }

    public function testFullSeasonRowEscapesAmount(): void
    {
        $payload = '<script>alert(1)</script>';
        $seasonRecords = [
            'Most Points' => [[
                'pid' => 1,
                'name' => 'Test',
                'teamAbbr' => 'tst',
                'teamTid' => 1,
                'teamYr' => '2026',
                'season' => '2026',
                'amount' => $payload,
            ]],
        ];

        $html = $this->renderer->renderPlayerFullSeasonRecords($seasonRecords);

        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
    }
}
