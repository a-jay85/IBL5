<?php

declare(strict_types=1);

namespace Tests\Trading;

use Auth\Contracts\AuthServiceInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Security\CsrfGuard;
use Tests\WideUnit\Mocks\MockDatabase;
use Trading\Contracts\TradeExecutionServiceInterface;
use Trading\Contracts\TradeOfferInterface;
use Trading\Contracts\TradeOfferRepositoryInterface;
use Trading\Contracts\TradingServiceInterface;
use Trading\Contracts\TradingViewInterface;
use Trading\TradingController;

/**
 * Characterization tests for TradingController::submitTradeOffer().
 *
 * HtmxHelper::redirect() ends in exit, so each test halts the controller before its next
 * redirect by throwing an \Error from a mock callback. The controller only catches \Exception,
 * so the \Error escapes and the test catches it. A run that reaches exit prints no OK line.
 */
class TradingControllerSubmitOfferCharacterizationTest extends TestCase
{
    public const HALT = 'halt-before-redirect';

    private MockDatabase $mockDb;
    private string $csrfToken = '';

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $_SESSION = [];
        $_POST = [];
        $this->csrfToken = CsrfGuard::generateRawToken('trade_offer');
        $_POST['_csrf_token'] = $this->csrfToken;
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

    private function buildController(
        ?TradeOfferInterface $tradeOffer = null,
        ?LoggerInterface $auditLogger = null,
        ?LoggerInterface $tradeLogger = null,
    ): TradingController {
        $nukeCompat = self::createStub(\Utilities\NukeCompat::class);
        $nukeCompat->method('isUser')->willReturn(true);

        $teamIdentityRepo = self::createStub(TeamIdentityRepositoryInterface::class);
        $teamIdentityRepo->method('getTeamnameFromUsername')->willReturn('Metros');

        $authService = self::createStub(AuthServiceInterface::class);
        $authService->method('getUsername')->willReturn('metrosgm');

        return new TradingController(
            self::createStub(TradingServiceInterface::class),
            self::createStub(TradeOfferRepositoryInterface::class),
            $tradeOffer ?? self::createStub(TradeOfferInterface::class),
            self::createStub(TradingViewInterface::class),
            $teamIdentityRepo,
            $nukeCompat,
            $this->mockDb,
            self::createStub(TradeExecutionServiceInterface::class),
            $authService,
            $auditLogger ?? self::createStub(LoggerInterface::class),
            $tradeLogger ?? self::createStub(LoggerInterface::class),
        );
    }

    /**
     * @param array<string, mixed> $post
     */
    private function submitUntilHalt(TradingController $controller, array $post): void
    {
        try {
            $controller->submitTradeOffer(null, $post + ['_csrf_token' => $this->csrfToken]);
        } catch (\Error $e) {
            self::assertSame(self::HALT, $e->getMessage());
            return;
        }

        self::fail('submitTradeOffer returned without reaching the halt point');
    }

    /**
     * Mock whose createTradeOffer records its argument into $captured, then halts.
     *
     * @param array<string, mixed>|null $captured
     */
    private function buildCapturingTradeOffer(?array &$captured): TradeOfferInterface
    {
        $tradeOffer = self::createMock(TradeOfferInterface::class);
        $tradeOffer->expects(self::once())
            ->method('createTradeOffer')
            ->willReturnCallback(function (array $data) use (&$captured): array {
                $captured = $data;
                throw new \Error(self::HALT);
            });

        return $tradeOffer;
    }

    public function testCreateTradeOfferReceivesSessionTeamWhenPostClaimsAnotherTeam(): void
    {
        $captured = null;
        $auditLogger = self::createMock(LoggerInterface::class);
        $auditLogger->expects(self::once())
            ->method('warning')
            ->with('trade_offer_team_mismatch', ['post' => 'Stars', 'session' => 'Metros']);

        $controller = $this->buildController($this->buildCapturingTradeOffer($captured), $auditLogger);
        $this->submitUntilHalt($controller, ['offeringTeam' => 'Stars', 'listeningTeam' => 'Stars']);

        self::assertIsArray($captured);
        self::assertSame('Metros', $captured['offeringTeam']);
    }

    /**
     * @param array<string, mixed> $post
     */
    #[DataProvider('postedOfferingTeamWithoutMismatchProvider')]
    public function testNoMismatchWarningWhenPostedOfferingTeamMatchesOrIsEmpty(array $post): void
    {
        $captured = null;
        $auditLogger = self::createMock(LoggerInterface::class);
        $auditLogger->expects(self::never())->method('warning');

        $controller = $this->buildController($this->buildCapturingTradeOffer($captured), $auditLogger);
        $this->submitUntilHalt($controller, $post);

        self::assertIsArray($captured);
        self::assertSame('Metros', $captured['offeringTeam']);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function postedOfferingTeamWithoutMismatchProvider(): array
    {
        return [
            'matches session' => [['offeringTeam' => 'Metros']],
            'empty string' => [['offeringTeam' => '']],
            'non-string' => [['offeringTeam' => 5]],
            'absent' => [[]],
        ];
    }

    public function testParsesPostedFieldsIntoTradeData(): void
    {
        $captured = null;
        $controller = $this->buildController($this->buildCapturingTradeOffer($captured));
        $this->submitUntilHalt($controller, [
            'listeningTeam' => 'Stars',
            'switchCounter' => '1',
            'fieldsCounter' => '2',
            'userSendsCash0' => '150',
            'userSendsCash1' => 'abc',
            'partnerSendsCash3' => '75',
            'partnerSendsCash6' => '20',
            'check0' => 'on',
            'check2' => 'on',
            'contract0' => '1000',
            'contract1' => 7,
            'contract2' => ['x'],
            'index0' => '11',
            'index1' => 22,
            'type0' => '1',
            'type1' => '2',
            'type2' => ['bad'],
        ]);

        // contract[2], index[2] and type[2] hold arrays, so each coerces to '0'.
        self::assertSame([
            'offeringTeam' => 'Metros',
            'listeningTeam' => 'Stars',
            'switchCounter' => 1,
            'fieldsCounter' => 3,
            'userSendsCash' => [150, 0, 0, 0, 0, 0, 0],
            'partnerSendsCash' => [0, 0, 0, 75, 0, 0, 20],
            'check' => ['on', null, 'on'],
            'contract' => ['1000', '7', '0'],
            'index' => ['11', '22', '0'],
            'type' => ['1', '2', '0'],
        ], $captured);
    }

    /**
     * @param array<string, mixed> $post
     */
    #[DataProvider('fieldsCounterBoundaryProvider')]
    public function testNonNumericFieldsCounterParsesAsOneRow(array $post): void
    {
        $captured = null;
        $controller = $this->buildController($this->buildCapturingTradeOffer($captured));
        $this->submitUntilHalt($controller, $post);

        self::assertIsArray($captured);
        self::assertSame(1, $captured['fieldsCounter']);
        self::assertIsArray($captured['check']);
        self::assertSame([0], array_keys($captured['check']));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function fieldsCounterBoundaryProvider(): array
    {
        return [
            'non-numeric' => [['fieldsCounter' => 'abc']],
            'absent' => [[]],
        ];
    }

    public function testCreateTradeOfferExceptionIsLoggedToTradeLogger(): void
    {
        $tradeOffer = self::createStub(TradeOfferInterface::class);
        $tradeOffer->method('createTradeOffer')->willThrowException(new \RuntimeException('db down'));

        $tradeLogger = self::createMock(LoggerInterface::class);
        $tradeLogger->expects(self::once())
            ->method('error')
            ->with('Failed to create trade offer', ['error' => 'db down'])
            ->willReturnCallback(static function (): void {
                throw new \Error(self::HALT);
            });

        $controller = $this->buildController($tradeOffer, null, $tradeLogger);
        $this->submitUntilHalt($controller, ['listeningTeam' => 'Stars']);
    }

    public function testSuccessfulOfferWritesAuditInfoBeforeRedirect(): void
    {
        $tradeOffer = self::createStub(TradeOfferInterface::class);
        $tradeOffer->method('createTradeOffer')->willReturn(['success' => true]);

        // Logging through ->notice( instead of info would skip this halt and run on into exit.
        $auditLogger = self::createMock(LoggerInterface::class);
        $auditLogger->expects(self::once())
            ->method('info')
            ->with('trade_offer_created', ['offering_team' => 'Metros', 'listening_team' => 'Stars'])
            ->willReturnCallback(static function (): void {
                throw new \Error(self::HALT);
            });

        $controller = $this->buildController($tradeOffer, $auditLogger);
        $this->submitUntilHalt($controller, ['listeningTeam' => 'Stars']);
    }

    public function testFailedOfferStoresCheckedItemsAndCashInSession(): void
    {
        $trap = new class ([]) extends \ArrayObject {
            public mixed $captured = null;

            public function offsetSet(mixed $key, mixed $value): void
            {
                if ($key === 'tradeFormData') {
                    $this->captured = $value;
                    throw new \Error(TradingControllerSubmitOfferCharacterizationTest::HALT);
                }
                parent::offsetSet($key, $value);
            }
        };

        // Install the trap inside the callback: CsrfGuard has already read the real $_SESSION by then.
        $tradeOffer = self::createStub(TradeOfferInterface::class);
        $tradeOffer->method('createTradeOffer')->willReturnCallback(
            static function () use ($trap): array {
                $_SESSION = $trap;
                return ['success' => false, 'error' => 'Over the cap'];
            }
        );

        $controller = $this->buildController($tradeOffer);
        $this->submitUntilHalt($controller, [
            'listeningTeam' => 'Stars',
            'fieldsCounter' => '1',
            'check0' => 'on',
            'type0' => '1',
            'index0' => '11',
            'type1' => '2',
            'index1' => '22',
            'userSendsCash0' => '150',
            'partnerSendsCash3' => '75',
        ]);

        // checkedItems is keyed type:index, so type 1 + index 11 gives '1:11'.
        self::assertSame([
            'checkedItems' => ['1:11' => true],
            'userSendsCash' => [150, 0, 0, 0, 0, 0, 0],
            'partnerSendsCash' => [0, 0, 0, 75, 0, 0, 0],
        ], $trap->captured);
    }
}
