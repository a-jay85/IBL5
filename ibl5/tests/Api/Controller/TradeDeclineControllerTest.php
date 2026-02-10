<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\TradeDeclineController;
use Api\Response\JsonResponder;
use Tests\Integration\IntegrationTestCase;

class TradeDeclineControllerTest extends IntegrationTestCase
{
    public function testReturns400WhenOfferIdMissing(): void
    {
        $controller = new TradeDeclineController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('error')
            ->with(400, 'bad_request', $this->stringContains('offerId'));

        $controller->handle([], [], $responder, ['discord_user_id' => '123']);
    }

    public function testReturns400WhenDiscordUserIdMissing(): void
    {
        $controller = new TradeDeclineController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('error')
            ->with(400, 'bad_request', $this->stringContains('discord_user_id'));

        $controller->handle(['offerId' => '42'], [], $responder, []);
    }

    public function testReturns404WhenTradeOfferNotFound(): void
    {
        $this->mockDb->setMockData([]);

        $controller = new TradeDeclineController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('error')
            ->with(404, 'not_found', $this->stringContains('not found'));

        $controller->handle(
            ['offerId' => '999'],
            [],
            $responder,
            ['discord_user_id' => '123456789']
        );
    }

    public function testReturns403WhenDiscordIdDoesNotMatchGm(): void
    {
        $this->suppressErrorLog();

        $this->mockDb->setMockData([
            [
                'tradeofferid' => 42,
                'itemid' => 100,
                'itemtype' => '1',
                'from' => 'Lakers',
                'to' => 'Celtics',
                'approval' => 'Celtics',
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
                'discordID' => '999999999',
            ],
        ]);

        $controller = new TradeDeclineController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('error')
            ->with(403, 'forbidden', $this->stringContains('not authorized'));

        $controller->handle(
            ['offerId' => '42'],
            [],
            $responder,
            ['discord_user_id' => '000000000']
        );
    }
}
