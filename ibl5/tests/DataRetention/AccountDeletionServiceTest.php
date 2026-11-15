<?php

declare(strict_types=1);

namespace Tests\DataRetention;

use DataRetention\AccountFootprintTarget;
use DataRetention\Contracts\DataRetentionRepositoryInterface;
use DataRetention\DataRetentionService;
use DataRetention\UnknownAccountException;
use PHPUnit\Framework\TestCase;

class AccountDeletionServiceTest extends TestCase
{
    public function testDeleteAccountRejectsNonPositiveUserId(): void
    {
        foreach ([0, -1] as $userId) {
            $repo = $this->createMock(DataRetentionRepositoryInterface::class);
            $repo->expects($this->never())->method('findAuthUserById');
            $repo->expects($this->never())->method('runInTransaction');

            $thrown = false;
            try {
                (new DataRetentionService($repo))->deleteAccount($userId, true);
            } catch (\InvalidArgumentException $e) {
                $thrown = true;
                self::assertSame('User id must be a positive integer, got ' . $userId, $e->getMessage());
            }
            self::assertTrue($thrown, 'id ' . $userId . ' was accepted');
        }
    }

    public function testDeleteAccountUnknownIdThrowsBeforeAnyWrite(): void
    {
        $repo = $this->createMock(DataRetentionRepositoryInterface::class);
        $repo->expects($this->once())->method('findAuthUserById')->with(42)->willReturn(null);
        $repo->expects($this->never())->method('runInTransaction');
        $repo->expects($this->never())->method('applyAccountTarget');
        $repo->expects($this->never())->method('countAccountTarget');

        $this->expectException(UnknownAccountException::class);
        $this->expectExceptionMessageIs('No auth_users row has id 42');
        (new DataRetentionService($repo))->deleteAccount(42, true);
    }

    public function testDeleteAccountAcceptsUserIdOne(): void
    {
        $repo = $this->createMock(DataRetentionRepositoryInterface::class);
        $repo->expects($this->once())->method('findAuthUserById')->with(1)->willReturn(null);
        $repo->expects($this->never())->method('runInTransaction');

        $this->expectException(UnknownAccountException::class);
        $this->expectExceptionMessageIs('No auth_users row has id 1');
        (new DataRetentionService($repo))->deleteAccount(1, true);
    }

    public function testDeleteAccountDryRunNeverOpensTransaction(): void
    {
        $repo = $this->createMock(DataRetentionRepositoryInterface::class);
        $repo->method('findAuthUserById')->willReturn(['id' => 7, 'username' => 'gm_seven']);
        $repo->expects($this->once())->method('findTeamsByGmUsername')->with('gm_seven')
            ->willReturn([['teamid' => 3, 'team_name' => 'Hawks']]);
        $repo->expects($this->exactly(count(AccountFootprintTarget::cases())))
            ->method('countAccountTarget')->willReturn(1);
        $repo->expects($this->never())->method('runInTransaction');
        $repo->expects($this->never())->method('applyAccountTarget');

        $report = (new DataRetentionService($repo))->deleteAccount(7, false);

        self::assertTrue($report->dryRun);
        self::assertSame(7, $report->userId);
        self::assertSame('gm_seven', $report->username);
        self::assertSame([['teamid' => 3, 'team_name' => 'Hawks']], $report->vacatedTeams);
        $expected = [];
        foreach (AccountFootprintTarget::cases() as $target) {
            $expected[$target->value] = 1;
        }
        self::assertSame($expected, $report->affected);
    }

    public function testDeleteAccountAppliesTargetsInEnumOrderInsideOneTransaction(): void
    {
        /** @var list<AccountFootprintTarget> $applied */
        $applied = [];
        $tx = new class { public bool $open = false; };

        $repo = $this->createMock(DataRetentionRepositoryInterface::class);
        $repo->method('findAuthUserById')->willReturn(['id' => 7, 'username' => 'gm_seven']);
        $repo->method('findTeamsByGmUsername')->willReturn([]);
        $repo->expects($this->never())->method('countAccountTarget');
        $repo->expects($this->once())->method('runInTransaction')
            ->willReturnCallback(static function (callable $work) use ($tx): mixed {
                $tx->open = true;
                $result = $work();
                $tx->open = false;
                return $result;
            });
        $repo->expects($this->exactly(count(AccountFootprintTarget::cases())))
            ->method('applyAccountTarget')
            ->willReturnCallback(static function (AccountFootprintTarget $target, int $userId, ?string $username) use (&$applied, $tx): int {
                self::assertTrue($tx->open, $target->name . ' ran outside the transaction');
                self::assertSame(7, $userId);
                self::assertSame('gm_seven', $username);
                $applied[] = $target;
                return $target === AccountFootprintTarget::AuthUsers ? 1 : 2;
            });

        $report = (new DataRetentionService($repo))->deleteAccount(7, true);

        self::assertSame(AccountFootprintTarget::cases(), $applied);
        self::assertFalse($report->dryRun);
        self::assertSame(1, $report->affected[AccountFootprintTarget::AuthUsers->value]);
        self::assertSame(2, $report->affected[AccountFootprintTarget::Confirmations->value]);
    }

    public function testDeleteAccountThrowsWhenAuthUsersRowNotDeleted(): void
    {
        $repo = $this->createMock(DataRetentionRepositoryInterface::class);
        $repo->method('findAuthUserById')->willReturn(['id' => 7, 'username' => 'gm_seven']);
        $repo->method('findTeamsByGmUsername')->willReturn([]);
        $repo->expects($this->once())->method('runInTransaction')
            ->willReturnCallback(static fn (callable $work): mixed => $work());
        $repo->method('applyAccountTarget')->willReturnCallback(
            static fn (AccountFootprintTarget $target): int => $target === AccountFootprintTarget::AuthUsers ? 0 : 1
        );

        $thrown = null;
        try {
            (new DataRetentionService($repo))->deleteAccount(7, true);
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }
        self::assertNotNull($thrown, 'a missed auth_users row did not throw');
        self::assertSame(
            'Expected to delete 1 auth_users row for id 7, deleted 0; rolled back',
            $thrown->getMessage()
        );
    }

    public function testDeleteAccountWithNullUsernameSkipsUsernameTargets(): void
    {
        $repo = $this->createMock(DataRetentionRepositoryInterface::class);
        $repo->method('findAuthUserById')->willReturn(['id' => 7, 'username' => null]);
        $repo->expects($this->never())->method('findTeamsByGmUsername');
        $repo->method('runInTransaction')->willReturnCallback(static fn (callable $work): mixed => $work());
        $repo->method('applyAccountTarget')->willReturnCallback(
            static fn (AccountFootprintTarget $target, int $id, ?string $username): int
                => $id === 7 && $target->bindsUsername() && $username === null ? 0 : 1
        );

        $report = (new DataRetentionService($repo))->deleteAccount(7, true);

        self::assertNull($report->username);
        self::assertSame([], $report->vacatedTeams);
        self::assertSame(0, $report->affected[AccountFootprintTarget::EventsUsername->value]);
        self::assertSame(0, $report->affected[AccountFootprintTarget::TeamGmUsername->value]);
        self::assertSame(1, $report->affected[AccountFootprintTarget::AuthUsers->value]);
    }
}
