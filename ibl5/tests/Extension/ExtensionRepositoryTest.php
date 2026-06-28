<?php

declare(strict_types=1);

namespace Tests\Extension;

use Extension\ExtensionRepository;
use Logging\LoggerFactory;
use Monolog\Handler\TestHandler;
use Tests\WideUnit\Mocks\MockDatabase;
use Tests\WideUnit\WideUnitTestCase;

/**
 * Tests for ExtensionRepository
 *
 * Tests database operations via BaseMysqliRepository helpers:
 * - Player contract updates
 * - Team extension usage flags
 * - News story creation
 * - Team tradition data retrieval
 *
 * @covers \Extension\ExtensionRepository
 */
class ExtensionRepositoryTest extends WideUnitTestCase
{
    protected function tearDown(): void
    {
        LoggerFactory::reset();
        parent::tearDown();
    }

    private function repo(): ExtensionRepository
    {
        $db = $this->mockDb;
        self::assertNotNull($db);
        return new ExtensionRepository($db);
    }

    // ============================================
    // WRITE OPERATIONS
    // ============================================

    public function testUpdatesPlayerContractOnAcceptedExtension(): void
    {
        $offer = [
            'year1' => 1000, 'year2' => 1100, 'year3' => 1200,
            'year4' => 1300, 'year5' => 1400,
        ];

        $result = $this->repo()->updatePlayerContract('Test Player', $offer, 800);

        $this->assertTrue($result);
        $this->assertQueryExecuted('UPDATE ibl_plr');
    }

    public function testUpdatesPlayerContractWith3YearExtension(): void
    {
        $offer = [
            'year1' => 1000, 'year2' => 1100, 'year3' => 1200,
            'year4' => 0, 'year5' => 0,
        ];

        $result = $this->repo()->updatePlayerContract('Test Player', $offer, 800);

        $this->assertTrue($result);
    }

    public function testMarksExtensionUsedThisSim(): void
    {
        $result = $this->repo()->markExtensionUsedThisSim('Test Team');

        $this->assertTrue($result);
        $this->assertQueryExecuted('used_extension_this_chunk');
    }

    public function testMarksExtensionUsedThisSeason(): void
    {
        $result = $this->repo()->markExtensionUsedThisSeason('Test Team');

        $this->assertTrue($result);
        $this->assertQueryExecuted('used_extension_this_season');
    }

    // ============================================
    // NEWS STORY CREATION
    // ============================================

    public function testCreatesNewsStoryForAcceptedExtension(): void
    {
        $this->mockDb->setMockData([
            ['topicid' => 5, 'catid' => 1],
            ['topicid' => 5, 'catid' => 1],
        ]);
        $this->mockDb->setNumRows(1);
        $this->mockDb->setReturnTrue(true);

        $result = $this->repo()->createAcceptedExtensionStory(
            'Test Player',
            'Test Team',
            120.0,
            5,
            '1000 1100 1200 1300 1400'
        );

        $this->assertTrue($result);
        $this->assertQueryExecuted('nuke_stories');
    }

    public function testCreatesNewsStoryForRejectedExtension(): void
    {
        $this->mockDb->setMockData([
            ['topicid' => 5, 'catid' => 1],
            ['topicid' => 5, 'catid' => 1],
        ]);
        $this->mockDb->setNumRows(1);
        $this->mockDb->setReturnTrue(true);

        $result = $this->repo()->createRejectedExtensionStory(
            'Test Player',
            'Test Team',
            100.0,
            5
        );

        $this->assertTrue($result);
        $this->assertQueryExecuted('nuke_stories');
    }

    // ============================================
    // READ OPERATIONS
    // ============================================

    public function testGetTeamTraditionDataReturnsTeamData(): void
    {
        $this->mockDb->setMockData([
            ['contract_wins' => 50, 'contract_losses' => 32, 'contract_avg_w' => 2500, 'contract_avg_l' => 2000],
        ]);

        $result = $this->repo()->getTeamTraditionData('Test Team');

        $this->assertSame(50, $result['currentSeasonWins']);
        $this->assertSame(32, $result['currentSeasonLosses']);
        $this->assertSame(2500, $result['tradition_wins']);
        $this->assertSame(2000, $result['tradition_losses']);
    }

    public function testGetTeamTraditionDataReturnsDefaultsWhenNotFound(): void
    {
        // Empty mock data — no rows returned
        $this->mockDb->setMockData([]);

        $result = $this->repo()->getTeamTraditionData('Nonexistent Team');

        $this->assertSame(41, $result['currentSeasonWins']);
        $this->assertSame(41, $result['currentSeasonLosses']);
        $this->assertSame(41, $result['tradition_wins']);
        $this->assertSame(41, $result['tradition_losses']);
    }

    public function testSaveAcceptedExtensionCallsAllOperations(): void
    {
        $this->mockDb->setMockData([
            ['topicid' => 5, 'catid' => 1],
            ['topicid' => 5, 'catid' => 1],
        ]);
        $this->mockDb->setReturnTrue(true);

        $offer = [
            'year1' => 1000, 'year2' => 1100, 'year3' => 1200,
            'year4' => 0, 'year5' => 0,
        ];

        $this->repo()->saveAcceptedExtension(
            'Test Player',
            'Test Team',
            $offer,
            800,
            33.0,
            3,
            '1000 1100 1200 0 0'
        );

        // Verify all three operations were executed
        $this->assertQueryExecuted('UPDATE ibl_plr');
        $this->assertQueryExecuted('used_extension_this_season');
        $this->assertQueryExecuted('nuke_stories');
    }

    // ============================================
    // FAILURE PATH LOGGING
    // ============================================

    public function testUpdatePlayerContractLogsOnFailure(): void
    {
        $handler = new TestHandler();
        LoggerFactory::forTesting($handler);

        $repo = new class (new MockDatabase()) extends ExtensionRepository {
            protected function execute(string $query, string $types = '', mixed ...$params): int
            {
                throw new \RuntimeException('forced failure', 1003);
            }
        };

        $offer = ['year1' => 100, 'year2' => 110, 'year3' => 120, 'year4' => 0, 'year5' => 0];
        $result = $repo->updatePlayerContract('Test Player', $offer, 80);

        $this->assertFalse($result);
        $this->assertTrue($handler->hasErrorThatContains('updatePlayerContract failed'));
    }

    public function testMarkExtensionUsedThisSimLogsOnFailure(): void
    {
        $handler = new TestHandler();
        LoggerFactory::forTesting($handler);

        $repo = new class (new MockDatabase()) extends ExtensionRepository {
            protected function execute(string $query, string $types = '', mixed ...$params): int
            {
                throw new \RuntimeException('forced failure', 1003);
            }
        };

        $result = $repo->markExtensionUsedThisSim('Test Team');

        $this->assertFalse($result);
        $this->assertTrue($handler->hasErrorThatContains('markExtensionUsedThisSim failed'));
    }

    public function testMarkExtensionUsedThisSeasonLogsOnFailure(): void
    {
        $handler = new TestHandler();
        LoggerFactory::forTesting($handler);

        $repo = new class (new MockDatabase()) extends ExtensionRepository {
            protected function execute(string $query, string $types = '', mixed ...$params): int
            {
                throw new \RuntimeException('forced failure', 1003);
            }
        };

        $result = $repo->markExtensionUsedThisSeason('Test Team');

        $this->assertFalse($result);
        $this->assertTrue($handler->hasErrorThatContains('markExtensionUsedThisSeason failed'));
    }

    // ============================================
    // PER-CHANNEL LOGGER SEAM (Matrix #6, #7, #8)
    // ============================================

    /**
     * Positive seam — db channel: injected dbLogger spy receives the db-channel error.
     * Cross-talk negative: injected appLogger spy receives NO error call (Matrix #7).
     */
    public function testDbLoggerSpyReceivesDbChannelErrorAndAppSpyDoesNot(): void
    {
        $dbSpy = $this->createMock(\Psr\Log\LoggerInterface::class);
        $dbSpy->expects($this->once())
            ->method('error')
            ->with('updatePlayerContract failed', self::arrayHasKey('exception'));

        $appSpy = $this->createMock(\Psr\Log\LoggerInterface::class);
        $appSpy->expects($this->never())->method('error');

        $repo = new class(new MockDatabase(), appLogger: $appSpy, dbLogger: $dbSpy) extends ExtensionRepository {
            protected function execute(string $query, string $types = '', mixed ...$params): int
            {
                throw new \RuntimeException('forced db failure', 1003);
            }
        };

        $offer = ['year1' => 100, 'year2' => 110, 'year3' => 120, 'year4' => 0, 'year5' => 0];
        $result = $repo->updatePlayerContract('Test Player', $offer, 80);

        $this->assertFalse($result);
    }

    /**
     * Positive seam — app channel: injected appLogger spy receives the app-channel warning.
     * Cross-talk negative: injected dbLogger spy receives NO warning call (Matrix #7).
     */
    public function testAppLoggerSpyReceivesAppChannelWarningAndDbSpyDoesNot(): void
    {
        $appSpy = $this->createMock(\Psr\Log\LoggerInterface::class);
        $appSpy->expects($this->once())
            ->method('warning')
            ->with('ExtensionRepository::getTeamTraditionData failed', self::arrayHasKey('error'));

        $dbSpy = $this->createMock(\Psr\Log\LoggerInterface::class);
        $dbSpy->expects($this->never())->method('warning');

        $repo = new class(new MockDatabase(), appLogger: $appSpy, dbLogger: $dbSpy) extends ExtensionRepository {
            protected function fetchOne(string $query, string $types = '', mixed ...$params): ?array
            {
                throw new \RuntimeException('forced app failure');
            }
        };

        $result = $repo->getTeamTraditionData('Test Team');

        $this->assertSame(41, $result['currentSeasonWins']);
    }

    /**
     * Subclass seam: injected loggers ($appLogger, $dbLogger) are distinct from the
     * parent BaseMysqliRepository's private $logger — no property shadow regression.
     */
    public function testInjectedLoggersDoNotShadowParentLogger(): void
    {
        $appSpy = self::createStub(\Psr\Log\LoggerInterface::class);
        $dbSpy = self::createStub(\Psr\Log\LoggerInterface::class);

        $repo = new ExtensionRepository(new MockDatabase(), appLogger: $appSpy, dbLogger: $dbSpy);

        $refApp = new \ReflectionProperty(ExtensionRepository::class, 'appLogger');
        $refDb = new \ReflectionProperty(ExtensionRepository::class, 'dbLogger');

        $this->assertSame($appSpy, $refApp->getValue($repo));
        $this->assertSame($dbSpy, $refDb->getValue($repo));

        // Parent's private $logger is a separate property — verify it resolves independently
        $refParentLogger = new \ReflectionProperty(\BaseMysqliRepository::class, 'logger');
        $parentLogger = $refParentLogger->getValue($repo);
        $this->assertNotSame($appSpy, $parentLogger);
        $this->assertNotSame($dbSpy, $parentLogger);
    }

    /**
     * Boundary (Matrix #8): constructing without logger args does not throw;
     * fallback-to-LoggerFactory fires and the class remains functional.
     */
    public function testConstructsWithoutLoggerArgsDoesNotThrow(): void
    {
        $repo = new ExtensionRepository(new MockDatabase());
        $this->assertIsObject($repo);
    }
}
