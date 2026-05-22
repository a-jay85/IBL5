<?php

declare(strict_types=1);

namespace Tests\LastSimRecap;

use LastSimRecap\Contracts\LastSimRecapRepositoryInterface;
use LastSimRecap\Dto\RecapSlate;
use LastSimRecap\LastSimRecapService;
use PHPUnit\Framework\TestCase;
use Repositories\Contracts\PlayerLookupRepositoryInterface;

/**
 * Module-integration coverage for the Last-Sim Recap card on the News page.
 *
 * Verifies that the service contract surface that News/index.php depends on
 * returns the documented shapes — null for a team that didn't play in the
 * last sim, a populated RecapSlate otherwise.
 */
class NewsModuleIntegrationTest extends TestCase
{
    public function testServiceReturnsNullForTeamWithNoGames(): void
    {
        $repo = $this->repoWithGames([]);
        $playerLookup = $this->createStub(PlayerLookupRepositoryInterface::class);
        $playerLookup->method('getPlayerByID')->willReturn(null);
        $svc = new LastSimRecapService($repo, $playerLookup);

        self::assertNull($svc->buildSlateForTeam(7));
    }

    public function testServiceReturnsPopulatedSlateForTeamWithGames(): void
    {
        $repo = $this->repoWithGames([
            [
                'schedId' => 1, 'boxId' => 0, 'date' => '2026-05-13',
                'visitor' => 2, 'vScore' => 113, 'home' => 1, 'hScore' => 117,
                'year' => 2026,
            ],
        ]);
        $playerLookup = $this->createStub(PlayerLookupRepositoryInterface::class);
        $playerLookup->method('getPlayerByID')->willReturn(null);
        $svc = new LastSimRecapService($repo, $playerLookup);

        $slate = $svc->buildSlateForTeam(1);

        self::assertInstanceOf(RecapSlate::class, $slate);
        self::assertCount(1, $slate->games);
        self::assertSame(1, $slate->wins);
        self::assertSame(0, $slate->losses);
        self::assertSame('Cavaliers', $slate->teamName);
    }

    private function repoWithGames(array $games): LastSimRecapRepositoryInterface
    {
        return new class($games) implements LastSimRecapRepositoryInterface {
            public function __construct(private array $games) {}
            public function getLastSimWindow(): ?array {
                return ['sim' => 1, 'startDate' => '2026-05-01', 'endDate' => '2026-05-13'];
            }
            public function getGamesForTeamInWindow(int $tid, string $startDate, string $endDate): array { return $this->games; }
            public function getTeamBoxscoreLines(int $visitor, int $home, string $date): ?array {
                return [
                    'visQ' => [25, 25, 25, 25], 'homeQ' => [30, 30, 30, 27],
                    'visOT' => 0, 'homeOT' => 0,
                    'visitorPreWins' => 60, 'visitorPreLosses' => 22,
                    'homePreWins' => 52, 'homePreLosses' => 30,
                    'gameOfThatDay' => 1,
                ];
            }
            public function getActiveInjuriesForPlayers(array $playerIds, string $date): array { return []; }
            public function getTeamRosterPids(int $tid): array { return []; }
            public function getStarterPidsFromLastSim(int $tid): ?array { return null; }
            public function getStarterPidsFromSnapshot(int $tid, string $date): ?array { return null; }
            public function getStarterPidsFromBoxScores(int $schedId, int $tid): array {
                return ['PG' => 0, 'SG' => 0, 'SF' => 0, 'PF' => 0, 'C' => 0];
            }
            public function getPlayerLineForGame(int $pid, int $schedId): ?array { return null; }
            public function getTeamRecordAsOf(int $tid, string $date): array { return ['wins' => 52, 'losses' => 30]; }
            public function getTeamInfo(int $tid): ?array {
                $teams = [
                    1 => ['tid' => 1, 'city' => 'Cleveland', 'name' => 'Cavaliers'],
                    2 => ['tid' => 2, 'city' => 'Detroit', 'name' => 'Pistons'],
                ];
                return $teams[$tid] ?? null;
            }
        };
    }
}
