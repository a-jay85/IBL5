<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\TradeReviewView;

class TradeReviewViewTest extends TestCase
{
    /** Hostile input: apostrophe, angle brackets, ampersand, double quotes. */
    private const HOSTILE = 'O\'Neal <xss>&"q"';
    /** HtmlSanitizer::safeHtmlOutput(self::HOSTILE) under ENT_QUOTES|ENT_HTML5. */
    private const ESCAPED = 'O&apos;Neal &lt;xss&gt;&amp;&quot;q&quot;';

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

    public function testHostileTeamNamesAndItemTextEscapedExactlyOnce(): void
    {
        $pageData = [
            'userTeam' => 'U' . self::HOSTILE,
            'userTeamId' => 1,
            'tradeOffers' => [
                1 => $this->createTradeOffer([
                    'oppositeTeam' => 'O' . self::HOSTILE,
                    'items' => [
                        ['type' => 'player', 'description' => 'D' . self::HOSTILE, 'notes' => 'N' . self::HOSTILE, 'from' => 'Lakers', 'to' => 'Heat'],
                    ],
                ]),
            ],
            'teams' => [],
            'result' => null,
            'error' => null,
        ];

        $html = $this->view->renderTradeReview($pageData);

        foreach (['U', 'O', 'D', 'N'] as $prefix) {
            $this->assertGreaterThanOrEqual(1, substr_count($html, $prefix . self::ESCAPED), $prefix);
        }
        $this->assertStringNotContainsString('<xss>', $html);
        $this->assertStringNotContainsString('&amp;lt;', $html);
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
