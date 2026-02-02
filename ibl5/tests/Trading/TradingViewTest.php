<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\TradingView;

class TradingViewTest extends TestCase
{
    private TradingView $view;

    protected function setUp(): void
    {
        $this->view = new TradingView();
    }

    // ============================================
    // TRADE OFFER FORM TESTS
    // ============================================

    public function testRenderTradeOfferFormContainsFormElement(): void
    {
        $pageData = $this->createTradeOfferPageData();

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertStringContainsString('<form name="Trade_Offer"', $html);
        $this->assertStringContainsString('method="post"', $html);
        $this->assertStringContainsString('maketradeoffer.php', $html);
    }

    public function testRenderTradeOfferFormContainsBothTeamNames(): void
    {
        $pageData = $this->createTradeOfferPageData();

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertStringContainsString('Lakers', $html);
        $this->assertStringContainsString('Celtics', $html);
    }

    public function testRenderTradeOfferFormContainsTeamColorStyles(): void
    {
        $pageData = $this->createTradeOfferPageData();

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertStringContainsString('--team-color-primary: #552583', $html);
        $this->assertStringContainsString('--team-color-secondary: #FDB927', $html);
        $this->assertStringContainsString('--team-color-primary: #007A33', $html);
        $this->assertStringContainsString('team-table', $html);
    }

    public function testRenderTradeOfferFormContainsPlayerRows(): void
    {
        $pageData = $this->createTradeOfferPageData();
        $pageData['userPlayers'] = [
            ['pos' => 'PG', 'name' => 'LeBron James', 'pid' => '1', 'ordinal' => '5', 'cy' => '1', 'cy1' => '500', 'cy2' => '600', 'cy3' => '0', 'cy4' => '0', 'cy5' => '0', 'cy6' => '0'],
        ];

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertStringContainsString('LeBron James', $html);
        $this->assertStringContainsString('PG', $html);
        $this->assertStringContainsString('500', $html);
    }

    public function testRenderTradeOfferFormContainsDraftPickRows(): void
    {
        $pageData = $this->createTradeOfferPageData();
        $pageData['userPicks'] = [
            ['pickid' => '10', 'year' => '2025', 'teampick' => 'Lakers', 'round' => '1', 'notes' => null],
        ];

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertStringContainsString('2025', $html);
        $this->assertStringContainsString('Round 1', $html);
    }

    public function testRenderTradeOfferFormContainsCapTotals(): void
    {
        $pageData = $this->createTradeOfferPageData();

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertStringContainsString('Cap Totals', $html);
    }

    public function testRenderTradeOfferFormContainsCashExchange(): void
    {
        $pageData = $this->createTradeOfferPageData();

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertStringContainsString('Cash Exchange', $html);
        $this->assertStringContainsString('userSendsCash', $html);
        $this->assertStringContainsString('partnerSendsCash', $html);
    }

    public function testRenderTradeOfferFormContainsSubmitButton(): void
    {
        $pageData = $this->createTradeOfferPageData();

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertStringContainsString('Make Trade Offer', $html);
    }

    public function testRenderTradeOfferFormContainsHiddenFields(): void
    {
        $pageData = $this->createTradeOfferPageData();

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertStringContainsString('name="offeringTeam"', $html);
        $this->assertStringContainsString('name="listeningTeam"', $html);
        $this->assertStringContainsString('name="switchCounter"', $html);
        $this->assertStringContainsString('name="fieldsCounter"', $html);
    }

    public function testRenderTradeOfferFormEscapesTeamNames(): void
    {
        $pageData = $this->createTradeOfferPageData();
        $pageData['userTeam'] = 'Team <script>alert("xss")</script>';

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testRenderTradeOfferFormDisablesCheckboxForWaivedPlayers(): void
    {
        $pageData = $this->createTradeOfferPageData();
        $pageData['userPlayers'] = [
            ['pos' => 'PG', 'name' => 'Waived Player', 'pid' => '1', 'ordinal' => '999', 'cy' => '1', 'cy1' => '500', 'cy2' => '0', 'cy3' => '0', 'cy4' => '0', 'cy5' => '0', 'cy6' => '0'],
        ];

        $html = $this->view->renderTradeOfferForm($pageData);

        // Player with ordinal > WAIVERS_ORDINAL should have hidden input instead of checkbox
        $this->assertStringContainsString('type="hidden" name="check0"', $html);
    }

    // ============================================
    // TRADE REVIEW TESTS
    // ============================================

    public function testRenderTradeReviewShowsNoOffersMessage(): void
    {
        $pageData = $this->createTradeReviewPageData();
        $pageData['tradeOffers'] = [];

        $html = $this->view->renderTradeReview($pageData);

        $this->assertStringContainsString('No pending trade offers', $html);
    }

    public function testRenderTradeReviewShowsTradeOfferCards(): void
    {
        $pageData = $this->createTradeReviewPageData();
        $pageData['tradeOffers'] = [
            1 => [
                'from' => 'Lakers',
                'to' => 'Celtics',
                'approval' => 'Celtics',
                'oppositeTeam' => 'Celtics',
                'hasHammer' => false,
                'items' => [
                    ['type' => 'player', 'description' => 'The Lakers send PG LeBron James to the Celtics.', 'notes' => null, 'from' => 'Lakers', 'to' => 'Celtics'],
                ],
            ],
        ];

        $html = $this->view->renderTradeReview($pageData);

        $this->assertStringContainsString('Trade Offer #1', $html);
        $this->assertStringContainsString('LeBron James', $html);
    }

    public function testRenderTradeReviewShowsAcceptButtonWhenUserHasHammer(): void
    {
        $pageData = $this->createTradeReviewPageData();
        $pageData['tradeOffers'] = [
            1 => [
                'from' => 'Celtics',
                'to' => 'Lakers',
                'approval' => 'Lakers',
                'oppositeTeam' => 'Celtics',
                'hasHammer' => true,
                'items' => [
                    ['type' => 'player', 'description' => 'Trade item.', 'notes' => null, 'from' => 'Celtics', 'to' => 'Lakers'],
                ],
            ],
        ];

        $html = $this->view->renderTradeReview($pageData);

        $this->assertStringContainsString('Accept', $html);
        $this->assertStringContainsString('accepttradeoffer.php', $html);
    }

    public function testRenderTradeReviewShowsAwaitingApprovalWhenNoHammer(): void
    {
        $pageData = $this->createTradeReviewPageData();
        $pageData['tradeOffers'] = [
            1 => [
                'from' => 'Lakers',
                'to' => 'Celtics',
                'approval' => 'Celtics',
                'oppositeTeam' => 'Celtics',
                'hasHammer' => false,
                'items' => [
                    ['type' => 'player', 'description' => 'Trade item.', 'notes' => null, 'from' => 'Lakers', 'to' => 'Celtics'],
                ],
            ],
        ];

        $html = $this->view->renderTradeReview($pageData);

        $this->assertStringContainsString('Awaiting Approval', $html);
    }

    public function testRenderTradeReviewAlwaysShowsRejectButton(): void
    {
        $pageData = $this->createTradeReviewPageData();
        $pageData['tradeOffers'] = [
            1 => [
                'from' => 'Lakers',
                'to' => 'Celtics',
                'approval' => 'Celtics',
                'oppositeTeam' => 'Celtics',
                'hasHammer' => false,
                'items' => [
                    ['type' => 'player', 'description' => 'Trade item.', 'notes' => null, 'from' => 'Lakers', 'to' => 'Celtics'],
                ],
            ],
        ];

        $html = $this->view->renderTradeReview($pageData);

        $this->assertStringContainsString('Reject', $html);
        $this->assertStringContainsString('rejecttradeoffer.php', $html);
    }

    public function testRenderTradeReviewShowsPickNotes(): void
    {
        $pageData = $this->createTradeReviewPageData();
        $pageData['tradeOffers'] = [
            1 => [
                'from' => 'Lakers',
                'to' => 'Celtics',
                'approval' => 'Celtics',
                'oppositeTeam' => 'Celtics',
                'hasHammer' => false,
                'items' => [
                    ['type' => 'pick', 'description' => 'The Lakers send pick to Celtics.', 'notes' => 'Top 5 protected', 'from' => 'Lakers', 'to' => 'Celtics'],
                ],
            ],
        ];

        $html = $this->view->renderTradeReview($pageData);

        $this->assertStringContainsString('Top 5 protected', $html);
    }

    public function testRenderTradeReviewContainsTeamSelectionLinks(): void
    {
        $pageData = $this->createTradeReviewPageData();
        $pageData['teams'] = [
            ['name' => 'Heat', 'city' => 'Miami', 'fullName' => 'Miami Heat', 'teamid' => 5, 'color1' => '98002E', 'color2' => 'FFFFFF'],
        ];

        $html = $this->view->renderTradeReview($pageData);

        $this->assertStringContainsString('Make Trade Offer To...', $html);
        $this->assertStringContainsString('Miami Heat', $html);
    }

    public function testRenderTradeReviewEscapesTeamNames(): void
    {
        $pageData = $this->createTradeReviewPageData();
        $pageData['tradeOffers'] = [
            1 => [
                'from' => 'Lakers',
                'to' => 'Celtics',
                'approval' => 'Celtics',
                'oppositeTeam' => '<script>xss</script>',
                'hasHammer' => false,
                'items' => [
                    ['type' => 'player', 'description' => '<script>alert("xss")</script>', 'notes' => null, 'from' => 'Lakers', 'to' => 'Celtics'],
                ],
            ],
        ];

        $html = $this->view->renderTradeReview($pageData);

        $this->assertStringNotContainsString('<script>', $html);
    }

    // ============================================
    // RESULT BANNER TESTS
    // ============================================

    public function testRenderTradeOfferFormShowsSuccessBanner(): void
    {
        $pageData = $this->createTradeOfferPageData();
        $pageData['result'] = 'offer_sent';

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertStringContainsString('ibl-alert--success', $html);
        $this->assertStringContainsString('Trade offer sent!', $html);
    }

    public function testRenderTradeOfferFormShowsErrorBanner(): void
    {
        $pageData = $this->createTradeOfferPageData();
        $pageData['error'] = 'Trade exceeds salary cap';

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertStringContainsString('ibl-alert--error', $html);
        $this->assertStringContainsString('Trade exceeds salary cap', $html);
    }

    public function testRenderTradeOfferFormEscapesErrorBanner(): void
    {
        $pageData = $this->createTradeOfferPageData();
        $pageData['error'] = '<script>alert("xss")</script>';

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testRenderTradeReviewShowsAcceptedBanner(): void
    {
        $pageData = $this->createTradeReviewPageData();
        $pageData['result'] = 'trade_accepted';

        $html = $this->view->renderTradeReview($pageData);

        $this->assertStringContainsString('ibl-alert--success', $html);
        $this->assertStringContainsString('Trade accepted!', $html);
    }

    public function testRenderTradeReviewShowsRejectedBanner(): void
    {
        $pageData = $this->createTradeReviewPageData();
        $pageData['result'] = 'trade_rejected';

        $html = $this->view->renderTradeReview($pageData);

        $this->assertStringContainsString('ibl-alert--info', $html);
        $this->assertStringContainsString('Trade offer rejected.', $html);
    }

    public function testRenderTradeReviewShowsNoBannerByDefault(): void
    {
        $pageData = $this->createTradeReviewPageData();

        $html = $this->view->renderTradeReview($pageData);

        $this->assertStringNotContainsString('ibl-alert', $html);
    }

    // ============================================
    // TRADES CLOSED TESTS
    // ============================================

    public function testRenderTradesClosedShowsMessage(): void
    {
        $season = $this->createStub(\Season::class);
        $season->allowWaivers = 'No';

        $html = $this->view->renderTradesClosed($season);

        $this->assertStringContainsString('trades are not allowed right now', $html);
        $this->assertStringContainsString('waiver wire is also closed', $html);
    }

    public function testRenderTradesClosedShowsWaiverLinksWhenOpen(): void
    {
        $season = $this->createStub(\Season::class);
        $season->allowWaivers = 'Yes';

        $html = $this->view->renderTradesClosed($season);

        $this->assertStringContainsString('Added From Waivers', $html);
        $this->assertStringContainsString('Dropped to Waivers', $html);
    }

    // ============================================
    // TEAM SELECTION LINKS TESTS
    // ============================================

    public function testRenderTeamSelectionLinksShowsAllTeams(): void
    {
        $teams = [
            ['name' => 'Lakers', 'city' => 'Los Angeles', 'fullName' => 'Los Angeles Lakers', 'teamid' => 1, 'color1' => '552583', 'color2' => 'FDB927'],
            ['name' => 'Celtics', 'city' => 'Boston', 'fullName' => 'Boston Celtics', 'teamid' => 2, 'color1' => '007A33', 'color2' => 'FFFFFF'],
        ];

        $html = $this->view->renderTeamSelectionLinks($teams);

        $this->assertStringContainsString('Los Angeles Lakers', $html);
        $this->assertStringContainsString('Boston Celtics', $html);
        $this->assertStringContainsString('partner=Lakers', $html);
        $this->assertStringContainsString('partner=Celtics', $html);
    }

    public function testRenderTeamSelectionLinksReturnsTableWithHeader(): void
    {
        $teams = [
            ['name' => 'Heat', 'city' => 'Miami', 'fullName' => 'Miami Heat', 'teamid' => 3, 'color1' => '98002E', 'color2' => 'FFFFFF'],
        ];

        $html = $this->view->renderTeamSelectionLinks($teams);

        $this->assertStringContainsString('Make Trade Offer To...', $html);
        $this->assertStringContainsString('trading-team-select', $html);
    }

    // ============================================
    // HELPERS
    // ============================================

    private function createTradeOfferPageData(): array
    {
        return [
            'userTeam' => 'Lakers',
            'userTeamId' => 1,
            'partnerTeam' => 'Celtics',
            'partnerTeamId' => 2,
            'userPlayers' => [],
            'userPicks' => [],
            'userFutureSalary' => ['player' => [0, 0, 0, 0, 0, 0], 'hold' => [0, 0, 0, 0, 0, 0]],
            'partnerPlayers' => [],
            'partnerPicks' => [],
            'partnerFutureSalary' => ['player' => [0, 0, 0, 0, 0, 0], 'hold' => [0, 0, 0, 0, 0, 0]],
            'seasonEndingYear' => 2025,
            'seasonPhase' => 'Regular Season',
            'cashStartYear' => 1,
            'cashEndYear' => 6,
            'userTeamColor1' => '552583',
            'userTeamColor2' => 'FDB927',
            'partnerTeamColor1' => '007A33',
            'partnerTeamColor2' => 'FFFFFF',
            'result' => null,
            'error' => null,
        ];
    }

    private function createTradeReviewPageData(): array
    {
        return [
            'userTeam' => 'Lakers',
            'userTeamId' => 1,
            'tradeOffers' => [],
            'teams' => [],
            'result' => null,
            'error' => null,
        ];
    }
}
