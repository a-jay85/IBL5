<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Notifications\NotificationRepository;
use Notifications\NotificationType;
use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
class NotificationsRepositoryTest extends DatabaseTestCase
{
    private NotificationRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new NotificationRepository($this->db);
    }

    public function testGetForTeamReturnsNewestFirstAndCountsUnread(): void
    {
        $this->repo->insert(1, NotificationType::TRADE_OFFER_RECEIVED, 'first', null);
        $this->repo->insert(1, NotificationType::TRADE_ACCEPTED, 'second', '/ibl5/modules.php?name=Trading');
        $this->repo->insert(1, NotificationType::TRADE_REJECTED, 'third', null);

        $rows = $this->repo->getForTeam(1);

        self::assertCount(3, $rows);
        // Newest-first: last inserted (highest id) appears first.
        self::assertSame('third', $rows[0]['message']);
        self::assertSame('first', $rows[2]['message']);
        self::assertSame(3, $this->repo->countUnread(1));
    }

    public function testMarkReadFlipsSingleRowAndReducesUnread(): void
    {
        $id = $this->repo->insert(1, NotificationType::TRADE_OFFER_RECEIVED, 'unread one', null);
        $this->repo->insert(1, NotificationType::TRADE_ACCEPTED, 'unread two', null);

        self::assertSame(2, $this->repo->countUnread(1));

        $affected = $this->repo->markRead($id, 1);

        self::assertSame(1, $affected);
        self::assertSame(1, $this->repo->countUnread(1));
    }

    public function testMarkReadIsIdempotentForAlreadyReadRow(): void
    {
        $id = $this->repo->insert(1, NotificationType::TRADE_REJECTED, 'once', null);

        self::assertSame(1, $this->repo->markRead($id, 1));
        // Second call affects 0 rows (read_at already set).
        self::assertSame(0, $this->repo->markRead($id, 1));
    }

    public function testMarkAllReadClearsRemainingUnread(): void
    {
        $this->repo->insert(1, NotificationType::TRADE_OFFER_RECEIVED, 'a', null);
        $this->repo->insert(1, NotificationType::TRADE_ACCEPTED, 'b', null);
        $this->repo->insert(1, NotificationType::TRADE_REJECTED, 'c', null);

        $affected = $this->repo->markAllRead(1);

        self::assertSame(3, $affected);
        self::assertSame(0, $this->repo->countUnread(1));
    }

    public function testMarkReadForAnotherTeamsNotificationAffectsZeroRows(): void
    {
        // Notification belongs to team 2; team 1 must not be able to mark it read.
        $otherTeamsId = $this->repo->insert(2, NotificationType::TRADE_OFFER_RECEIVED, 'team 2 only', null);

        $affected = $this->repo->markRead($otherTeamsId, 1);

        self::assertSame(0, $affected, 'A GM cannot mark another team\'s notification read');
        self::assertSame(1, $this->repo->countUnread(2), 'Target row stays unread');
    }

    public function testInsertWithNullLinkStoresAndReadsBackNull(): void
    {
        $this->repo->insert(3, NotificationType::TRADE_ACCEPTED, 'no link', null);

        $rows = $this->repo->getForTeam(3);

        self::assertCount(1, $rows);
        self::assertNull($rows[0]['link']);
    }

    public function testInsertWithLinkStoresAndReadsBackLink(): void
    {
        $this->repo->insert(3, NotificationType::TRADE_ACCEPTED, 'with link', '/ibl5/modules.php?name=Trading&op=reviewtrade');

        $rows = $this->repo->getForTeam(3);

        self::assertSame('/ibl5/modules.php?name=Trading&op=reviewtrade', $rows[0]['link']);
    }
}
