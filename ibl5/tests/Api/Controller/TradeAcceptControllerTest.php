<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\TradeAcceptController;
use Api\Response\JsonResponder;
use Tests\Integration\IntegrationTestCase;

class TradeAcceptControllerTest extends IntegrationTestCase
{
    public function testReturns400WhenOfferIdMissing(): void
    {
        $controller = new TradeAcceptController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('error')
            ->with(400, 'bad_request', $this->stringContains('offerId'));

        $controller->handle([], [], $responder, ['discord_user_id' => '123']);
    }

    public function testReturns400WhenDiscordUserIdMissing(): void
    {
        $controller = new TradeAcceptController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('error')
            ->with(400, 'bad_request', $this->stringContains('discord_user_id'));

        $controller->handle(['offerId' => '42'], [], $responder, []);
    }

    public function testReturns400WhenBodyIsNull(): void
    {
        $controller = new TradeAcceptController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('error')
            ->with(400, 'bad_request', $this->stringContains('discord_user_id'));

        $controller->handle(['offerId' => '42'], [], $responder, null);
    }

    public function testReturns404WhenTradeOfferNotFound(): void
    {
        $this->mockDb->setMockData([]);

        $controller = new TradeAcceptController($this->mockDb);
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

        // Mock trade rows exist
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
                // Discord lookup returns this GM's ID
                'discordID' => '999999999',
            ],
        ]);

        $controller = new TradeAcceptController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('error')
            ->with(403, 'forbidden', $this->stringContains('not authorized'));

        $controller->handle(
            ['offerId' => '42'],
            [],
            $responder,
            ['discord_user_id' => '000000000'] // Wrong ID
        );
    }
}
