<?php

declare(strict_types=1);

namespace Tests\Api;

use Api\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testMatchesHealth(): void
    {
        $result = $this->router->match('health', 'GET');

        $this->assertNotNull($result);
        $this->assertSame('Api\Controller\HealthController', $result['controller']);
        $this->assertSame([], $result['params']);
    }

    public function testHealthIsListedAsPublicRoute(): void
    {
        $this->assertContains('health', Router::PUBLIC_ROUTES);
    }

    public function testMatchesPlayersExport(): void
    {
        $result = $this->router->match('players/export', 'GET');

        $this->assertNotNull($result);
        $this->assertSame('Api\Controller\PlayerExportController', $result['controller']);
        $this->assertSame([], $result['params']);
    }

    public function testMatchesPlayersList(): void
    {
        $result = $this->router->match('players', 'GET');

        $this->assertNotNull($result);
        $this->assertSame('Api\Controller\PlayerListController', $result['controller']);
        $this->assertSame([], $result['params']);
    }

    public function testMatchesPlayerDetailWithUuid(): void
    {
        $uuid = '479337fd-bb40-11f0-a2a0-2c44fd7a1534';
        $result = $this->router->match('players/' . $uuid, 'GET');

        $this->assertNotNull($result);
        $this->assertSame('Api\Controller\PlayerDetailController', $result['controller']);
        $this->assertSame(['uuid' => $uuid], $result['params']);
    }

    public function testMatchesPlayerStats(): void
    {
        $uuid = '479337fd-bb40-11f0-a2a0-2c44fd7a1534';
        $result = $this->router->match('players/' . $uuid . '/stats', 'GET');

        $this->assertNotNull($result);
        $this->assertSame('Api\Controller\PlayerStatsController', $result['controller']);
        $this->assertSame(['uuid' => $uuid], $result['params']);
    }

    public function testMatchesPlayerHistory(): void
    {
        $uuid = '479337fd-bb40-11f0-a2a0-2c44fd7a1534';
        $result = $this->router->match('players/' . $uuid . '/history', 'GET');

        $this->assertNotNull($result);
        $this->assertSame('Api\Controller\PlayerHistoryController', $result['controller']);
        $this->assertSame(['uuid' => $uuid], $result['params']);
    }

    public function testMatchesTeamsList(): void
    {
        $result = $this->router->match('teams', 'GET');

        $this->assertNotNull($result);
        $this->assertSame('Api\Controller\TeamListController', $result['controller']);
        $this->assertSame([], $result['params']);
    }

    public function testMatchesTeamDetail(): void
    {
        $uuid = 'abcdef01-2345-6789-abcd-ef0123456789';
        $result = $this->router->match('teams/' . $uuid, 'GET');

        $this->assertNotNull($result);
        $this->assertSame('Api\Controller\TeamDetailController', $result['controller']);
        $this->assertSame(['uuid' => $uuid], $result['params']);
    }

    public function testMatchesTeamRoster(): void
    {
        $uuid = 'abcdef01-2345-6789-abcd-ef0123456789';
        $result = $this->router->match('teams/' . $uuid . '/roster', 'GET');

        $this->assertNotNull($result);
        $this->assertSame('Api\Controller\TeamRosterController', $result['controller']);
        $this->assertSame(['uuid' => $uuid], $result['params']);
    }

    public function testMatchesStandingsAll(): void
    {
        $result = $this->router->match('standings', 'GET');

        $this->assertNotNull($result);
        $this->assertSame('Api\Controller\StandingsController', $result['controller']);
        $this->assertSame([], $result['params']);
    }

    public function testMatchesStandingsByConference(): void
    {
        $result = $this->router->match('standings/East', 'GET');

        $this->assertNotNull($result);
        $this->assertSame('Api\Controller\StandingsController', $result['controller']);
        $this->assertSame(['conference' => 'East'], $result['params']);
    }

    public function testMatchesStandingsWestConference(): void
    {
        $result = $this->router->match('standings/West', 'GET');

        $this->assertNotNull($result);
        $this->assertSame('Api\Controller\StandingsController', $result['controller']);
        $this->assertSame(['conference' => 'West'], $result['params']);
    }

    public function testMatchesStandingsEasternConference(): void
    {
        $result = $this->router->match('standings/Eastern', 'GET');

        $this->assertNotNull($result);
        $this->assertSame('Api\Controller\StandingsController', $result['controller']);
        $this->assertSame(['conference' => 'Eastern'], $result['params']);
    }

    public function testMatchesStandingsWesternConference(): void
    {
        $result = $this->router->match('standings/Western', 'GET');

        $this->assertNotNull($result);
        $this->assertSame('Api\Controller\StandingsController', $result['controller']);
        $this->assertSame(['conference' => 'Western'], $result['params']);
    }

    public function testMatchesGamesList(): void
    {
        $result = $this->router->match('games', 'GET');

        $this->assertNotNull($result);
        $this->assertSame('Api\Controller\GameListController', $result['controller']);
        $this->assertSame([], $result['params']);
    }

    public function testMatchesGameDetail(): void
    {
        $uuid = 'abcdef01-2345-6789-abcd-ef0123456789';
        $result = $this->router->match('games/' . $uuid, 'GET');

        $this->assertNotNull($result);
        $this->assertSame('Api\Controller\GameDetailController', $result['controller']);
        $this->assertSame(['uuid' => $uuid], $result['params']);
    }

    public function testMatchesGameBoxscore(): void
    {
        $uuid = 'abcdef01-2345-6789-abcd-ef0123456789';
        $result = $this->router->match('games/' . $uuid . '/boxscore', 'GET');

        $this->assertNotNull($result);
        $this->assertSame('Api\Controller\GameBoxscoreController', $result['controller']);
        $this->assertSame(['uuid' => $uuid], $result['params']);
    }

    public function testMatchesStatsLeaders(): void
    {
        $result = $this->router->match('stats/leaders', 'GET');

        $this->assertNotNull($result);
        $this->assertSame('Api\Controller\LeadersController', $result['controller']);
        $this->assertSame([], $result['params']);
    }

    public function testMatchesInjuries(): void
    {
        $result = $this->router->match('injuries', 'GET');

        $this->assertNotNull($result);
        $this->assertSame('Api\Controller\InjuriesController', $result['controller']);
        $this->assertSame([], $result['params']);
    }

    /**
     * Characterization: lock the full set of resolvable GET routes so inserting a
     * new route cannot silently alter dispatch of an existing one.
     *
     */
    #[DataProvider('currentGetRouteProvider')]
    public function testCurrentGetRouteSetResolves(string $path, string $expectedController): void
    {
        $result = $this->router->match($path, 'GET');

        $this->assertNotNull($result);
        $this->assertSame($expectedController, $result['controller']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function currentGetRouteProvider(): array
    {
        $uuid = '479337fd-bb40-11f0-a2a0-2c44fd7a1534';

        return [
            'players/export'      => ['players/export', 'Api\Controller\PlayerExportController'],
            'players/{uuid}/stats' => ['players/' . $uuid . '/stats', 'Api\Controller\PlayerStatsController'],
            'players/{uuid}/history' => ['players/' . $uuid . '/history', 'Api\Controller\PlayerHistoryController'],
            'players/{uuid}'      => ['players/' . $uuid, 'Api\Controller\PlayerDetailController'],
            'players'             => ['players', 'Api\Controller\PlayerListController'],
            'teams/{uuid}/roster' => ['teams/' . $uuid . '/roster', 'Api\Controller\TeamRosterController'],
            'teams/{uuid}'        => ['teams/' . $uuid, 'Api\Controller\TeamDetailController'],
            'teams'               => ['teams', 'Api\Controller\TeamListController'],
            'standings/{conference}' => ['standings/East', 'Api\Controller\StandingsController'],
            'standings'           => ['standings', 'Api\Controller\StandingsController'],
            'games/{uuid}/boxscore' => ['games/' . $uuid . '/boxscore', 'Api\Controller\GameBoxscoreController'],
            'games/{uuid}'        => ['games/' . $uuid, 'Api\Controller\GameDetailController'],
            'games'               => ['games', 'Api\Controller\GameListController'],
            'stats/leaders'       => ['stats/leaders', 'Api\Controller\LeadersController'],
            'injuries'            => ['injuries', 'Api\Controller\InjuriesController'],
            'season'              => ['season', 'Api\Controller\SeasonController'],
        ];
    }

    public function testReturnsNullForUnknownRoute(): void
    {
        $result = $this->router->match('nonexistent', 'GET');

        $this->assertNull($result);
    }

    public function testReturnsNullForInvalidUuid(): void
    {
        $result = $this->router->match('players/not-a-valid-uuid', 'GET');

        $this->assertNull($result);
    }

    public function testReturnsNullForInvalidConference(): void
    {
        $result = $this->router->match('standings/North', 'GET');

        $this->assertNull($result);
    }

    public function testReturnsNullForNonGetMethod(): void
    {
        $result = $this->router->match('players', 'POST');

        $this->assertNull($result);
    }

    public function testReturnsNullForDeleteMethod(): void
    {
        $result = $this->router->match('players', 'DELETE');

        $this->assertNull($result);
    }

    public function testStripsLeadingAndTrailingSlashes(): void
    {
        $result = $this->router->match('/players/', 'GET');

        $this->assertNotNull($result);
        $this->assertSame('Api\Controller\PlayerListController', $result['controller']);
    }

    public function testUuidMatchIsCaseInsensitive(): void
    {
        $uuid = '479337FD-BB40-11F0-A2A0-2C44FD7A1534';
        $result = $this->router->match('players/' . $uuid, 'GET');

        $this->assertNotNull($result);
        $this->assertSame(['uuid' => $uuid], $result['params']);
    }
}
