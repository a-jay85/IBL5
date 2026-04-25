<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\TradingView;
use Season\Season;

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
            ['pos' => 'PG', 'name' => 'LeBron James', 'pid' => 1, 'ordinal' => 5, 'cy' => 1, 'salary_yr1' => 500, 'salary_yr2' => 600, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0],
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
            ['pickid' => 10, 'year' => '2025', 'teampick' => 'Lakers', 'teampick_id' => 5, 'round' => '1', 'notes' => null, 'ownerofpick' => 'Lakers', 'created_at' => '', 'updated_at' => ''],
        ];

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertStringContainsString('2025', $html);
        $this->assertStringContainsString('R1', $html);
    }

    public function testRenderTradeOfferFormContainsCashExchange(): void
    {
        $pageData = $this->createTradeOfferPageData();

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertStringContainsString('data-panel="cash"', $html);
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

        // The XSS payload should be HTML-escaped in the page content
        $this->assertStringContainsString('&lt;script&gt;', $html);
        // The unescaped XSS payload should NOT appear in user-visible areas
        // (legitimate <script> tags for JS config are expected)
        $this->assertStringNotContainsString('<script>alert', $html);
    }

    public function testRenderTradeOfferFormDisablesCheckboxForWaivedPlayers(): void
    {
        $pageData = $this->createTradeOfferPageData();
        $pageData['userPlayers'] = [
            ['pos' => 'PG', 'name' => 'Waived Player', 'pid' => 1, 'ordinal' => 999, 'cy' => 1, 'salary_yr1' => 500, 'salary_yr2' => 0, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0],
        ];

        $html = $this->view->renderTradeOfferForm($pageData);

        // Player with ordinal > WAIVERS_ORDINAL should have hidden input instead of checkbox
        $this->assertStringContainsString('type="hidden" name="check0"', $html);
    }

    // ============================================
    // COLLAPSIBLE ROSTER DETAILS TESTS
    // ============================================

    public function testRenderTradeOfferFormUsesDetailsElements(): void
    {
        $pageData = $this->createTradeOfferPageData();

        $html = $this->view->renderTradeOfferForm($pageData);

        // Two collapsible <details> elements should wrap each roster
        $this->assertSame(2, substr_count($html, '<details class="trading-roster-details"'));
        $this->assertStringContainsString('trading-roster-details__summary', $html);
        $this->assertStringContainsString('trading-roster-details__chevron', $html);
    }

    public function testRenderTradeOfferFormLogoInSummaryNotThead(): void
    {
        $pageData = $this->createTradeOfferPageData();

        $html = $this->view->renderTradeOfferForm($pageData);

        // Logo banner in <thead> should be gone
        $this->assertStringNotContainsString('team-logo-banner', $html);
        $this->assertStringNotContainsString('<th colspan="4">', $html);

        // Logo should be in <summary> instead
        $this->assertStringContainsString('trading-roster-details__logo', $html);
    }

    public function testRenderTradeOfferFormSubmitUsesClass(): void
    {
        $pageData = $this->createTradeOfferPageData();

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertStringContainsString('class="trading-layout__submit"', $html);
        // Should not have inline style on submit div
        $this->assertStringNotContainsString('style="text-align: center; padding: 1rem;"', $html);
    }

    // ============================================
    // TRADE REVIEW SEMANTIC LAYOUT TESTS
    // ============================================

    public function testRenderTradeReviewUsesSemanticLayout(): void
    {
        $pageData = $this->createTradeReviewPageData();

        $html = $this->view->renderTradeReview($pageData);

        // Old table layout should be gone
        $this->assertStringNotContainsString('<table class="trading-layout"', $html);
        $this->assertStringNotContainsString('style="margin: 0 auto;"', $html);

        // New semantic classes should be present
        $this->assertStringContainsString('trading-layout__header', $html);
        $this->assertStringContainsString('trading-review-wrapper', $html);
        $this->assertStringContainsString('trading-review-offers', $html);
    }

    public function testRenderTradeOfferCardUsesClasses(): void
    {
        $pageData = $this->createTradeReviewPageData();
        $pageData['tradeOffers'] = [
            1 => $this->createTradeOfferWithPreview([
                'items' => [
                    ['type' => 'player', 'description' => 'Trade item.', 'notes' => 'Some note', 'from' => 'Lakers', 'to' => 'Celtics'],
                ],
            ]),
        ];

        $html = $this->view->renderTradeReview($pageData);

        // New class names should be present
        $this->assertStringContainsString('trade-offer-card__header', $html);
        $this->assertStringContainsString('trade-offer-card__actions', $html);
        $this->assertStringContainsString('trade-offer-card__notes', $html);
        $this->assertStringContainsString('trade-offer-card__preview-wrap', $html);

        // Old inline styles should be gone
        $this->assertStringNotContainsString('style="margin-bottom: 0.5rem;"', $html);
        $this->assertStringNotContainsString('style="display: flex; justify-content: center;', $html);
        $this->assertStringNotContainsString('style="margin-left: 1rem; font-style: italic;', $html);
    }

    // ============================================
    // TRADE REVIEW TESTS
    // ============================================

    public function testRenderTradeReviewShowsTradeOfferCards(): void
    {
        $pageData = $this->createTradeReviewPageData();
        $pageData['tradeOffers'] = [
            1 => $this->createTradeOfferWithPreview([
                'items' => [
                    ['type' => 'player', 'description' => 'The Lakers send PG LeBron James to the Celtics.', 'notes' => null, 'from' => 'Lakers', 'to' => 'Celtics'],
                ],
            ]),
        ];

        $html = $this->view->renderTradeReview($pageData);

        $this->assertStringContainsString('Trade Offer #1', $html);
        $this->assertStringContainsString('LeBron James', $html);
    }

    public function testRenderTradeReviewShowsAcceptButtonWhenUserHasHammer(): void
    {
        $pageData = $this->createTradeReviewPageData();
        $pageData['tradeOffers'] = [
            1 => $this->createTradeOfferWithPreview([
                'from' => 'Celtics',
                'to' => 'Lakers',
                'approval' => 'Lakers',
                'oppositeTeam' => 'Celtics',
                'hasHammer' => true,
                'items' => [
                    ['type' => 'player', 'description' => 'Trade item.', 'notes' => null, 'from' => 'Celtics', 'to' => 'Lakers'],
                ],
            ]),
        ];

        $html = $this->view->renderTradeReview($pageData);

        $this->assertStringContainsString('Accept', $html);
        $this->assertStringContainsString('accepttradeoffer.php', $html);
    }

    public function testRenderTradeReviewShowsAwaitingApprovalWhenNoHammer(): void
    {
        $pageData = $this->createTradeReviewPageData();
        $pageData['tradeOffers'] = [
            1 => $this->createTradeOfferWithPreview([
                'items' => [
                    ['type' => 'player', 'description' => 'Trade item.', 'notes' => null, 'from' => 'Lakers', 'to' => 'Celtics'],
                ],
            ]),
        ];

        $html = $this->view->renderTradeReview($pageData);

        $this->assertStringContainsString('Awaiting Approval', $html);
    }

    public function testRenderTradeReviewAlwaysShowsRejectButton(): void
    {
        $pageData = $this->createTradeReviewPageData();
        $pageData['tradeOffers'] = [
            1 => $this->createTradeOfferWithPreview([
                'items' => [
                    ['type' => 'player', 'description' => 'Trade item.', 'notes' => null, 'from' => 'Lakers', 'to' => 'Celtics'],
                ],
            ]),
        ];

        $html = $this->view->renderTradeReview($pageData);

        $this->assertStringContainsString('Reject', $html);
        $this->assertStringContainsString('rejecttradeoffer.php', $html);
    }

    public function testRenderTradeReviewShowsPickNotes(): void
    {
        $pageData = $this->createTradeReviewPageData();
        $pageData['tradeOffers'] = [
            1 => $this->createTradeOfferWithPreview([
                'items' => [
                    ['type' => 'pick', 'description' => 'The Lakers send pick to Celtics.', 'notes' => 'Top 5 protected', 'from' => 'Lakers', 'to' => 'Celtics'],
                ],
            ]),
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

        $this->assertStringContainsString('West', $html);
        $this->assertStringContainsString('East', $html);
        $this->assertStringContainsString('Miami Heat', $html);
    }

    public function testRenderTradeReviewEscapesTeamNames(): void
    {
        $pageData = $this->createTradeReviewPageData();
        $pageData['tradeOffers'] = [
            1 => $this->createTradeOfferWithPreview([
                'oppositeTeam' => '<script>xss</script>',
                'items' => [
                    ['type' => 'player', 'description' => '<script>alert("xss")</script>', 'notes' => null, 'from' => 'Lakers', 'to' => 'Celtics'],
                ],
            ]),
        ];

        $html = $this->view->renderTradeReview($pageData);

        // The unescaped XSS payload should NOT appear — legitimate <script> tags for JS config are expected
        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringNotContainsString('<script>xss', $html);
    }

    // ============================================
    // ROSTER PREVIEW PANEL TESTS
    // ============================================

    public function testRenderTradeOfferFormContainsRosterPreviewPanel(): void
    {
        $pageData = $this->createTradeOfferPageData();

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertStringContainsString('id="trade-roster-preview"', $html);
        $this->assertStringContainsString('trade-roster-preview', $html);
        $this->assertStringContainsString('Roster Preview', $html);
        $this->assertStringContainsString('trade-roster-preview__tabs', $html);
        $this->assertStringContainsString('data-display="ratings"', $html);
        $this->assertStringContainsString('data-display="contracts"', $html);
    }

    public function testRenderTradeOfferFormContainsRosterPreviewLogos(): void
    {
        $pageData = $this->createTradeOfferPageData();

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertStringContainsString('trade-roster-preview__logo', $html);
        $this->assertStringContainsString('data-team-id="1"', $html);
        $this->assertStringContainsString('data-team-id="2"', $html);
    }

    public function testRenderTradeOfferFormConfigContainsRosterPreviewApiUrl(): void
    {
        $pageData = $this->createTradeOfferPageData();

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertStringContainsString('rosterPreviewApiBaseUrl', $html);
        $this->assertStringContainsString('roster-preview-api', $html);
    }

    public function testRenderTradeOfferFormLoadsRosterPreviewJs(): void
    {
        $pageData = $this->createTradeOfferPageData();

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertStringContainsString('trade-roster-preview.js', $html);
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
        $season = $this->createStub(Season::class);
        $season->method('areWaiversAllowed')->willReturn(false);

        $html = $this->view->renderTradesClosed($season);

        $this->assertStringContainsString('trades are not allowed right now', $html);
        $this->assertStringContainsString('waiver wire is also closed', $html);
    }

    public function testRenderTradesClosedShowsWaiverLinksWhenOpen(): void
    {
        $season = $this->createStub(Season::class);
        $season->method('areWaiversAllowed')->willReturn(true);

        $html = $this->view->renderTradesClosed($season);

        $this->assertStringContainsString('Added From Waivers', $html);
        $this->assertStringContainsString('Waived', $html);
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

        $this->assertStringContainsString('West', $html);
        $this->assertStringContainsString('East', $html);
        $this->assertStringContainsString('trading-team-select', $html);
    }

    // ============================================
    // REVIEW PAGE PREVIEW TESTS
    // ============================================

    public function testRenderTradeReviewShowsPreviewButton(): void
    {
        $pageData = $this->createTradeReviewPageData();
        $pageData['tradeOffers'] = [
            1 => $this->createTradeOfferWithPreview(),
        ];

        $html = $this->view->renderTradeReview($pageData);

        $this->assertStringContainsString('data-preview-offer="1"', $html);
        $this->assertStringContainsString('ibl-btn--neutral', $html);
        $this->assertStringContainsString('>Preview</button>', $html);
    }

    public function testRenderTradeReviewShowsPreviewPanel(): void
    {
        $pageData = $this->createTradeReviewPageData();
        $pageData['tradeOffers'] = [
            1 => $this->createTradeOfferWithPreview(),
        ];

        $html = $this->view->renderTradeReview($pageData);

        $this->assertStringContainsString('id="trade-review-preview-1"', $html);
        $this->assertStringContainsString('trade-roster-preview__logo', $html);
        $this->assertStringContainsString('data-display="ratings"', $html);
        $this->assertStringContainsString('data-display="contracts"', $html);
        $this->assertStringContainsString('Roster Preview', $html);
    }

    public function testRenderTradeReviewLoadsPreviewJs(): void
    {
        $pageData = $this->createTradeReviewPageData();
        $pageData['tradeOffers'] = [
            1 => $this->createTradeOfferWithPreview(),
        ];

        $html = $this->view->renderTradeReview($pageData);

        $this->assertStringContainsString('trade-review-preview.js', $html);
    }

    public function testRenderTradeReviewInjectsConfig(): void
    {
        $pageData = $this->createTradeReviewPageData();
        $pageData['tradeOffers'] = [
            1 => $this->createTradeOfferWithPreview(),
        ];

        $html = $this->view->renderTradeReview($pageData);

        $this->assertStringContainsString('IBL_TRADE_REVIEW_CONFIGS', $html);
        $this->assertStringContainsString('rosterPreviewApiBaseUrl', $html);
        $this->assertStringContainsString('roster-preview-api', $html);
    }

    public function testRenderTradeReviewPreviewButtonBelowTradeItems(): void
    {
        $pageData = $this->createTradeReviewPageData();
        $pageData['tradeOffers'] = [
            1 => $this->createTradeOfferWithPreview([
                'items' => [
                    ['type' => 'player', 'description' => 'The Lakers send PG LeBron James to the Celtics.', 'notes' => null, 'from' => 'Lakers', 'to' => 'Celtics'],
                ],
            ]),
        ];

        $html = $this->view->renderTradeReview($pageData);

        // Preview button should appear after trade-offer-items and before the panel
        $itemsPos = strpos($html, 'trade-offer-items');
        $buttonPos = strpos($html, 'data-preview-offer="1"');
        $panelPos = strpos($html, 'trade-review-preview-1');

        $this->assertNotFalse($itemsPos);
        $this->assertNotFalse($buttonPos);
        $this->assertNotFalse($panelPos);
        $this->assertGreaterThan($itemsPos, $buttonPos);
        $this->assertGreaterThan($buttonPos, $panelPos);
    }

    public function testRenderTradeReviewPreviewPanelUsesTeamLogos(): void
    {
        $pageData = $this->createTradeReviewPageData();
        $pageData['tradeOffers'] = [
            1 => $this->createTradeOfferWithPreview([
                'previewData' => [
                    'fromPids' => [100],
                    'toPids' => [200],
                    'fromTeamId' => 5,
                    'toTeamId' => 10,
                    'fromColor1' => '552583',
                    'toColor1' => '007A33',
                    'fromCash' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0],
                    'toCash' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0],
                    'cashStartYear' => 1,
                    'cashEndYear' => 6,
                    'seasonEndingYear' => 2025,
                ],
            ]),
        ];

        $html = $this->view->renderTradeReview($pageData);

        $this->assertStringContainsString('data-team-id="5"', $html);
        $this->assertStringContainsString('data-team-id="10"', $html);
        $this->assertStringContainsString('images/logo/new5.png', $html);
        $this->assertStringContainsString('images/logo/new10.png', $html);
    }

    public function testRenderTradeReviewNoScriptWhenNoOffers(): void
    {
        $pageData = $this->createTradeReviewPageData();
        $pageData['tradeOffers'] = [];

        $html = $this->view->renderTradeReview($pageData);

        $this->assertStringNotContainsString('IBL_TRADE_REVIEW_CONFIGS', $html);
        $this->assertStringNotContainsString('trade-review-preview.js', $html);
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

    /**
     * Create a trade offer array with previewData for review page tests.
     *
     * @param array<string, mixed> $overrides Fields to override
     * @return array<string, mixed>
     */
    private function createTradeOfferWithPreview(array $overrides = []): array
    {
        $default = [
            'from' => 'Lakers',
            'to' => 'Celtics',
            'approval' => 'Celtics',
            'oppositeTeam' => 'Celtics',
            'hasHammer' => false,
            'items' => [
                ['type' => 'player', 'description' => 'Trade item.', 'notes' => null, 'from' => 'Lakers', 'to' => 'Celtics'],
            ],
            'previewData' => [
                'fromPids' => [100],
                'toPids' => [200],
                'fromTeamId' => 1,
                'toTeamId' => 2,
                'fromColor1' => '552583',
                'toColor1' => '007A33',
                'fromCash' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0],
                'toCash' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0],
                'cashStartYear' => 1,
                'cashEndYear' => 6,
                'seasonEndingYear' => 2025,
            ],
        ];

        $merged = array_merge($default, $overrides);

        // Deep-merge previewData if provided as override
        if (isset($overrides['previewData'])) {
            $merged['previewData'] = array_merge($default['previewData'], $overrides['previewData']);
        }

        return $merged;
    }
}
