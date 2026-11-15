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
 * A victim account (990001) and a bystander (990002), each with one row in every footprint
 * table. The victim is GM of teamid 1.
 */
#[Group('database')]
class AccountDeletionDbTest extends DatabaseTestCase
{
    private const VICTIM_ID = 990001;
    private const VICTIM_NAME = 'dr_victim';
    private const BYSTANDER_ID = 990002;
    private const BYSTANDER_NAME = 'dr_bystander';
    private const TEAM_ID = 1;

    private DataRetentionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DataRetentionService(new DataRetentionRepository($this->db));
        $this->insertFootprint(self::VICTIM_ID, self::VICTIM_NAME, 'v');
        $this->insertFootprint(self::BYSTANDER_ID, self::BYSTANDER_NAME, 'b');
        // A bystander audit row an admin action by the victim produced.
        $this->insertRow('auth_users_audit_log', [
            'user_id' => self::BYSTANDER_ID,
            'admin_id' => self::VICTIM_ID,
            'event_at' => 1_700_000_100,
            'event_type' => 'admin.user.update',
        ]);

        $stmt = $this->db->prepare('UPDATE ibl_team_info SET gm_username = ? WHERE teamid = ?');
        self::assertNotFalse($stmt);
        $name = self::VICTIM_NAME;
        $teamId = self::TEAM_ID;
        $stmt->bind_param('si', $name, $teamId);
        $stmt->execute();
        self::assertSame(1, $stmt->affected_rows);
        $stmt->close();
    }

    public function testDeleteAccountDryRunReportsCountsAndWritesNothing(): void
    {
        $before = $this->footprintSnapshot();

        $report = $this->service->deleteAccount(self::VICTIM_ID, false);

        self::assertSame($before, $this->footprintSnapshot());
        self::assertTrue($report->dryRun);
        self::assertSame(self::VICTIM_NAME, $report->username);
        self::assertSame(1, $report->affected[AccountFootprintTarget::AuthUsers->value]);
        self::assertSame(1, $report->affected[AccountFootprintTarget::Confirmations->value]);
        self::assertSame(1, $report->affected[AccountFootprintTarget::Remembered->value]);
        self::assertSame(1, $report->affected[AccountFootprintTarget::AuditLogAdmin->value]);
        self::assertSame(1, $report->affected[AccountFootprintTarget::EventsUsername->value]);
        self::assertSame(1, $report->affected[AccountFootprintTarget::TeamGmUsername->value]);
        self::assertSame([self::TEAM_ID], array_column($report->vacatedTeams, 'teamid'));
    }

    public function testDeleteAccountUnknownIdWritesNothing(): void
    {
        $before = $this->footprintSnapshot();

        $thrown = false;
        try {
            $this->service->deleteAccount(990999, true);
        } catch (UnknownAccountException) {
            $thrown = true;
        }

        self::assertTrue($thrown, 'an unknown id did not throw UnknownAccountException');
        self::assertSame($before, $this->footprintSnapshot());
    }

    public function testDeleteAccountConfirmRemovesEveryUserKeyedRow(): void
    {
        $report = $this->service->deleteAccount(self::VICTIM_ID, true);

        self::assertFalse($report->dryRun);
        self::assertSame(1, $report->affected[AccountFootprintTarget::AuthUsers->value]);
        foreach ($this->keyedCounts(self::VICTIM_ID, self::VICTIM_NAME) as $label => $count) {
            self::assertSame(0, $count, $label . ' still holds a victim row');
        }
    }

    public function testDeleteAccountLeavesOtherUsersRowsIntact(): void
    {
        $this->service->deleteAccount(self::VICTIM_ID, true);

        foreach ($this->keyedCounts(self::BYSTANDER_ID, self::BYSTANDER_NAME) as $label => $count) {
            // The bystander has two audit-log rows: their own, and the one the victim produced.
            $expected = $label === 'auth_users_audit_log.user_id' ? 2 : 1;
            self::assertSame($expected, $count, $label . ' lost a bystander row');
        }
    }

    public function testDeleteAccountNullsAdminIdOnRowsAboutOtherUsers(): void
    {
        $this->service->deleteAccount(self::VICTIM_ID, true);

        $rows = $this->rows(
            'SELECT `admin_id` FROM `auth_users_audit_log` WHERE `user_id` = ? AND `event_type` = ?',
            'is',
            self::BYSTANDER_ID,
            'admin.user.update'
        );

        self::assertSame([['admin_id' => null]], $rows);
    }

    public function testDeleteAccountVacatesGmSlotButKeepsTeamRow(): void
    {
        $this->service->deleteAccount(self::VICTIM_ID, true);

        $rows = $this->rows('SELECT `gm_username` FROM ibl_team_info WHERE `teamid` = ?', 'i', self::TEAM_ID);

        self::assertSame([['gm_username' => null]], $rows);
    }

    private function insertFootprint(int $userId, string $username, string $tag): void
    {
        $this->insertRow('auth_users', [
            'id' => $userId,
            'email' => $username . '@example.test',
            'password' => 'not-a-hash',
            'username' => $username,
            'registered' => 1_700_000_000,
        ]);
        $this->insertRow('auth_users_confirmations', [
            'user_id' => $userId,
            'email' => $username . '@example.test',
            'selector' => 'drc' . $tag . $userId,
            'token' => 'token',
            'expires' => 1_800_000_000,
        ]);
        $this->insertRow('auth_users_otps', [
            'user_id' => $userId,
            'mechanism' => 1,
            'selector' => 'dro' . $tag . $userId,
            'token' => 'token',
        ]);
        $this->insertRow('auth_users_2fa', [
            'user_id' => $userId,
            'mechanism' => 1,
            'created_at' => 1_700_000_000,
        ]);
        $this->insertRow('auth_users_remembered', [
            'user' => $userId,
            'selector' => 'drm' . $tag . $userId,
            'token' => 'token',
            'expires' => 1_800_000_000,
        ]);
        $this->insertRow('auth_users_resets', [
            'user' => $userId,
            'selector' => 'drr' . $tag . $userId,
            'token' => 'token',
            'expires' => 1_800_000_000,
        ]);
        $this->insertRow('auth_users_audit_log', [
            'user_id' => $userId,
            'event_at' => 1_700_000_000,
            'event_type' => 'login',
        ]);
        $this->insertRow('ibl_api_keys', [
            'user_id' => $userId,
            'key_hash' => str_repeat($tag, 58) . $userId,
            'key_prefix' => 'dr' . $userId,
            'owner_name' => 'retention test',
        ]);
        $this->insertRow('ibl_google_sheet_connections', [
            'user_id' => $userId,
            'refresh_token_enc' => 'encrypted',
            'spreadsheet_id' => 'sheet' . $userId,
            'spreadsheet_url' => 'https://example.test/' . $userId,
        ]);
        $this->insertRow('ibl_events', [
            'username' => $username,
            'request_uri' => '/ibl5/retention-test',
        ]);
    }

    /**
     * Rows keyed to one account, per footprint table.
     *
     * @return array<string, int>
     */
    private function keyedCounts(int $userId, string $username): array
    {
        $byId = [
            'auth_users' => 'SELECT COUNT(*) AS n FROM `auth_users` WHERE `id` = ?',
            'auth_users_confirmations' => 'SELECT COUNT(*) AS n FROM `auth_users_confirmations` WHERE `user_id` = ?',
            'auth_users_otps' => 'SELECT COUNT(*) AS n FROM `auth_users_otps` WHERE `user_id` = ?',
            'auth_users_2fa' => 'SELECT COUNT(*) AS n FROM `auth_users_2fa` WHERE `user_id` = ?',
            'auth_users_remembered' => 'SELECT COUNT(*) AS n FROM `auth_users_remembered` WHERE `user` = ?',
            'auth_users_resets' => 'SELECT COUNT(*) AS n FROM `auth_users_resets` WHERE `user` = ?',
            'auth_users_audit_log.user_id' => 'SELECT COUNT(*) AS n FROM `auth_users_audit_log` WHERE `user_id` = ?',
            'ibl_api_keys' => 'SELECT COUNT(*) AS n FROM `ibl_api_keys` WHERE `user_id` = ?',
            'ibl_google_sheet_connections' => 'SELECT COUNT(*) AS n FROM `ibl_google_sheet_connections` WHERE `user_id` = ?',
        ];
        $counts = [];
        foreach ($byId as $label => $sql) {
            $counts[$label] = $this->countRows($sql, 'i', $userId);
        }
        $counts['ibl_events.username'] = $this->countRows('SELECT COUNT(*) AS n FROM `ibl_events` WHERE `username` = ?', 's', $username);

        return $counts;
    }

    /**
     * md5 of every row the fixture created, plus the team row, in a fixed order.
     */
    private function footprintSnapshot(): string
    {
        $ids = [self::VICTIM_ID, self::BYSTANDER_ID];
        $snapshot = [];
        foreach ([
            'auth_users' => 'SELECT * FROM `auth_users` WHERE `id` IN (?, ?) ORDER BY `id`',
            'auth_users_confirmations' => 'SELECT * FROM `auth_users_confirmations` WHERE `user_id` IN (?, ?) ORDER BY `id`',
            'auth_users_otps' => 'SELECT * FROM `auth_users_otps` WHERE `user_id` IN (?, ?) ORDER BY `id`',
            'auth_users_2fa' => 'SELECT * FROM `auth_users_2fa` WHERE `user_id` IN (?, ?) ORDER BY `id`',
            'auth_users_remembered' => 'SELECT * FROM `auth_users_remembered` WHERE `user` IN (?, ?) ORDER BY `id`',
            'auth_users_resets' => 'SELECT * FROM `auth_users_resets` WHERE `user` IN (?, ?) ORDER BY `id`',
            'auth_users_audit_log' => 'SELECT * FROM `auth_users_audit_log` WHERE `user_id` IN (?, ?) ORDER BY `id`',
            'ibl_api_keys' => 'SELECT * FROM `ibl_api_keys` WHERE `user_id` IN (?, ?) ORDER BY `id`',
            'ibl_google_sheet_connections' => 'SELECT * FROM `ibl_google_sheet_connections` WHERE `user_id` IN (?, ?) ORDER BY `id`',
        ] as $table => $sql) {
            $snapshot[$table] = $this->rows($sql, 'ii', ...$ids);
        }
        $snapshot['ibl_events'] = $this->rows(
            'SELECT * FROM `ibl_events` WHERE `username` IN (?, ?) ORDER BY `id`',
            'ss',
            self::VICTIM_NAME,
            self::BYSTANDER_NAME
        );
        $snapshot['ibl_team_info'] = $this->rows('SELECT * FROM ibl_team_info WHERE `teamid` = ?', 'i', self::TEAM_ID);

        return md5(serialize($snapshot));
    }

    private function countRows(string $sql, string $types, int|string $value): int
    {
        $rows = $this->rows($sql, $types, $value);
        $n = $rows[0]['n'] ?? null;

        return is_int($n) ? $n : -1;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, string $types, int|string ...$values): array
    {
        $stmt = $this->db->prepare($sql);
        self::assertNotFalse($stmt);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);
        /** @var list<array<string, mixed>> $rows */
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }
}
