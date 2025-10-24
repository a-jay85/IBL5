<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use PHPUnit\Framework\TestCase;
use Voting\VotingResultsController;
use Voting\VotingResultsProvider;
use Voting\VotingResultsTableRenderer;

final class VotingResultsControllerTest extends TestCase
{
    public function testRenderUsesAllStarResultsDuringRegularSeason(): void
    {
        $provider = new StubVotingResultsProvider();
        $renderer = new StubVotingResultsRenderer();
        $season = $this->createSeason('Regular Season');

        $controller = new VotingResultsController($provider, $renderer, $season);
        $output = $controller->render();

        $this->assertSame(1, $provider->allStarCalls);
        $this->assertSame(0, $provider->endOfYearCalls);
        $this->assertSame('All-Star', $output);
        $this->assertSame($provider->allStarResponse, $renderer->lastRenderedTables);
    }

    public function testRenderUsesEndOfYearResultsOutsideRegularSeason(): void
    {
        $provider = new StubVotingResultsProvider();
        $renderer = new StubVotingResultsRenderer();
        $season = $this->createSeason('Playoffs');

        $controller = new VotingResultsController($provider, $renderer, $season);
        $output = $controller->render();

        $this->assertSame(0, $provider->allStarCalls);
        $this->assertSame(1, $provider->endOfYearCalls);
        $this->assertSame('End-Of-Year', $output);
        $this->assertSame($provider->endOfYearResponse, $renderer->lastRenderedTables);
    }

    public function testExplicitRenderMethodsBypassSeasonPhase(): void
    {
        $provider = new StubVotingResultsProvider();
        $renderer = new StubVotingResultsRenderer();
        $season = $this->createSeason('Free Agency');

        $controller = new VotingResultsController($provider, $renderer, $season);

        $allStar = $controller->renderAllStarView();
        $endOfYear = $controller->renderEndOfYearView();

        $this->assertSame('All-Star', $allStar);
        $this->assertSame('End-Of-Year', $endOfYear);
        $this->assertSame(1, $provider->allStarCalls);
        $this->assertSame(1, $provider->endOfYearCalls);
    }

    private function createSeason(string $phase): Season
    {
        $season = new Season(new MockDatabase());
        $season->phase = $phase;

        return $season;
    }
}

final class StubVotingResultsProvider implements VotingResultsProvider
{
    public array $allStarResponse = [['title' => 'All-Star', 'rows' => []]];
    public array $endOfYearResponse = [['title' => 'End-Of-Year', 'rows' => []]];
    public int $allStarCalls = 0;
    public int $endOfYearCalls = 0;

    public function getAllStarResults(): array
    {
        $this->allStarCalls++;
        return $this->allStarResponse;
    }

    public function getEndOfYearResults(): array
    {
        $this->endOfYearCalls++;
        return $this->endOfYearResponse;
    }
}

final class StubVotingResultsRenderer extends VotingResultsTableRenderer
{
    /** @var array<int, array{title: string, rows: array<int, array{name: string, votes: int}>}> */
    public array $lastRenderedTables = [];

    public function renderTables(array $tables): string
    {
        $this->lastRenderedTables = $tables;
        return $tables[0]['title'] ?? '';
    }
}
