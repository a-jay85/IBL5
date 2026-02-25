<?php

declare(strict_types=1);

namespace Tests\VotingResults;
require_once __DIR__ . '/../bootstrap.php';

use PHPUnit\Framework\TestCase;
use Voting\VotingResultsService;

final class VotingResultsServiceTest extends TestCase
{
    private FakeVotingDatabase $database;
    private VotingResultsService $service;

    protected function setUp(): void
    {
        $this->database = new FakeVotingDatabase();
        $this->service = new VotingResultsService($this->database);
    }

    public function testGetAllStarResultsReturnsSortedTotals(): void
    {
        $this->database->queueResults([
            // Category 1: vote query results
            [
                ['name' => '', 'votes' => '9'],
                ['name' => 'Mason Lee', 'votes' => '5'],
                ['name' => 'Aaron Smith', 'votes' => '2'],
                ['name' => 'Zeke Adams', 'votes' => '2'],
            ],
            // Category 1: pid resolution results
            [
                ['pid' => 101, 'name' => 'Mason Lee'],
                ['pid' => 102, 'name' => 'Aaron Smith'],
            ],
            // Categories 2-4: empty vote results (no pid query needed for empty results)
            [],
            [],
            [],
        ]);

        $results = $this->service->getAllStarResults();

        $this->assertCount(4, $results);
        $this->assertSame('Eastern Conference Frontcourt', $results[0]['title']);
        $this->assertSame([
            ['name' => VotingResultsService::BLANK_BALLOT_LABEL, 'votes' => 9, 'pid' => 0],
            ['name' => 'Mason Lee', 'votes' => 5, 'pid' => 101],
            ['name' => 'Aaron Smith', 'votes' => 2, 'pid' => 102],
            ['name' => 'Zeke Adams', 'votes' => 2, 'pid' => 0],
        ], $results[0]['rows']);

        $queries = $this->database->getExecutedQueries();
        $this->assertCount(5, $queries);
        $this->assertStringContainsString('East_F1', $queries[0]);
        $this->assertStringContainsString('ibl_plr', $queries[1]);
        $this->assertStringContainsString('East_B4', $queries[2]);
        $this->assertStringContainsString('West_F4', $queries[3]);
        $this->assertStringContainsString('West_B4', $queries[4]);
    }

    public function testAllStarNamesWithTeamSuffixResolveCorrectly(): void
    {
        $this->database->queueResults([
            // Category 1: vote query returns "Name, Team" format
            [
                ['name' => 'LeBron James, Sting', 'votes' => '23'],
                ['name' => "Jermaine O'Neal, Nets", 'votes' => '25'],
            ],
            // Category 1: pid resolution uses extracted player name
            [
                ['pid' => 10, 'name' => 'LeBron James'],
                ['pid' => 11, 'name' => "Jermaine O'Neal"],
            ],
            // Categories 2-4: empty
            [],
            [],
            [],
        ]);

        $results = $this->service->getAllStarResults();

        $this->assertSame([
            ['name' => 'LeBron James, Sting', 'votes' => 23, 'pid' => 10],
            ['name' => "Jermaine O'Neal, Nets", 'votes' => 25, 'pid' => 11],
        ], $results[0]['rows']);
    }

    public function testGetEndOfYearResultsReturnsWeightedTotals(): void
    {
        $this->database->queueResults([
            // MVP: vote query results
            [
                ['name' => 'Alana Cruz', 'votes' => '21'],
                ['name' => 'Bree Jones', 'votes' => '19'],
            ],
            // MVP: pid resolution results
            [
                ['pid' => 200, 'name' => 'Alana Cruz'],
                ['pid' => 201, 'name' => 'Bree Jones'],
            ],
            // Sixth Man + pid resolution
            [
                ['name' => 'Chris Owens', 'votes' => '13'],
            ],
            [
                ['pid' => 202, 'name' => 'Chris Owens'],
            ],
            // ROY + pid resolution
            [
                ['name' => 'Dev Patel', 'votes' => '11'],
            ],
            [
                ['pid' => 203, 'name' => 'Dev Patel'],
            ],
            // GM of Year + pid resolution (GMs won't match players)
            [
                ['name' => 'Eddie Reed', 'votes' => '9'],
            ],
            [],
        ]);

        $results = $this->service->getEndOfYearResults();

        $this->assertCount(4, $results);
        $this->assertSame('Most Valuable Player', $results[0]['title']);
        $this->assertSame([
            ['name' => 'Alana Cruz', 'votes' => 21, 'pid' => 200],
            ['name' => 'Bree Jones', 'votes' => 19, 'pid' => 201],
        ], $results[0]['rows']);

        // GM of the Year: pid=0 because GM names don't match players
        $this->assertSame([
            ['name' => 'Eddie Reed', 'votes' => 9, 'pid' => 0],
        ], $results[3]['rows']);

        $queries = $this->database->getExecutedQueries();
        $this->assertCount(8, $queries);
        $this->assertStringContainsString('MVP_1', $queries[0]);
        $this->assertStringContainsString('3 AS score', $queries[0]);
        $this->assertStringContainsString('ibl_plr', $queries[1]);
        $this->assertStringContainsString('Six_2', $queries[2]);
        $this->assertStringContainsString('ROY_3', $queries[4]);
        $this->assertStringContainsString('GM_1', $queries[6]);
    }
}

/**
 * Lightweight in-memory database stub for deterministic testing.
 * Extends mysqli to satisfy type hints while providing test functionality.
 */
final class FakeVotingDatabase extends \mysqli
{
    /** @var array<int, array<int, array{name: string, votes: string}>> */
    private array $resultsQueue = [];

    /** @var array<int, string> */
    private array $executedQueries = [];

    public function __construct()
    {
        // Don't call parent constructor - we're a fake
    }

    public function queueResults(array $resultsQueue): void
    {
        $this->resultsQueue = $resultsQueue;
        $this->executedQueries = [];
    }

    #[\ReturnTypeWillChange]
    public function prepare(string $query): FakeVotingStatement|false
    {
        $this->executedQueries[] = $query;
        $data = array_shift($this->resultsQueue) ?? [];

        return new FakeVotingStatement($data);
    }

    public function getExecutedQueries(): array
    {
        return $this->executedQueries;
    }
}

/**
 * Fake prepared statement for testing.
 */
final class FakeVotingStatement
{
    /** @var array<int, array{name: string, votes: string}> */
    private array $rows;

    public function __construct(array $rows)
    {
        $this->rows = array_values($rows);
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function get_result(): FakeVotingResult
    {
        return new FakeVotingResult($this->rows);
    }

    public function close(): void
    {
        // No-op
    }
}

final class FakeVotingResult
{
    /** @var array<int, array{name: string, votes: string}> */
    private array $rows;

    private int $position = 0;

    public function __construct(array $rows)
    {
        $this->rows = array_values($rows);
    }

    public function fetch_assoc(): array|false
    {
        if ($this->position >= count($this->rows)) {
            return false;
        }

        return $this->rows[$this->position++];
    }
}
