<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Season\Season;
use Trading\TradingView;
use Trading\TradeOfferFormView;
use Trading\TradeReviewView;
use Trading\TradeClosedView;

class TradingViewFacadeTest extends TestCase
{
    public function testDelegatesRenderTradeOfferFormToSubView(): void
    {
        $subView = $this->createMock(TradeOfferFormView::class);
        $subView->expects($this->once())
            ->method('renderTradeOfferForm')
            ->with(['key' => 'value'])
            ->willReturn('<html>form</html>');

        $facade = new TradingView(offerFormView: $subView);
        $result = $facade->renderTradeOfferForm(['key' => 'value']);
        $this->assertSame('<html>form</html>', $result);
    }

    public function testDelegatesRenderTradeReviewToSubView(): void
    {
        $subView = $this->createMock(TradeReviewView::class);
        $subView->expects($this->once())
            ->method('renderTradeReview')
            ->with(['key' => 'value'])
            ->willReturn('<html>review</html>');

        $facade = new TradingView(reviewView: $subView);
        $result = $facade->renderTradeReview(['key' => 'value']);
        $this->assertSame('<html>review</html>', $result);
    }

    public function testDelegatesRenderTeamSelectionLinksToSubView(): void
    {
        $subView = $this->createMock(TradeReviewView::class);
        $subView->expects($this->once())
            ->method('renderTeamSelectionLinks')
            ->with([])
            ->willReturn('<html>teams</html>');

        $facade = new TradingView(reviewView: $subView);
        $result = $facade->renderTeamSelectionLinks([]);
        $this->assertSame('<html>teams</html>', $result);
    }

    public function testDelegatesRenderTradesToSubView(): void
    {
        $season = self::createStub(Season::class);
        $subView = $this->createMock(TradeClosedView::class);
        $subView->expects($this->once())
            ->method('renderTradesClosed')
            ->with($season)
            ->willReturn('<html>closed</html>');

        $facade = new TradingView(closedView: $subView);
        $result = $facade->renderTradesClosed($season);
        $this->assertSame('<html>closed</html>', $result);
    }
}
