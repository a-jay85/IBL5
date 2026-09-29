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
    private function playerRecord(string $dateDisplay = 'January 1, 2026', string $amount = '42', string $oppAbbr = 'opp', string $boxScoreUrl = ''): array
    {
        return [
            'pid' => 1,
            'name' => 'Test Player',
            'teamAbbr' => 'tst',
            'teamTid' => 1,
            'teamYr' => '2026',
            'boxScoreUrl' => $boxScoreUrl,
            'dateDisplay' => $dateDisplay,
            'oppAbbr' => $oppAbbr,
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
            'Most Points' => [$this->playerRecord('Jan 1', '42', 'opp', $payload)],
        ];

        $html = $this->renderer->renderPlayerSingleGameRecords($records);

        self::assertStringNotContainsString('"onload="alert(1)', $html);
    }

    public function testSingleGameRowEscapesOpponentAbbreviation(): void
    {
        $payload = '<script>opp()</script>';
        $records = $this->minimalRecords();
        $records['playerSingleGame']['regularSeason'] = [
            'Most Points' => [$this->playerRecord('Jan 1', '42', $payload)],
        ];

        $html = $this->renderer->renderPlayerSingleGameRecords($records);

        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>opp()</script>', $html);
    }

    public function testSingleGameRowLinksBoxScoreOnlyWhenUrlPresent(): void
    {
        $url = 'box-score.php?game=99';
        $date = 'March 15, 2026';

        $records = $this->minimalRecords();
        $records['playerSingleGame']['regularSeason'] = [
            'Most Points' => [$this->playerRecord($date, '42', 'opp', $url)],
        ];

        $html = $this->renderer->renderPlayerSingleGameRecords($records);

        self::assertStringContainsString('<a href="box-score.php?game=99">', $html);
        self::assertStringContainsString($date, $html);

        // Without URL: date is plain text, not an anchor
        $recordsNoUrl = $this->minimalRecords();
        $recordsNoUrl['playerSingleGame']['regularSeason'] = [
            'Most Points' => [$this->playerRecord($date, '42', 'opp', '')],
        ];

        $htmlNoUrl = $this->renderer->renderPlayerSingleGameRecords($recordsNoUrl);

        self::assertStringNotContainsString('>' . $date . '</a>', $htmlNoUrl);
        self::assertStringContainsString($date, $htmlNoUrl);
    }

    public function testFullSeasonRowEscapesSeasonAndAmount(): void
    {
        $seasonPayload = '<script>season()</script>';
        $amountPayload = '<script>amount()</script>';
        $seasonRecords = [
            'Most Points' => [[
                'pid' => 1,
                'name' => 'Test',
                'teamAbbr' => 'tst',
                'teamTid' => 1,
                'teamYr' => '2026',
                'season' => $seasonPayload,
                'amount' => $amountPayload,
            ]],
        ];

        $html = $this->renderer->renderPlayerFullSeasonRecords($seasonRecords);

        self::assertStringNotContainsString('<script>season()</script>', $html);
        self::assertStringNotContainsString('<script>amount()</script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testAllStarBlockEscapesYears(): void
    {
        $payload = '<script>years()</script>';
        $records = $this->minimalRecords();
        $records['allStarRecord'] = [
            'name' => 'Test Player',
            'pid' => 1,
            'teams' => '',
            'teamTids' => '',
            'amount' => 3,
            'years' => $payload,
        ];

        $html = $this->renderer->renderPlayerSingleGameRecords($records);

        self::assertStringNotContainsString('<script>years()</script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testQuadrupleDoublesRenderMultiLineAmountWithBreaks(): void
    {
        $records = $this->minimalRecords();
        $records['quadrupleDoubles'] = [$this->playerRecord('Jan 1', "10\n10\n10\n10")];

        $html = $this->renderer->renderPlayerSingleGameRecords($records);

        self::assertStringContainsString('10<br>', $html);
    }
}
