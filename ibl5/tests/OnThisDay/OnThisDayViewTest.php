<?php

declare(strict_types=1);

namespace Tests\OnThisDay;

use OnThisDay\Moment;
use OnThisDay\MomentKind;
use OnThisDay\OnThisDaySelection;
use OnThisDay\OnThisDayView;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OnThisDayViewTest extends TestCase
{
    private OnThisDayView $view;

    protected function setUp(): void
    {
        $this->view = new OnThisDayView();
    }

    /**
     * @param array<string, int> $facts
     */
    private function makeMoment(
        int $seasonYear,
        string $date,
        string $playerName = 'Test Player',
        string $teamName = 'Hawks',
        int $gameOfThatDay = 0,
        MomentKind $kind = MomentKind::FiftyPointGame,
        int $pid = 1,
        array $facts = ['points' => 51],
    ): Moment {
        return new Moment(
            kind: $kind,
            key: 'fifty_point:' . $date . ':1',
            date: $date,
            seasonYear: $seasonYear,
            pid: $pid,
            playerName: $playerName,
            teamId: 1,
            teamName: $teamName,
            opponentTeamId: 2,
            opponentName: 'Celtics',
            facts: $facts,
            gameOfThatDay: $gameOfThatDay,
        );
    }

    /**
     * @param list<Moment> $moments
     */
    private function makeSelection(array $moments): OnThisDaySelection
    {
        return new OnThisDaySelection($moments, 2026, '2026-02-14', 0, 3);
    }

    public function testRendersSeasonsAgoBadgePerMoment(): void
    {
        $html = $this->view->renderHomepageBlock($this->makeSelection([
            $this->makeMoment(2025, '2025-02-14'),
            $this->makeMoment(2016, '2016-02-14'),
        ]));

        $this->assertStringContainsString('data-component="on-this-day"', $html);
        $this->assertStringContainsString('League history around Feb 14', $html);
        $this->assertStringContainsString('<span class="ibl-badge ibl-badge--info on-this-day__when">1 season ago</span>', $html);
        $this->assertStringContainsString('<span class="ibl-badge ibl-badge--info on-this-day__when">10 seasons ago</span>', $html);
        $this->assertStringContainsString('data-moment-kind="fifty_point"', $html);
        $this->assertStringContainsString('Feb 14, 2025', $html);
        $this->assertSame(2, substr_count($html, '<li class="centerbox-item"'));
    }

    public function testEscapesXssInPlayerAndTeamNames(): void
    {
        $html = $this->view->renderHomepageBlock($this->makeSelection([
            $this->makeMoment(2025, '2025-02-14', '<script>alert(1)</script>', '"><img src=x onerror=alert(1)>'),
        ]));

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('"><img src=x onerror=alert(1)>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    public function testEmptySelectionRendersNothing(): void
    {
        $this->assertSame('', $this->view->renderHomepageBlock($this->makeSelection([])));
    }

    public function testSkipsMomentsFromCurrentOrFutureSeason(): void
    {
        $html = $this->view->renderHomepageBlock($this->makeSelection([
            $this->makeMoment(2026, '2026-02-14'),
            $this->makeMoment(2025, '2025-02-14'),
        ]));

        $this->assertSame(1, substr_count($html, '<li class="centerbox-item"'));
        $this->assertStringContainsString('1 season ago', $html);
    }

    /**
     * @return array<string, array{MomentKind, array<string, int>, int, bool}>
     */
    public static function linkKindProvider(): array
    {
        return [
            'fifty point' => [MomentKind::FiftyPointGame, ['points' => 52], 7, true],
            'triple double' => [MomentKind::TripleDouble, ['points' => 21, 'rebounds' => 12, 'assists' => 10], 7, true],
            'record game' => [MomentKind::RecordGame, ['value' => 17], 7, true],
            'series clincher' => [MomentKind::SeriesClincher, ['round' => 3, 'wins' => 4, 'losses' => 2], 0, false],
            'trade' => [MomentKind::Trade, ['players_moved' => 1, 'mover_pid' => 7], 0, true],
        ];
    }

    /**
     * @param array<string, int> $facts
     */
    #[DataProvider('linkKindProvider')]
    public function testRendersEscapedBoxScoreAndPlayerLinksPerKind(
        MomentKind $kind,
        array $facts,
        int $pid,
        bool $hasPlayerLink,
    ): void {
        $html = $this->view->renderHomepageBlock($this->makeSelection([
            $this->makeMoment(1999, '1999-03-04', gameOfThatDay: 3, kind: $kind, pid: $pid, facts: $facts),
        ]));

        $this->assertStringContainsString(
            '<a href="modules.php?name=GameBoxscore&amp;date=1999-03-04&amp;game=3&amp;team=1">',
            $html,
        );
        if ($hasPlayerLink) {
            $this->assertStringContainsString(
                '<a href="modules.php?name=Player&amp;pa=showpage&amp;pid=7">',
                $html,
            );
        } else {
            $this->assertStringNotContainsString('name=Player', $html);
        }
    }

    public function testHrefNeverContainsRawAmpersand(): void
    {
        $html = $this->view->renderHomepageBlock($this->makeSelection([
            $this->makeMoment(1999, '1999-03-04', gameOfThatDay: 3, pid: 7),
        ]));

        $count = preg_match_all('/href="([^"]*)"/', $html, $matches);
        $this->assertGreaterThanOrEqual(2, $count);
        foreach ($matches[1] as $href) {
            $this->assertSame(0, preg_match('/&(?!amp;)/', $href), 'Raw ampersand in href: ' . $href);
        }
    }

    public function testUnknownOrdinalRendersNoBoxScoreLink(): void
    {
        $html = $this->view->renderHomepageBlock($this->makeSelection([
            $this->makeMoment(1999, '1999-03-04', gameOfThatDay: 0, pid: 7),
        ]));

        $this->assertStringNotContainsString('GameBoxscore', $html);
        $this->assertStringContainsString('scored 51 points against the Celtics', $html);
    }

    public function testEscapesXssInsideLinkText(): void
    {
        $html = $this->view->renderHomepageBlock($this->makeSelection([
            $this->makeMoment(1999, '1999-03-04', '<script>alert(1)</script>', gameOfThatDay: 3, pid: 7),
        ]));

        $this->assertMatchesRegularExpression('/<a href="[^"]*">&lt;script&gt;/', $html);
        $this->assertStringNotContainsString('<script', $html);
    }
}
