<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Trading\Contracts\TradingServiceInterface;
use Trading\Contracts\TradeOfferRepositoryInterface;
use Trading\Contracts\TradeOfferInterface;
use Trading\Contracts\TradingViewInterface;
use Trading\Contracts\TradeExecutionServiceInterface;
use Auth\Contracts\AuthServiceInterface;
use Trading\TradingController;

class TradingControllerOfferTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    protected function tearDown(): void
    {
        unset(
            $_GET['prefill'],
            $_SESSION['tradeFormData'],
            $GLOBALS['authService'],
            $GLOBALS['user'],
            $GLOBALS['sitename'],
            $GLOBALS['pagetitle'],
            $_SERVER['HTTP_HX_BOOSTED'],
        );
        parent::tearDown();
    }

    private function buildController(
        ?\Utilities\NukeCompat $nukeCompat = null,
        ?TradingServiceInterface $service = null,
        ?TradingViewInterface $view = null,
        ?TradeOfferRepositoryInterface $offerRepository = null,
        ?TradeOfferInterface $tradeOffer = null,
    ): TradingController {
        return new TradingController(
            $service ?? self::createStub(TradingServiceInterface::class),
            $offerRepository ?? self::createStub(TradeOfferRepositoryInterface::class),
            $tradeOffer ?? self::createStub(TradeOfferInterface::class),
            $view ?? self::createStub(TradingViewInterface::class),
            self::createStub(TeamIdentityRepositoryInterface::class),
            $nukeCompat ?? self::createStub(\Utilities\NukeCompat::class),
            $this->mockDb,
            self::createStub(TradeExecutionServiceInterface::class),
            self::createStub(AuthServiceInterface::class),
        );
    }

    public function testRedirectsToLoginWhenUserNotAuthenticated(): void
    {
        $loginBoxCalled = false;
        $nukeCompat = self::createStub(\Utilities\NukeCompat::class);
        $nukeCompat->method('isUser')->willReturn(false);
        $nukeCompat->method('loginBox')->willReturnCallback(function () use (&$loginBoxCalled): void {
            $loginBoxCalled = true;
        });

        $controller = $this->buildController(nukeCompat: $nukeCompat);
        $controller->handleTradeOffer(null, 'Boston');

        $this->assertTrue($loginBoxCalled);
    }

    public function testCanBeInstantiated(): void
    {
        $controller = $this->buildController();
        $this->assertIsObject($controller);
    }

    // ============================================
    // PER-CHANNEL LOGGER SEAM (Matrix #8 boundary)
    // ============================================

    /**
     * Boundary (Matrix #8): constructing TradingController with injected audit/trade logger
     * spies (the new trailing args 9 & 10) does not throw and produces a usable instance.
     * Full invocation tests for the logging paths require E2E — all TradingController
     * action methods call HtmxHelper::redirect() → exit after logging.
     */
    public function testConstructableWithAuditAndTradeLoggerSpies(): void
    {
        $auditSpy = self::createStub(\Psr\Log\LoggerInterface::class);
        $tradeSpy = self::createStub(\Psr\Log\LoggerInterface::class);

        $controller = new TradingController(
            self::createStub(TradingServiceInterface::class),
            self::createStub(TradeOfferRepositoryInterface::class),
            self::createStub(TradeOfferInterface::class),
            self::createStub(TradingViewInterface::class),
            self::createStub(TeamIdentityRepositoryInterface::class),
            self::createStub(\Utilities\NukeCompat::class),
            $this->mockDb,
            self::createStub(TradeExecutionServiceInterface::class),
            self::createStub(AuthServiceInterface::class),
            auditLogger: $auditSpy,
            tradeLogger: $tradeSpy,
        );

        $this->assertIsObject($controller);
    }

    // ============================================
    // PREFILL FROM ?prefill= (untrusted GET input)
    // ============================================

    private const USER_PID = 999001;
    private const USER_PICK_ID = 7;
    private const PARTNER_PID = 999005;
    private const PARTNER_PICK_ID = 9;

    /**
     * Trade offer page data with one player and one pick on each side, all tradeable
     * (nonzero current salary, active ordinal) so every row renders a checkbox.
     *
     * @return array{
     *     userTeam: string,
     *     userTeamId: int,
     *     partnerTeam: string,
     *     partnerTeamId: int,
     *     userPlayers: list<array<string, mixed>>,
     *     userPicks: list<array<string, mixed>>,
     *     userFutureSalary: array{player: array<int, int>, hold: array<int, int>},
     *     partnerPlayers: list<array<string, mixed>>,
     *     partnerPicks: list<array<string, mixed>>,
     *     partnerFutureSalary: array{player: array<int, int>, hold: array<int, int>},
     *     seasonEndingYear: int,
     *     seasonPhase: string,
     *     cashStartYear: int,
     *     cashEndYear: int,
     *     userTeamColor1: string,
     *     userTeamColor2: string,
     *     partnerTeamColor1: string,
     *     partnerTeamColor2: string,
     * }
     */
    private function offerPageData(): array
    {
        return [
            'userTeam' => 'Lakers',
            'userTeamId' => 1,
            'partnerTeam' => 'Celtics',
            'partnerTeamId' => 2,
            'userPlayers' => [
                ['pos' => 'G', 'name' => 'User Guard', 'pid' => self::USER_PID, 'ordinal' => 1, 'cy' => 1, 'salary_yr1' => 500, 'salary_yr2' => 0, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0],
            ],
            'userPicks' => [
                ['pickid' => self::USER_PICK_ID, 'ownerofpick' => 'Lakers', 'teampick' => 'Lakers', 'teampick_id' => 1, 'year' => '2026', 'round' => '1', 'notes' => null, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
            ],
            'userFutureSalary' => ['player' => [0, 0, 0, 0, 0, 0], 'hold' => [0, 0, 0, 0, 0, 0]],
            'partnerPlayers' => [
                ['pos' => 'F', 'name' => 'Partner Forward', 'pid' => self::PARTNER_PID, 'ordinal' => 2, 'cy' => 1, 'salary_yr1' => 600, 'salary_yr2' => 0, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0],
            ],
            'partnerPicks' => [
                ['pickid' => self::PARTNER_PICK_ID, 'ownerofpick' => 'Celtics', 'teampick' => 'Celtics', 'teampick_id' => 2, 'year' => '2026', 'round' => '2', 'notes' => null, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
            ],
            'partnerFutureSalary' => ['player' => [0, 0, 0, 0, 0, 0], 'hold' => [0, 0, 0, 0, 0, 0]],
            'seasonEndingYear' => 2026,
            'seasonPhase' => 'Regular Season',
            'cashStartYear' => 1,
            'cashEndYear' => 6,
            'userTeamColor1' => '552583',
            'userTeamColor2' => 'FDB927',
            'partnerTeamColor1' => '007A33',
            'partnerTeamColor2' => 'FFFFFF',
        ];
    }

    /**
     * Render the offer page through the real TradingView and capture the output.
     * Boosted PageLayout::footer() ob_end_flush()es the inner buffer into the outer one.
     */
    private function renderOfferPage(
        ?TradeOfferRepositoryInterface $offerRepository = null,
        ?TradeOfferInterface $tradeOffer = null,
    ): string {
        require_once dirname(__DIR__, 2) . '/classes/Bootstrap/LegacyFunctions.php';
        $stubAuth = self::createStub(AuthServiceInterface::class);
        $stubAuth->method('getCookieArray')->willReturn(null);
        $stubAuth->method('getUsername')->willReturn(null);
        $GLOBALS['authService'] = $stubAuth;
        $GLOBALS['user'] = '';
        $GLOBALS['sitename'] = '';
        $GLOBALS['pagetitle'] = '';
        $_SERVER['HTTP_HX_BOOSTED'] = 'true';

        $nukeCompat = self::createStub(\Utilities\NukeCompat::class);
        $nukeCompat->method('isUser')->willReturn(true);
        $service = self::createStub(TradingServiceInterface::class);
        $service->method('getTradeOfferPageData')->willReturn($this->offerPageData());

        $controller = $this->buildController(
            nukeCompat: $nukeCompat,
            service: $service,
            view: new \Trading\TradingView(),
            offerRepository: $offerRepository,
            tradeOffer: $tradeOffer,
        );

        $baseLevel = ob_get_level();
        ob_start(); // outer capture
        ob_start(); // inner buffer, consumed by PageLayout::footer()
        try {
            $controller->handleTradeOffer('user', 'Celtics');
        } catch (\Throwable $e) {
            while (ob_get_level() > $baseLevel) {
                ob_end_clean();
            }
            throw $e;
        }
        $output = '';
        while (ob_get_level() > $baseLevel) {
            $output = (string) ob_get_clean() . $output;
        }
        return $output;
    }

    /**
     * Map each checked checkbox to its "type:id" key via the row's index/type hidden inputs.
     *
     * @return list<string>
     */
    private static function checkedKeys(string $html): array
    {
        preg_match_all(
            '/name="index(\d+)" value="(\d+)">(?:(?!name="index).)*?name="type\1" value="([01])">\s*<input type="checkbox" name="check\1"( checked)?>/s',
            $html,
            $matches,
            PREG_SET_ORDER
        );
        $checked = [];
        foreach ($matches as $m) {
            if (($m[4] ?? '') === ' checked') {
                $checked[] = $m[3] . ':' . $m[2];
            }
        }
        sort($checked);
        return $checked;
    }

    private static function checkboxCount(string $html): int
    {
        return (int) preg_match_all('/<input type="checkbox" name="check\d+"/', $html);
    }

    public function testPrefillTicksSuggestedCheckboxes(): void
    {
        $_GET['prefill'] = '1:' . self::USER_PID . ',0:' . self::PARTNER_PICK_ID;

        $html = $this->renderOfferPage();

        $this->assertSame(4, self::checkboxCount($html));
        $this->assertSame(
            ['0:' . self::PARTNER_PICK_ID, '1:' . self::USER_PID],
            self::checkedKeys($html)
        );
        $this->assertSame(2, substr_count($html, '" checked>'));
    }

    public function testSessionRestoreBeatsPrefill(): void
    {
        $_GET['prefill'] = '1:' . self::USER_PID . ',0:' . self::PARTNER_PICK_ID;
        $_SESSION['tradeFormData'] = [
            'checkedItems' => ['1:' . self::PARTNER_PID => true],
            'userSendsCash' => [],
            'partnerSendsCash' => [],
        ];

        $html = $this->renderOfferPage();

        $this->assertSame(['1:' . self::PARTNER_PID], self::checkedKeys($html));
        $this->assertSame(1, substr_count($html, '" checked>'));
        $this->assertArrayNotHasKey('tradeFormData', $_SESSION);
    }

    public function testTamperedPrefillRendersNothingChecked(): void
    {
        $_GET['prefill'] = '1:' . self::USER_PID . ',1:123456';

        $html = $this->renderOfferPage();

        $this->assertSame(4, self::checkboxCount($html));
        $this->assertSame([], self::checkedKeys($html));
        $this->assertStringNotContainsString('" checked>', $html);
    }

    public function testPrefillNeverSubmitsOffer(): void
    {
        $_GET['prefill'] = '1:' . self::USER_PID . ',0:' . self::PARTNER_PICK_ID;

        $offerRepository = self::createMock(TradeOfferRepositoryInterface::class);
        $offerRepository->expects(self::never())->method('generateNextTradeOfferId');
        $offerRepository->expects(self::never())->method('insertTradeItem');
        $tradeOffer = self::createMock(TradeOfferInterface::class);
        $tradeOffer->expects(self::never())->method('createTradeOffer');

        $html = $this->renderOfferPage(offerRepository: $offerRepository, tradeOffer: $tradeOffer);

        $this->assertSame(2, substr_count($html, '" checked>'));
    }
}
