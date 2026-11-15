<?php

declare(strict_types=1);

namespace Tests\DataRetention;

use DataRetention\AccountFootprintTarget;
use PHPUnit\Framework\TestCase;

class AccountFootprintTargetTest extends TestCase
{
    public function testEveryTargetStatementIsKeyedByExactlyOnePlaceholder(): void
    {
        foreach (AccountFootprintTarget::cases() as $target) {
            self::assertSame(1, substr_count($target->countSql(), '?'), $target->name . ' countSql');
            self::assertSame(1, substr_count($target->writeSql(), '?'), $target->name . ' writeSql');
        }
    }

    public function testAuthUsersIsTheLastTarget(): void
    {
        $cases = AccountFootprintTarget::cases();

        self::assertSame(AccountFootprintTarget::AuthUsers, $cases[count($cases) - 1]);
    }

    public function testOnlyEventsAndTeamTargetsBindTheUsername(): void
    {
        $usernameTargets = array_values(array_filter(
            AccountFootprintTarget::cases(),
            static fn (AccountFootprintTarget $t): bool => $t->bindsUsername()
        ));

        self::assertSame(
            [AccountFootprintTarget::EventsUsername, AccountFootprintTarget::TeamGmUsername],
            $usernameTargets
        );
    }

    public function testUnlinkTargetsUpdateAndNeverDelete(): void
    {
        foreach ([AccountFootprintTarget::AuditLogAdmin, AccountFootprintTarget::EventsUsername, AccountFootprintTarget::TeamGmUsername] as $target) {
            self::assertStringStartsWith('UPDATE ', $target->writeSql(), $target->name);
            self::assertStringContainsString('= NULL', $target->writeSql(), $target->name);
        }
    }
}
