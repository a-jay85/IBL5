<?php

declare(strict_types=1);

namespace Tests\Watchlist;

use League\League;
use PHPUnit\Framework\TestCase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Watchlist\Contracts\WatchlistRepositoryInterface;
use Watchlist\Contracts\WatchlistServiceInterface;
use Watchlist\WatchlistService;

class WatchlistServiceTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $interfaces = class_implements(WatchlistService::class);
        self::assertContains(
            WatchlistServiceInterface::class,
            $interfaces ? $interfaces : [],
        );
    }

    public function testResolveOwnerTeamidReturnsNullForFreeAgentName(): void
    {
        // Guard short-circuits on the name — getTidFromTeamname must never be called.
        $identity = $this->createMock(TeamIdentityRepositoryInterface::class);
        $identity->method('getTeamnameFromUsername')->willReturn(League::FREE_AGENTS_TEAM_NAME);
        $identity->expects(self::never())->method('getTidFromTeamname');

        $service = new WatchlistService($identity, self::createStub(WatchlistRepositoryInterface::class));

        self::assertNull($service->resolveOwnerTeamid('freeagent'));
    }

    public function testResolveOwnerTeamidReturnsNullWhenNoTeam(): void
    {
        $identity = self::createStub(TeamIdentityRepositoryInterface::class);
        $identity->method('getTeamnameFromUsername')->willReturn(null);

        $service = new WatchlistService($identity, self::createStub(WatchlistRepositoryInterface::class));

        self::assertNull($service->resolveOwnerTeamid('ghost'));
    }

    public function testResolveOwnerTeamidResolvesTeamid(): void
    {
        $identity = self::createStub(TeamIdentityRepositoryInterface::class);
        $identity->method('getTeamnameFromUsername')->willReturn('Metros');
        $identity->method('getTidFromTeamname')->willReturn(1);

        $service = new WatchlistService($identity, self::createStub(WatchlistRepositoryInterface::class));

        self::assertSame(1, $service->resolveOwnerTeamid('gm_metros'));
    }

    public function testToggleWatchAddsWhenUnwatched(): void
    {
        $repo = $this->createMock(WatchlistRepositoryInterface::class);
        $repo->method('isWatched')->willReturn(false);
        $repo->expects(self::once())->method('addWatch')->with(1, 42)->willReturn(true);
        $repo->expects(self::never())->method('removeWatch');

        $service = new WatchlistService($this->makeOwnerIdentity(1), $repo);

        self::assertSame(['success' => true, 'result' => 'watched'], $service->toggleWatch('gm', 42));
    }

    public function testToggleWatchRemovesWhenWatched(): void
    {
        $repo = $this->createMock(WatchlistRepositoryInterface::class);
        $repo->method('isWatched')->willReturn(true);
        $repo->expects(self::once())->method('removeWatch')->with(1, 42)->willReturn(true);
        $repo->expects(self::never())->method('addWatch');

        $service = new WatchlistService($this->makeOwnerIdentity(1), $repo);

        self::assertSame(['success' => true, 'result' => 'unwatched'], $service->toggleWatch('gm', 42));
    }

    public function testToggleWatchNoTeamReturnsError(): void
    {
        $identity = self::createStub(TeamIdentityRepositoryInterface::class);
        $identity->method('getTeamnameFromUsername')->willReturn(null);
        $repo = $this->createMock(WatchlistRepositoryInterface::class);
        $repo->expects(self::never())->method('addWatch');
        $repo->expects(self::never())->method('removeWatch');

        $service = new WatchlistService($identity, $repo);

        self::assertSame(['success' => false, 'error' => 'no_team'], $service->toggleWatch('ghost', 42));
    }

    public function testSaveNoteCapsLengthAndPersists(): void
    {
        $longNote = str_repeat('a', 2500);
        $repo = $this->createMock(WatchlistRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('saveNote')
            ->with(1, 42, self::callback(static fn (string $n): bool => mb_strlen($n) === 2000))
            ->willReturn(true);

        $service = new WatchlistService($this->makeOwnerIdentity(1), $repo);

        self::assertSame(['success' => true, 'result' => 'note_saved'], $service->saveNote('gm', 42, $longNote));
    }

    public function testSaveNoteNoTeamReturnsError(): void
    {
        $identity = self::createStub(TeamIdentityRepositoryInterface::class);
        $identity->method('getTeamnameFromUsername')->willReturn(League::FREE_AGENTS_TEAM_NAME);
        $repo = $this->createMock(WatchlistRepositoryInterface::class);
        $repo->expects(self::never())->method('saveNote');

        $service = new WatchlistService($identity, $repo);

        self::assertSame(['success' => false, 'error' => 'no_team'], $service->saveNote('fa', 42, 'x'));
    }

    public function testGetWatchlistViewEmptyWhenNoTeam(): void
    {
        $identity = self::createStub(TeamIdentityRepositoryInterface::class);
        $identity->method('getTeamnameFromUsername')->willReturn(null);
        $repo = $this->createMock(WatchlistRepositoryInterface::class);
        $repo->expects(self::never())->method('getWatchlistForTeam');

        $service = new WatchlistService($identity, $repo);

        self::assertSame([], $service->getWatchlistView('ghost'));
    }

    public function testIsWatchedByUserFalseWhenNoTeam(): void
    {
        $identity = self::createStub(TeamIdentityRepositoryInterface::class);
        $identity->method('getTeamnameFromUsername')->willReturn(null);
        $repo = $this->createMock(WatchlistRepositoryInterface::class);
        $repo->expects(self::never())->method('isWatched');

        $service = new WatchlistService($identity, $repo);

        self::assertFalse($service->isWatchedByUser('ghost', 42));
    }

    private function makeOwnerIdentity(int $teamid): TeamIdentityRepositoryInterface
    {
        $identity = self::createStub(TeamIdentityRepositoryInterface::class);
        $identity->method('getTeamnameFromUsername')->willReturn('Metros');
        $identity->method('getTidFromTeamname')->willReturn($teamid);
        return $identity;
    }
}
