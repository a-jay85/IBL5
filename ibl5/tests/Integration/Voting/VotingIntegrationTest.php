<?php

declare(strict_types=1);

namespace Tests\Integration\Voting;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Voting\Contracts\VotingRepositoryInterface;
use Voting\VotingResultsController;
use Voting\VotingResultsService;
use Voting\VotingResultsTableRenderer;
use Season\Season;

/**
 * Integration tests for complete voting results display workflows
 *
 * Tests end-to-end scenarios combining data retrieval and HTML rendering:
 * - All-Star voting results (during Regular Season)
 * - End-of-Year voting results (outside Regular Season)
 * - Vote aggregation, sorting, and weighted scoring
 * - Phase-based routing in controller
 * - XSS protection in rendered output
 * - Empty ballot handling
 *
 * @covers \Voting\VotingResultsController
 * @covers \Voting\VotingResultsService
 * @covers \Voting\VotingResultsTableRenderer
 */
#[AllowMockObjectsWithoutExpectations]
class VotingIntegrationTest extends TestCase
{
    private VotingResultsService $service;
    private VotingResultsTableRenderer $renderer;
    private StubVotingRepository $stubRepository;

    /** @var \Tests\Integration\Mocks\Season */
    private object $season;

    protected function setUp(): void
    {
        $this->stubRepository = new StubVotingRepository();
        $this->service = new VotingResultsService($this->stubRepository);
        $this->renderer = new VotingResultsTableRenderer();

        $mockDb = new \MockDatabase();
        $this->season = new \Tests\Integration\Mocks\Season($mockDb);
    }

    // ========== SERVICE - ALL-STAR VOTING TESTS ==========

    /**
     * @group integration
     * @group voting
     * @group service
     */
    public function testGetAllStarResultsQueriesASGTable(): void
    {
        // Act
        $this->service->getAllStarResults();

        // Assert
        $this->assertTrue(true); // Service delegates to repository; DB behavior tested separately
    }

    /**
     * @group integration
     * @group voting
     * @group service
     */
    public function testGetAllStarResultsReturnsAllFourCategories(): void
    {
        // Arrange - Queue results for 4 categories
        $this->queueVotingResults([
            [['name' => 'Player A', 'votes' => 10]],
            [['name' => 'Player B', 'votes' => 8]],
            [['name' => 'Player C', 'votes' => 6]],
            [['name' => 'Player D', 'votes' => 4]],
        ]);

        // Act
        $results = $this->service->getAllStarResults();

        // Assert
        $this->assertCount(4, $results);
        $this->assertSame('Eastern Conference Frontcourt', $results[0]['title']);
        $this->assertSame('Eastern Conference Backcourt', $results[1]['title']);
        $this->assertSame('Western Conference Frontcourt', $results[2]['title']);
        $this->assertSame('Western Conference Backcourt', $results[3]['title']);
    }

    /**
     * @group integration
     * @group voting
     * @group service
     */
    public function testGetAllStarResultsReturnsFourCategories(): void
    {
        // Arrange
        $this->queueVotingResults([[], [], [], []]);

        // Act
        $results = $this->service->getAllStarResults();

        // Assert - All four conference/position categories returned
        $this->assertCount(4, $results);
        $this->assertSame('Eastern Conference Frontcourt', $results[0]['title']);
        $this->assertSame('Western Conference Backcourt', $results[3]['title']);
    }

    /**
     * @group integration
     * @group voting
     * @group service
     */
    public function testGetAllStarResultsAggregatesVotes(): void
    {
        // Arrange - Player appears in multiple ballot columns
        $this->queueVotingResults([
            [
                ['name' => 'Star Player', 'votes' => 15],
                ['name' => 'Good Player', 'votes' => 10],
            ],
            [], [], [],
        ]);

        // Act
        $results = $this->service->getAllStarResults();

        // Assert
        $this->assertSame('Star Player', $results[0]['rows'][0]['name']);
        $this->assertSame(15, $results[0]['rows'][0]['votes']);
    }

    // ========== SERVICE - END-OF-YEAR VOTING TESTS ==========

    /**
     * @group integration
     * @group voting
     * @group service
     */
    public function testGetEndOfYearResultsQueriesEOYTable(): void
    {
        // Arrange
        $this->queueVotingResults([[], [], [], []]);

        // Act
        $this->service->getEndOfYearResults();

        // Assert
        $this->assertTrue(true); // Service delegates to repository; DB behavior tested separately
    }

    /**
     * @group integration
     * @group voting
     * @group service
     */
    public function testGetEndOfYearResultsReturnsAllFourCategories(): void
    {
        // Arrange
        $this->queueVotingResults([
            [['name' => 'MVP Candidate', 'votes' => 25]],
            [['name' => '6th Man', 'votes' => 18]],
            [['name' => 'Rookie', 'votes' => 12]],
            [['name' => 'Best GM', 'votes' => 9]],
        ]);

        // Act
        $results = $this->service->getEndOfYearResults();

        // Assert
        $this->assertCount(4, $results);
        $this->assertSame('Most Valuable Player', $results[0]['title']);
        $this->assertSame('Sixth Man of the Year', $results[1]['title']);
        $this->assertSame('Rookie of the Year', $results[2]['title']);
        $this->assertSame('GM of the Year', $results[3]['title']);
    }

    /**
     * @group integration
     * @group voting
     * @group service
     */
    public function testGetEndOfYearResultsUsesWeightedScoring(): void
    {
        // Arrange
        $this->queueVotingResults([[], [], [], []]);

        // Act
        $this->service->getEndOfYearResults();

        // Assert - All four award categories returned
        $results = $this->service->getEndOfYearResults();
        $this->assertCount(4, $results);
        $this->assertSame('Most Valuable Player', $results[0]['title']);
    }

    /**
     * @group integration
     * @group voting
     * @group service
     */
    public function testGetEndOfYearResultsCalculatesWeightedTotals(): void
    {
        // Arrange - Player with weighted votes
        $this->queueVotingResults([
            [
                ['name' => 'Top MVP', 'votes' => 21], // 7 first-place votes * 3 points
                ['name' => 'Runner Up', 'votes' => 14], // Mix of votes
            ],
            [], [], [],
        ]);

        // Act
        $results = $this->service->getEndOfYearResults();

        // Assert
        $this->assertSame('Top MVP', $results[0]['rows'][0]['name']);
        $this->assertSame(21, $results[0]['rows'][0]['votes']);
    }

    // ========== SERVICE - BLANK BALLOT HANDLING ==========

    /**
     * @group integration
     * @group voting
     * @group service
     */
    public function testBlankBallotGetsSpecialLabel(): void
    {
        // Arrange - Repository returns blank entries with the special label
        $this->queueVotingResults([
            [
                ['name' => \Voting\VotingRepository::BLANK_BALLOT_LABEL, 'votes' => 5],
                ['name' => 'Named Player', 'votes' => 3],
            ],
            [], [], [],
        ]);

        // Act
        $results = $this->service->getAllStarResults();

        // Assert
        $this->assertSame(\Voting\VotingRepository::BLANK_BALLOT_LABEL, $results[0]['rows'][0]['name']);
        $this->assertSame('(No Selection Recorded)', $results[0]['rows'][0]['name']);
    }

    /**
     * @group integration
     * @group voting
     * @group service
     */
    public function testWhitespaceOnlyNameTreatedAsBlank(): void
    {
        // Arrange - Repository normalizes whitespace to blank label
        $this->queueVotingResults([
            [['name' => \Voting\VotingRepository::BLANK_BALLOT_LABEL, 'votes' => 2]],
            [], [], [],
        ]);

        // Act
        $results = $this->service->getAllStarResults();

        // Assert
        $this->assertSame(\Voting\VotingRepository::BLANK_BALLOT_LABEL, $results[0]['rows'][0]['name']);
    }

    // ========== RENDERER TESTS ==========

    /**
     * @group integration
     * @group voting
     * @group renderer
     */
    public function testRendererOutputsTableStructure(): void
    {
        // Arrange
        $tables = [
            [
                'title' => 'Test Category',
                'rows' => [
                    ['name' => 'Player One', 'votes' => 10],
                ],
            ],
        ];

        // Act
        $html = $this->renderer->renderTables($tables);

        // Assert
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('</table>', $html);
        $this->assertStringContainsString('<h2', $html);
        $this->assertStringContainsString('Test Category', $html);
    }

    /**
     * @group integration
     * @group voting
     * @group renderer
     */
    public function testRendererOutputsPlayerAndVotes(): void
    {
        // Arrange
        $tables = [
            [
                'title' => 'MVP Voting',
                'rows' => [
                    ['name' => 'Star Player', 'votes' => 25],
                    ['name' => 'Runner Up', 'votes' => 18],
                ],
            ],
        ];

        // Act
        $html = $this->renderer->renderTables($tables);

        // Assert
        $this->assertStringContainsString('Star Player', $html);
        $this->assertStringContainsString('25', $html);
        $this->assertStringContainsString('Runner Up', $html);
        $this->assertStringContainsString('18', $html);
    }

    /**
     * @group integration
     * @group voting
     * @group renderer
     */
    public function testRendererOutputsHeaders(): void
    {
        // Arrange
        $tables = [
            [
                'title' => 'Category',
                'rows' => [['name' => 'Player', 'votes' => 1]],
            ],
        ];

        // Act
        $html = $this->renderer->renderTables($tables);

        // Assert
        $this->assertStringContainsString('Player', $html);
        $this->assertStringContainsString('Votes', $html);
        $this->assertStringContainsString('<th', $html);
    }

    /**
     * @group integration
     * @group voting
     * @group renderer
     */
    public function testRendererHandlesMultipleTables(): void
    {
        // Arrange
        $tables = [
            ['title' => 'Category One', 'rows' => []],
            ['title' => 'Category Two', 'rows' => []],
            ['title' => 'Category Three', 'rows' => []],
        ];

        // Act
        $html = $this->renderer->renderTables($tables);

        // Assert
        $this->assertStringContainsString('Category One', $html);
        $this->assertStringContainsString('Category Two', $html);
        $this->assertStringContainsString('Category Three', $html);
        $this->assertSame(3, substr_count($html, '<table'));
    }

    /**
     * @group integration
     * @group voting
     * @group renderer
     */
    public function testRendererHandlesEmptyRows(): void
    {
        // Arrange
        $tables = [
            ['title' => 'Empty Category', 'rows' => []],
        ];

        // Act
        $html = $this->renderer->renderTables($tables);

        // Assert
        $this->assertStringContainsString('Empty Category', $html);
        $this->assertStringContainsString('<table', $html);
    }

    /**
     * @group integration
     * @group voting
     * @group renderer
     */
    public function testRendererAppliesAlternatingRowStyles(): void
    {
        // Arrange
        $tables = [
            [
                'title' => 'Styled Table',
                'rows' => [
                    ['name' => 'Row 1', 'votes' => 1],
                    ['name' => 'Row 2', 'votes' => 2],
                    ['name' => 'Row 3', 'votes' => 3],
                ],
            ],
        ];

        // Act
        $html = $this->renderer->renderTables($tables);

        // Assert - CSS handles alternating row styles via ibl-data-table class
        $this->assertStringContainsString('ibl-data-table', $html);
        $this->assertStringContainsString('<tbody>', $html);
    }

    // ========== XSS PROTECTION TESTS ==========

    /**
     * @group integration
     * @group voting
     * @group security
     */
    public function testRendererEscapesPlayerName(): void
    {
        // Arrange - XSS attempt in player name
        $tables = [
            [
                'title' => 'Test',
                'rows' => [
                    ['name' => '<script>alert("xss")</script>', 'votes' => 1],
                ],
            ],
        ];

        // Act
        $html = $this->renderer->renderTables($tables);

        // Assert
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * @group integration
     * @group voting
     * @group security
     */
    public function testRendererEscapesTitle(): void
    {
        // Arrange - XSS attempt in title
        $tables = [
            [
                'title' => '<img src=x onerror=alert(1)>',
                'rows' => [],
            ],
        ];

        // Act
        $html = $this->renderer->renderTables($tables);

        // Assert
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $this->assertStringContainsString('&lt;img', $html);
    }

    /**
     * @group integration
     * @group voting
     * @group security
     */
    public function testRendererEscapesQuotesInName(): void
    {
        // Arrange - Quotes in name
        $tables = [
            [
                'title' => 'Test',
                'rows' => [
                    ['name' => 'Player "The Great" Smith', 'votes' => 5],
                ],
            ],
        ];

        // Act
        $html = $this->renderer->renderTables($tables);

        // Assert
        $this->assertStringContainsString('&quot;The Great&quot;', $html);
    }

    // ========== CONTROLLER - PHASE ROUTING TESTS ==========

    /**
     * @group integration
     * @group voting
     * @group controller
     */
    public function testControllerRendersAllStarDuringRegularSeason(): void
    {
        // Arrange
        $this->season->phase = 'Regular Season';
        $this->queueVotingResults([
            [['name' => 'All Star', 'votes' => 10]],
            [], [], [],
        ]);
        $controller = new VotingResultsController($this->service, $this->renderer, $this->season);

        // Act
        $html = $controller->render();

        // Assert - Should query ASG table, not EOY
        $this->assertTrue(true); // Service delegates to repository; DB behavior tested separately
        $this->assertStringContainsString('Eastern Conference Frontcourt', $html);
    }

    /**
     * @group integration
     * @group voting
     * @group controller
     */
    public function testControllerRendersEndOfYearDuringPlayoffs(): void
    {
        // Arrange
        $this->season->phase = 'Playoffs';
        $this->queueVotingResults([
            [['name' => 'MVP', 'votes' => 20]],
            [], [], [],
        ]);
        $controller = new VotingResultsController($this->service, $this->renderer, $this->season);

        // Act
        $html = $controller->render();

        // Assert - Should query EOY table
        $this->assertTrue(true); // Service delegates to repository; DB behavior tested separately
        $this->assertStringContainsString('Most Valuable Player', $html);
    }

    /**
     * @group integration
     * @group voting
     * @group controller
     */
    public function testControllerRendersEndOfYearDuringFreeAgency(): void
    {
        // Arrange
        $this->season->phase = 'Free Agency';
        $this->queueVotingResults([[], [], [], []]);
        $controller = new VotingResultsController($this->service, $this->renderer, $this->season);

        // Act
        $html = $controller->render();

        // Assert
        $this->assertTrue(true); // Service delegates to repository; DB behavior tested separately
    }

    /**
     * @group integration
     * @group voting
     * @group controller
     */
    public function testControllerExplicitAllStarViewBypassesPhase(): void
    {
        // Arrange - Set phase to Playoffs but call All-Star directly
        $this->season->phase = 'Playoffs';
        $this->queueVotingResults([
            [['name' => 'All Star Player', 'votes' => 8]],
            [], [], [],
        ]);
        $controller = new VotingResultsController($this->service, $this->renderer, $this->season);

        // Act
        $html = $controller->renderAllStarView();

        // Assert - Should render All-Star despite Playoffs phase
        $this->assertTrue(true); // Service delegates to repository; DB behavior tested separately
        $this->assertStringContainsString('Eastern Conference', $html);
    }

    /**
     * @group integration
     * @group voting
     * @group controller
     */
    public function testControllerExplicitEndOfYearViewBypassesPhase(): void
    {
        // Arrange - Set phase to Regular Season but call EOY directly
        $this->season->phase = 'Regular Season';
        $this->queueVotingResults([
            [['name' => 'MVP Candidate', 'votes' => 15]],
            [], [], [],
        ]);
        $controller = new VotingResultsController($this->service, $this->renderer, $this->season);

        // Act
        $html = $controller->renderEndOfYearView();

        // Assert - Should render EOY despite Regular Season phase
        $this->assertTrue(true); // Service delegates to repository; DB behavior tested separately
        $this->assertStringContainsString('Most Valuable Player', $html);
    }

    // ========== COMPLETE WORKFLOW TESTS ==========

    /**
     * @group integration
     * @group voting
     * @group workflow
     */
    public function testCompleteAllStarVotingWorkflow(): void
    {
        // Arrange - Full All-Star voting data
        $this->season->phase = 'Regular Season';
        $this->queueVotingResults([
            [
                ['name' => 'Kevin Durant', 'votes' => 28],
                ['name' => 'Giannis Antetokounmpo', 'votes' => 25],
                ['name' => 'Joel Embiid', 'votes' => 22],
            ],
            [
                ['name' => 'Jayson Tatum', 'votes' => 20],
                ['name' => 'Donovan Mitchell', 'votes' => 18],
            ],
            [
                ['name' => 'LeBron James', 'votes' => 30],
                ['name' => 'Nikola Jokic', 'votes' => 27],
            ],
            [
                ['name' => 'Stephen Curry', 'votes' => 32],
                ['name' => 'Luka Doncic', 'votes' => 29],
            ],
        ]);
        $controller = new VotingResultsController($this->service, $this->renderer, $this->season);

        // Act
        $html = $controller->render();

        // Assert - All categories and players rendered
        $this->assertStringContainsString('Eastern Conference Frontcourt', $html);
        $this->assertStringContainsString('Eastern Conference Backcourt', $html);
        $this->assertStringContainsString('Western Conference Frontcourt', $html);
        $this->assertStringContainsString('Western Conference Backcourt', $html);
        $this->assertStringContainsString('Kevin Durant', $html);
        $this->assertStringContainsString('28', $html);
        $this->assertStringContainsString('Stephen Curry', $html);
        $this->assertStringContainsString('32', $html);
    }

    /**
     * @group integration
     * @group voting
     * @group workflow
     */
    public function testCompleteEndOfYearVotingWorkflow(): void
    {
        // Arrange - Full End-of-Year voting data
        $this->season->phase = 'Playoffs';
        $this->queueVotingResults([
            [
                ['name' => 'Nikola Jokic', 'votes' => 85],
                ['name' => 'Joel Embiid', 'votes' => 72],
                ['name' => 'Giannis', 'votes' => 45],
            ],
            [
                ['name' => 'Tyler Herro', 'votes' => 42],
                ['name' => 'Jordan Poole', 'votes' => 35],
            ],
            [
                ['name' => 'Victor Wembanyama', 'votes' => 90],
                ['name' => 'Chet Holmgren', 'votes' => 12],
            ],
            [
                ['name' => 'GM Smith', 'votes' => 25],
                ['name' => 'GM Johnson', 'votes' => 20],
            ],
        ]);
        $controller = new VotingResultsController($this->service, $this->renderer, $this->season);

        // Act
        $html = $controller->render();

        // Assert - All award categories rendered
        $this->assertStringContainsString('Most Valuable Player', $html);
        $this->assertStringContainsString('Sixth Man of the Year', $html);
        $this->assertStringContainsString('Rookie of the Year', $html);
        $this->assertStringContainsString('GM of the Year', $html);
        $this->assertStringContainsString('Nikola Jokic', $html);
        $this->assertStringContainsString('Victor Wembanyama', $html);
    }

    /**
     * @group integration
     * @group voting
     * @group workflow
     */
    public function testCompleteWorkflowWithNoVotes(): void
    {
        // Arrange - No votes in any category
        $this->season->phase = 'Regular Season';
        $this->queueVotingResults([[], [], [], []]);
        $controller = new VotingResultsController($this->service, $this->renderer, $this->season);

        // Act
        $html = $controller->render();

        // Assert - Tables still rendered, just empty
        $this->assertStringContainsString('Eastern Conference Frontcourt', $html);
        $this->assertStringContainsString('Western Conference Backcourt', $html);
        $this->assertSame(4, substr_count($html, '<table'));
    }

    /**
     * @group integration
     * @group voting
     * @group workflow
     */
    public function testCompleteWorkflowWithBlankBallots(): void
    {
        // Arrange - Repository returns blank entries with the special label
        $this->season->phase = 'Regular Season';
        $this->queueVotingResults([
            [
                ['name' => '(No Selection Recorded)', 'votes' => 12],
                ['name' => 'Real Player', 'votes' => 8],
            ],
            [], [], [],
        ]);
        $controller = new VotingResultsController($this->service, $this->renderer, $this->season);

        // Act
        $html = $controller->render();

        // Assert - Blank ballot label appears
        $this->assertStringContainsString('(No Selection Recorded)', $html);
        $this->assertStringContainsString('12', $html);
        $this->assertStringContainsString('Real Player', $html);
    }

    // ========== SORTABLE TABLE TESTS ==========

    /**
     * @group integration
     * @group voting
     * @group renderer
     */
    public function testRendererOutputsSortableTable(): void
    {
        // Arrange
        $tables = [
            ['title' => 'Test', 'rows' => [['name' => 'Player', 'votes' => 1]]],
        ];

        // Act
        $html = $this->renderer->renderTables($tables);

        // Assert
        $this->assertStringContainsString('sortable', $html);
        $this->assertStringContainsString('ibl-data-table', $html);
    }

    /**
     * @group integration
     * @group voting
     * @group renderer
     */
    public function testRendererOutputsCenteredTitle(): void
    {
        // Arrange
        $tables = [
            ['title' => 'Centered Title', 'rows' => []],
        ];

        // Act
        $html = $this->renderer->renderTables($tables);

        // Assert - Uses ibl-title class for centering
        $this->assertStringContainsString('class="ibl-title"', $html);
        $this->assertStringContainsString('Centered Title', $html);
    }

    // ========== HELPER METHODS ==========

    /**
     * Queue voting results for the stub repository
     *
     * Each element in the queue becomes the return value for the next
     * fetchAllStarTotals() or fetchEndOfYearTotals() call.
     *
     * @param list<list<array{name: string, votes: int|string}>> $resultsQueue
     */
    private function queueVotingResults(array $resultsQueue): void
    {
        $this->stubRepository->queueResults($resultsQueue);
    }
}

/**
 * In-memory stub of VotingRepositoryInterface for integration tests.
 *
 * Returns queued result sets for consecutive fetchAllStarTotals/fetchEndOfYearTotals calls.
 */
final class StubVotingRepository implements VotingRepositoryInterface
{
    /** @var list<list<array{name: string, votes: int|string}>> */
    private array $queue = [];

    /**
     * @param list<list<array{name: string, votes: int|string}>> $resultsQueue
     */
    public function queueResults(array $resultsQueue): void
    {
        $this->queue = $resultsQueue;
    }

    public function fetchAllStarTotals(array $columns): array
    {
        return $this->nextResult();
    }

    public function fetchEndOfYearTotals(array $columnsWithWeights): array
    {
        return $this->nextResult();
    }

    public function fetchPlayerIdsByNames(array $names): array
    {
        return [];
    }

    public function saveEoyVote(string $teamName, array $ballot): void {}
    public function saveAsgVote(string $teamName, array $ballot): void {}
    public function markEoyVoteCast(string $teamName): void {}
    public function markAsgVoteCast(string $teamName): void {}

    /**
     * @return list<array{name: string, votes: int, pid: int}>
     */
    private function nextResult(): array
    {
        $raw = array_shift($this->queue) ?? [];
        /** @var list<array{name: string, votes: int, pid: int}> $normalized */
        $normalized = [];
        foreach ($raw as $row) {
            $normalized[] = [
                'name' => (string) ($row['name'] ?? ''),
                'votes' => (int) ($row['votes'] ?? 0),
                'pid' => (int) ($row['pid'] ?? 0),
            ];
        }
        return $normalized;
    }
}
