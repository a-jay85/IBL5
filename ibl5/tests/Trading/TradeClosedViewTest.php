<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Season\Season;
use Trading\TradeClosedView;

class TradeClosedViewTest extends TestCase
{
    private TradeClosedView $view;

    protected function setUp(): void
    {
        $this->view = new TradeClosedView();
    }

    public function testRenderMatchesFacadeGolden(): void
    {
        $season = self::createStub(Season::class);
        $season->method('areWaiversAllowed')->willReturn(true);
        $html = $this->view->renderTradesClosed($season);
        $this->assertStringEqualsFile(
            __DIR__ . '/fixtures/trades-closed.golden.html',
            $html
        );
    }

    public function testRenderWhenWaiversNotAllowed(): void
    {
        $season = self::createStub(Season::class);
        $season->method('areWaiversAllowed')->willReturn(false);
        $html = $this->view->renderTradesClosed($season);
        $this->assertStringContainsString('waiver wire is also closed', $html);
    }
}
