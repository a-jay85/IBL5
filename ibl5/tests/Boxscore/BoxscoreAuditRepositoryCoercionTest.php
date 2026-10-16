<?php

declare(strict_types=1);

namespace Tests\Boxscore;

use Boxscore\BoxscoreAuditRepository;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * Pins scalar coercion of BoxscoreAuditRepository row mapping.
 *
 * @covers \Boxscore\BoxscoreAuditRepository
 */
final class BoxscoreAuditRepositoryCoercionTest extends TestCase
{
    private MockDatabase $mockDb;
    private BoxscoreAuditRepository $repository;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->repository = new BoxscoreAuditRepository($this->mockDb);
    }

    public function testFindScheduledGamesWithoutBoxscoresCoercesMixedScalarColumns(): void
    {
        $this->mockDb->onQuery('NOT EXISTS', [
            ['game_date' => '2025-03-01', 'visitor_teamid' => '8', 'home_teamid' => 11, 'visitor_score' => 101.0, 'home_score' => '99'],
        ]);

        $result = $this->repository->findScheduledGamesWithoutBoxscores(2025);

        $this->assertSame(
            [['game_date' => '2025-03-01', 'visitor_teamid' => 8, 'home_teamid' => 11, 'visitor_score' => 101, 'home_score' => 99]],
            $result
        );
    }

    public function testFindScheduledGamesWithoutBoxscoresCoercesNonNumericToZeroAndEmpty(): void
    {
        $this->mockDb->onQuery('NOT EXISTS', [
            ['game_date' => false, 'visitor_teamid' => 'abc', 'home_teamid' => [1], 'visitor_score' => null, 'home_score' => '7x'],
        ]);

        $result = $this->repository->findScheduledGamesWithoutBoxscores(2025);

        $this->assertSame(
            [['game_date' => '', 'visitor_teamid' => 0, 'home_teamid' => 0, 'visitor_score' => 0, 'home_score' => 0]],
            $result
        );
    }
}
