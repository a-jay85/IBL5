<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Contracts\ControllerInterface;
use Api\Response\JsonResponder;
use Trading\TradingRepository;

class TradeDeclineController implements ControllerInterface
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

        // The approval team is the team that needs to accept/decline
        $approvalTeam = $tradeRows[0]['approval'] ?? '';

        // On localhost, TradingRepository sets approval to 'test' — skip Discord ID verification
        // and decline notification (no real GM to notify)
        if ($approvalTeam !== 'test') {
            // Verify the Discord user is the GM of the approval team
            $discord = new \Discord($this->db);
            $gmDiscordId = $discord->getDiscordIDFromTeamname($approvalTeam);

            if ($gmDiscordId === '' || $gmDiscordId !== $discordUserId) {
                $responder->error(403, 'forbidden', 'You are not authorized to decline this trade.');
                return;
            }

            // Determine the offering team (the team that proposed the trade)
            $offeringTeam = '';
            foreach ($tradeRows as $row) {
                $from = $row['from'] ?? '';
                if ($from !== '' && $from !== $approvalTeam) {
                    $offeringTeam = $from;
                    break;
                }
            }

            // Send decline notification to the offering team's GM
            if ($offeringTeam !== '') {
                try {
                    $offeringGmDiscordId = $discord->getDiscordIDFromTeamname($offeringTeam);

                    $declineMessage = 'Sorry, trade proposal declined by <@!' . $gmDiscordId . '>.'
                        . "\n" . 'Go here to make another offer: http://www.iblhoops.net/ibl5/modules.php?name=Trading&op=reviewtrade';

                    \Discord::sendDM($offeringGmDiscordId, $declineMessage);
                } catch (\Exception $e) {
                    // Log but don't fail the decline
                    error_log('Discord decline notification failed: ' . $e->getMessage());
                }
            }
        }

        // Delete the trade offer
        $repository->deleteTradeOffer($offerId);

        $responder->success(['declined' => true]);
    }
}
