<?php

declare(strict_types=1);

namespace Tests\HeadToHeadRecords;

use HeadToHeadRecords\Contracts\HeadToHeadRecordsRepositoryInterface;
use HeadToHeadRecords\HeadToHeadRecordsController;
use HeadToHeadRecords\HeadToHeadRecordsView;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Season\Season;

/**
 * @covers \HeadToHeadRecords\HeadToHeadRecordsController
 */
#[AllowMockObjectsWithoutExpectations]
class HeadToHeadRecordsControllerTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $overrides
     * @return array{key: string, franchise_id: int, label: string, sublabel: string, color1: string, color2: string, logo: string, link_franchise_id: int}
     */
    private function makeEntry(array $overrides = []): array
    {
        return [
            'key'               => (string) ($overrides['key']               ?? 'celtics'),
            'franchise_id'      => (int)    ($overrides['franchise_id']      ?? 1),
            'label'             => (string) ($overrides['label']             ?? 'Celtics'),
            'sublabel'          => (string) ($overrides['sublabel']          ?? ''),
            'color1'            => (string) ($overrides['color1']            ?? ''),
            'color2'            => (string) ($overrides['color2']            ?? ''),
            'logo'              => (string) ($overrides['logo']              ?? ''),
            'link_franchise_id' => (int)    ($overrides['link_franchise_id'] ?? 0),
        ];
    }

    /**
     * @param list<array{key: string, franchise_id: int, label: string, sublabel: string, color1: string, color2: string, logo: string, link_franchise_id: int}> $axis
     * @return array{dimension: string, phase: string, scope: string, axis: list<array{key: string, franchise_id: int, label: string, sublabel: string, color1: string, color2: string, logo: string, link_franchise_id: int}>, records: array<string, array<string, array{wins: int, losses: int}>>}
     */
    private function makePayload(array $axis = []): array
    {
        return [
            'dimension' => 'franchises',
            'phase'     => 'regular',
            'scope'     => 'current',
            'axis'      => $axis,
            'records'   => [],
        ];
    }

    /**
     * Build a Season stub with a given phase string.
     *
     * @return Season&MockObject
     */
    private function makeSeasonWithPhase(string $phase): Season
    {
        /** @var Season&MockObject $season */
        $season = $this->createMock(Season::class);
        $season->phase = $phase;
        return $season;
    }

    /**
     * Build a controller with a fixed owner_name lookup.
     * Uses an anonymous subclass to override lookupOwnerName().
     */
    private function makeControllerWithOwner(
        HeadToHeadRecordsRepositoryInterface $repo,
        HeadToHeadRecordsView $view,
        Season $season,
        object $user,
        ?string $ownerName
    ): HeadToHeadRecordsController {
        return new class ($repo, $view, $season, $user, self::createStub(\mysqli::class), $ownerName)
            extends HeadToHeadRecordsController {
            private ?string $fakeOwner;

            public function __construct(
                HeadToHeadRecordsRepositoryInterface $repo,
                HeadToHeadRecordsView $view,
                Season $season,
                object $user,
                \mysqli $db,
                ?string $fakeOwner
            ) {
                parent::__construct($repo, $view, $season, $user, $db);
                $this->fakeOwner = $fakeOwner;
            }

            protected function lookupOwnerName(int $teamid): ?string
            {
                return $this->fakeOwner;
            }
        };
    }

    // ---------------------------------------------------------------------------
    // resolveFilters — validation
    // ---------------------------------------------------------------------------

    public function testUnknownFilterValuesFallBackToDefaults(): void
    {
        $season = $this->makeSeasonWithPhase('Regular Season');
        /** @var HeadToHeadRecordsRepositoryInterface&MockObject $repo */
        $repo = $this->createMock(HeadToHeadRecordsRepositoryInterface::class);
        $repo->method('currentSeasonHasGames')->willReturn(true);
        $ctrl = new HeadToHeadRecordsController($repo, new HeadToHeadRecordsView(), $season, new \stdClass(), self::createStub(\mysqli::class));

        $result = $ctrl->resolveFilters([
            'dimension' => 'invalid_dim',
            'phase'     => 'invalid_phase',
            'scope'     => 'invalid_scope',
        ]);

        self::assertSame('franchises', $result['dimension']);
        self::assertSame('regular', $result['phase']); // mapped from 'Regular Season'
        self::assertSame('current', $result['scope']);
    }

    public function testValidFilterValuesPassThrough(): void
    {
        $season = $this->makeSeasonWithPhase('Regular Season');
        /** @var HeadToHeadRecordsRepositoryInterface&MockObject $repo */
        $repo = $this->createMock(HeadToHeadRecordsRepositoryInterface::class);
        $ctrl = new HeadToHeadRecordsController($repo, new HeadToHeadRecordsView(), $season, new \stdClass(), self::createStub(\mysqli::class));

        $result = $ctrl->resolveFilters([
            'dimension' => 'gms',
            'phase'     => 'playoffs',
            'scope'     => 'all',
        ]);

        self::assertSame('gms', $result['dimension']);
        self::assertSame('playoffs', $result['phase']);
        self::assertSame('all', $result['scope']);
    }

    public function testDefaultPhaseFollowsSeasonPhase(): void
    {
        $season = $this->makeSeasonWithPhase('Playoffs');
        /** @var HeadToHeadRecordsRepositoryInterface&MockObject $repo */
        $repo = $this->createMock(HeadToHeadRecordsRepositoryInterface::class);
        $ctrl = new HeadToHeadRecordsController($repo, new HeadToHeadRecordsView(), $season, new \stdClass(), self::createStub(\mysqli::class));

        $result = $ctrl->resolveFilters([]);

        self::assertSame('playoffs', $result['phase']);
    }

    public function testNonStringPostValueFallsBackToDefault(): void
    {
        $season = $this->makeSeasonWithPhase('HEAT');
        /** @var HeadToHeadRecordsRepositoryInterface&MockObject $repo */
        $repo = $this->createMock(HeadToHeadRecordsRepositoryInterface::class);
        $repo->method('currentSeasonHasGames')->willReturn(true);
        $ctrl = new HeadToHeadRecordsController($repo, new HeadToHeadRecordsView(), $season, new \stdClass(), self::createStub(\mysqli::class));

        // PHP type coercion edge-cases: pass an array instead of string
        $result = $ctrl->resolveFilters([
            'dimension' => ['franchises'],  // array, not string
            'scope'     => 1,               // int, not string
        ]);

        self::assertSame('franchises', $result['dimension']);
        self::assertSame('current', $result['scope']);
    }

    // ---------------------------------------------------------------------------
    // resolveFilters — scope default driven by currentSeasonHasGames()
    // ---------------------------------------------------------------------------

    public function testNoPostAndNoGamesScopeDefaultsToAll(): void
    {
        $season = $this->makeSeasonWithPhase('Regular Season');
        /** @var HeadToHeadRecordsRepositoryInterface&MockObject $repo */
        $repo = $this->createMock(HeadToHeadRecordsRepositoryInterface::class);
        $repo->method('currentSeasonHasGames')->willReturn(false);
        $ctrl = new HeadToHeadRecordsController($repo, new HeadToHeadRecordsView(), $season, new \stdClass(), self::createStub(\mysqli::class));

        $result = $ctrl->resolveFilters([]);

        self::assertSame('all', $result['scope']);
    }

    public function testNoPostWithGamesScopeDefaultsToCurrent(): void
    {
        $season = $this->makeSeasonWithPhase('Regular Season');
        /** @var HeadToHeadRecordsRepositoryInterface&MockObject $repo */
        $repo = $this->createMock(HeadToHeadRecordsRepositoryInterface::class);
        $repo->method('currentSeasonHasGames')->willReturn(true);
        $ctrl = new HeadToHeadRecordsController($repo, new HeadToHeadRecordsView(), $season, new \stdClass(), self::createStub(\mysqli::class));

        $result = $ctrl->resolveFilters([]);

        self::assertSame('current', $result['scope']);
    }

    public function testPostedCurrentScopeWinsEvenWhenNoGames(): void
    {
        $season = $this->makeSeasonWithPhase('Regular Season');
        /** @var HeadToHeadRecordsRepositoryInterface&MockObject $repo */
        $repo = $this->createMock(HeadToHeadRecordsRepositoryInterface::class);
        // currentSeasonHasGames should NOT be called when scope is explicitly POSTed.
        $repo->expects($this->never())->method('currentSeasonHasGames');
        $ctrl = new HeadToHeadRecordsController($repo, new HeadToHeadRecordsView(), $season, new \stdClass(), self::createStub(\mysqli::class));

        $result = $ctrl->resolveFilters(['scope' => 'current']);

        self::assertSame('current', $result['scope']);
    }

    // ---------------------------------------------------------------------------
    // resolveUserMatchKeys — franchises dimension
    // ---------------------------------------------------------------------------

    public function testFranchisesUserMatchUsesTeamId(): void
    {
        $season = $this->makeSeasonWithPhase('Regular Season');
        /** @var HeadToHeadRecordsRepositoryInterface&MockObject $repo */
        $repo = $this->createMock(HeadToHeadRecordsRepositoryInterface::class);

        $user = new \stdClass();
        $user->teamid = 7;

        $ctrl    = new HeadToHeadRecordsController($repo, new HeadToHeadRecordsView(), $season, $user, self::createStub(\mysqli::class));
        $payload = $this->makePayload([]);
        $keys    = $ctrl->resolveUserMatchKeys('franchises', $payload);

        self::assertSame(['7'], $keys);
    }

    // ---------------------------------------------------------------------------
    // resolveUserMatchKeys — teams dimension
    // ---------------------------------------------------------------------------

    public function testTeamsUserMatchReturnsEveryEraOfTheUsersFranchise(): void
    {
        $season = $this->makeSeasonWithPhase('Regular Season');
        /** @var HeadToHeadRecordsRepositoryInterface&MockObject $repo */
        $repo = $this->createMock(HeadToHeadRecordsRepositoryInterface::class);

        $user = new \stdClass();
        $user->teamid = 3;

        $axis = [
            $this->makeEntry(['key' => 'celtics-2010', 'franchise_id' => 3]),
            $this->makeEntry(['key' => 'celtics-2020', 'franchise_id' => 3]),
            $this->makeEntry(['key' => 'lakers-2020',  'franchise_id' => 5]),
        ];
        $payload = $this->makePayload($axis);
        $ctrl    = new HeadToHeadRecordsController($repo, new HeadToHeadRecordsView(), $season, $user, self::createStub(\mysqli::class));
        $keys    = $ctrl->resolveUserMatchKeys('teams', $payload);

        self::assertSame(['celtics-2010', 'celtics-2020'], $keys);
    }

    // ---------------------------------------------------------------------------
    // resolveUserMatchKeys — gms dimension
    // ---------------------------------------------------------------------------

    public function testGmUserMatchResolvesViaOwnerName(): void
    {
        $season = $this->makeSeasonWithPhase('Regular Season');
        /** @var HeadToHeadRecordsRepositoryInterface&MockObject $repo */
        $repo = $this->createMock(HeadToHeadRecordsRepositoryInterface::class);

        $user = new \stdClass();
        $user->teamid = 5;

        $axis = [
            $this->makeEntry(['key' => 'AjayNicolas',  'franchise_id' => 5]),
            $this->makeEntry(['key' => 'OtherGM',      'franchise_id' => 2]),
        ];
        $payload = $this->makePayload($axis);

        $ctrl = $this->makeControllerWithOwner($repo, new HeadToHeadRecordsView(), $season, $user, 'AjayNicolas');
        $keys = $ctrl->resolveUserMatchKeys('gms', $payload);

        self::assertSame(['AjayNicolas'], $keys);
    }

    public function testGmUserMatchReturnsEmptyWhenOwnerIsNotOnAxis(): void
    {
        $season = $this->makeSeasonWithPhase('Regular Season');
        /** @var HeadToHeadRecordsRepositoryInterface&MockObject $repo */
        $repo = $this->createMock(HeadToHeadRecordsRepositoryInterface::class);

        $user = new \stdClass();
        $user->teamid = 5;

        $axis = [
            $this->makeEntry(['key' => 'OtherGM', 'franchise_id' => 2]),
        ];
        $payload = $this->makePayload($axis);

        // owner_name found in DB but not present as an axis key
        $ctrl = $this->makeControllerWithOwner($repo, new HeadToHeadRecordsView(), $season, $user, 'MissingGM');
        $keys = $ctrl->resolveUserMatchKeys('gms', $payload);

        self::assertSame([], $keys);
    }

    // ---------------------------------------------------------------------------
    // resolveUserMatchKeys — anonymous user
    // ---------------------------------------------------------------------------

    public function testAnonymousUserHasNoMatchKeys(): void
    {
        $season = $this->makeSeasonWithPhase('Regular Season');
        /** @var HeadToHeadRecordsRepositoryInterface&MockObject $repo */
        $repo = $this->createMock(HeadToHeadRecordsRepositoryInterface::class);

        // No teamid property
        $user    = new \stdClass();
        $axis    = [$this->makeEntry()];
        $payload = $this->makePayload($axis);

        $ctrl = new HeadToHeadRecordsController($repo, new HeadToHeadRecordsView(), $season, $user, self::createStub(\mysqli::class));
        $keys = $ctrl->resolveUserMatchKeys('franchises', $payload);

        self::assertSame([], $keys);
    }
}
