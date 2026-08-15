<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\TradeReviewView;

class TradeReviewViewTest extends TestCase
{
    private TradeReviewView $view;

    protected function setUp(): void
    {
        $this->view = new TradeReviewView();
    }

    public function testRenderMatchesFacadeGolden(): void
    {
        $pageData = [
            'userTeam' => 'Lakers',
            'userTeamId' => 1,
            'tradeOffers' => [
                1 => $this->createTradeOffer(),
                2 => $this->createTradeOffer(['hasHammer' => true, 'oppositeTeam' => 'Heat']),
            ],
            'teams' => [],
            'result' => null,
            'error' => null,
        ];
        $html = $this->view->renderTradeReview($pageData);
        $normalized = preg_replace(
            '/name="_csrf_token" value="[0-9a-f]{64}"/',
            'name="_csrf_token" value="__CSRF__"',
            $html
        ) ?? $html;
        $this->assertStringEqualsFile(
            __DIR__ . '/fixtures/trade-review.golden.html',
            $normalized
        );
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function createTradeOffer(array $overrides = []): array
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
        return array_merge($default, $overrides);
    }
}
