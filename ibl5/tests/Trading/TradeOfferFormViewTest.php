<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\TradeOfferFormView;

class TradeOfferFormViewTest extends TestCase
{
    /** Hostile input: apostrophe, angle brackets, ampersand, double quotes. */
    private const HOSTILE = 'O\'Neal <xss>&"q"';
    /** HtmlSanitizer::safeHtmlOutput(self::HOSTILE) under ENT_QUOTES|ENT_HTML5. */
    private const ESCAPED = 'O&apos;Neal &lt;xss&gt;&amp;&quot;q&quot;';

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

    public function testHostileTeamNamesEscapedExactlyOnceAtEverySite(): void
    {
        $pageData = $this->createTradeOfferPageData();
        $pageData['userTeam'] = 'U' . self::HOSTILE;
        $pageData['partnerTeam'] = 'P' . self::HOSTILE;

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertGreaterThanOrEqual(3, substr_count($html, 'U' . self::ESCAPED));
        $this->assertGreaterThanOrEqual(3, substr_count($html, 'P' . self::ESCAPED));
        $this->assertStringNotContainsString('<xss>', $html);
        $this->assertStringNotContainsString('&amp;lt;', $html);
    }

    public function testHostileRosterRowFieldsEscapedExactlyOnce(): void
    {
        $pageData = $this->createTradeOfferPageData();
        $pageData['userPlayers'] = [
            ['pos' => 'G' . self::HOSTILE, 'name' => 'Plain Player', 'pid' => 100, 'ordinal' => 1, 'cy' => 1, 'salary_yr1' => 500, 'salary_yr2' => 0, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0],
        ];
        $pageData['userPicks'] = [
            ['pickid' => 7, 'ownerofpick' => 'Lakers', 'teampick' => 'K' . self::HOSTILE, 'teampick_id' => 3, 'year' => '2026', 'round' => '1', 'notes' => 'N' . self::HOSTILE, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
        ];

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertGreaterThanOrEqual(1, substr_count($html, 'G' . self::ESCAPED));
        $this->assertGreaterThanOrEqual(1, substr_count($html, 'N' . self::ESCAPED));
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'K' . self::ESCAPED));
        $this->assertStringNotContainsString('<xss>', $html);
        $this->assertStringNotContainsString('&amp;lt;', $html);
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
