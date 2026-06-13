<?php

declare(strict_types=1);

namespace Tests\Notifications;

use Notifications\Contracts\NotificationServiceInterface;
use Notifications\NotificationType;
use PHPUnit\Framework\TestCase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Trading\TradeOffer;
use Trading\TradeProcessor;

/**
 * Verifies that the trade dispatch points reachable without `exit` write an
 * in-app notification through NotificationService::notify() with the right
 * recipient, type, and message. The reject path exits via HtmxHelper::redirect()
 * and is covered end-to-end (verification matrix #13), not here.
 *
 * The anonymous test doubles are inlined per test (not built by a typed helper):
 * a helper returning the parent TradeOffer/TradeProcessor type would erase the
 * subclass and PHPStan would reject the call to the exposed wrapper method.
 *
 * @covers \Trading\TradeOffer
 * @covers \Trading\TradeProcessor
 */
final class TradeNotificationWiringTest extends TestCase
{
    public function testOfferReceivedNotifiesListeningTeam(): void
    {
        $service = self::createMock(NotificationServiceInterface::class);
        $service->expects($this->once())
            ->method('notify')
            ->with(
                7,
                NotificationType::TRADE_OFFER_RECEIVED,
                'Stars sent you a trade offer.',
                'modules.php?name=Trading&op=reviewtrade'
            );

        $identity = self::createStub(TeamIdentityRepositoryInterface::class);
        $identity->method('getTidFromTeamname')->willReturn(7);

        $offer = new class ($service, $identity) extends TradeOffer {
            // @phpstan-ignore constructor.missingParentCall (intentional: skips the live-DB constructor to inject the notification seam directly)
            public function __construct(
                NotificationServiceInterface $service,
                TeamIdentityRepositoryInterface $identity,
            ) {
                $this->notificationService = $service;
                $this->commonRepository = $identity;
                $this->discord = null;
            }

            public function callSendTradeNotification(string $offeringTeam, string $listeningTeam): void
            {
                $this->sendTradeNotification(
                    [
                        'offeringTeam' => $offeringTeam,
                        'listeningTeam' => $listeningTeam,
                        'switchCounter' => 0,
                        'fieldsCounter' => 0,
                        'check' => [],
                        'index' => [],
                        'type' => [],
                        'contract' => [],
                        'userSendsCash' => [],
                        'partnerSendsCash' => [],
                    ],
                    'trade text',
                    123
                );
            }
        };

        $offer->callSendTradeNotification('Stars', 'Metros');
    }

    public function testOfferReceivedSkipsNotifyWhenTeamUnresolved(): void
    {
        $service = self::createMock(NotificationServiceInterface::class);
        $service->expects($this->never())->method('notify');

        $identity = self::createStub(TeamIdentityRepositoryInterface::class);
        $identity->method('getTidFromTeamname')->willReturn(null);

        $offer = new class ($service, $identity) extends TradeOffer {
            // @phpstan-ignore constructor.missingParentCall (intentional: skips the live-DB constructor to inject the notification seam directly)
            public function __construct(
                NotificationServiceInterface $service,
                TeamIdentityRepositoryInterface $identity,
            ) {
                $this->notificationService = $service;
                $this->commonRepository = $identity;
                $this->discord = null;
            }

            public function callSendTradeNotification(string $offeringTeam, string $listeningTeam): void
            {
                $this->sendTradeNotification(
                    [
                        'offeringTeam' => $offeringTeam,
                        'listeningTeam' => $listeningTeam,
                        'switchCounter' => 0,
                        'fieldsCounter' => 0,
                        'check' => [],
                        'index' => [],
                        'type' => [],
                        'contract' => [],
                        'userSendsCash' => [],
                        'partnerSendsCash' => [],
                    ],
                    'trade text',
                    123
                );
            }
        };

        $offer->callSendTradeNotification('Stars', 'Nobody');
    }

    public function testAcceptedNotifiesOfferingTeam(): void
    {
        $service = self::createMock(NotificationServiceInterface::class);
        $service->expects($this->once())
            ->method('notify')
            ->with(
                5,
                NotificationType::TRADE_ACCEPTED,
                'Metros accepted your trade.',
                'modules.php?name=Trading&op=reviewtrade'
            );

        $identity = self::createStub(TeamIdentityRepositoryInterface::class);
        $identity->method('getTidFromTeamname')->willReturn(5);

        $processor = new class ($service, $identity) extends TradeProcessor {
            // @phpstan-ignore constructor.missingParentCall (intentional: skips the live-DB constructor to inject the notification seam directly)
            public function __construct(
                NotificationServiceInterface $service,
                TeamIdentityRepositoryInterface $identity,
            ) {
                $this->notificationService = $service;
                $this->commonRepository = $identity;
                $this->discord = null;
                $this->serverName = 'localhost';
            }

            public function callSendNotifications(string $offering, string $listening, string $story): void
            {
                $this->sendNotifications($offering, $listening, $story);
            }
        };

        $processor->callSendNotifications('Stars', 'Metros', 'story text');
    }
}
