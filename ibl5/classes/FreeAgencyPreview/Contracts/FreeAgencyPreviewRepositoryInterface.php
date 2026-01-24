<?php

declare(strict_types=1);

namespace FreeAgencyPreview\Contracts;

/**
 * Repository interface for Free Agency Preview module.
 *
 * Provides method to retrieve upcoming free agents from the database.
 */
interface FreeAgencyPreviewRepositoryInterface
{
    /**
     * Get all active players ordered for free agency preview.
     *
     * @return array<int, array> Array of player data with contract and rating info
     */
    public function getActivePlayers(): array;
}
