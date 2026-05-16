<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\TradingView;

class TradingViewXssTest extends TestCase
{
    private TradingView $view;

    protected function setUp(): void
    {
        $this->view = new TradingView();
    }

    public function testTradeOfferFormEscapesTeamNames(): void
    {
        $xssPayload = '<script>alert("xss")</script>';

        $pageData = [
            'userTeam' => $xssPayload,
            'userTeamId' => 1,
            'partnerTeam' => $xssPayload,
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

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('&lt;script&gt;alert', $html);
    }

    public function testTradeReviewEscapesTeamAndDescription(): void
    {
        $xssPayload = '<script>alert("xss")</script>';

        $pageData = [
            'userTeam' => $xssPayload,
            'userTeamId' => 1,
            'tradeOffers' => [
                1 => [
                    'from' => 'Lakers',
                    'to' => 'Celtics',
                    'approval' => 'Celtics',
                    'oppositeTeam' => $xssPayload,
                    'hasHammer' => true,
                    'items' => [
                        ['type' => 'player', 'description' => $xssPayload, 'notes' => $xssPayload, 'from' => 'Lakers', 'to' => 'Celtics'],
                    ],
                    'previewData' => [
                        'fromPids' => [],
                        'toPids' => [],
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
                ],
            ],
            'teams' => [],
            'result' => null,
            'error' => null,
        ];

        $html = $this->view->renderTradeReview($pageData);

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('&lt;script&gt;alert', $html);
    }

    public function testTradeOfferFormEscapesPickNotes(): void
    {
        $xssPayload = '<img onerror="alert(1)" src=x>';

        $pageData = [
            'userTeam' => 'Lakers',
            'userTeamId' => 1,
            'partnerTeam' => 'Celtics',
            'partnerTeamId' => 2,
            'userPlayers' => [],
            'userPicks' => [
                ['pickid' => 1, 'year' => 2025, 'teampick' => 'Lakers', 'teampick_id' => 1, 'round' => 1, 'notes' => $xssPayload],
            ],
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

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertStringNotContainsString('onerror="alert(1)"', $html);
        $this->assertStringContainsString('&lt;img onerror=', $html);
    }
}
