<?php

declare(strict_types=1);

namespace Tests\OnThisDay;

use OnThisDay\Moment;
use OnThisDay\MomentKind;
use OnThisDay\OnThisDaySelection;
use OnThisDay\OnThisDayView;
use PHPUnit\Framework\TestCase;

class OnThisDayViewTest extends TestCase
{
    private OnThisDayView $view;

    protected function setUp(): void
    {
        $this->view = new OnThisDayView();
    }

    private function makeMoment(
        int $seasonYear,
        string $date,
        string $playerName = 'Test Player',
        string $teamName = 'Hawks',
    ): Moment {
        return new Moment(
            kind: MomentKind::FiftyPointGame,
            key: 'fifty_point:' . $date . ':1',
            date: $date,
            seasonYear: $seasonYear,
            pid: 1,
            playerName: $playerName,
            teamId: 1,
            teamName: $teamName,
            opponentTeamId: 2,
            opponentName: 'Celtics',
            facts: ['points' => 51],
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
}
