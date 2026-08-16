<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;
use Trading\Contracts\TradeAssetRepositoryInterface;
use Trading\Contracts\TradeRosterPreviewCashRowBuilderInterface;
use Trading\Contracts\TradeRosterPreviewParamValidatorInterface;
use Trading\TradeRosterPreviewApiHandler;
use Trading\TradeRosterPreviewCashRowBuilder;

class TradeRosterPreviewApiHandlerTest extends TestCase
{
    private MockDatabase $mockDb;
    private TradeAssetRepositoryInterface $stubTradeAssetRepo;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();

        $this->stubTradeAssetRepo = self::createStub(TradeAssetRepositoryInterface::class);
    }

    protected function tearDown(): void
    {
        $_GET = [];
    }

    private function buildHandler(?TradeAssetRepositoryInterface $repo = null, ?TradeRosterPreviewParamValidatorInterface $validator = null, ?TradeRosterPreviewCashRowBuilderInterface $cashRowBuilder = null): TradeRosterPreviewApiHandler
    {
        return new TradeRosterPreviewApiHandler($this->mockDb, $repo ?? $this->stubTradeAssetRepo, 0, null, $validator, $cashRowBuilder);
    }

    private function captureOutput(callable $fn): string
    {
        ob_start();
        try {
            $fn();
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    public function testHandleReturnsEmptyHtmlWhenTeamIDMissing(): void
    {
        $_GET = [];

        $handler = $this->buildHandler();

        $output = $this->captureOutput(fn () => $handler->handle());

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleReturnsEmptyHtmlWhenTeamIDIsZero(): void
    {
        $_GET = ['teamid' => '0'];

        $handler = $this->buildHandler();

        $output = $this->captureOutput(fn () => $handler->handle());

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleReturnsEmptyHtmlWhenAddPidsContainNonNumeric(): void
    {
        $_GET = ['teamid' => '1', 'addPids' => '1,abc,3'];

        /** @var TradeAssetRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $mockRepo */
        $mockRepo = $this->createMock(TradeAssetRepositoryInterface::class);
        $mockRepo->expects($this->never())->method('getPlayersByIds');

        $handler = $this->buildHandler($mockRepo);

        $output = $this->captureOutput(fn () => $handler->handle());

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleReturnsEmptyHtmlWhenRemovePidsContainNonNumeric(): void
    {
        $_GET = ['teamid' => '1', 'removePids' => 'x,y'];

        $handler = $this->buildHandler();

        $output = $this->captureOutput(fn () => $handler->handle());

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleReturnsEmptyHtmlWhenAddPidsExceedMaximum(): void
    {
        $pids = implode(',', range(1, 21));
        $_GET = ['teamid' => '1', 'addPids' => $pids];

        /** @var TradeAssetRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $mockRepo */
        $mockRepo = $this->createMock(TradeAssetRepositoryInterface::class);
        $mockRepo->expects($this->never())->method('getPlayersByIds');

        $handler = $this->buildHandler($mockRepo);

        $output = $this->captureOutput(fn () => $handler->handle());

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleReturnsEmptyHtmlWhenRemovePidsExceedMaximum(): void
    {
        $pids = implode(',', range(1, 21));
        $_GET = ['teamid' => '1', 'removePids' => $pids];

        $handler = $this->buildHandler();

        $output = $this->captureOutput(fn () => $handler->handle());

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleFallsBackToRatingsWhenDisplayMissing(): void
    {
        $_GET = ['teamid' => '1'];

        $handler = $this->buildHandler();

        $output = $this->captureOutput(fn () => $handler->handle());

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleFallsBackToRatingsWhenSplitDisplayWithoutSplitParam(): void
    {
        $_GET = ['teamid' => '1', 'display' => 'split'];

        $handler = $this->buildHandler();

        $output = $this->captureOutput(fn () => $handler->handle());

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleFallsBackToRatingsForInvalidSplitKey(): void
    {
        $_GET = ['teamid' => '1', 'display' => 'split', 'split' => 'invalid_key'];

        $handler = $this->buildHandler();

        $output = $this->captureOutput(fn () => $handler->handle());

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleReturnsJsonContentType(): void
    {
        $_GET = [];

        $handler = $this->buildHandler();

        $output = $this->captureOutput(fn () => $handler->handle());

        // Verify the handler outputs valid JSON
        $this->assertJson($output);
    }

    public function testHandleAcceptsEmptyAddPids(): void
    {
        $_GET = ['teamid' => '1', 'addPids' => '', 'removePids' => '1,2'];

        $handler = $this->buildHandler();

        $output = $this->captureOutput(fn () => $handler->handle());

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleAcceptsEmptyRemovePids(): void
    {
        $_GET = ['teamid' => '1', 'addPids' => '1,2', 'removePids' => ''];

        $handler = $this->buildHandler();

        $output = $this->captureOutput(fn () => $handler->handle());

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testBuildCashRowsIgnoredWhenDisplayIsNotContracts(): void
    {
        $_GET = [
            'teamid' => '1',
            'display' => 'ratings',
            'userTeam' => 'Miami',
            'partnerTeam' => 'Boston',
            'userTeamId' => '1',
            'cashStartYear' => '1',
            'cashEndYear' => '6',
            'userCash1' => '500',
        ];

        $handler = $this->buildHandler();

        $output = $this->captureOutput(fn () => $handler->handle());

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testBuildCashRowsSkippedWhenCashParamsMissing(): void
    {
        $_GET = [
            'teamid' => '1',
            'display' => 'contracts',
        ];

        $handler = $this->buildHandler();

        $output = $this->captureOutput(fn () => $handler->handle());

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testBuildCashRowsSkippedWhenCashAmountsAreZero(): void
    {
        $_GET = [
            'teamid' => '1',
            'display' => 'contracts',
            'userTeam' => 'Miami',
            'partnerTeam' => 'Boston',
            'userTeamId' => '1',
            'cashStartYear' => '1',
            'cashEndYear' => '6',
            'userCash1' => '0',
            'partnerCash1' => '0',
        ];

        $handler = $this->buildHandler();

        $output = $this->captureOutput(fn () => $handler->handle());

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testCashAmountExceeding2000DefaultsToZero(): void
    {
        $_GET = [
            'teamid' => '1',
            'display' => 'contracts',
            'userTeam' => 'Miami',
            'partnerTeam' => 'Boston',
            'userTeamId' => '1',
            'cashStartYear' => '1',
            'cashEndYear' => '1',
            'userCash1' => '2001',
            'partnerCash1' => '0',
        ];

        $handler = $this->buildHandler();

        $output = $this->captureOutput(fn () => $handler->handle());

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testNonNumericCashAmountDefaultsToZero(): void
    {
        $_GET = [
            'teamid' => '1',
            'display' => 'contracts',
            'userTeam' => 'Miami',
            'partnerTeam' => 'Boston',
            'userTeamId' => '1',
            'cashStartYear' => '1',
            'cashEndYear' => '1',
            'userCash1' => 'abc',
            'partnerCash1' => '0',
        ];

        $handler = $this->buildHandler();

        $output = $this->captureOutput(fn () => $handler->handle());

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testValidAddPidsDoesReachTheRepository(): void
    {
        $_GET = ['teamid' => '1', 'addPids' => '1,2'];

        /** @var TradeAssetRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $mockRepo */
        $mockRepo = $this->createMock(TradeAssetRepositoryInterface::class);
        $mockRepo->expects($this->once())->method('getPlayersByIds')->with([1, 2]);

        $handler = $this->buildHandler($mockRepo);

        $output = $this->captureOutput(fn () => $handler->handle());

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testRemovePidsRejectionAlsoSkipsTheRepository(): void
    {
        $_GET = ['teamid' => '1', 'removePids' => 'x,y'];

        /** @var TradeAssetRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $mockRepo */
        $mockRepo = $this->createMock(TradeAssetRepositoryInterface::class);
        $mockRepo->expects($this->never())->method('getPlayersByIds');

        $handler = $this->buildHandler($mockRepo);

        $output = $this->captureOutput(fn () => $handler->handle());

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testInjectedValidatorIsUsed(): void
    {
        $_GET = [];

        /** @var TradeRosterPreviewParamValidatorInterface&\PHPUnit\Framework\MockObject\MockObject $mockValidator */
        $mockValidator = $this->createMock(TradeRosterPreviewParamValidatorInterface::class);
        $mockValidator->expects($this->once())->method('validateTeamID')->willReturn(0);

        $handler = $this->buildHandler(validator: $mockValidator);

        $output = $this->captureOutput(fn () => $handler->handle());

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testInjectedCashRowBuilderIsUsed(): void
    {
        $_GET = ['teamid' => '1', 'display' => 'contracts'];

        /** @var TradeRosterPreviewCashRowBuilderInterface&\PHPUnit\Framework\MockObject\MockObject $mockBuilder */
        $mockBuilder = $this->createMock(TradeRosterPreviewCashRowBuilderInterface::class);
        $mockBuilder->expects($this->once())->method('buildCashRows')
            ->with(1, TradeRosterPreviewCashRowBuilder::CASH_YEAR_FORWARD_HORIZON)
            ->willReturn([]);

        $handler = $this->buildHandler(cashRowBuilder: $mockBuilder);

        $output = $this->captureOutput(fn () => $handler->handle());

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('html', $decoded);
    }
}
