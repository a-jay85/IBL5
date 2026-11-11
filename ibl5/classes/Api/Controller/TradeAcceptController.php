<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Contracts\ControllerInterface;
use Api\Middleware\ServiceKeyGuard;
use Api\Repository\ApiKeyRepository;
use Api\Response\JsonResponder;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Discord\Discord;

class TradeAcceptController implements ControllerInterface
{
    private TeamIdentityRepositoryInterface $commonRepository;
    private \Trading\Contracts\TradeOfferRepositoryInterface $offerRepository;
    private \Trading\Contracts\TradeProcessorInterface $processor;

    private ServiceKeyGuard $serviceKeys;

    public function __construct(
        TeamIdentityRepositoryInterface $commonRepository,
        \Trading\Contracts\TradeOfferRepositoryInterface $offerRepository,
        \Trading\Contracts\TradeProcessorInterface $processor,
        ApiKeyRepository $apiKeys
    ) {
        $this->commonRepository = $commonRepository;
        $this->offerRepository = $offerRepository;
        $this->processor = $processor;
        $this->serviceKeys = new ServiceKeyGuard($apiKeys);
    }

    /**
     * @see ControllerInterface::handle()
     */
    public function handle(array $params, array $query, JsonResponder $responder, ?array $body = null): void
    {
        // Runs before any param or body field is read, so a rejected request reads and writes nothing.
        // discord_user_id is body data and Discord ids are public, so the caller must also be the bot's key.
        if (!$this->serviceKeys->callerIsServiceKey()) {
            $responder->error(403, 'forbidden', 'Only the IBLbot service key can accept trades.');
            return;
        }

        $offerId = isset($params['offerId']) ? (int) $params['offerId'] : 0;
        if ($offerId === 0) {
            $responder->error(400, 'bad_request', 'Missing or invalid offerId.');
            return;
        }

        $discordUserId = $body['discord_user_id'] ?? null;
        if (!is_string($discordUserId) || $discordUserId === '') {
            $responder->error(400, 'bad_request', 'Missing discord_user_id in request body.');
            return;
        }

        $repository = $this->offerRepository;
        $tradeRows = $repository->getTradesByOfferId($offerId);

        if ($tradeRows === []) {
            $responder->error(404, 'not_found', 'Trade offer not found or already processed.');
            return;
        }

        // The approval team is the team that needs to accept (stored in the trade rows)
        $approvalTeam = $tradeRows[0]['approval'] ?? '';

        // On localhost, TradingRepository sets approval to 'test' — skip Discord ID verification
        $isLocalhostTestTrade = $approvalTeam === 'test';

        if (!$isLocalhostTestTrade) {
            // Verify the Discord user is the GM of the approval team
            $discord = new Discord($this->commonRepository);
            $gmDiscordId = $discord->getDiscordIDFromTeamname($approvalTeam);

            if ($gmDiscordId === '' || $gmDiscordId !== $discordUserId) {
                $responder->error(403, 'forbidden', 'You are not authorized to accept this trade.');
                return;
            }
        }

        $processor = $this->processor;
        $result = $processor->processTrade($offerId);

        if ($result['success'] !== true) {
            $responder->error(500, 'processing_error', $result['error'] ?? 'Failed to process trade.');
            return;
        }

        $responder->success([
            'accepted' => true,
            'story' => $result['storytext'] ?? '',
        ]);
    }
}
