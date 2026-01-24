<?php

declare(strict_types=1);

namespace FreeAgencyPreview\Contracts;

/**
 * Service interface for Free Agency Preview module.
 *
 * Provides business logic for calculating free agency eligibility.
 */
interface FreeAgencyPreviewServiceInterface
{
    /**
     * Get all upcoming free agents for the specified season.
     *
     * @param int $seasonEndingYear The ending year of the current season
     * @return array<int, array{
     *     pid: int,
     *     tid: int,
     *     name: string,
     *     teamname: string,
     *     pos: string,
     *     age: int,
     *     r_fga: int,
     *     r_fgp: int,
     *     r_fta: int,
     *     r_ftp: int,
     *     r_tga: int,
     *     r_tgp: int,
     *     r_orb: int,
     *     r_drb: int,
     *     r_ast: int,
     *     r_stl: int,
     *     r_blk: int,
     *     r_to: int,
     *     r_foul: int,
     *     oo: int,
     *     do: int,
     *     po: int,
     *     to: int,
     *     od: int,
     *     dd: int,
     *     pd: int,
     *     td: int,
     *     loyalty: int,
     *     winner: int,
     *     playingTime: int,
     *     security: int,
     *     tradition: int
     * }> Array of upcoming free agents
     */
    public function getUpcomingFreeAgents(int $seasonEndingYear): array;
}
