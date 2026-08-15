<?php

declare(strict_types=1);

namespace Trading;

use Trading\Contracts\TradingViewInterface;
use Season\Season;

/**
 * @see TradingViewInterface
 */
class TradingView implements TradingViewInterface
{
    private TradeOfferFormView $offerFormView;
    private TradeReviewView $reviewView;
    private TradeClosedView $closedView;

    public function __construct(
        ?TradeOfferFormView $offerFormView = null,
        ?TradeReviewView $reviewView = null,
        ?TradeClosedView $closedView = null
    ) {
        $this->offerFormView = $offerFormView ?? new TradeOfferFormView();
        $this->reviewView    = $reviewView    ?? new TradeReviewView();
        $this->closedView    = $closedView    ?? new TradeClosedView();
    }

    /**
     * @see TradingViewInterface::renderTradeOfferForm()
     *
     * @param array<string, mixed> $pageData
     */
    public function renderTradeOfferForm(array $pageData): string
    {
        return $this->offerFormView->renderTradeOfferForm($pageData);
    }

    /**
     * @see TradingViewInterface::renderTradeReview()
     *
     * @param array<string, mixed> $pageData
     */
    public function renderTradeReview(array $pageData): string
    {
        return $this->reviewView->renderTradeReview($pageData);
    }

    /**
     * @see TradingViewInterface::renderTradesClosed()
     */
    public function renderTradesClosed(Season $season): string
    {
        return $this->closedView->renderTradesClosed($season);
    }

    /**
     * @see TradingViewInterface::renderTeamSelectionLinks()
     *
     * @param list<array{name: string, city: string, fullName: string, teamid: int, color1: string, color2: string, mobileOrder: int}> $teams Conference-split, sorted, interleaved team list from TradingService::buildTeamList()
     */
    public function renderTeamSelectionLinks(array $teams): string
    {
        return $this->reviewView->renderTeamSelectionLinks($teams);
    }
}
