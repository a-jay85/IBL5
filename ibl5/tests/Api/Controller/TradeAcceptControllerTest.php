<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\TradeAcceptController;
use Api\Repository\ApiKeyRepository;
use Api\Response\JsonResponder;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Tests\WideUnit\WideUnitTestCase;

class TradeAcceptControllerTest extends WideUnitTestCase
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
        $commonStub    = self::createStub(TeamIdentityRepositoryInterface::class);
        $offerStub     = self::createStub(\Trading\Contracts\TradeOfferRepositoryInterface::class);
        $processorStub = self::createStub(\Trading\Contracts\TradeProcessorInterface::class);
        $controller    = new TradeAcceptController($commonStub, $offerStub, $processorStub, $this->apiKeys());
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('error')
            ->with(400, 'bad_request', self::stringContains('offerId'));

        $controller->handle([], [], $responder, ['discord_user_id' => '123']);
    }

    public function testReturns400WhenDiscordUserIdMissing(): void
    {
        $commonStub    = self::createStub(TeamIdentityRepositoryInterface::class);
        $offerStub     = self::createStub(\Trading\Contracts\TradeOfferRepositoryInterface::class);
        $processorStub = self::createStub(\Trading\Contracts\TradeProcessorInterface::class);
        $controller    = new TradeAcceptController($commonStub, $offerStub, $processorStub, $this->apiKeys());
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('error')
            ->with(400, 'bad_request', self::stringContains('discord_user_id'));

        $controller->handle(['offerId' => '42'], [], $responder, []);
    }

    public function testReturns400WhenBodyIsNull(): void
    {
        $commonStub    = self::createStub(TeamIdentityRepositoryInterface::class);
        $offerStub     = self::createStub(\Trading\Contracts\TradeOfferRepositoryInterface::class);
        $processorStub = self::createStub(\Trading\Contracts\TradeProcessorInterface::class);
        $controller    = new TradeAcceptController($commonStub, $offerStub, $processorStub, $this->apiKeys());
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('error')
            ->with(400, 'bad_request', self::stringContains('discord_user_id'));

        $controller->handle(['offerId' => '42'], [], $responder, null);
    }

    public function testReturns404WhenTradeOfferNotFound(): void
    {
        $commonStub    = self::createStub(TeamIdentityRepositoryInterface::class);
        $offerStub     = self::createStub(\Trading\Contracts\TradeOfferRepositoryInterface::class);
        $processorStub = self::createStub(\Trading\Contracts\TradeProcessorInterface::class);
        $offerStub->method('getTradesByOfferId')->willReturn([]);
        $controller    = new TradeAcceptController($commonStub, $offerStub, $processorStub, $this->apiKeys());
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

        $commonStub    = self::createStub(TeamIdentityRepositoryInterface::class);
        $offerStub     = self::createStub(\Trading\Contracts\TradeOfferRepositoryInterface::class);
        $processorStub = self::createStub(\Trading\Contracts\TradeProcessorInterface::class);
        // Mock trade rows exist
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

        $controller = new TradeAcceptController($commonStub, $offerStub, $processorStub, $this->apiKeys());
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('error')
            ->with(403, 'forbidden', self::stringContains('not authorized'));

        $controller->handle(
            ['offerId' => '42'],
            [],
            $responder,
            ['discord_user_id' => '000000000'] // Wrong ID
        );
    }

    /**
     * @param array<string, string> $params
     * @param array<string, mixed>|null $body
     */
    private function rejectedAcceptHandle(string $tier, bool $withKey, array $params, ?array $body): void
    {
        if (!$withKey) {
            unset($_SERVER['HTTP_X_API_KEY']);
        }
        $commonStub = self::createStub(TeamIdentityRepositoryInterface::class);
        $offerRepo = $this->createMock(\Trading\Contracts\TradeOfferRepositoryInterface::class);
        $processor = $this->createMock(\Trading\Contracts\TradeProcessorInterface::class);
        $offerRepo->expects($this->never())->method('getTradesByOfferId');
        $processor->expects($this->never())->method('processTrade');
        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(403, 'forbidden', 'Only the IBLbot service key can accept trades.');
        $responder->expects($this->never())->method('success');

        $controller = new TradeAcceptController($commonStub, $offerRepo, $processor, $this->apiKeys($tier));
        $controller->handle($params, [], $responder, $body);
    }

    public function testStandardTierKeyRejected403AndNoProcessorCall(): void
    {
        $this->rejectedAcceptHandle('standard', true, ['offerId' => '7'], ['discord_user_id' => '123456789012345678']);
    }

    public function testMissingKeyRejected403AndNoProcessorCall(): void
    {
        $this->rejectedAcceptHandle('unlimited', false, ['offerId' => '7'], ['discord_user_id' => '123456789012345678']);
    }

    public function testStandardTierKeyWithMissingOfferIdRejected403Not400(): void
    {
        $this->rejectedAcceptHandle('standard', true, [], null);
    }

    public function testElevatedTierKeyLocalhostTradeStillAccepts(): void
    {
        // An elevated key on a localhost 'test' trade must reach processTrade(7) exactly once.
        $commonStub = self::createStub(TeamIdentityRepositoryInterface::class);
        $offerRepo = self::createStub(\Trading\Contracts\TradeOfferRepositoryInterface::class);
        $processor = $this->createMock(\Trading\Contracts\TradeProcessorInterface::class);
        $offerRepo->method('getTradesByOfferId')->willReturn([['approval' => 'test', 'trade_from' => 'Metros']]);
        $processor->expects($this->once())
            ->method('processTrade')
            ->with(7)
            ->willReturn(['success' => true, 'storytext' => 'story']);
        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->never())->method('error');
        $responder->expects($this->once())
            ->method('success')
            ->with(['accepted' => true, 'story' => 'story']);

        $controller = new TradeAcceptController($commonStub, $offerRepo, $processor, $this->apiKeys('unlimited'));
        $controller->handle(['offerId' => '7'], [], $responder, ['discord_user_id' => '123456789012345678']);
    }
}
