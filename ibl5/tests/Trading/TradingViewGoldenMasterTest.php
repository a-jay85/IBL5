<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Season\Season;
use Trading\TradingView;

class TradingViewGoldenMasterTest extends TestCase
{
    private TradingView $view;

    protected function setUp(): void
    {
        $this->view = new TradingView();
    }

    public function testRenderTradeOfferFormOutputIsUnchanged(): void
    {
        $pageData = $this->createTradeOfferPageData();
        $html = $this->view->renderTradeOfferForm($pageData);
        $this->assertGolden(__DIR__ . '/fixtures/trade-offer-form.golden.html', $html);
    }

    public function testRenderTradeReviewOutputIsUnchanged(): void
    {
        $pageData = $this->createTradeReviewPageData();
        $pageData['tradeOffers'] = [
            1 => $this->createTradeOfferWithPreview(),
            2 => $this->createTradeOfferWithPreview([
                'hasHammer' => true,
                'oppositeTeam' => 'Heat',
            ]),
        ];
        $html = $this->view->renderTradeReview($pageData);
        $this->assertGolden(__DIR__ . '/fixtures/trade-review.golden.html', $html);
    }

    public function testRenderTradersClosedOutputIsUnchanged(): void
    {
        $season = self::createStub(Season::class);
        $season->method('areWaiversAllowed')->willReturn(true);
        $html = $this->view->renderTradesClosed($season);
        $this->assertGolden(__DIR__ . '/fixtures/trades-closed.golden.html', $html);
    }

    private static function normalizeCsrf(string $html): string
    {
        return preg_replace(
            '/name="_csrf_token" value="[0-9a-f]{64}"/',
            'name="_csrf_token" value="__CSRF__"',
            $html
        ) ?? $html;
    }

    private function assertGolden(string $path, string $html): void
    {
        if (getenv('IBL_GOLDEN_CAPTURE') === '1') {
            file_put_contents($path, self::normalizeCsrf($html));
        }
        $this->assertStringEqualsFile($path, self::normalizeCsrf($html));
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<string, mixed>
     */
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
     * @param array<string, mixed> $overrides
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

        if (isset($overrides['previewData'])) {
            $merged['previewData'] = array_merge($default['previewData'], $overrides['previewData']);
        }

        return $merged;
    }
}
