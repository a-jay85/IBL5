<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\DataRetention;

use DataRetention\AccountFootprintTarget;
use DataRetention\DataRetentionRepository;
use DataRetention\DataRetentionService;
use DataRetention\UnknownAccountException;
use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;

/**
 * Victim 990001 (`dr_victim`) and bystander 990002 (`dr_bystander`) each get one row in
 * every footprint table. One extra audit row is about the bystander with the victim as admin.
 */
#[Group('database')]
class AccountDeletionDbTest extends DatabaseTestCase
{
    private const VICTIM_ID = 990001;
    private const BYSTANDER_ID = 990002;
    private const VICTIM_NAME = 'dr_victim';
    private const BYSTANDER_NAME = 'dr_bystander';
    private const TEAM_ID = 1;

    private DataRetentionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DataRetentionService(new DataRetentionRepository($this->db));
        $this->insertUserFootprint(self::VICTIM_ID, self::VICTIM_NAME, 'v');
        $this->insertUserFootprint(self::BYSTANDER_ID, self::BYSTANDER_NAME, 'b');
        $this->insertRow('auth_users_audit_log', [
            'user_id' => self::BYSTANDER_ID,
            'admin_id' => self::VICTIM_ID,
            'event_at' => 900_000_100,
            'event_type' => 'admin.user.update',
        ]);
        $stmt = $this->db->prepare('UPDATE `ibl_team_info` SET `gm_username` = ? WHERE `teamid` = ?');
        self::assertNotFalse($stmt);
        $name = self::VICTIM_NAME;
        $teamId = self::TEAM_ID;
        $stmt->bind_param('si', $name, $teamId);
        $stmt->execute();
        $stmt->close();
    }

    public function testDeleteAccountDryRunReportsCountsAndWritesNothing(): void
    {
        $before = $this->footprintSnapshot();

        $report = $this->service->deleteAccount(self::VICTIM_ID, false);

        self::assertSame($before, $this->footprintSnapshot());
        self::assertTrue($report->dryRun);
        self::assertSame(1, $report->affected[AccountFootprintTarget::AuthUsers->value]);
        self::assertSame(1, $report->affected[AccountFootprintTarget::AuditLogUser->value]);
        self::assertSame(1, $report->affected[AccountFootprintTarget::AuditLogAdmin->value]);
        self::assertSame(1, $report->affected[AccountFootprintTarget::EventsUsername->value]);
        self::assertSame(1, $report->affected[AccountFootprintTarget::TeamGmUsername->value]);
        self::assertSame([self::TEAM_ID], array_column($report->vacatedTeams, 'teamid'));
    }

    public function testDeleteAccountUnknownIdWritesNothing(): void
    {
        $before = $this->footprintSnapshot();

        try {
            $this->service->deleteAccount(990999, true);
            self::fail('expected UnknownAccountException');
        } catch (UnknownAccountException $e) {
            self::assertStringContainsString('990999', $e->getMessage());
        }

        self::assertSame($before, $this->footprintSnapshot());
    }

    public function testDeleteAccountConfirmRemovesEveryUserKeyedRow(): void
    {
        $report = $this->service->deleteAccount(self::VICTIM_ID, true);

        self::assertFalse($report->dryRun);
        self::assertSame(1, $report->affected[AccountFootprintTarget::AuthUsers->value]);
        foreach ($this->victimKeyedCounts() as $label => $count) {
            self::assertSame(0, $count, $label);
        }
    }

    public function testDeleteAccountLeavesOtherUsersRowsIntact(): void
    {
        $this->service->deleteAccount(self::VICTIM_ID, true);

        self::assertSame(1, $this->countWhere('SELECT COUNT(*) AS n FROM `auth_users` WHERE `id` = ?', self::BYSTANDER_ID));
        self::assertSame(1, $this->countWhere('SELECT COUNT(*) AS n FROM `auth_users_confirmations` WHERE `user_id` = ?', self::BYSTANDER_ID));
        self::assertSame(1, $this->countWhere('SELECT COUNT(*) AS n FROM `auth_users_otps` WHERE `user_id` = ?', self::BYSTANDER_ID));
        self::assertSame(1, $this->countWhere('SELECT COUNT(*) AS n FROM `auth_users_2fa` WHERE `user_id` = ?', self::BYSTANDER_ID));
        self::assertSame(1, $this->countWhere('SELECT COUNT(*) AS n FROM `auth_users_remembered` WHERE `user` = ?', self::BYSTANDER_ID));
        self::assertSame(1, $this->countWhere('SELECT COUNT(*) AS n FROM `auth_users_resets` WHERE `user` = ?', self::BYSTANDER_ID));
        // The bystander's own audit row plus the row the victim wrote about them.
        self::assertSame(2, $this->countWhere('SELECT COUNT(*) AS n FROM `auth_users_audit_log` WHERE `user_id` = ?', self::BYSTANDER_ID));
        self::assertSame(1, $this->countWhere('SELECT COUNT(*) AS n FROM `ibl_api_keys` WHERE `user_id` = ?', self::BYSTANDER_ID));
        self::assertSame(1, $this->countWhereString('SELECT COUNT(*) AS n FROM `ibl_events` WHERE `username` = ?', self::BYSTANDER_NAME));
    }

    public function testDeleteAccountNullsAdminIdOnRowsAboutOtherUsers(): void
    {
        $this->service->deleteAccount(self::VICTIM_ID, true);

        self::assertSame(1, $this->countWhere(
            'SELECT COUNT(*) AS n FROM `auth_users_audit_log` WHERE `user_id` = ? AND `admin_id` IS NULL AND `event_type` = \'admin.user.update\'',
            self::BYSTANDER_ID
        ));
        self::assertSame(0, $this->countWhere('SELECT COUNT(*) AS n FROM `auth_users_audit_log` WHERE `admin_id` = ?', self::VICTIM_ID));
    }

    public function testDeleteAccountVacatesGmSlotButKeepsTeamRow(): void
    {
        $report = $this->service->deleteAccount(self::VICTIM_ID, true);

        self::assertSame(1, $this->countWhere(
            'SELECT COUNT(*) AS n FROM `ibl_team_info` WHERE `teamid` = ? AND `gm_username` IS NULL',
            self::TEAM_ID
        ));
        self::assertSame(1, $report->affected[AccountFootprintTarget::TeamGmUsername->value]);
        self::assertSame([self::TEAM_ID], array_column($report->vacatedTeams, 'teamid'));
    }

    private function insertUserFootprint(int $userId, string $username, string $tag): void
    {
        $this->insertRow('auth_users', [
            'id' => $userId,
            'email' => $username . '@example.test',
            'password' => 'x',
            'username' => $username,
            'registered' => 900_000_000,
        ]);
        $this->insertRow('auth_users_confirmations', [
            'user_id' => $userId,
            'email' => $username . '@example.test',
            'selector' => 'drconf' . $tag,
            'token' => 't',
            'expires' => 900_000_000,
        ]);
        $this->insertRow('auth_users_otps', [
            'user_id' => $userId,
            'mechanism' => 1,
            'selector' => 'drotp' . $tag,
            'token' => 't',
        ]);
        $this->insertRow('auth_users_2fa', [
            'user_id' => $userId,
            'mechanism' => 1,
            'created_at' => 900_000_000,
        ]);
        $this->insertRow('auth_users_remembered', [
            'user' => $userId,
            'selector' => 'drrem' . $tag,
            'token' => 't',
            'expires' => 900_000_000,
        ]);
        $this->insertRow('auth_users_resets', [
            'user' => $userId,
            'selector' => 'drres' . $tag,
            'token' => 't',
            'expires' => 900_000_000,
        ]);
        $this->insertRow('auth_users_audit_log', [
            'user_id' => $userId,
            'event_at' => 900_000_000,
            'event_type' => 'login',
        ]);
        $this->insertRow('ibl_api_keys', [
            'user_id' => $userId,
            'key_hash' => str_repeat($tag, 64),
            'key_prefix' => 'dr' . $tag . 'pref',
            'owner_name' => 'data retention test',
        ]);
        $this->insertRow('ibl_events', [
            'request_uri' => '/ibl5/dr-test',
            'username' => $username,
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function victimKeyedCounts(): array
    {
        $counts = [];
        foreach (AccountFootprintTarget::cases() as $target) {
            $counts[$target->value] = $target->bindsUsername()
                ? $this->countWhereString($target->countSql(), self::VICTIM_NAME)
                : $this->countWhere($target->countSql(), self::VICTIM_ID);
        }

        return $counts;
    }

    /**
     * md5 of every footprint row for both test users, plus team 1.
     */
    private function footprintSnapshot(): string
    {
        $ids = [self::VICTIM_ID, self::BYSTANDER_ID];
        $parts = [
            $this->rowsJson('SELECT * FROM `auth_users` WHERE `id` IN (?, ?) ORDER BY `id`', $ids),
            $this->rowsJson('SELECT * FROM `auth_users_confirmations` WHERE `user_id` IN (?, ?) ORDER BY `id`', $ids),
            $this->rowsJson('SELECT * FROM `auth_users_otps` WHERE `user_id` IN (?, ?) ORDER BY `id`', $ids),
            $this->rowsJson('SELECT * FROM `auth_users_2fa` WHERE `user_id` IN (?, ?) ORDER BY `id`', $ids),
            $this->rowsJson('SELECT * FROM `auth_users_remembered` WHERE `user` IN (?, ?) ORDER BY `id`', $ids),
            $this->rowsJson('SELECT * FROM `auth_users_resets` WHERE `user` IN (?, ?) ORDER BY `id`', $ids),
            $this->rowsJson('SELECT * FROM `auth_users_audit_log` WHERE `user_id` IN (?, ?) OR `admin_id` IN (?, ?) ORDER BY `id`', [...$ids, ...$ids]),
            $this->rowsJson('SELECT * FROM `ibl_api_keys` WHERE `user_id` IN (?, ?) ORDER BY `id`', $ids),
            $this->rowsJson('SELECT * FROM `ibl_events` WHERE `request_uri` = ? ORDER BY `id`', ['/ibl5/dr-test']),
            $this->rowsJson('SELECT `teamid`, `gm_username` FROM `ibl_team_info` WHERE `teamid` = ?', [self::TEAM_ID]),
        ];

        return md5(implode("\n", $parts));
    }

    /**
     * @param list<int|string> $params
     */
    private function rowsJson(string $sql, array $params): string
    {
        $stmt = $this->db->prepare($sql);
        self::assertNotFalse($stmt, $this->db->error);
        $types = implode('', array_map(static fn (int|string $p): string => is_int($p) ? 'i' : 's', $params));
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        self::assertNotSame([], $rows, 'snapshot query matched no rows: ' . $sql);

        return (string) json_encode($rows);
    }

    private function countWhere(string $sql, int $value): int
    {
        $stmt = $this->db->prepare($sql);
        self::assertNotFalse($stmt, $this->db->error);
        $stmt->bind_param('i', $value);

        return $this->fetchCount($stmt);
    }

    private function countWhereString(string $sql, string $value): int
    {
        $stmt = $this->db->prepare($sql);
        self::assertNotFalse($stmt, $this->db->error);
        $stmt->bind_param('s', $value);

        return $this->fetchCount($stmt);
    }

    private function fetchCount(\mysqli_stmt $stmt): int
    {
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        $stmt->close();

        return is_array($row) && is_int($row['n']) ? $row['n'] : -1;
    }
}
