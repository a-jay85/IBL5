<?php

declare(strict_types=1);

namespace Tests\SeasonLeaderboards;

use PHPUnit\Framework\TestCase;
use SeasonLeaderboards\SeasonLeaderboardsRepository;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * SeasonLeaderboardsRepositoryTest - Tests for SeasonLeaderboardsRepository database operations
 */
class SeasonLeaderboardsRepositoryTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $GLOBALS['mysqli_db'] = $this->mockDb;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
    }

    public function testRepositoryCanBeInstantiated(): void
    {
        $repository = new SeasonLeaderboardsRepository($this->mockDb);

        $this->assertInstanceOf(SeasonLeaderboardsRepository::class, $repository);
    }

    public function testRepositoryImplementsCorrectInterface(): void
    {
        $repository = new SeasonLeaderboardsRepository($this->mockDb);

        $this->assertInstanceOf(
            \SeasonLeaderboards\Contracts\SeasonLeaderboardsRepositoryInterface::class,
            $repository
        );
    }

    public function testRepositoryExtendsBaseMysqliRepository(): void
    {
        $repository = new SeasonLeaderboardsRepository($this->mockDb);

        $this->assertInstanceOf(\BaseMysqliRepository::class, $repository);
    }



    public function testMultipleRepositoriesCanBeInstantiated(): void
    {
        $repo1 = new SeasonLeaderboardsRepository($this->mockDb);
        $repo2 = new SeasonLeaderboardsRepository($this->mockDb);

        $this->assertNotSame($repo1, $repo2);
    }
}
