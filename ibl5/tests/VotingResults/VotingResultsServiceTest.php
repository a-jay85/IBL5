<?php

declare(strict_types=1);

namespace Tests\VotingResults;

use PHPUnit\Framework\TestCase;
use Voting\Contracts\VotingRepositoryInterface;
use Voting\VotingResultsService;

/**
 * @covers \Voting\VotingResultsService
 */
final class VotingResultsServiceTest extends TestCase
{
    public function testGetAllStarResultsReturnsFourCategoriesWithCorrectTitles(): void
    {
        $repository = $this->createStub(VotingRepositoryInterface::class);
        $repository->method('fetchAllStarTotals')->willReturn([]);

        $service = new VotingResultsService($repository);
        $results = $service->getAllStarResults();

        $this->assertCount(4, $results);
        $this->assertSame('Eastern Conference Frontcourt', $results[0]['title']);
        $this->assertSame('Eastern Conference Backcourt', $results[1]['title']);
        $this->assertSame('Western Conference Frontcourt', $results[2]['title']);
        $this->assertSame('Western Conference Backcourt', $results[3]['title']);
    }

    public function testGetAllStarResultsReturnsRowsFromRepository(): void
    {
        $repository = $this->createStub(VotingRepositoryInterface::class);
        $repository->method('fetchAllStarTotals')->willReturnOnConsecutiveCalls(
            [
                ['name' => 'Mason Lee', 'votes' => 5, 'pid' => 101],
                ['name' => 'Aaron Smith', 'votes' => 2, 'pid' => 102],
            ],
            [],
            [],
            [],
        );

        $service = new VotingResultsService($repository);
        $results = $service->getAllStarResults();

        $this->assertSame([
            ['name' => 'Mason Lee', 'votes' => 5, 'pid' => 101],
            ['name' => 'Aaron Smith', 'votes' => 2, 'pid' => 102],
        ], $results[0]['rows']);
        $this->assertSame([], $results[1]['rows']);
    }

    public function testGetEndOfYearResultsReturnsFourCategoriesWithCorrectTitles(): void
    {
        $repository = $this->createStub(VotingRepositoryInterface::class);
        $repository->method('fetchEndOfYearTotals')->willReturn([]);

        $service = new VotingResultsService($repository);
        $results = $service->getEndOfYearResults();

        $this->assertCount(4, $results);
        $this->assertSame('Most Valuable Player', $results[0]['title']);
        $this->assertSame('Sixth Man of the Year', $results[1]['title']);
        $this->assertSame('Rookie of the Year', $results[2]['title']);
        $this->assertSame('GM of the Year', $results[3]['title']);
    }

    public function testGetEndOfYearResultsReturnsRowsFromRepository(): void
    {
        $repository = $this->createStub(VotingRepositoryInterface::class);
        $repository->method('fetchEndOfYearTotals')->willReturnOnConsecutiveCalls(
            [
                ['name' => 'Alana Cruz', 'votes' => 21, 'pid' => 200],
                ['name' => 'Bree Jones', 'votes' => 19, 'pid' => 201],
            ],
            [['name' => 'Chris Owens', 'votes' => 13, 'pid' => 202]],
            [['name' => 'Dev Patel', 'votes' => 11, 'pid' => 203]],
            [['name' => 'Eddie Reed', 'votes' => 9, 'pid' => 0]],
        );

        $service = new VotingResultsService($repository);
        $results = $service->getEndOfYearResults();

        $this->assertSame([
            ['name' => 'Alana Cruz', 'votes' => 21, 'pid' => 200],
            ['name' => 'Bree Jones', 'votes' => 19, 'pid' => 201],
        ], $results[0]['rows']);

        // GM of the Year: pid=0 because GM names don't match players
        $this->assertSame([
            ['name' => 'Eddie Reed', 'votes' => 9, 'pid' => 0],
        ], $results[3]['rows']);
    }
}
