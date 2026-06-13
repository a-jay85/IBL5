<?php

declare(strict_types=1);

namespace Tests\Notifications;

use Notifications\NotificationsView;
use Notifications\NotificationType;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Notifications\NotificationsView
 */
final class NotificationsViewTest extends TestCase
{
    /**
     * @param array{id?: int, team_id?: int, type?: string, message?: string, link?: string|null, read_at?: string|null, created_at?: string} $overrides
     * @return array{id: int, team_id: int, type: string, message: string, link: string|null, read_at: string|null, created_at: string}
     */
    private static function row(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'team_id' => 1,
            'type' => NotificationType::TRADE_OFFER_RECEIVED,
            'message' => 'Stars sent you a trade offer.',
            'link' => 'modules.php?name=Trading&op=reviewtrade',
            'read_at' => null,
            'created_at' => '2026-06-13 12:00:00',
        ], $overrides);
    }

    public function testRendersEscapedMessageAndUnreadMarker(): void
    {
        $view = new NotificationsView();

        $html = $view->render([self::row()], 'tok-mark', 'tok-mark-all');

        self::assertStringContainsString('Stars sent you a trade offer.', $html);
        self::assertStringContainsString('notification-card--unread', $html);
        // Unread row carries a mark-read form with the shared token.
        self::assertStringContainsString('op=mark', $html);
        self::assertStringContainsString('tok-mark', $html);
    }

    public function testEscapesMaliciousMessage(): void
    {
        $view = new NotificationsView();

        $html = $view->render(
            [self::row(['message' => '<script>alert(1)</script>'])],
            'tok-mark',
            'tok-mark-all'
        );

        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function testReadRowHasNoUnreadMarkerOrMarkForm(): void
    {
        $view = new NotificationsView();

        $html = $view->render(
            [self::row(['read_at' => '2026-06-13 13:00:00'])],
            'tok-mark',
            'tok-mark-all'
        );

        self::assertStringNotContainsString('notification-card--unread', $html);
        self::assertStringNotContainsString('op=mark_all', $html);
    }

    public function testEmptyListRendersEmptyState(): void
    {
        $view = new NotificationsView();

        $html = $view->render([], 'tok-mark', 'tok-mark-all');

        self::assertStringContainsString('ibl-empty-state', $html);
        self::assertStringContainsString('no notifications', $html);
    }
}
