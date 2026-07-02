<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use NotificationSettings\NotificationSettingsRepository;
use PHPUnit\Framework\Attributes\Group;

/**
 * Real-MariaDB write-path coverage for gm_notification_prefs.
 *
 * user_id 1 (testgm) is seeded in db-seed.sql; the FK to auth_users(id)
 * requires a real account, and each test rolls back for isolation.
 */
#[Group('database')]
class NotificationSettingsRepositoryTest extends DatabaseTestCase
{
    private const USER_ID = 1;

    private NotificationSettingsRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new NotificationSettingsRepository($this->db);
    }

    public function testFindByUserIdReturnsNullWhenNoRow(): void
    {
        self::assertNull($this->repo->findByUserId(self::USER_ID));
    }

    /** Matrix #1 — round-trips all 6 columns through real prepared writes. */
    public function testSaveThenFindRoundTripsAllColumns(): void
    {
        $values = [
            'notify_trade_offers' => 1,
            'notify_waiver_claims' => 0,
            'notify_fa_outbids' => 1,
            'digest_depth_chart_reminder' => 0,
            'digest_weekly_transactions' => 1,
            'digest_channel_discord' => 0,
        ];

        $this->repo->savePrefs(self::USER_ID, $values);
        $row = $this->repo->findByUserId(self::USER_ID);

        self::assertNotNull($row);
        self::assertSame(1, $row['notify_trade_offers']);
        self::assertSame(0, $row['notify_waiver_claims']);
        self::assertSame(1, $row['notify_fa_outbids']);
        self::assertSame(0, $row['digest_depth_chart_reminder']);
        self::assertSame(1, $row['digest_weekly_transactions']);
        self::assertSame(0, $row['digest_channel_discord']);
    }

    /** Matrix #3 — saving all-off writes 0 to every column (checkbox-absent-means-OFF). */
    public function testSaveAllOffWritesZeroEverywhere(): void
    {
        $this->repo->savePrefs(self::USER_ID, [
            'notify_trade_offers' => 0,
            'notify_waiver_claims' => 0,
            'notify_fa_outbids' => 0,
            'digest_depth_chart_reminder' => 0,
            'digest_weekly_transactions' => 0,
            'digest_channel_discord' => 0,
        ]);

        $row = $this->repo->findByUserId(self::USER_ID);

        self::assertNotNull($row);
        foreach ($row as $column => $value) {
            self::assertSame(0, $value, "$column should be 0");
        }
    }

    /** Matrix #5 — a single toggle on round-trips as exactly that subset. */
    public function testSaveSubsetReloadsExactSubset(): void
    {
        $this->repo->savePrefs(self::USER_ID, [
            'notify_trade_offers' => 1,
            'notify_waiver_claims' => 0,
            'notify_fa_outbids' => 0,
            'digest_depth_chart_reminder' => 0,
            'digest_weekly_transactions' => 0,
            'digest_channel_discord' => 0,
        ]);

        $row = $this->repo->findByUserId(self::USER_ID);

        self::assertNotNull($row);
        self::assertSame(1, $row['notify_trade_offers']);
        self::assertSame(0, $row['notify_waiver_claims']);
        self::assertSame(0, $row['notify_fa_outbids']);
        self::assertSame(0, $row['digest_depth_chart_reminder']);
        self::assertSame(0, $row['digest_weekly_transactions']);
        self::assertSame(0, $row['digest_channel_discord']);
    }

    /** Matrix #6 — UPSERT: saving twice updates the single row, second save wins. */
    public function testSaveTwiceUpsertsSingleRowSecondWins(): void
    {
        $first = [
            'notify_trade_offers' => 1,
            'notify_waiver_claims' => 1,
            'notify_fa_outbids' => 1,
            'digest_depth_chart_reminder' => 1,
            'digest_weekly_transactions' => 1,
            'digest_channel_discord' => 1,
        ];
        $second = [
            'notify_trade_offers' => 0,
            'notify_waiver_claims' => 1,
            'notify_fa_outbids' => 0,
            'digest_depth_chart_reminder' => 1,
            'digest_weekly_transactions' => 0,
            'digest_channel_discord' => 1,
        ];

        $this->repo->savePrefs(self::USER_ID, $first);
        $this->repo->savePrefs(self::USER_ID, $second);

        // Exactly one row exists for this user (no duplicate-PK error, single row).
        $count = $this->db->query(
            'SELECT COUNT(*) AS c FROM gm_notification_prefs WHERE user_id = ' . self::USER_ID
        );
        self::assertNotFalse($count);
        $countRow = $count->fetch_assoc();
        self::assertNotNull($countRow);
        self::assertSame(1, (int) $countRow['c']);

        $row = $this->repo->findByUserId(self::USER_ID);
        self::assertNotNull($row);
        self::assertSame(0, $row['notify_trade_offers']);
        self::assertSame(1, $row['notify_waiver_claims']);
        self::assertSame(0, $row['notify_fa_outbids']);
        self::assertSame(1, $row['digest_depth_chart_reminder']);
        self::assertSame(0, $row['digest_weekly_transactions']);
        self::assertSame(1, $row['digest_channel_discord']);
    }
}
