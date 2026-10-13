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

    public function testPlayerCellLinkRendersThumbnailAndEscapedName(): void
    {
        $pageData = $this->createTradeOfferPageData();
        $pageData['userPlayers'] = [
            $this->playerRow(100, self::HOSTILE),
            $this->playerRow(101, 'A|B'),
        ];

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertStringContainsString('<a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=100"><img', $html);
        $this->assertSame(1, preg_match('/pid=100"><img[^>]*>' . preg_quote(self::ESCAPED, '/') . '<\/a>/', $html));
        $this->assertStringContainsString('pid=101">AB</a>', $html);
        $this->assertStringNotContainsString('<xss>', $html);
        $this->assertStringNotContainsString('&amp;lt;', $html);
    }

    public function testFieldCountersMatchRowOrder(): void
    {
        $pageData = $this->createTradeOfferPageData();
        $pageData['userPlayers'] = [$this->playerRow(100, 'One'), $this->playerRow(101, 'Two')];
        $pageData['userPicks'] = [
            ['pickid' => 7, 'ownerofpick' => 'Lakers', 'teampick' => 'Lakers', 'teampick_id' => 3, 'year' => '2026', 'round' => '1', 'notes' => null, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
        ];
        $pageData['partnerPlayers'] = [$this->playerRow(200, 'Three')];
        $pageData['partnerPicks'] = [
            ['pickid' => 8, 'ownerofpick' => 'Celtics', 'teampick' => 'Celtics', 'teampick_id' => 4, 'year' => '2026', 'round' => '2', 'notes' => null, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
        ];

        $html = $this->view->renderTradeOfferForm($pageData);

        $this->assertStringContainsString('name="switchCounter" value="3"', $html);
        $this->assertStringContainsString('name="fieldsCounter" value="4"', $html);
        foreach (range(0, 4) as $index) {
            $this->assertSame(1, substr_count($html, 'name="index' . $index . '"'), 'index' . $index);
        }
        $this->assertStringNotContainsString('name="index5"', $html);
    }

    public function testPopulatedRenderMatchesGolden(): void
    {
        $html = $this->view->renderTradeOfferForm($this->createPopulatedTradeOfferPageData());
        $this->assertGolden(__DIR__ . '/fixtures/trade-offer-form-populated.golden.html', $html);
    }

    public function testCsrfInputRendersExactlyOnceDirectlyAfterFormOpen(): void
    {
        $html = $this->view->renderTradeOfferForm($this->createPopulatedTradeOfferPageData());

        $this->assertSame(1, substr_count($html, 'name="_csrf_token"'));
        $this->assertSame(1, preg_match(
            '/<form name="Trade_Offer" method="post" action="\/ibl5\/modules\/Trading\/maketradeoffer\.php">\n[ \t]*<input type="hidden" name="_csrf_token" value="[0-9a-f]{64}">\s*<input type="hidden" name="offeringTeam" value="/',
            $html
        ));
    }

    public function testHiddenFieldsKeepOfferingSwitchListeningFieldsOrder(): void
    {
        $html = $this->view->renderTradeOfferForm($this->createPopulatedTradeOfferPageData());

        $offering = strpos($html, 'name="offeringTeam" value="Lakers &amp; Co"');
        $switch = strpos($html, 'name="switchCounter" value="5"');
        $listening = strpos($html, 'name="listeningTeam" value="Celtics"');
        $fields = strpos($html, 'name="fieldsCounter" value="7"');
        $this->assertNotFalse($offering);
        $this->assertNotFalse($switch);
        $this->assertNotFalse($listening);
        $this->assertNotFalse($fields);
        $this->assertLessThan($switch, $offering);
        $this->assertLessThan($listening, $switch);
        $this->assertLessThan($fields, $listening);
        foreach (['offeringTeam', 'switchCounter', 'listeningTeam', 'fieldsCounter'] as $name) {
            $this->assertSame(1, substr_count($html, 'name="' . $name . '"'), $name);
        }
        $this->assertSame(3, substr_count($html, ' checked>'));
        $this->assertSame(3, preg_match_all('/<input type="hidden" name="check\d+">/', $html), 'hidden checkN inputs');
    }

    public function testViewIsStateFreeAndTakesNoCollaborators(): void
    {
        $reflection = new \ReflectionClass(TradeOfferFormView::class);

        $this->assertSame([], $reflection->getProperties());
        $this->assertNull($reflection->getConstructor());
        foreach ($reflection->getMethods() as $method) {
            foreach ($method->getParameters() as $parameter) {
                $type = $parameter->getType();
                $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : '';
                $this->assertNotSame('mysqli', ltrim($typeName, '\\'), $method->getName() . '::$' . $parameter->getName());
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function playerRow(int $pid, string $name): array
    {
        return ['pos' => 'G', 'name' => $name, 'pid' => $pid, 'ordinal' => 1, 'cy' => 1, 'salary_yr1' => 500, 'salary_yr2' => 0, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0];
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
     * Every branch the empty-roster golden leaves unpinned: both teams populated,
     * offseason phase (cy advances), checked rows, hidden-check rows (waived and
     * zero-contract), picks with and without notes, prefilled cash on both sides,
     * and a result/error alert.
     *
     * @return array<string, mixed>
     */
    private function createPopulatedTradeOfferPageData(): array
    {
        $pageData = $this->createTradeOfferPageData();
        $pageData['seasonPhase'] = 'Playoffs';
        $pageData['userTeam'] = 'Lakers & Co';
        $pageData['userPlayers'] = [
            ['pos' => 'G', 'name' => 'Kobe & Bryant', 'pid' => 999001, 'ordinal' => 1, 'cy' => 1, 'salary_yr1' => 500, 'salary_yr2' => 750, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0],
            ['pos' => 'F', 'name' => 'Waived Forward', 'pid' => 999002, 'ordinal' => \League\JsbConstants::WAIVERS_ORDINAL + 1, 'cy' => 1, 'salary_yr1' => 500, 'salary_yr2' => 400, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0],
            ['pos' => 'C', 'name' => 'Zero Deal', 'pid' => 999003, 'ordinal' => 2, 'cy' => 1, 'salary_yr1' => 0, 'salary_yr2' => 0, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0],
        ];
        $pageData['userPicks'] = [
            ['pickid' => 7, 'ownerofpick' => 'Lakers', 'teampick' => 'Lakers', 'teampick_id' => 1, 'year' => '2026', 'round' => '1', 'notes' => 'Via <Nets> & "swap"', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
            ['pickid' => 8, 'ownerofpick' => 'Lakers', 'teampick' => 'Knicks', 'teampick_id' => 5, 'year' => '2027', 'round' => '2', 'notes' => null, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
        ];
        $pageData['partnerPlayers'] = [
            ['pos' => 'F', 'name' => 'Paul "Pierce"', 'pid' => 999004, 'ordinal' => 1, 'cy' => 6, 'salary_yr1' => 0, 'salary_yr2' => 0, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 900],
            ['pos' => 'G', 'name' => 'Rookie|Pipe', 'pid' => 999005, 'ordinal' => 2, 'cy' => 0, 'salary_yr1' => 600, 'salary_yr2' => 0, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0],
        ];
        $pageData['partnerPicks'] = [
            ['pickid' => 9, 'ownerofpick' => 'Celtics', 'teampick' => 'Celtics', 'teampick_id' => 2, 'year' => '2026', 'round' => '2', 'notes' => '', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
        ];
        $pageData['previousFormData'] = [
            'checkedItems' => ['1:999001' => true, '0:7' => true, '1:999005' => true],
            'userSendsCash' => [1 => 100, 3 => 250],
            'partnerSendsCash' => [6 => 50],
        ];
        $pageData['result'] = array_key_first(\Trading\TradeAlertMap::MAP);
        $pageData['error'] = 'Oops <b>& "quoted"</b>';

        return $pageData;
    }
}
