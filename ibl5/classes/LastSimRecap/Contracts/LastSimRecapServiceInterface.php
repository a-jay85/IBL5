<?php

declare(strict_types=1);

namespace LastSimRecap\Contracts;

use LastSimRecap\Dto\RecapSlate;

interface LastSimRecapServiceInterface
{
    /**
     * Build the Last-Sim Recap slate for a team. Returns null when no sim
     * window exists or the team is unknown. Returns a slate with empty
     * games when the window exists but the team had no games.
     */
    public function buildSlateForTeam(int $tid): ?RecapSlate;
}
