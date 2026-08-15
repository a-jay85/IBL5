<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\TradeOfferFormView;

class TradeOfferFormViewTest extends TestCase
{
    private TradeOfferFormView $view;

    protected function setUp(): void
    {
        $this->view = new TradeOfferFormView();
    }

    public function testRenderMatchesFacadeGolden(): void
    {
        // Reads the same golden file the facade test uses — proves the facade adds nothing
        $pageData = $this->createTradeOfferPageData();
        $html = $this->view->renderTradeOfferForm($pageData);
        $normalized = preg_replace(
            '/name="_csrf_token" value="[0-9a-f]{64}"/',
            'name="_csrf_token" value="__CSRF__"',
            $html
        ) ?? $html;
        $this->assertStringEqualsFile(
            __DIR__ . '/fixtures/trade-offer-form.golden.html',
            $normalized
        );
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
}
