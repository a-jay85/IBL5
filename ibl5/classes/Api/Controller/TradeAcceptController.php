<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Contracts\ControllerInterface;
use Api\Response\JsonResponder;
use Trading\TradeProcessor;
use Trading\TradingRepository;

class TradeAcceptController implements ControllerInterface
{
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * @see ControllerInterface::handle()
     */
    public function handle(array $params, array $query, JsonResponder $responder, ?array $body = null): void
    {
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

        $repository = new TradingRepository($this->db);
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
            $discord = new \Discord($this->db);
            $gmDiscordId = $discord->getDiscordIDFromTeamname($approvalTeam);

            if ($gmDiscordId === '' || $gmDiscordId !== $discordUserId) {
                $responder->error(403, 'forbidden', 'You are not authorized to accept this trade.');
                return;
            }
        }

        $processor = new TradeProcessor($this->db);
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
