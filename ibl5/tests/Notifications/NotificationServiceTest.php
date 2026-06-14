<?php

declare(strict_types=1);

namespace Tests\Notifications;

use Notifications\Contracts\NotificationRepositoryInterface;
use Notifications\NotificationService;
use Notifications\NotificationType;
use PHPUnit\Framework\TestCase;

final class NotificationServiceTest extends TestCase
{
    public function testNotifyDelegatesToRepositoryInsertWithPassedArgs(): void
    {
        $repository = self::createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('insert')
            ->with(
                7,
                NotificationType::TRADE_OFFER_RECEIVED,
                'Stars sent you a trade offer.',
                'modules.php?name=Trading&op=reviewtrade'
            )
            ->willReturn(42);

        $service = new NotificationService($repository);
        $service->notify(
            7,
            NotificationType::TRADE_OFFER_RECEIVED,
            'Stars sent you a trade offer.',
            'modules.php?name=Trading&op=reviewtrade'
        );
    }

    public function testNotifyPassesNullLinkThrough(): void
    {
        $repository = self::createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('insert')
            ->with(3, NotificationType::TRADE_ACCEPTED, 'Cougars accepted your trade.', null)
            ->willReturn(1);

        $service = new NotificationService($repository);
        $service->notify(3, NotificationType::TRADE_ACCEPTED, 'Cougars accepted your trade.');
    }
}
