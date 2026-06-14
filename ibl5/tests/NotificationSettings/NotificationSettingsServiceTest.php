<?php

declare(strict_types=1);

namespace Tests\NotificationSettings;

use NotificationSettings\Contracts\NotificationPrefsRepositoryInterface;
use NotificationSettings\NotificationPref;
use NotificationSettings\NotificationSettingsService;
use PHPUnit\Framework\TestCase;
use Tests\Support\AuditLogAssertions;

class NotificationSettingsServiceTest extends TestCase
{
    use AuditLogAssertions;

    protected function setUp(): void
    {
        $this->setUpAuditLogCapture();
    }

    protected function tearDown(): void
    {
        $this->tearDownAuditLogCapture();
    }

    // ── Defaults (matrix #2) ──────────────────────────────────────────

    public function testGetPrefsForUserReturnsDefaultsWhenNoRow(): void
    {
        $stubRepo = self::createStub(NotificationPrefsRepositoryInterface::class);
        $stubRepo->method('findByUserId')->willReturn(null);

        $service = new NotificationSettingsService($stubRepo);
        $prefs = $service->getPrefsForUser(1);

        // Event toggles default ON, digests/reminders default OFF.
        self::assertSame(1, $prefs['notify_trade_offers']);
        self::assertSame(1, $prefs['notify_waiver_claims']);
        self::assertSame(1, $prefs['notify_fa_outbids']);
        self::assertSame(0, $prefs['digest_depth_chart_reminder']);
        self::assertSame(0, $prefs['digest_weekly_transactions']);
        self::assertSame(0, $prefs['digest_channel_discord']);
    }

    public function testDefaultsMapMatchesEnumSource(): void
    {
        $stubRepo = self::createStub(NotificationPrefsRepositoryInterface::class);
        $stubRepo->method('findByUserId')->willReturn(null);

        $service = new NotificationSettingsService($stubRepo);
        $prefs = $service->getPrefsForUser(1);

        // The defaults must equal NotificationPref::defaultEnabled() for every case —
        // guards that the PHP source of defaults stays the single source of truth.
        foreach (NotificationPref::cases() as $case) {
            self::assertSame(
                $case->defaultEnabled() ? 1 : 0,
                $prefs[$case->value],
                "Default for {$case->value} must match NotificationPref::defaultEnabled()"
            );
        }
    }

    public function testGetPrefsForUserReturnsStoredRowAsInts(): void
    {
        $stubRepo = self::createStub(NotificationPrefsRepositoryInterface::class);
        // mysqli may hand back ints as strings — service must normalize to int.
        $stubRepo->method('findByUserId')->willReturn([
            'notify_trade_offers' => '1',
            'notify_waiver_claims' => '0',
            'notify_fa_outbids' => '1',
            'digest_depth_chart_reminder' => '0',
            'digest_weekly_transactions' => '1',
            'digest_channel_discord' => '0',
        ]);

        $service = new NotificationSettingsService($stubRepo);
        $prefs = $service->getPrefsForUser(7);

        self::assertSame(1, $prefs['notify_trade_offers']);
        self::assertSame(0, $prefs['notify_waiver_claims']);
        self::assertSame(1, $prefs['notify_fa_outbids']);
        self::assertSame(0, $prefs['digest_depth_chart_reminder']);
        self::assertSame(1, $prefs['digest_weekly_transactions']);
        self::assertSame(0, $prefs['digest_channel_discord']);
    }

    // ── Save: checkbox-absent-means-OFF + unknown-key-ignored (matrix #4) ──

    public function testSaveWritesZeroForEveryAbsentKey(): void
    {
        $captured = [];
        $mockRepo = $this->createMock(NotificationPrefsRepositoryInterface::class);
        $mockRepo->expects($this->once())
            ->method('savePrefs')
            ->willReturnCallback(function (int $userId, array $values) use (&$captured): void {
                $captured = $values;
            });

        $service = new NotificationSettingsService($mockRepo);
        // Empty submission — every unchecked box absent.
        $service->savePrefsForUser(1, []);

        // Every column must be written explicitly as 0.
        foreach (NotificationPref::cases() as $case) {
            self::assertSame(0, $captured[$case->column()], "{$case->column()} must be written as 0");
        }
    }

    public function testSaveMapsOnlySubmittedKnownKeys(): void
    {
        $captured = [];
        $mockRepo = $this->createMock(NotificationPrefsRepositoryInterface::class);
        $mockRepo->expects($this->once())
            ->method('savePrefs')
            ->willReturnCallback(function (int $userId, array $values) use (&$captured): void {
                $captured = $values;
            });

        $service = new NotificationSettingsService($mockRepo);
        // Only one known toggle on.
        $service->savePrefsForUser(1, ['notify_trade_offers']);

        self::assertSame(1, $captured['notify_trade_offers']);
        self::assertSame(0, $captured['notify_waiver_claims']);
        self::assertSame(0, $captured['notify_fa_outbids']);
        self::assertSame(0, $captured['digest_depth_chart_reminder']);
        self::assertSame(0, $captured['digest_weekly_transactions']);
        self::assertSame(0, $captured['digest_channel_discord']);
    }

    public function testSaveIgnoresUnknownToggleKey(): void
    {
        $captured = [];
        $mockRepo = $this->createMock(NotificationPrefsRepositoryInterface::class);
        $mockRepo->expects($this->once())
            ->method('savePrefs')
            ->willReturnCallback(function (int $userId, array $values) use (&$captured): void {
                $captured = $values;
            });

        $service = new NotificationSettingsService($mockRepo);
        // 'bogus_key' and 'user_id' are not enum cases — must never map to a column.
        $service->savePrefsForUser(1, ['notify_trade_offers', 'bogus_key', 'user_id']);

        // Only the six known columns are present; unknown keys never leak in.
        self::assertCount(count(NotificationPref::cases()), $captured);
        self::assertArrayNotHasKey('bogus_key', $captured);
        self::assertArrayNotHasKey('user_id', $captured);
        self::assertSame(1, $captured['notify_trade_offers']);
    }

    // ── Audit logging ─────────────────────────────────────────────────

    public function testSaveEmitsAuditLog(): void
    {
        $stubRepo = self::createStub(NotificationPrefsRepositoryInterface::class);

        $service = new NotificationSettingsService($stubRepo);
        $service->savePrefsForUser(42, ['notify_trade_offers']);

        $this->assertAuditLogEmitted('notification_prefs_saved');
        $this->assertAuditLogContext('notification_prefs_saved', [
            'action' => 'notification_prefs_saved',
            'user_id' => 42,
        ]);
    }
}
