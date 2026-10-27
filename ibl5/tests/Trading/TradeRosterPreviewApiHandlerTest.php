<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Tests\Support\GoldenSnapshotTrait;
use Tests\WideUnit\Mocks\MockDatabase;
use Tests\WideUnit\Mocks\TestDataFactory;
use Trading\Contracts\TradeAssetRepositoryInterface;
use Trading\Contracts\TradeRosterPreviewCashRowBuilderInterface;
use Trading\Contracts\TradeRosterPreviewParamValidatorInterface;
use Trading\TradeRosterPreviewApiHandler;
use Trading\TradeRosterPreviewCashRowBuilder;

class TradeRosterPreviewApiHandlerTest extends TestCase
{
    use GoldenSnapshotTrait;

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

    private function buildHandler(?TradeAssetRepositoryInterface $repo = null, ?TradeRosterPreviewParamValidatorInterface $validator = null, ?TradeRosterPreviewCashRowBuilderInterface $cashRowBuilder = null, int $loggedInTeamID = 0): TradeRosterPreviewApiHandler
    {
        return new TradeRosterPreviewApiHandler($this->mockDb, $repo ?? $this->stubTradeAssetRepo, $loggedInTeamID, null, $validator, $cashRowBuilder);
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

    /**
     * @return list<array<string, mixed>>
     */
    private static function rosterRows(): array
    {
        return [
            TestDataFactory::createPlayer(['pid' => 101, 'name' => 'Alpha Probe', 'teamid' => 1, 'ordinal' => 1]),
            TestDataFactory::createPlayer(['pid' => 102, 'name' => 'Beta Probe', 'teamid' => 1, 'ordinal' => 2]),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rosterRows
     * @param list<array<string, mixed>> $cashRows
     */
    private function seedSuccessPath(array $rosterRows, array $cashRows = []): void
    {
        // Patterns are regexes matched against backtick-stripped SQL; the first registered match wins.
        $this->mockDb->onQuery('FROM ibl_plr', $rosterRows);
        $this->mockDb->onQuery('ibl_team_info', [TestDataFactory::createTeam(['teamid' => 1, 'color1' => '112233', 'color2' => 'AABBCC'])]);
        $this->mockDb->onQuery('ibl_cash_considerations', $cashRows);
    }

    private function decodeHtml(string $output): string
    {
        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('html', $decoded);
        $this->assertIsString($decoded['html']);

        return $decoded['html'];
    }

    public function testHandleRatingsSuccessPathMatchesSnapshot(): void
    {
        $_GET = ['teamid' => '1'];
        $this->seedSuccessPath(self::rosterRows());

        $handler = $this->buildHandler();
        $output = $this->captureOutput(fn () => $handler->handle());
        $html = $this->decodeHtml($output);

        $this->assertStringContainsString('Alpha Probe', $html);
        $this->assertStringContainsString('Beta Probe', $html);
        $this->assertStringContainsString('<select', $html);
        $this->assertSnapshotMatches($html, 'TradeRosterPreview.ratings.html');
    }

    public function testHandleContractsSuccessPathAppendsExistingAndTradeCashRows(): void
    {
        $_GET = [
            'teamid' => '1',
            'display' => 'contracts',
            'userTeam' => 'Miami',
            'partnerTeam' => 'Boston',
            'userTeamId' => '1',
            'cashStartYear' => '1',
            'cashEndYear' => '2',
            'userCash1' => '500',
            'partnerCash1' => '0',
        ];
        $this->seedSuccessPath(self::rosterRows(), [
            ['id' => 7, 'teamid' => 1, 'label' => 'Cash from Boston', 'cy' => 1, 'cyt' => 2, 'salary_yr1' => 100, 'salary_yr2' => 50, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0],
        ]);

        $builder = self::createStub(TradeRosterPreviewCashRowBuilderInterface::class);
        $builder->method('buildCashRows')->willReturn([
            \Team\TeamTableService::cashConsiderationToRosterRow(['teamid' => 1, 'label' => 'Cash to Boston', 'cy' => 1, 'cyt' => 2, 'salary_yr1' => 500, 'salary_yr2' => 500]),
        ]);

        $handler = $this->buildHandler(cashRowBuilder: $builder);
        $output = $this->captureOutput(fn () => $handler->handle());
        $html = $this->decodeHtml($output);

        $this->assertStringContainsString('contracts-table', $html);
        $this->assertStringContainsString('Cash from Boston', $html);
        $this->assertStringContainsString('Cash to Boston', $html);
        $this->assertSame(2, substr_count($html, 'data-cash-row'));
        $this->assertSnapshotMatches($html, 'TradeRosterPreview.contracts.html');
    }

    public function testHandleContractsOwnerViewRendersClickableExtensionLink(): void
    {
        $_GET = [
            'teamid' => '1',
            'display' => 'contracts',
            'userTeam' => 'Miami',
            'partnerTeam' => 'Boston',
            'userTeamId' => '1',
            'cashStartYear' => '1',
            'cashEndYear' => '2',
            'userCash1' => '500',
            'partnerCash1' => '0',
        ];
        $this->seedSuccessPath([
            TestDataFactory::createPlayer(['pid' => 103, 'name' => 'Final Year', 'teamid' => 1, 'ordinal' => 1, 'cy' => 6, 'cyt' => 6]),
        ]);

        $otherHandler = $this->buildHandler();
        $other = $this->decodeHtml($this->captureOutput(fn () => $otherHandler->handle()));
        $ownerHandler = $this->buildHandler(loggedInTeamID: 1);
        $owner = $this->decodeHtml($this->captureOutput(fn () => $ownerHandler->handle()));

        $this->assertNotSame($other, $owner);
        $this->assertStringContainsString('pa=negotiate&amp;pid=103', $owner);
        $this->assertStringContainsString('Final Year', $other);
        $this->assertSnapshotMatches($owner, 'TradeRosterPreview.contracts-owner.html');
    }

    public function testHandleAppendsIncomingPlayersAfterBaseRoster(): void
    {
        $_GET = ['teamid' => '1', 'addPids' => '201', 'removePids' => '102'];
        $this->seedSuccessPath(self::rosterRows());

        $stubRepo = self::createStub(TradeAssetRepositoryInterface::class);
        $stubRepo->method('getPlayersByIds')->willReturn([
            TestDataFactory::createPlayer(['pid' => 201, 'name' => 'Gamma Probe', 'teamid' => 5, 'ordinal' => 1]),
        ]);

        $handler = $this->buildHandler($stubRepo);
        $output = $this->captureOutput(fn () => $handler->handle());
        $html = $this->decodeHtml($output);

        $this->assertStringContainsString('Gamma Probe', $html);
        $alphaPos = strpos($html, 'Alpha Probe');
        $gammaPos = strpos($html, 'Gamma Probe');
        $this->assertIsInt($alphaPos);
        $this->assertIsInt($gammaPos);
        $this->assertGreaterThan($alphaPos, $gammaPos);
        $this->assertSnapshotMatches($html, 'TradeRosterPreview.incoming.html');
    }

    public function testHandleDropdownFallsBackToDefaultColorsWhenTeamRowIsMissing(): void
    {
        $_GET = ['teamid' => '1'];
        $this->mockDb->onQuery('FROM ibl_plr', self::rosterRows());
        $this->mockDb->onQuery('LEFT JOIN ibl_standings', [TestDataFactory::createTeam(['teamid' => 1, 'color1' => '112233', 'color2' => 'AABBCC'])]);
        $this->mockDb->onQuery('FROM ibl_team_info WHERE teamid = 1', []);

        $handler = $this->buildHandler();
        $output = $this->captureOutput(fn () => $handler->handle());
        $html = $this->decodeHtml($output);

        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('000000', $html);
        $this->assertSnapshotMatches($html, 'TradeRosterPreview.ratings-default-colors.html');
    }

    public function testHandleReturnsEmptyJsonAndRestoresBuffersWhenTeamLookupThrows(): void
    {
        $_GET = ['teamid' => '1'];
        $level = ob_get_level();

        $handler = $this->buildHandler();
        $output = $this->captureOutput(fn () => $handler->handle());

        $this->assertSame('{"html":""}', $output);
        $this->assertSame($level, ob_get_level());
        $this->assertStringContainsString('ibl_team_info', implode("\n", $this->mockDb->getExecutedQueries()));
    }

    public function testExtractedRosterMethodsCarryNoActorIdentityOrRequestState(): void
    {
        $ref = new \ReflectionClass(TradeRosterPreviewApiHandler::class);

        $properties = array_map(static fn (\ReflectionProperty $p): string => $p->getName(), $ref->getProperties());
        sort($properties);
        $this->assertSame(['cashRowBuilder', 'db', 'loggedInTeamID', 'paramValidator', 'season', 'tradeAssetRepository'], $properties);

        $publicMethods = array_map(static fn (\ReflectionMethod $m): string => $m->getName(), $ref->getMethods(\ReflectionMethod::IS_PUBLIC));
        sort($publicMethods);
        $this->assertSame(['__construct', 'handle'], $publicMethods);

        $privateMethods = array_map(static fn (\ReflectionMethod $m): string => $m->getName(), $ref->getMethods(\ReflectionMethod::IS_PRIVATE));
        sort($privateMethods);
        $this->assertSame(['buildPreviewRoster', 'collectRosterPids', 'renderTable', 'wrapWithDropdown'], $privateMethods);

        foreach (['buildPreviewRoster', 'collectRosterPids', 'wrapWithDropdown'] as $name) {
            foreach ($ref->getMethod($name)->getParameters() as $param) {
                $label = $name . '::$' . $param->getName();
                $this->assertNotContains($param->getName(), ['loggedInTeamID', 'showActionLinks', 'removePids'], $label);
                $type = $param->getType();
                $this->assertInstanceOf(\ReflectionNamedType::class, $type, $label);
                $this->assertContains($type->getName(), ['Team\TeamTableService', 'Team\TeamRepository', 'Season\Season', 'int', 'string', 'array'], $label);
            }
        }

        $this->assertSame('array', $this->returnTypeName($ref->getMethod('buildPreviewRoster')));
        $this->assertSame('array', $this->returnTypeName($ref->getMethod('collectRosterPids')));
        $this->assertSame('string', $this->returnTypeName($ref->getMethod('wrapWithDropdown')));
        $this->assertSame(1, $ref->getMethod('collectRosterPids')->getNumberOfParameters());
    }

    private function returnTypeName(\ReflectionMethod $method): string
    {
        $type = $method->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $type);

        return $type->getName();
    }
}
