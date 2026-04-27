<?php

declare(strict_types=1);

namespace Tests\Extension;

use PHPUnit\Framework\TestCase;
use Extension\ExtensionRepository;

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
class ExtensionRepositoryTest extends TestCase
{
    private \MockDatabase $mockDb;
    private ExtensionRepository $repository;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        $this->repository = new ExtensionRepository($this->mockDb);
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

        $result = $this->repository->updatePlayerContract('Test Player', $offer, 800);

        $this->assertTrue($result);
        $this->assertQueryExecuted('UPDATE ibl_plr');
    }

    public function testUpdatesPlayerContractWith3YearExtension(): void
    {
        $offer = [
            'year1' => 1000, 'year2' => 1100, 'year3' => 1200,
            'year4' => 0, 'year5' => 0,
        ];

        $result = $this->repository->updatePlayerContract('Test Player', $offer, 800);

        $this->assertTrue($result);
    }

    public function testMarksExtensionUsedThisSim(): void
    {
        $result = $this->repository->markExtensionUsedThisSim('Test Team');

        $this->assertTrue($result);
        $this->assertQueryExecuted('used_extension_this_chunk');
    }

    public function testMarksExtensionUsedThisSeason(): void
    {
        $result = $this->repository->markExtensionUsedThisSeason('Test Team');

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

        $result = $this->repository->createAcceptedExtensionStory(
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

        $result = $this->repository->createRejectedExtensionStory(
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

        $result = $this->repository->getTeamTraditionData('Test Team');

        $this->assertSame(50, $result['currentSeasonWins']);
        $this->assertSame(32, $result['currentSeasonLosses']);
        $this->assertSame(2500, $result['tradition_wins']);
        $this->assertSame(2000, $result['tradition_losses']);
    }

    public function testGetTeamTraditionDataReturnsDefaultsWhenNotFound(): void
    {
        // Empty mock data — no rows returned
        $this->mockDb->setMockData([]);

        $result = $this->repository->getTeamTraditionData('Nonexistent Team');

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

        $this->repository->saveAcceptedExtension(
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

    /**
     * Assert that at least one executed query contains the given substring.
     */
    private function assertQueryExecuted(string $substring): void
    {
        $queries = $this->mockDb->getExecutedQueries();
        foreach ($queries as $query) {
            if (str_contains($query, $substring)) {
                $this->addToAssertionCount(1);
                return;
            }
        }
        $this->fail("Expected a query containing '{$substring}' but none was found. Queries: " . implode("\n", $queries));
    }
}
