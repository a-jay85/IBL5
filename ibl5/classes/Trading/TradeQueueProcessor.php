<?php

declare(strict_types=1);

namespace Trading;

use Trading\Contracts\TradeExecutionRepositoryInterface;

/**
 * TradeQueueProcessor - Executes queued trade operations
 *
 * During certain season phases (Playoffs, Draft, Free Agency), trades are queued
 * rather than executed immediately. This class processes the queue by executing
 * each operation via prepared statements.
 *
 * @phpstan-type QueuedTradeRow array{id: int, operation_type: string, params: string, tradeline: string}
 * @phpstan-type PlayerTransferParams array{player_id: int, team_name: string, team_id: int}
 * @phpstan-type PickTransferParams array{pick_id: int, new_owner: string}
 */
class TradeQueueProcessor
{
    private TradeExecutionRepositoryInterface $executionRepository;

    public function __construct(TradeExecutionRepositoryInterface $executionRepository)
    {
        $this->executionRepository = $executionRepository;
    }

    /**
     * Process all queued trades
     *
     * Executes each queued operation via prepared statements and returns
     * a summary of processed trades.
     *
     * @return array{processed: int, failed: int, messages: list<string>}
     */
    public function processQueue(): array
    {
        $queuedTrades = $this->executionRepository->getQueuedTrades();
        $processed = 0;
        $failed = 0;
        $messages = [];

        foreach ($queuedTrades as $trade) {
            $result = $this->processQueuedTrade($trade);

            if ($result['success']) {
                $processed++;
                $messages[] = $trade['tradeline'];
                $this->executionRepository->deleteQueuedTrade($trade['id']);
            } else {
                $failed++;
                $messages[] = "FAILED: " . $trade['tradeline'] . " - " . $result['error'];
            }
        }

        return [
            'processed' => $processed,
            'failed' => $failed,
            'messages' => $messages,
        ];
    }

    /**
     * Process a single queued trade operation
     *
     * @param QueuedTradeRow $trade Queue entry
     * @return array{success: bool, error: string}
     */
    private function processQueuedTrade(array $trade): array
    {
        $operationType = $trade['operation_type'];
        $paramsJson = $trade['params'];

        try {
            /** @var array<string, mixed> $params */
            $params = json_decode($paramsJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return ['success' => false, 'error' => 'Invalid JSON params: ' . $e->getMessage()];
        }

        switch ($operationType) {
            case 'player_transfer':
                return $this->executePlayerTransfer($params);

            case 'pick_transfer':
                return $this->executePickTransfer($params);

            default:
                return ['success' => false, 'error' => "Unknown operation type: $operationType"];
        }
    }

    /**
     * Execute a player transfer operation
     *
     * @param array<string, mixed> $params Parameters from queue
     * @return array{success: bool, error: string}
     */
    private function executePlayerTransfer(array $params): array
    {
        if (!isset($params['player_id'], $params['team_name'], $params['team_id'])) {
            return ['success' => false, 'error' => 'Missing required player transfer params'];
        }

        $rawPlayerId = $params['player_id'];
        $rawTeamName = $params['team_name'];
        $rawTeamId = $params['team_id'];

        if (!is_int($rawPlayerId) && !is_string($rawPlayerId)) {
            return ['success' => false, 'error' => 'Invalid player_id type'];
        }
        if (!is_string($rawTeamName)) {
            return ['success' => false, 'error' => 'Invalid team_name type'];
        }
        if (!is_int($rawTeamId) && !is_string($rawTeamId)) {
            return ['success' => false, 'error' => 'Invalid team_id type'];
        }

        $playerId = (int) $rawPlayerId;
        $teamName = $rawTeamName;
        $teamId = (int) $rawTeamId;

        $affectedRows = $this->executionRepository->executeQueuedPlayerTransfer($playerId, $teamName, $teamId);

        if ($affectedRows > 0) {
            return ['success' => true, 'error' => ''];
        }

        return ['success' => false, 'error' => "Player transfer failed (0 rows affected)"];
    }

    /**
     * Execute a pick transfer operation
     *
     * @param array<string, mixed> $params Parameters from queue
     * @return array{success: bool, error: string}
     */
    private function executePickTransfer(array $params): array
    {
        if (!isset($params['pick_id'], $params['new_owner'])) {
            return ['success' => false, 'error' => 'Missing required pick transfer params'];
        }

        $rawPickId = $params['pick_id'];
        $rawNewOwner = $params['new_owner'];
        $rawNewOwnerId = $params['new_owner_id'] ?? 0;

        if (!is_int($rawPickId) && !is_string($rawPickId)) {
            return ['success' => false, 'error' => 'Invalid pick_id type'];
        }
        if (!is_string($rawNewOwner)) {
            return ['success' => false, 'error' => 'Invalid new_owner type'];
        }

        $pickId = (int) $rawPickId;
        $newOwner = $rawNewOwner;
        $newOwnerId = is_int($rawNewOwnerId) ? $rawNewOwnerId : (is_string($rawNewOwnerId) ? (int) $rawNewOwnerId : 0);

        $affectedRows = $this->executionRepository->executeQueuedPickTransfer($pickId, $newOwner, $newOwnerId);

        if ($affectedRows > 0) {
            return ['success' => true, 'error' => ''];
        }

        return ['success' => false, 'error' => "Pick transfer failed (0 rows affected)"];
    }

}
