<?php

declare(strict_types=1);

namespace Tests\DataRetention;

use DataRetention\AccountFootprintTarget;
use DataRetention\Contracts\DataRetentionRepositoryInterface;
use DataRetention\DataRetentionService;
use DataRetention\UnknownAccountException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AccountDeletionServiceTest extends TestCase
{
    /** @var DataRetentionRepositoryInterface&MockObject */
    private DataRetentionRepositoryInterface $repository;

    private DataRetentionService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(DataRetentionRepositoryInterface::class);
        $this->service = new DataRetentionService($this->repository);
    }

    public function testDeleteAccountRejectsNonPositiveUserId(): void
    {
        $this->repository->expects(self::never())->method('findAuthUserById');
        $this->repository->expects(self::never())->method('runInTransaction');

        foreach ([0, -1] as $badId) {
            try {
                $this->service->deleteAccount($badId, true);
                self::fail('expected InvalidArgumentException for id ' . $badId);
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString((string) $badId, $e->getMessage());
            }
        }
    }

    public function testDeleteAccountUnknownIdThrowsBeforeAnyWrite(): void
    {
        $this->repository->method('findAuthUserById')->willReturn(null);
        $this->repository->expects(self::never())->method('runInTransaction');
        $this->repository->expects(self::never())->method('applyAccountTarget');
        $this->repository->expects(self::never())->method('countAccountTarget');

        $this->expectException(UnknownAccountException::class);
        $this->service->deleteAccount(42, true);
    }

    public function testDeleteAccountDryRunNeverOpensTransaction(): void
    {
        $this->repository->method('findAuthUserById')->willReturn(['id' => 42, 'username' => 'gm42']);
        $this->repository->method('findTeamsByGmUsername')->willReturn([['teamid' => 3, 'team_name' => 'Bulls']]);
        $this->repository->method('countAccountTarget')->willReturn(1);
        $this->repository->expects(self::never())->method('runInTransaction');
        $this->repository->expects(self::never())->method('applyAccountTarget');

        $report = $this->service->deleteAccount(42, false);

        self::assertTrue($report->dryRun);
        self::assertSame(42, $report->userId);
        self::assertSame('gm42', $report->username);
        self::assertCount(count(AccountFootprintTarget::cases()), $report->affected);
        foreach (AccountFootprintTarget::cases() as $target) {
            self::assertSame(1, $report->affected[$target->value], $target->name);
        }
        self::assertSame([['teamid' => 3, 'team_name' => 'Bulls']], $report->vacatedTeams);
    }

    public function testDeleteAccountAppliesTargetsInEnumOrderInsideOneTransaction(): void
    {
        $this->repository->method('findAuthUserById')->willReturn(['id' => 42, 'username' => 'gm42']);
        $this->repository->method('findTeamsByGmUsername')->willReturn([]);
        $this->repository->expects(self::never())->method('countAccountTarget');

        $events = [];
        $this->repository->expects(self::once())->method('runInTransaction')
            ->willReturnCallback(static function (callable $work) use (&$events): mixed {
                $events[] = 'begin';
                $result = $work();
                $events[] = 'end';
                return $result;
            });

        $this->repository->expects(self::exactly(count(AccountFootprintTarget::cases())))->method('applyAccountTarget')
            ->willReturnCallback(static function (AccountFootprintTarget $target, int $userId, ?string $username) use (&$events): int {
                self::assertSame(42, $userId);
                self::assertSame('gm42', $username);
                $events[] = $target->name;
                return $target === AccountFootprintTarget::AuthUsers ? 1 : 2;
            });

        $report = $this->service->deleteAccount(42, true);

        $expected = array_map(static fn (AccountFootprintTarget $t): string => $t->name, AccountFootprintTarget::cases());
        self::assertSame(['begin', ...$expected, 'end'], $events);
        self::assertFalse($report->dryRun);
        self::assertSame(1, $report->affected[AccountFootprintTarget::AuthUsers->value]);
        self::assertSame(2, $report->affected[AccountFootprintTarget::Confirmations->value]);
    }

    public function testDeleteAccountThrowsWhenAuthUsersRowNotDeleted(): void
    {
        $this->repository->method('findAuthUserById')->willReturn(['id' => 42, 'username' => 'gm42']);
        $this->repository->method('findTeamsByGmUsername')->willReturn([]);
        $this->repository->expects(self::once())->method('runInTransaction')
            ->willReturnCallback(static fn (callable $work): mixed => $work());
        $this->repository->method('applyAccountTarget')->willReturn(0);

        try {
            $this->service->deleteAccount(42, true);
            self::fail('expected RuntimeException');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('expected to delete 1 auth_users row', $e->getMessage());
        }
    }

    public function testDeleteAccountWithNullUsernameSkipsUsernameTargets(): void
    {
        $this->repository->method('findAuthUserById')->willReturn(['id' => 42, 'username' => null]);
        $this->repository->expects(self::never())->method('findTeamsByGmUsername');
        $this->repository->method('runInTransaction')
            ->willReturnCallback(static fn (callable $work): mixed => $work());

        $applied = [];
        $this->repository->method('applyAccountTarget')
            ->willReturnCallback(static function (AccountFootprintTarget $target) use (&$applied): int {
                $applied[] = $target;
                return 1;
            });

        $report = $this->service->deleteAccount(42, true);

        self::assertNotContains(AccountFootprintTarget::EventsUsername, $applied);
        self::assertNotContains(AccountFootprintTarget::TeamGmUsername, $applied);
        self::assertSame(0, $report->affected[AccountFootprintTarget::EventsUsername->value]);
        self::assertSame(0, $report->affected[AccountFootprintTarget::TeamGmUsername->value]);
        self::assertSame(1, $report->affected[AccountFootprintTarget::AuthUsers->value]);
        self::assertSame([], $report->vacatedTeams);
    }
}
