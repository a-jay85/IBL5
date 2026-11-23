<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Cache\DatabaseCache;
use PHPUnit\Framework\Attributes\Group;
use Repositories\TeamIdentityRepository;
use Team\Team;
use Team\TeamAccomplishmentsCache;
use Team\TeamRepository;
use Updater\Steps\RefreshTeamAccomplishmentsStep;

/**
 * Cold and warm cache reads must equal the repository rows for every real franchise. The byte
 * comparison fails if get() reorders rows, for example by returning array_reverse($fresh).
 *
 * The page reads Team::initialize()->name and the step rebuilds the names from
 * realTeamNames(), which the plan called teamNamesFromDb(). The two must agree for every teamid.
 */
#[Group('database')]
class TeamAccomplishmentsCacheTest extends DatabaseTestCase
{
    private TeamRepository $repo;
    private TeamAccomplishmentsCache $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new TeamRepository($this->db);
        $this->cache = new TeamAccomplishmentsCache($this->repo, new DatabaseCache($this->db));
    }

    public function testCachedRowsByteIdenticalToRepositoryForEveryRealTeam(): void
    {
        $teamNames = RefreshTeamAccomplishmentsStep::realTeamNames(new TeamIdentityRepository($this->db));
        self::assertNotSame([], $teamNames);

        foreach ($teamNames as $teamName) {
            $expected = $this->repo->getTeamAccomplishments($teamName);

            $this->cache->invalidate($teamName);
            $cold = $this->cache->get($teamName);
            $warm = $this->cache->get($teamName);

            self::assertSame($expected, $warm, 'warm cache row differs for ' . $teamName);
            self::assertSame(json_encode($expected), json_encode($cold), 'cold cache row differs for ' . $teamName);
        }
    }

    public function testTeamPageNameMatchesTeamInfoNameForEveryRealTeam(): void
    {
        $identityRepo = new TeamIdentityRepository($this->db);
        $teams = $identityRepo->getAllRealTeams('teamid ASC');
        $teamNames = RefreshTeamAccomplishmentsStep::realTeamNames($identityRepo);
        self::assertNotSame([], $teams);
        self::assertCount(count($teams), $teamNames);

        foreach ($teams as $index => $team) {
            self::assertSame(
                $teamNames[$index],
                Team::initialize($this->db, $team['teamid'])->name,
                'Team page name differs from the cache key name for teamid ' . $team['teamid'],
            );
        }
    }
}
