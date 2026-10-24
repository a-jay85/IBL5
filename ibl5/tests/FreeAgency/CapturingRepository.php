<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use FreeAgency\Contracts\FreeAgencyRepositoryInterface;

/**
 * Capturing repository stub — records what was passed to saveOffer().
 */
class CapturingRepository implements FreeAgencyRepositoryInterface
{
    /** @var array<string, mixed>|null */
    public ?array $lastSavedOffer = null;
    public bool $saveReturn = true;
    public bool $pendingMleExists = false;
    public bool $pendingLleExists = false;

    public function getExistingOffer(int $teamid, int $pid): ?array
    {
        return null;
    }

    public function deleteOffer(int $teamid, int $pid): int
    {
        return 0;
    }

    public function saveOffer(array $offerData): bool
    {
        $this->lastSavedOffer = $offerData;
        return $this->saveReturn;
    }

    public function getAllPlayersExcludingTeam(int $teamId): array
    {
        return [];
    }

    public function isPlayerAlreadySigned(int $playerId): bool
    {
        return false;
    }

    public function hasPendingMleOffer(int $teamid, int $excludePid): bool
    {
        return $this->pendingMleExists;
    }

    public function hasPendingLleOffer(int $teamid, int $excludePid): bool
    {
        return $this->pendingLleExists;
    }
}
