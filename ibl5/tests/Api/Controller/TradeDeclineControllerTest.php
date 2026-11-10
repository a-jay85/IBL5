<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\TradeDeclineController;
use Api\Repository\ApiKeyRepository;
use Api\Response\JsonResponder;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Tests\WideUnit\WideUnitTestCase;

class TradeDeclineControllerTest extends WideUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['HTTP_X_API_KEY'] = 'test-bot-key';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_API_KEY'], $_GET['key']);
        parent::tearDown();
    }

    private function apiKeys(string $tier = 'unlimited'): ApiKeyRepository
    {
        $keys = self::createStub(ApiKeyRepository::class);
        $keys->method('findByHash')->willReturn([
            'key_hash'         => hash('sha256', 'test-bot-key'),
            'key_prefix'       => 'test-bot',
            'owner_name'       => 'x',
            'permission_level' => 'public',
            'rate_limit_tier'  => $tier,
        ]);
        return $keys;
    }
    public function testReturns400WhenOfferIdMissing(): void
    {
        $commonStub = self::createStub(TeamIdentityRepositoryInterface::class);
        $offerStub  = self::createStub(\Trading\Contracts\TradeOfferRepositoryInterface::class);
        $controller = new TradeDeclineController($commonStub, $offerStub, $this->apiKeys());
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('error')
            ->with(400, 'bad_request', self::stringContains('offerId'));

        $controller->handle([], [], $responder, ['discord_user_id' => '123']);
    }

    public function testReturns400WhenDiscordUserIdMissing(): void
    {
        $commonStub = self::createStub(TeamIdentityRepositoryInterface::class);
        $offerStub  = self::createStub(\Trading\Contracts\TradeOfferRepositoryInterface::class);
        $controller = new TradeDeclineController($commonStub, $offerStub, $this->apiKeys());
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('error')
            ->with(400, 'bad_request', self::stringContains('discord_user_id'));

        $controller->handle(['offerId' => '42'], [], $responder, []);
    }

    public function testReturns404WhenTradeOfferNotFound(): void
    {
        $commonStub = self::createStub(TeamIdentityRepositoryInterface::class);
        $offerStub  = self::createStub(\Trading\Contracts\TradeOfferRepositoryInterface::class);
        $offerStub->method('getTradesByOfferId')->willReturn([]);
        $controller = new TradeDeclineController($commonStub, $offerStub, $this->apiKeys());
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('error')
            ->with(404, 'not_found', self::stringContains('not found'));

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

        $commonStub = self::createStub(TeamIdentityRepositoryInterface::class);
        $offerStub  = self::createStub(\Trading\Contracts\TradeOfferRepositoryInterface::class);
        $offerStub->method('getTradesByOfferId')->willReturn([
            [
                'tradeofferid' => 42,
                'itemid' => 100,
                'itemtype' => '1',
                'trade_from' => 'Lakers',
                'trade_to' => 'Celtics',
                'approval' => 'Celtics',
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
        ]);
        $controller = new TradeDeclineController($commonStub, $offerStub, $this->apiKeys());
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('error')
            ->with(403, 'forbidden', self::stringContains('not authorized'));

        $controller->handle(
            ['offerId' => '42'],
            [],
            $responder,
            ['discord_user_id' => '000000000']
        );
    }

    /**
     * @param array<string, string> $params
     * @param array<string, mixed>|null $body
     */
    private function rejectedDeclineHandle(string $tier, bool $withKey, array $params, ?array $body): void
    {
        if (!$withKey) {
            unset($_SERVER['HTTP_X_API_KEY']);
        }
        $commonStub = self::createStub(TeamIdentityRepositoryInterface::class);
        $offerRepo = $this->createMock(\Trading\Contracts\TradeOfferRepositoryInterface::class);
        $offerRepo->expects($this->never())->method('getTradesByOfferId');
        $offerRepo->expects($this->never())->method('deleteTradeOffer');
        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(403, 'forbidden', 'Only the IBLbot service key can decline trades.');
        $responder->expects($this->never())->method('success');

        $controller = new TradeDeclineController($commonStub, $offerRepo, $this->apiKeys($tier));
        $controller->handle($params, [], $responder, $body);
    }

    public function testStandardTierKeyRejected403AndNoOfferRead(): void
    {
        $this->rejectedDeclineHandle('standard', true, ['offerId' => '8'], ['discord_user_id' => '123456789012345678']);
    }

    public function testMissingKeyRejected403AndNoOfferRead(): void
    {
        $this->rejectedDeclineHandle('unlimited', false, ['offerId' => '8'], ['discord_user_id' => '123456789012345678']);
    }

    public function testStandardTierKeyWithMissingOfferIdRejected403Not400(): void
    {
        $this->rejectedDeclineHandle('standard', true, [], null);
    }

    public function testElevatedTierKeyLocalhostTradeStillDeclines(): void
    {
        $commonStub = self::createStub(TeamIdentityRepositoryInterface::class);
        $offerRepo = $this->createMock(\Trading\Contracts\TradeOfferRepositoryInterface::class);
        $offerRepo->method('getTradesByOfferId')->willReturn([['approval' => 'test', 'trade_from' => 'Metros']]);
        $offerRepo->expects($this->once())->method('deleteTradeOffer')->with(8);
        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->never())->method('error');
        $responder->expects($this->once())->method('success');

        $controller = new TradeDeclineController($commonStub, $offerRepo, $this->apiKeys('unlimited'));
        $controller->handle(['offerId' => '8'], [], $responder, ['discord_user_id' => '123456789012345678']);
    }
}
