<?php

declare(strict_types=1);

namespace Trading;

use EventLog\EventLogger;
use Psr\Log\LoggerInterface;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Trading\Contracts\TradeExecutionServiceInterface;
use Trading\Contracts\TradeOfferRepositoryInterface;

class TradeDecisionService implements Contracts\TradeDecisionServiceInterface
{
    public function __construct(
        private TradeOfferRepositoryInterface $offerRepository,
        private TradeExecutionServiceInterface $executionService,
        private TeamIdentityRepositoryInterface $teamIdentityRepo,
        private LoggerInterface $auditLogger,
        private LoggerInterface $tradeLogger,
    ) {}

    /**
     * @see Contracts\TradeDecisionServiceInterface::reject()
     */
    public function reject(int $offerId, string $actingTeam, string $teamRejecting, string $teamReceiving): array
    {
        if ($this->offerRepository->getTradesByOfferId($offerId) === []) {
            return ['success' => false, 'redirect' => '/ibl5/modules.php?name=Trading&op=reviewtrade&result=already_processed'];
        }

        // Authz/IDOR gate: only a GM whose team is a party to the offer may reject
        // it. $actingTeam comes from the authenticated session, never from
        // $teamRejecting (attacker-controlled, Discord-DM addressing only).
        if (!$this->executionService->assertActingTeamIsParty($offerId, $actingTeam)) {
            $this->tradeLogger->warning('Rejected non-party trade reject attempt', [
                'offer_id' => $offerId,
            ]);

            return ['success' => false, 'redirect' => '/ibl5/modules.php?name=Trading&op=reviewtrade&result=reject_error'];
        }

        $this->offerRepository->deleteTradeOffer($offerId);

        $this->auditLogger->info('trade_offer_rejected', [
            'offer_id' => $offerId,
        ]);

        try {
            $discord = new \Discord\Discord($this->teamIdentityRepo);
            $rejectingUserDiscordID = $discord->getDiscordIDFromTeamname($teamRejecting);
            $receivingUserDiscordID = $discord->getDiscordIDFromTeamname($teamReceiving);
            $declineMessage = TradingService::buildDeclineMessage($rejectingUserDiscordID, $teamRejecting);
            \Discord\Discord::sendDM($receivingUserDiscordID, $declineMessage);
        } catch (\Exception $e) {
            // Silently fail if Discord notification fails
            // The trade rejection itself has already succeeded
        }

        EventLogger::setAction('trade_offer_rejected');

        return ['success' => true, 'redirect' => '/ibl5/modules.php?name=Trading&op=reviewtrade&result=trade_rejected'];
    }
}
